<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Specific, plain-language messages for a file that did not upload, instead of Laravel's "The file failed to upload."
 * and "may not be greater than 10240 kilobytes". Each message names the file and says what to do.
 *
 * Wired in once, in AppServiceProvider::registerUploadMessages(), as validation replacers for the `uploaded`, `max`,
 * `mimes` and `image` rules, so every upload form in the app gets them. The numbers are read from PHP at the moment
 * of the failure, so they stay true on any server (dev 2 MB, production whatever its php.ini says).
 */
final class UploadMessages
{
    /** The most one file may be on this server, in bytes: the smaller of PHP's per-file and per-request limits. */
    public static function serverLimitBytes(): ?int
    {
        $limits = array_filter([
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
        ]);

        return $limits === [] ? null : min($limits);
    }

    /** "2 MB", or null when PHP sets no limit. */
    public static function serverLimitLabel(): ?string
    {
        $bytes = self::serverLimitBytes();

        return $bytes === null ? null : self::size($bytes);
    }

    /** The most one whole submit may be (all files together), e.g. "8 MB". */
    public static function requestLimitLabel(): ?string
    {
        $bytes = self::iniBytes((string) ini_get('post_max_size'));

        return $bytes === null ? null : self::size($bytes);
    }

    /** For the `uploaded` rule: PHP itself refused or lost the file. The error code says which. */
    public static function uploaded(mixed $file): string
    {
        if (! $file instanceof UploadedFile) {
            return 'The file could not be uploaded. Please try again.';
        }

        $name = self::name($file);

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $name.' is bigger than this server accepts'
                .(($limit = self::serverLimitLabel()) ? " ({$limit} at most)" : '')
                .'. Choose a smaller file and try again. For a photo, a screenshot of it or a lower-quality copy is usually enough.',
            UPLOAD_ERR_PARTIAL => $name.' was only partly uploaded: the connection dropped before it finished. Check your signal and try again.',
            UPLOAD_ERR_NO_FILE => 'No file was received. Choose a file and try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save '.$name.'. This is a problem on our side, not with your file. Try again, and contact support if it keeps happening.',
            default => $name.' could not be uploaded. Please try again.',
        };
    }

    /**
     * For the `max` rule. A file gets a message with its real size; anything else (text, numbers, lists) keeps
     * Laravel's own wording, with :max filled in the way Laravel does.
     *
     * @param  list<string>  $parameters
     */
    public static function tooBig(mixed $value, array $parameters, string $message): string
    {
        $maxKb = (int) ($parameters[0] ?? 0);

        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return str_replace(':max', (string) ($parameters[0] ?? ''), $message);
        }

        return self::name($value).' is '.self::size((int) $value->getSize()).'. The most allowed here is '.self::size($maxKb * 1024).'. Choose a smaller file.';
    }

    /**
     * For the `mimes` rule (documents) and the `image` rule (photos).
     *
     * @param  list<string>  $parameters  the allowed extensions for `mimes`, empty for `image`
     */
    public static function wrongType(mixed $value, array $parameters, string $message): string
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return $parameters === [] ? $message : str_replace(':values', implode(', ', $parameters), $message);
        }

        $name = self::name($value);
        $extension = strtolower($value->getClientOriginalExtension());
        $allowed = $parameters === [] ? 'a JPG, PNG or WebP image' : 'a '.self::typeList($parameters).' file';

        if (in_array($extension, ['heic', 'heif'], true)) {
            return $name.' is a HEIC photo, which browsers and this site cannot use. On iPhone, share or export it as JPG, or turn on Settings > Camera > Formats > Most Compatible. Then upload '.$allowed.'.';
        }

        $what = $extension !== '' ? 'a .'.strtoupper($extension).' file' : 'not a file type we accept';

        return $name.' is '.$what.', which is not accepted here. Use '.$allowed.'.';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** “IMG_2034.jpg”, or "The file" when the browser sent no name. */
    private static function name(UploadedFile $file): string
    {
        $name = trim($file->getClientOriginalName());

        return $name === '' ? 'The file' : '“'.mb_strimwidth($name, 0, 60, '…').'”';
    }

    /** "PDF, JPG, PNG or WebP" from ['pdf', 'jpg', 'jpeg', 'png', 'webp']. */
    private static function typeList(array $extensions): string
    {
        $labels = collect($extensions)
            ->map(fn ($e) => strtolower($e) === 'jpeg' ? 'jpg' : strtolower($e))
            ->unique()
            ->map(fn ($e) => $e === 'webp' ? 'WebP' : strtoupper($e))
            ->values();

        return $labels->count() > 1
            ? $labels->slice(0, -1)->implode(', ').' or '.$labels->last()
            : (string) $labels->first();
    }

    /** "1.8 MB", "512 KB", "10 MB". */
    private static function size(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.').' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }

    /** php.ini shorthand ("2M", "512K", "1G", "8388608") to bytes; null for no limit (0 or -1) or nothing set. */
    private static function iniBytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^(-?\d+)\s*([kmg]?)b?$/i', $value, $m)) {
            return null;
        }

        $bytes = (int) $m[1] * match (strtolower($m[2])) {
            'k' => 1024,
            'm' => 1048576,
            'g' => 1073741824,
            default => 1,
        };

        return $bytes > 0 ? $bytes : null;
    }
}
