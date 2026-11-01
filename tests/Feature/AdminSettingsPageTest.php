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

    public function test_online_payments_is_a_switch_that_asks_before_turning_off_but_not_before_turning_on(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        Cache::forget('settings.rentals.overrides');

        Setting::put('online_payments_enabled', '1');
        $on = $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('role="switch"', $on);
        $this->assertStringContainsString('aria-checked="true"', $on);
        $this->assertStringContainsString('id="toggle-online_payments_enabled"', $on);
        // On now, so the click turns it off: the standalone form posts 0 and asks first.
        $this->assertMatchesRegularExpression('/id="toggle-online_payments_enabled"[^>]*data-confirm="Turn off online payments/s', $on);
        $this->assertMatchesRegularExpression('/name="settings\[online_payments_enabled\]" value="0"/', $on);
        // The old dropdown is gone, so the main form can no longer overwrite the switch.
        $this->assertStringNotContainsString('<select name="settings[online_payments_enabled]"', $on);

        Setting::put('online_payments_enabled', '0');
        $off = $this->actingAs($admin)->get(route('admin.settings.edit'))->getContent();

        $this->assertStringContainsString('aria-checked="false"', $off);
        $this->assertDoesNotMatchRegularExpression('/id="toggle-online_payments_enabled"[^>]*data-confirm=/s', $off);
        $this->assertMatchesRegularExpression('/name="settings\[online_payments_enabled\]" value="1"/', $off);
    }

    public function test_flipping_the_switch_leaves_every_other_setting_alone(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        Setting::put('move_in_confirmation_days', 11);
        Setting::put('online_payments_enabled', '0');

        $this->actingAs($admin)
            ->patch(route('admin.settings.update'), ['settings' => ['online_payments_enabled' => '1']])
            ->assertSessionHas('success');

        $this->assertTrue(Setting::effective('online_payments_enabled'));
        $this->assertSame(11, Setting::effective('move_in_confirmation_days'));
    }
}
