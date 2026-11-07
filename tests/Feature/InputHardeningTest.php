<?php

namespace Tests\Feature;

use App\Models\LandlordTenant;
use App\Support\Csv;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Gaps found in the security audit: phone format, spreadsheet formulas, sign-up spam. */
class InputHardeningTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_profile_refuses_a_phone_number_with_letters_and_accepts_real_formats(): void
    {
        $user = $this->makeTenant();
        $base = ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email];

        $this->actingAs($user)->from(route('profile.edit'))
            ->patch(route('profile.update'), $base + ['contact_number' => 'call me maybe!!'])
            ->assertSessionHasErrors('contact_number');

        foreach (['09171234567', '+63 917 123 4567', '(032) 123-4567'] as $ok) {
            $this->actingAs($user)->patch(route('profile.update'), $base + ['contact_number' => $ok])
                ->assertSessionHasNoErrors();
            $this->assertSame($ok, $user->fresh()->contact_number);
        }
    }

    public function test_csv_cells_that_start_like_a_formula_are_neutralised_but_numbers_are_not(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", Csv::cell('=HYPERLINK("x")'));
        $this->assertSame("'+63917", Csv::cell('+63917'));
        $this->assertSame("'@SUM(A1)", Csv::cell('@SUM(A1)'));
        $this->assertSame('-1,200.50', Csv::cell('-1,200.50'));
        $this->assertSame(-5, Csv::cell(-5));
        $this->assertSame('Juan Dela Cruz', Csv::cell('Juan Dela Cruz'));
    }

    public function test_tenant_export_does_not_carry_a_live_formula(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $tenant->forceFill(['first_name' => '=HYPERLINK("http://evil.test","click")', 'last_name' => 'X'])->save();
        LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);

        $res = $this->actingAs($landlord)->get(route('landlord.tenants.export'));
        ob_start();
        $res->baseResponse->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString("\n\"=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
    }

    public function test_web_sign_up_is_rate_limited_per_ip(): void
    {
        $last = null;
        for ($i = 0; $i < 12; $i++) {
            $this->post('/logout');
            $last = $this->post('/register', [
                'first_name' => 'Spam', 'last_name' => 'Bot', 'email' => "bot{$i}_".uniqid().'@example.test',
                'contact_number' => '09171234567', 'password' => 'Str0ng!Passw0rd#1', 'password_confirmation' => 'Str0ng!Passw0rd#1',
            ]);
        }

        $last->assertStatus(429);
    }

    public function test_id_photo_check_refuses_bytes_that_are_not_an_image_and_an_unknown_id_type(): void
    {
        $user = $this->makeTenant();

        $this->actingAs($user)->postJson(route('landlord.verification.ocrCheck'), ['id_image' => str_repeat('A', 400), 'id_type' => 'Library Card'])
            ->assertStatus(422);

        $this->actingAs($user)->postJson(route('landlord.verification.ocrCheck'), ['id_image' => base64_encode(str_repeat('not a picture ', 20)), 'id_type' => 'Passport'])
            ->assertOk()->assertJson(['status' => 'fail', 'message' => 'Invalid image data.']);
    }
}
