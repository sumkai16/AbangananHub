<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LeaseTemplate;
use App\Models\Reservation;
use App\Support\HouseRules;
use App\Support\LeaseBody;
use App\Support\LeaseTemplates;
use App\Support\LeaseTerms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** The lease template library and the Leases page (plans/lease-templates-page.md, phases 1 to 3). */
class LeaseTemplatesTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null', 'rentals.lease_notice_days' => 30, 'rentals.rent_overdue_grace_days' => 3]);
    }

    private function setupLandlord(array $property = []): array
    {
        $landlord = $this->makeLandlord();
        $prop = $this->makeProperty($landlord, $property + ['house_rules' => ['No smoking']]);
        $unit = $this->makeUnit($prop, ['rental_fee' => 3000]);
        $reservation = Reservation::create([
            'tenant_id' => $this->makeTenant()->user_id, 'property_id' => $prop->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => 'Under Negotiation', 'reservation_date' => today(), 'target_move_in_date' => today()->addWeek(),
            'agreed_monthly_rent' => 3000, 'rent_due_day' => 5,
        ]);

        return compact('landlord', 'prop', 'unit', 'reservation');
    }

    private function template($landlord, array $attrs = []): LeaseTemplate
    {
        $t = new LeaseTemplate(['name' => $attrs['name'] ?? 'Lease '.uniqid()] + $attrs);
        $t->landlord_id = $landlord->user_id;
        $t->save();

        return $t;
    }

    // ─── which template applies ────────────────────────────────

    public function test_the_unit_template_beats_the_property_template_which_beats_none(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'unit' => $unit] = $this->setupLandlord();
        $this->assertNull(LeaseTemplates::for($prop, $unit));

        $forProperty = $this->template($l);
        $prop->forceFill(['lease_template_id' => $forProperty->lease_template_id])->save();
        $this->assertTrue(LeaseTemplates::for($prop->fresh(), $unit->fresh())->is($forProperty));

        $forUnit = $this->template($l);
        $unit->forceFill(['lease_template_id' => $forUnit->lease_template_id])->save();
        $this->assertTrue(LeaseTemplates::for($prop->fresh(), $unit->fresh())->is($forUnit));
    }

    public function test_another_landlords_template_is_never_applied(): void
    {
        ['prop' => $prop, 'unit' => $unit] = $this->setupLandlord();
        $foreign = $this->template($this->makeLandlord());
        $unit->forceFill(['lease_template_id' => $foreign->lease_template_id])->save();

        $this->assertNull(LeaseTemplates::for($prop, $unit->fresh()));
    }

    // ─── what a template sets ──────────────────────────────────

    public function test_template_rules_are_frozen_into_the_lease_and_blank_ones_fall_back(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord(['rent_grace_days' => 7, 'late_fee_type' => 'fixed', 'late_fee_value' => 100]);

        $snap = LeaseTerms::snapshot($r, template: $this->template($l, ['notice_days' => 15]));
        $this->assertSame(7, $snap['grace_days']);
        $this->assertEquals(['type' => 'fixed', 'value' => 100.0], $snap['late_fee']);
        $this->assertSame(15, $snap['notice_days']);

        $snap = LeaseTerms::snapshot($r, template: $this->template($l, ['rent_grace_days' => 2, 'late_fee_type' => 'percent', 'late_fee_value' => 5]));
        $this->assertSame(2, $snap['grace_days']);
        $this->assertEquals(['type' => 'percent', 'value' => 5.0], $snap['late_fee']);
        $this->assertSame(30, $snap['notice_days']);
    }

    public function test_no_late_fee_on_the_template_overrides_the_property_fee(): void
    {
        ['landlord' => $l, 'reservation' => $r] = $this->setupLandlord(['late_fee_type' => 'fixed', 'late_fee_value' => 100]);

        $snap = LeaseTerms::snapshot($r, template: $this->template($l, ['late_fee_type' => 'none']));

        $this->assertNull($snap['late_fee']);
    }

    public function test_the_ledger_charges_the_rules_the_lease_was_sent_with(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord(['rent_grace_days' => 7]);
        $template = $this->template($l, ['rent_grace_days' => 1, 'late_fee_type' => 'fixed', 'late_fee_value' => 250]);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();

        $r->fresh()->advanceToPendingAgreement();
        $template->update(['rent_grace_days' => 20]);

        $rules = LeaseTerms::rentRules($r->fresh());
        $this->assertSame(1, $rules['grace_days']);
        $this->assertEquals(['type' => 'fixed', 'value' => 250.0], $rules['late_fee']);
        $this->assertSame($template->name, $r->fresh()->lease_snapshot['template']['name']);
    }

    public function test_house_rules_come_from_the_policy_only(): void
    {
        // The listing has "No smoking" ticked (setupLandlord); it stays on the listing and is not printed.
        ['landlord' => $l, 'reservation' => $r] = $this->setupLandlord();

        $snap = LeaseTerms::snapshot($r, template: $this->template($l, ['house_rule_sections' => [
            ['title' => 'Curfew', 'rules' => ['Quiet after 10 PM']],
            ['title' => 'Visitors', 'rules' => ['Visitors leave by 9 PM']],
        ]]));

        // Flat list (older leases and the API): the policy's rules only.
        $this->assertSame(['Quiet after 10 PM', 'Visitors leave by 9 PM'], $snap['house_rules']);
        // Titled sections: the policy's own, no "Property rules" block.
        $this->assertSame(['Curfew', 'Visitors'], array_column($snap['house_rule_sections'], 'title'));
    }

    public function test_without_a_policy_the_lease_has_no_house_rules(): void
    {
        ['reservation' => $r] = $this->setupLandlord();

        $snap = LeaseTerms::snapshot($r);

        $this->assertSame([], $snap['house_rules']);
        $this->assertSame([], $snap['house_rule_sections']);
    }

    public function test_the_lease_prints_house_rules_under_their_section_titles(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $template = $this->template($l, ['house_rule_sections' => [['title' => 'Visitors', 'rules' => ['Visitors leave by 9 PM']]]]);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();
        $r->fresh()->advanceToPendingAgreement();

        $this->actingAs($r->tenant)->get(route('agreements.show', $r))->assertOk()
            ->assertSeeInOrder(['House rules', 'Visitors', 'Visitors leave by 9 PM'])
            ->assertDontSee('Property rules');
    }

    public function test_a_lease_sent_before_sections_still_prints_its_flat_rules(): void
    {
        ['reservation' => $r] = $this->setupLandlord();
        $r->advanceToPendingAgreement();
        // An older lease, frozen with the listing's rule in its flat list and no sections.
        $snap = $r->fresh()->lease_snapshot;
        unset($snap['house_rule_sections']);
        $snap['house_rules'] = ['No smoking'];
        $r->forceFill(['lease_snapshot' => $snap])->save();

        $this->actingAs($r->tenant)->get(route('agreements.show', $r))->assertOk()->assertSee('No smoking')->assertDontSee('Property rules');
    }

    public function test_the_editor_explains_only_its_rules_print_and_shows_the_listing_rules(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();

        $this->actingAs($l)->get(route('landlord.lease-templates.create'))->assertOk()
            ->assertSee('The lease prints only the rules you write here')->assertSee('No smoking')->assertSee('Suggested:')->assertSee('Curfew and quiet hours');
    }

    public function test_a_hidden_section_is_kept_in_the_policy_but_left_off_the_lease(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();

        $this->actingAs($l)->post(route('landlord.lease-templates.store'), [
            'name' => 'With hidden', 'assign_present' => '1', 'assigned_properties' => [$prop->property_id],
            'house_rule_sections' => [
                ['title' => 'Visitors', 'hidden' => '0', 'rules' => ['Visitors leave by 9 PM']],
                ['title' => 'Parking', 'hidden' => '1', 'rules' => ['Park only in assigned areas']],
            ],
        ])->assertSessionHasNoErrors();

        $t = LeaseTemplate::where('name', 'With hidden')->firstOrFail();
        // assertEquals, not assertSame: MySQL JSON does not keep key order.
        $this->assertEquals([['title' => 'Visitors', 'rules' => ['Visitors leave by 9 PM']], ['title' => 'Parking', 'rules' => ['Park only in assigned areas'], 'hidden' => true]], $t->house_rule_sections);

        $snap = LeaseTerms::snapshot($r, template: $t);
        $this->assertSame(['Visitors'], array_column($snap['house_rule_sections'], 'title'));
        $this->assertNotContains('Park only in assigned areas', $snap['house_rules']);
        $this->assertArrayNotHasKey('hidden', $snap['house_rule_sections'][0]);

        $this->actingAs($l)->get(route('landlord.lease-templates.edit', $t))->assertOk()->assertSee('Park only in assigned areas');
    }

    public function test_normalize_drops_blank_rules_and_empty_sections_and_names_untitled_ones(): void
    {
        $out = HouseRules::normalize([
            ['title' => 'Visitors', 'rules' => ['Visitors leave by 9 PM', '  ']],
            ['title' => 'Empty', 'rules' => ['', ' ']],
            ['title' => '  ', 'rules' => ['No pets']],
        ]);

        $this->assertSame([['title' => 'Visitors', 'rules' => ['Visitors leave by 9 PM']], ['title' => 'Rules', 'rules' => ['No pets']]], $out);
        $this->assertNull(HouseRules::normalize([['title' => 'x', 'rules' => ['']]]));
    }

    public function test_the_tenant_sees_the_house_rules_under_a_custom_wording(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $template = $this->template($l, ['body' => '<p>Our own wording.</p>', 'house_rule_sections' => [['title' => 'Curfew', 'rules' => ['Quiet after 10 PM']]]]);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();
        $r->fresh()->advanceToPendingAgreement();

        $this->actingAs($r->tenant)->get(route('agreements.show', $r))
            ->assertOk()
            ->assertSeeInOrder(['Our own wording.', 'House rules', 'Quiet after 10 PM']);
    }

    public function test_deleting_a_template_sends_the_standard_lease_and_keeps_sent_leases(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $template = $this->template($l, ['body' => '<p>Template wording.</p>']);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();
        $r->fresh()->advanceToPendingAgreement();

        $this->actingAs($l)->delete(route('landlord.lease-templates.destroy', $template))->assertRedirect(route('landlord.leases.index'));

        $this->assertNull($prop->fresh()->lease_template_id);
        $this->assertStringContainsString('Template wording.', $r->fresh()->lease_snapshot['body']);
    }

    // ─── the page ──────────────────────────────────────────────

    public function test_the_landlord_creates_and_updates_a_template_with_sanitized_wording(): void
    {
        ['landlord' => $l] = $this->setupLandlord();

        $this->actingAs($l)->post(route('landlord.lease-templates.store'), [
            'name' => 'Bedspace lease', 'body' => '<h3>Rules</h3><p>Be kind<script>x()</script></p>',
            'late_fee_type' => 'fixed', 'late_fee_value' => '150', 'notice_days' => '14',
            'house_rule_sections' => [['title' => 'Curfew', 'rules' => ['Lights out at 11', '']], ['title' => 'Empty', 'rules' => ['']]],
        ])->assertSessionHasNoErrors();

        $template = LeaseTemplate::where('landlord_id', $l->user_id)->firstOrFail();
        $this->assertSame('<h3>Rules</h3><p>Be kind</p>', $template->body);
        // assertEquals: MySQL stores JSON with the keys sorted, so the key order is not ours to assert.
        $this->assertEquals([['title' => 'Curfew', 'rules' => ['Lights out at 11']]], $template->house_rule_sections);
        $this->assertSame(14, $template->notice_days);

        $this->actingAs($l)->put(route('landlord.lease-templates.update', $template), [
            'name' => 'Bedspace lease v2', 'late_fee_type' => 'none', 'late_fee_value' => '150',
        ])->assertSessionHasNoErrors();
        $template->refresh();
        $this->assertSame('none', $template->late_fee_type);
        $this->assertNull($template->late_fee_value);
        $this->assertNull($template->house_rule_sections);
    }

    public function test_a_priced_late_fee_needs_an_amount(): void
    {
        ['landlord' => $l] = $this->setupLandlord();

        $this->actingAs($l)->post(route('landlord.lease-templates.store'), ['name' => 'X', 'late_fee_type' => 'percent'])
            ->assertSessionHasErrors('late_fee_value');
    }

    public function test_another_landlord_cannot_open_change_or_delete_a_template(): void
    {
        ['landlord' => $l] = $this->setupLandlord();
        $template = $this->template($l);
        $other = $this->makeLandlord();

        $this->actingAs($other)->get(route('landlord.lease-templates.edit', $template))->assertForbidden();
        $this->actingAs($other)->put(route('landlord.lease-templates.update', $template), ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($other)->delete(route('landlord.lease-templates.destroy', $template))->assertForbidden();
        $this->actingAs($other)->get(route('landlord.lease-templates.create', ['from' => $template->lease_template_id]))
            ->assertOk()->assertDontSee('Copy of '.$template->name);
    }

    public function test_assigning_templates_to_a_property_and_a_unit(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'unit' => $unit] = $this->setupLandlord();
        $template = $this->template($l);

        $this->actingAs($l)->patch(route('landlord.lease-templates.assign'), ['target' => 'property', 'id' => $prop->property_id, 'lease_template_id' => $template->lease_template_id])
            ->assertSessionHasNoErrors();
        $this->actingAs($l)->patch(route('landlord.lease-templates.assign'), ['target' => 'unit', 'id' => $unit->unit_id, 'lease_template_id' => $template->lease_template_id])
            ->assertSessionHasNoErrors();
        $this->assertSame($template->lease_template_id, $prop->fresh()->lease_template_id);
        $this->assertSame($template->lease_template_id, $unit->fresh()->lease_template_id);

        $this->actingAs($l)->patch(route('landlord.lease-templates.assign'), ['target' => 'unit', 'id' => $unit->unit_id, 'lease_template_id' => '']);
        $this->assertNull($unit->fresh()->lease_template_id);
    }

    public function test_a_landlord_cannot_assign_another_landlords_template_or_touch_their_property(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();
        ['landlord' => $other, 'prop' => $otherProp] = $this->setupLandlord();
        $foreign = $this->template($other);

        $this->actingAs($l)->patch(route('landlord.lease-templates.assign'), ['target' => 'property', 'id' => $prop->property_id, 'lease_template_id' => $foreign->lease_template_id])
            ->assertSessionHasErrors('lease_template_id');
        $this->actingAs($l)->patch(route('landlord.lease-templates.assign'), ['target' => 'property', 'id' => $otherProp->property_id, 'lease_template_id' => null])
            ->assertNotFound();
    }

    public function test_the_leases_page_lists_templates_and_where_they_are_used(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();
        $template = $this->template($l, ['name' => 'Bedspace lease']);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();

        // By property: the property's policy, its units, and the list of policies.
        $this->actingAs($l)->get(route('landlord.leases.index'))->assertOk()
            ->assertSee('Lease policies')->assertSee($prop->title)->assertSee('This property uses')->assertSee('Bedspace lease')
            ->assertSee('Standard policy')->assertSee('1 in use')->assertSee('Edit policy');
        $this->actingAs($l)->get(route('landlord.leases.index', ['property' => $prop->property_id]))->assertOk()->assertSee('Same as property');
        $this->actingAs($l)->get(route('landlord.lease-templates.edit', $template))->assertOk()->assertSee('Rent rules')->assertSee('Open editor')->assertSee('Save policy');
        $this->actingAs($l)->get(route('landlord.lease-templates.create'))->assertOk();
    }

    public function test_the_template_preview_uses_a_property_and_a_sample_tenant(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();

        $this->actingAs($l)->postJson(route('landlord.lease-templates.preview'), [
            'name' => 'Preview', 'property_id' => $prop->property_id,
            'body' => '<p>Rent is <span data-field="monthly_rent">Monthly rent</span> for <span data-field="tenant_name">T</span>.</p>',
        ])->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, '₱3,000.00') && str_contains($h, 'Sample Tenant'));

        $other = $this->makeLandlord();
        $this->actingAs($other)->postJson(route('landlord.lease-templates.preview'), ['name' => 'P', 'property_id' => $prop->property_id])
            ->assertStatus(422);
    }

    public function test_the_chat_editor_opens_on_the_units_template_and_names_it(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $template = $this->template($l, ['name' => 'Room lease', 'body' => '<p>Room wording.</p>']);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();
        $conversation = Conversation::create([
            'tenant_id' => $r->tenant_id, 'landlord_id' => $l->user_id, 'property_id' => $prop->property_id, 'unit_id' => $r->unit_id, 'type' => 'inquiry',
        ]);
        $r->update(['conversation_id' => $conversation->conversation_id]);

        $this->actingAs($l)->get(route('conversations.show', $conversation), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Sending: Room lease')
            ->assertSee('Room wording.');
    }

    public function test_a_walk_in_lease_is_issued_on_the_units_template(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $template = $this->template($l, ['notice_days' => 10]);
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();

        // WalkInTenantController freezes the lease with the same call.
        $snap = LeaseTerms::snapshot($r->fresh(), template: LeaseTemplates::forReservation($r->fresh()));

        $this->assertSame(10, $snap['notice_days']);
    }

    // ─── starters, versions, assignment from the editor, welcome and cover ───

    public function test_each_property_type_starter_adds_its_own_clause(): void
    {
        $this->assertStringContainsString('Shared room', LeaseBody::starter('Bedspace'));
        $this->assertStringContainsString('House and grounds', LeaseBody::starter('House'));
        $this->assertStringNotContainsString('Shared room', LeaseBody::starter());
        $this->assertStringNotContainsString('Monthly rent', LeaseBody::starter('blank'));

        ['landlord' => $l] = $this->setupLandlord();
        $this->actingAs($l)->get(route('landlord.lease-templates.create', ['starter' => 'Dormitory']))
            ->assertOk()->assertSee('Dormitory living')->assertSee('value="Dormitory policy"', false);
    }

    public function test_the_version_goes_up_when_content_changes_and_not_on_a_rename(): void
    {
        ['landlord' => $l] = $this->setupLandlord();
        $t = $this->template($l, ['name' => 'Room lease', 'body' => '<p>One.</p>']);
        $this->assertSame(1, $t->fresh()->version);

        $this->actingAs($l)->put(route('landlord.lease-templates.update', $t), ['name' => 'Room lease (new name)', 'body' => '<p>One.</p>']);
        $this->assertSame(1, $t->fresh()->version);

        $this->actingAs($l)->put(route('landlord.lease-templates.update', $t), ['name' => 'Room lease', 'body' => '<p>Two.</p>']);
        $this->assertSame(2, $t->fresh()->version);

        $this->actingAs($l)->put(route('landlord.lease-templates.update', $t), ['name' => 'Room lease', 'body' => '<p>Two.</p>', 'notice_days' => '20']);
        $this->assertSame(3, $t->fresh()->version);
    }

    public function test_the_sent_lease_records_the_template_version_and_shows_the_welcome_message_and_a_cover(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $t = $this->template($l, ['name' => 'Room lease', 'welcome_message' => "Welcome home!\nMessage us anytime."]);
        $t->forceFill(['version' => 4])->save();
        $prop->forceFill(['lease_template_id' => $t->lease_template_id])->save();

        $r->fresh()->advanceToPendingAgreement();

        $snap = $r->fresh()->lease_snapshot;
        $this->assertSame(4, $snap['template']['version']);
        $this->actingAs($r->tenant)->get(route('agreements.show', $r))
            ->assertOk()
            ->assertSee('Welcome home!')
            ->assertSee('print:break-after-page', false);
    }

    public function test_the_editor_checklist_assigns_and_unassigns_properties(): void
    {
        ['landlord' => $l, 'prop' => $a] = $this->setupLandlord();
        $b = $this->makeProperty($l);
        $t = $this->template($l, ['name' => 'Shared lease']);
        $a->forceFill(['lease_template_id' => $t->lease_template_id])->save();

        $this->actingAs($l)->put(route('landlord.lease-templates.update', $t), [
            'name' => 'Shared lease', 'assign_present' => '1', 'assigned_properties' => [$b->property_id],
        ])->assertSessionHasNoErrors();

        $this->assertNull($a->fresh()->lease_template_id);
        $this->assertSame($t->lease_template_id, $b->fresh()->lease_template_id);

        // A form without the checklist (or another client) leaves assignments alone.
        $this->actingAs($l)->put(route('landlord.lease-templates.update', $t), ['name' => 'Shared lease']);
        $this->assertSame($t->lease_template_id, $b->fresh()->lease_template_id);
    }

    public function test_the_checklist_refuses_another_landlords_property(): void
    {
        ['landlord' => $l] = $this->setupLandlord();
        ['prop' => $foreign] = $this->setupLandlord();

        $this->actingAs($l)->post(route('landlord.lease-templates.store'), [
            'name' => 'Mine', 'assign_present' => '1', 'assigned_properties' => [$foreign->property_id],
        ])->assertSessionHasErrors('assigned_properties.0');
        $this->assertNull($foreign->fresh()->lease_template_id);
    }

    // ─── Sent & signed tab and the move-in checklist ───────────────

    public function test_the_old_sent_tab_lands_on_tenants_or_the_reservations_agreement_stage(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();

        $this->actingAs($l)->get(route('landlord.leases.index', ['tab' => 'sent']))
            ->assertRedirect(route('landlord.tenants.index'));
        $this->actingAs($l)->get(route('landlord.leases.index', ['tab' => 'sent', 'property' => $prop->property_id, 'search' => 'ana']))
            ->assertRedirect(route('landlord.tenants.index', ['search' => 'ana', 'property' => $prop->property_id]));
        $this->actingAs($l)->get(route('landlord.leases.index', ['tab' => 'sent', 'status' => 'awaiting']))
            ->assertRedirect(route('landlord.reservations.index', ['stage' => 'agreement']));
    }

    public function test_the_move_in_checklist_shows_progress_to_both_sides(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'reservation' => $r] = $this->setupLandlord();
        $conversation = Conversation::create([
            'tenant_id' => $r->tenant_id, 'landlord_id' => $l->user_id, 'property_id' => $prop->property_id, 'unit_id' => $r->unit_id, 'type' => 'inquiry',
        ]);
        $r->update(['conversation_id' => $conversation->conversation_id]);
        $r->fresh()->advanceToPendingAgreement();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($l)->get(route('conversations.show', $conversation), $ajax)
            ->assertOk()->assertSee('Move-in checklist')->assertSee('1 of 6')->assertSee('signed');
        $this->actingAs($r->tenant)->get(route('conversations.show', $conversation), $ajax)
            ->assertOk()->assertSee('Move-in checklist')->assertSee('You signed')->assertSee('#emergency-contact', false);

        $r->fresh()->forceFill(['rental_status' => 'Rental Agreement Signed', 'agreed_at' => now(), 'move_in_paid_at' => now()])->save();
        $this->actingAs($l)->get(route('conversations.show', $conversation), $ajax)->assertOk()->assertSee('3 of 6');
    }

    public function test_the_leases_page_offers_the_lease_policies_modal_and_keeps_the_plain_link(): void
    {
        $l = $this->makeLandlord();

        $this->actingAs($l)->get(route('landlord.contracts.index'))
            ->assertOk()
            ->assertSee('open-lease-policies', false)
            ->assertSee('landlord\\/leases\\/panel', false)
            ->assertSee(route('landlord.leases.index'), false);
    }

    public function test_the_policies_panel_lists_only_my_properties_units_and_policies(): void
    {
        ['landlord' => $l, 'prop' => $prop, 'unit' => $unit] = $this->setupLandlord(['title' => 'My Mambaling Rooms']);
        $mine = $this->template($l, ['name' => 'My student policy']);
        $unit->forceFill(['lease_template_id' => $mine->lease_template_id])->save();

        $other = $this->makeLandlord();
        $this->makeProperty($other, ['title' => 'Someone Elses Place']);
        $this->template($other, ['name' => 'Their secret policy']);

        $this->actingAs($l)->get(route('landlord.leases.panel'))
            ->assertOk()
            ->assertSee('My Mambaling Rooms')
            ->assertSee('My student policy')
            ->assertSee('1 uses its own policy')
            ->assertDontSee('Someone Elses Place')
            ->assertDontSee('Their secret policy');
    }

    public function test_the_policies_panel_opens_the_first_property_unless_told_which_to_open(): void
    {
        ['landlord' => $l, 'prop' => $first] = $this->setupLandlord(['title' => 'Aaa First']);
        $second = $this->makeProperty($l, ['title' => 'Zzz Second']);

        $default = $this->actingAs($l)->get(route('landlord.leases.panel'))->getContent();
        $this->assertSame(2, substr_count($default, '<details data-id'));
        $this->assertSame(1, preg_match_all('/<details data-id="\d+"\s+open/', $default));
        $this->assertMatchesRegularExpression('/data-id="'.$first->property_id.'"\s+open/', $default);
        $this->assertDoesNotMatchRegularExpression('/data-id="'.$second->property_id.'"\s+open/', $default);

        $chosen = $this->actingAs($l)->get(route('landlord.leases.panel', ['open' => [$second->property_id]]))->getContent();
        $this->assertMatchesRegularExpression('/data-id="'.$second->property_id.'"\s+open/', $chosen);
        $this->assertDoesNotMatchRegularExpression('/data-id="'.$first->property_id.'"\s+open/', $chosen);

        $none = $this->actingAs($l)->get(route('landlord.leases.panel', ['open' => '']))->getContent();
        $this->assertDoesNotMatchRegularExpression('/data-id="\d+"\s+open/', $none);
    }

    public function test_the_policies_panel_shrugs_off_hostile_open_values(): void
    {
        ['landlord' => $l] = $this->setupLandlord();

        foreach ([['open' => ["x' OR 1=1 -- "]], ['open' => [['a' => 1]]], ['open' => 'abc'], ['open' => ['99999999999999999999']]] as $query) {
            $this->actingAs($l)->get(route('landlord.leases.panel', $query))->assertOk();
        }
    }

    public function test_the_policies_panel_is_for_landlords_only(): void
    {
        $this->actingAs($this->makeTenant())->get(route('landlord.leases.panel'))->assertForbidden();
    }

    public function test_assigning_from_the_modal_answers_json_and_a_normal_form_still_redirects(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();
        $template = $this->template($l);
        $payload = ['target' => 'property', 'id' => $prop->property_id, 'lease_template_id' => $template->lease_template_id];

        $this->actingAs($l)->patchJson(route('landlord.lease-templates.assign'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Policy updated. It applies to agreements you send from now on.');
        $this->assertSame($template->lease_template_id, $prop->fresh()->lease_template_id);

        $this->actingAs($l)->patch(route('landlord.lease-templates.assign'), ['lease_template_id' => ''] + $payload)->assertRedirect();
        $this->assertNull($prop->fresh()->lease_template_id);
    }

    public function test_the_modal_cannot_assign_someone_elses_policy_or_property(): void
    {
        ['landlord' => $l, 'prop' => $prop] = $this->setupLandlord();
        $theirs = $this->template($this->makeLandlord());

        $this->actingAs($l)->patchJson(route('landlord.lease-templates.assign'), ['target' => 'property', 'id' => $prop->property_id, 'lease_template_id' => $theirs->lease_template_id])
            ->assertStatus(422);

        $stranger = $this->makeLandlord();
        $this->actingAs($stranger)->patchJson(route('landlord.lease-templates.assign'), ['target' => 'property', 'id' => $prop->property_id, 'lease_template_id' => null])
            ->assertNotFound();
    }

    public function test_summary_chips_describe_a_policy_in_a_few_labels(): void
    {
        $l = $this->makeLandlord();

        $fixed = $this->template($l, ['late_fee_type' => 'fixed', 'late_fee_value' => 200, 'notice_days' => 7]);
        $this->assertContains('₱200.00 late fee', $fixed->summaryChips());
        $this->assertContains('7 days notice', $fixed->summaryChips());

        $none = $this->template($l, ['name' => 'No fee', 'late_fee_type' => 'none']);
        $this->assertContains('No late fee', $none->summaryChips());
    }
}
