<?php

namespace App\Services\Security;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Authenticator-app (TOTP) sign-in for admins, plus single-use recovery codes.
 * The secret and the recovery-code hashes live encrypted on `users` (see the User casts); this class is the only
 * writer of the two_factor_* columns. Never log a code or a secret.
 */
class TwoFactor
{
    public const ISSUER = 'AbangananHub';
    public const RECOVERY_CODE_COUNT = 8;

    /** No 0/O/1/I so a code read off a screenshot or paper is not misread. */
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private Google2FA $google2fa)
    {
    }

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** Inline SVG of the otpauth QR code (account = the user's email). */
    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(self::ISSUER, (string) $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd()));

        // Drop the XML prolog so the SVG can sit inline in an HTML page.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $writer->writeString($url));
    }

    /** True when $code is a valid current 6-digit code or an unused recovery code. A TOTP step is accepted once. */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);
        $digits = preg_replace('/\s+/', '', $code);

        if (preg_match('/^\d{6}$/', $digits)) {
            return $this->verifyTotp($user, $digits);
        }

        return $this->useRecoveryCode($user, $code);
    }

    /**
     * Confirms the first code against a not-yet-saved secret, then switches two-factor on and makes the recovery
     * codes. Returns false on a wrong code. The plain codes come back through $plainCodes (shown once, never stored).
     */
    public function enable(User $user, string $secret, string $code, ?array &$plainCodes = null): bool
    {
        $step = $this->matchStep($secret, preg_replace('/\s+/', '', $code), null);
        if ($step === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step'    => $step,
        ])->save();

        $plainCodes = $this->generateRecoveryCodes($user);

        return true;
    }

    /** Clears every two_factor_* column. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_last_step'      => null,
        ])->save();
    }

    /** Replaces the stored hashes with fresh ones. Returns the plain codes: the only time they exist. */
    public function generateRecoveryCodes(User $user): array
    {
        $plain = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $plain[] = $this->randomChunk(4).'-'.$this->randomChunk(4);
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $c) => Hash::make($c), $plain),
        ])->save();

        return $plain;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;
        if (! $secret) {
            return false;
        }

        $step = $this->matchStep($secret, $code, $user->two_factor_last_step);
        if ($step === null) {
            return false;
        }

        // Compare-and-set so two requests racing with the same code cannot both pass.
        $claimed = User::whereKey($user->getKey())
            ->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))
            ->update(['two_factor_last_step' => $step]);

        if ($claimed !== 1) {
            return false;
        }

        $user->two_factor_last_step = $step;
        $user->syncOriginalAttribute('two_factor_last_step');

        return true;
    }

    /** The accepted 30-second step for $code (window +-1, newer than $lastStep), or null. */
    private function matchStep(string $secret, string $code, ?int $lastStep): ?int
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $step = $this->google2fa->verifyKeyNewer($secret, $code, $lastStep ?? 0, 1);

        return is_int($step) ? $step : null;
    }

    private function useRecoveryCode(User $user, string $input): bool
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (strlen($code) !== 8) {
            return false;
        }
        $code = substr($code, 0, 4).'-'.substr($code, 4);

        return DB::transaction(function () use ($user, $code) {
            // Lock the row so two requests cannot spend the same code.
            $fresh = User::whereKey($user->getKey())->lockForUpdate()->first();
            $hashes = $fresh?->two_factor_recovery_codes ?? [];

            foreach ($hashes as $i => $hash) {
                if (Hash::check($code, $hash)) {
                    unset($hashes[$i]);
                    $fresh->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                    $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->syncOriginal();
                    app(AuthEventRecorder::class)->record('recovery_code_used', $user, null, ['remaining' => count($hashes)]);

                    return true;
                }
            }

            return false;
        });
    }

    private function randomChunk(int $length): string
    {
        $out = '';
        $max = strlen(self::RECOVERY_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= self::RECOVERY_ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
