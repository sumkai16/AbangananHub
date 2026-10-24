<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

class AdminSettingsPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_the_page_posts_each_field_under_its_real_key_and_shows_defaults(): void
    {
        $admin = $this->makeUserWithRole('Admin');

        $html = $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('name="settings[online_payments_enabled]"', $html);
        $this->assertStringContainsString('name="settings[move_in_confirmation_days]"', $html);
        $this->assertStringNotContainsString('name="settings[0]"', $html);
    }

    public function test_the_admin_can_switch_online_payments_on_and_off(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        config(['rentals.online_payments_enabled' => false]);
        Setting::where('key', 'online_payments_enabled')->delete();
        Cache::forget('settings.rentals.overrides');

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), ['settings' => ['online_payments_enabled' => '1']])
            ->assertSessionHas('success', 'System settings updated.');

        $this->assertTrue(Setting::effective('online_payments_enabled'));

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), ['settings' => ['online_payments_enabled' => '0']])
            ->assertSessionHas('success', 'System settings updated.');

        $this->assertFalse(Setting::effective('online_payments_enabled'));
    }
}
