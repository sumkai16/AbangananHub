<?php

namespace Tests\Feature;

use App\Models\LeaseTemplate;
use App\Models\Reservation;
use App\Support\LeaseBody;
use App\Support\LeaseTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The editable lease body (plans/editable-lease-document.md, phases 1 to 3). */
class LeaseBodyTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function negotiation(array $property = []): array
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $prop = $this->makeProperty($landlord, $property);
        $unit = $this->makeUnit($prop, ['rental_fee' => 4200, 'security_deposit' => 4200]);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $prop->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Under Negotiation', 'reservation_date' => today(), 'target_move_in_date' => today()->addWeek(),
            'agreed_monthly_rent' => 4200, 'rent_due_day' => 5,
        ]);

        return compact('landlord', 'tenant', 'prop', 'reservation');
    }

    private function templateFor($prop, array $attrs): LeaseTemplate
    {
        $template = new LeaseTemplate(['name' => 'Test lease'] + $attrs);
        $template->landlord_id = $prop->landlord_id;
        $template->save();
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();

        return $template;
    }

    // ─── sanitizing ────────────────────────────────────────────

    public function test_clean_removes_scripts_handlers_and_unknown_tags_but_keeps_formatting(): void
    {
        $dirty = '<h3 onclick="x()">Rent</h3><p style="color:red">Pay <strong>on time</strong><script>alert(1)</script>'
            .'<img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a><iframe src="https://evil.test"></iframe></p><ul><li>One</li></ul>';

        $clean = LeaseBody::clean($dirty);

        $this->assertStringContainsString('<h3>Rent</h3>', $clean);
        $this->assertStringContainsString('<strong>on time</strong>', $clean);
        $this->assertStringContainsString('<li>One</li>', $clean);
        foreach (['script', 'onerror', 'onclick', 'javascript', 'iframe', '<img', 'style=', '<a '] as $bad) {
            $this->assertStringNotContainsString($bad, $clean);
        }
    }

    public function test_clean_keeps_known_merge_fields_and_drops_unknown_ones(): void
    {
        $clean = LeaseBody::clean('<p><span data-field="monthly_rent">Monthly rent</span> <span data-field="evil">x</span><span data-field="monthly_rent" onclick="y()">Rent</span></p>');

        $this->assertStringContainsString('data-field="monthly_rent"', $clean);
        $this->assertStringNotContainsString('evil', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    // ─── merge fields ──────────────────────────────────────────

    public function test_snapshot_fills_merge_fields_from_the_leases_own_data(): void
    {
        ['reservation' => $reservation, 'tenant' => $tenant] = $this->negotiation();

        $terms = LeaseTerms::snapshot($reservation, body: '<p>Rent is <span data-field="monthly_rent">Monthly rent</span> due the <span data-field="due_day">Due</span>. Tenant: <span data-field="tenant_name">T</span>.</p>');

        $this->assertStringContainsString('₱4,200.00', $terms['body']);
        $this->assertStringContainsString('5th', $terms['body']);
        $this->assertStringContainsString($tenant->first_name, $terms['body']);
        $this->assertStringNotContainsString('data-field', $terms['body']);
    }

    public function test_a_snapshot_without_a_body_has_no_body_key(): void
    {
        ['reservation' => $reservation] = $this->negotiation();

        $this->assertArrayNotHasKey('body', LeaseTerms::snapshot($reservation));
    }

    // ─── which wording is sent ─────────────────────────────────

    public function test_with_no_template_and_no_edit_the_lease_keeps_the_built_in_clauses(): void
    {
        ['reservation' => $reservation, 'tenant' => $tenant] = $this->negotiation();
        $reservation->advanceToPendingAgreement();

        $this->assertArrayNotHasKey('body', $reservation->fresh()->lease_snapshot);
        $this->actingAs($tenant)->get(route('agreements.show', $reservation))->assertOk()->assertSee('1. Premises and use');
    }

    public function test_the_property_template_is_used_when_the_landlord_does_not_edit(): void
    {
        ['reservation' => $reservation, 'prop' => $prop, 'tenant' => $tenant] = $this->negotiation();
        $this->templateFor($prop, ['body' => '<h3>My house</h3><p>Rent <span data-field="monthly_rent">Monthly rent</span>.</p>']);

        $reservation->advanceToPendingAgreement();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('My house')
            ->assertSee('₱4,200.00')
            ->assertSee('Summary of key terms')
            ->assertDontSee('1. Premises and use');
    }

    public function test_an_edit_for_one_tenant_beats_the_template_and_does_not_change_it(): void
    {
        ['reservation' => $reservation, 'prop' => $prop] = $this->negotiation();
        $template = $this->templateFor($prop, ['body' => '<h3>Template</h3><p>Template text.</p>']);

        $reservation->advanceToPendingAgreement(body: '<h3>Just for you</h3><p>Special wording.</p>');

        $this->assertStringContainsString('Special wording.', $reservation->fresh()->lease_snapshot['body']);
        $this->assertStringNotContainsString('Template text.', $reservation->fresh()->lease_snapshot['body']);
        $this->assertStringContainsString('Template text.', $template->fresh()->body);
    }

    public function test_the_sent_wording_is_frozen_when_the_template_changes_later(): void
    {
        ['reservation' => $reservation, 'prop' => $prop] = $this->negotiation();
        $template = $this->templateFor($prop, ['body' => '<p>Original wording.</p>']);
        $reservation->advanceToPendingAgreement();

        $template->update(['body' => '<p>Rewritten later.</p>']);

        $this->assertStringContainsString('Original wording.', LeaseTerms::for($reservation->fresh())['body']);
    }

    public function test_the_key_terms_summary_always_comes_from_system_data(): void
    {
        ['reservation' => $reservation, 'tenant' => $tenant] = $this->negotiation();
        $reservation->advanceToPendingAgreement(body: '<p>The rent is a million pesos.</p>');

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('Monthly rent')
            ->assertSee('₱4,200.00')
            ->assertSee('the summary applies');
    }

    // ─── landlord actions ──────────────────────────────────────

    public function test_accept_and_send_stores_the_edited_lease_sanitized(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->negotiation();
        $reservation->update(['rental_status' => 'Inquiry']);

        $this->actingAs($landlord)->patch(route('landlord.reservations.acceptAndSend', $reservation), [
            'accept_tc' => '1',
            'lease_body' => '<h3>Mine</h3><p>Hello<script>alert(1)</script></p>',
        ])->assertSessionHasNoErrors();

        $body = $reservation->fresh()->lease_snapshot['body'];
        $this->assertStringContainsString('<h3>Mine</h3>', $body);
        $this->assertStringNotContainsString('script', $body);
    }

    public function test_the_landlord_can_preview_what_the_tenant_will_see_and_nothing_is_saved(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->negotiation();

        $this->actingAs($landlord)
            ->postJson(route('landlord.reservations.leasePreview', $reservation), ['lease_body' => '<p>Preview <span data-field="monthly_rent">Monthly rent</span></p>'])
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, '₱4,200.00') && str_contains($html, 'Summary of key terms'));

        $this->assertNull($reservation->fresh()->lease_snapshot);
    }

    public function test_the_tenant_cannot_use_the_landlord_preview(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->negotiation();

        $this->actingAs($tenant)
            ->postJson(route('landlord.reservations.leasePreview', $reservation), ['lease_body' => '<p>x</p>'])
            ->assertForbidden();
    }

    public function test_the_starter_template_uses_fields_and_survives_the_sanitizer(): void
    {
        $starter = LeaseBody::starter();

        $this->assertStringContainsString('data-field="monthly_rent"', $starter);
        $this->assertSame(LeaseBody::clean($starter), LeaseBody::clean(LeaseBody::clean($starter)));
        $this->assertStringContainsString('data-field="monthly_rent"', LeaseBody::clean($starter));
    }
}
