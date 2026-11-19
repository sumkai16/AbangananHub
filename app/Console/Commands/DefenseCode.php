<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DefenseSeeder;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;

/**
 * Prints the administrator's current two-factor code for the defense demo, so the presenter never needs a phone.
 * Only works for the seeded demo admin, and never in production.
 */
class DefenseCode extends Command
{
    protected $signature = 'defense:code {--secret : Also print the secret, to add to an authenticator app}';

    protected $description = 'Print the demo administrator\'s current two-factor code (defense demo only)';

    public function handle(Google2FA $google2fa): int
    {
        if (app()->isProduction()) {
            $this->error('Not available in production.');

            return self::FAILURE;
        }

        $admin = User::where('email', DefenseSeeder::ADMIN_EMAIL)->first();

        if (! $admin || ! $admin->two_factor_secret) {
            $this->error('The demo administrator has no two-factor secret. Run: php artisan db:seed --class=DefenseSeeder');

            return self::FAILURE;
        }

        $left = 30 - (time() % 30);
        $this->line('Admin:   '.$admin->email.'  (password: password)');
        $this->line('Code:    <fg=green;options=bold>'.$google2fa->getCurrentOtp($admin->two_factor_secret).'</>  (valid for about '.$left.' more seconds)');

        if ($this->option('secret')) {
            $this->line('Secret:  '.$admin->two_factor_secret.'  (add as a time-based key in an authenticator app)');
            $this->line('Recovery codes: '.implode('  ', DefenseSeeder::ADMIN_RECOVERY_CODES));
        }

        return self::SUCCESS;
    }
}
