<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LeaseTemplate;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\TenancyDocument;
use App\Models\TenantDetail;
use App\Services\TenancyDocuments;
use App\Support\RequiredDocuments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Documents a lease asks the tenant to upload before signing (plans/required-tenant-documents.md). */
class TenancyDocumentsTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Storage::fake(TenancyDocuments::DISK);
    }

    /** A reservation in negotiation on a lease that asks for a valid ID and one custom document. */
    private function setupAsking(array $required = null): array
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $prop = $this->makeProperty($landlord);
        $unit = $this->makeUnit($prop, ['rental_fee' => 3000]);
        $template = new LeaseTemplate([
            'name' => 'Docs lease',
            'required_documents' => $required ?? [['key' => 'valid_id'], ['key' => 'other', 'label' => 'Letter from employer']],
        ]);
        $template->landlord_id = $landlord->user_id;
        $template->save();
        $prop->forceFill(['lease_template_id' => $template->lease_template_id])->save();

        $conversation = Conversation::create(['tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $prop->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry']);
        $reservation = Reservation::create([
            'tenant_id' => $tenant->user_id, 'property_id' => $prop->property_id, 'unit_id' => $unit->unit_id,
            'conversation_id' => $conversation->conversation_id, 'rental_status' => 'Under Negotiation',
            'reservation_date' => today(), 'target_move_in_date' => today()->addWeek(), 'agreed_monthly_rent' => 3000, 'rent_due_day' => 5,
        ]);
        TenantDetail::saveFor($tenant, [
            'emergency_contact_name' => 'Ana Cruz', 'emergency_contact_relationship' => 'Parent',
            'emergency_contact_number' => '0917 123 4567', 'id_type' => 'PhilSys', 'id_number' => '1234-5678-9012-3456',
        ]);

        return compact('landlord', 'tenant', 'prop', 'template', 'reservation', 'conversation');
    }

    private function send(Reservation $r): Reservation
    {
        $r->advanceToPendingAgreement();

        return $r->fresh();
    }

    /** A real 1x1 PNG. UploadedFile::fake()->image() needs the GD extension, which not every machine has. */
    private function png(string $name = 'id.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    private function doc(Reservation $r, string $key): TenancyDocument
    {
        return $r->documents()->where('doc_key', $key)->firstOrFail();
    }

    // ─── what is asked, and frozen ─────────────────────────────

    public function test_normalize_keeps_known_types_and_numbers_custom_ones(): void
    {
        $stored = RequiredDocuments::normalize(['valid_id', 'bogus', 'barangay_clearance'], ['Letter from employer', '  ', 'Pay slip']);

        $this->assertSame([['key' => 'valid_id'], ['key' => 'barangay_clearance'], ['key' => 'other', 'label' => 'Letter from employer'], ['key' => 'other', 'label' => 'Pay slip']], $stored);
        $this->assertSame(['valid_id', 'barangay_clearance', 'other_1', 'other_2'], array_column(RequiredDocuments::forSnapshot($stored), 'key'));
        $this->assertNull(RequiredDocuments::normalize([], ['']));
    }

    public function test_sending_the_agreement_creates_one_row_per_requirement_and_freezes_them(): void
    {
        ['reservation' => $r, 'template' => $t] = $this->setupAsking();
        $r = $this->send($r);

        $this->assertSame(['valid_id', 'other_1'], $r->documents()->orderBy('tenancy_document_id')->pluck('doc_key')->all());
        $this->assertSame('Requested', $this->doc($r, 'valid_id')->status);
        $this->assertSame('Letter from employer', $this->doc($r, 'other_1')->label);

        $t->update(['required_documents' => null]);
        $this->assertCount(2, $r->fresh()->lease_snapshot['required_documents']);
    }

    public function test_a_lease_that_asks_for_nothing_creates_no_rows_and_does_not_block_signing(): void
    {
        ['reservation' => $r, 'tenant' => $tenant] = $this->setupAsking([]);
        $r = $this->send($r);

        $this->assertSame(0, $r->documents()->count());
        $this->actingAs($tenant)->post(route('agreements.sign', $r), ['agree' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Rental Agreement Signed', $r->fresh()->rental_status);
    }

    // ─── the signing gate ──────────────────────────────────────

    public function test_signing_is_refused_until_every_document_is_uploaded(): void
    {
        ['reservation' => $r, 'tenant' => $tenant] = $this->setupAsking();
        $r = $this->send($r);

        $this->actingAs($tenant)->post(route('agreements.sign', $r), ['agree' => '1'])
            ->assertSessionHas('error');
        $this->assertSame('Pending Rental Agreement', $r->fresh()->rental_status);
        $this->assertFalse($r->fresh()->signAgreement('127.0.0.1'), 'the model refuses too, so the API cannot skip it');

        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $this->doc($r, 'valid_id')), ['file' => $this->png()])->assertSessionHasNoErrors();
        $this->actingAs($tenant)->post(route('agreements.sign', $r), ['agree' => '1'])->assertSessionHas('error');

        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $this->doc($r, 'other_1')), ['file' => $this->png('letter.png')]);
        $this->actingAs($tenant)->post(route('agreements.sign', $r), ['agree' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Rental Agreement Signed', $r->fresh()->rental_status);
    }

    public function test_the_agreement_page_shows_the_card_and_a_locked_sign_button(): void
    {
        ['reservation' => $r, 'tenant' => $tenant] = $this->setupAsking();
        $r = $this->send($r);

        $this->actingAs($tenant)->get(route('agreements.show', $r))
            ->assertOk()->assertSee('Documents to upload')->assertSee('Letter from employer')
            ->assertSee('Upload 2 more documents above to sign.');
    }

    public function test_a_sent_back_document_blocks_signing_until_it_is_uploaded_again(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking([['key' => 'valid_id']]);
        $r = $this->send($r);
        $doc = $this->doc($r, 'valid_id');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()]);

        $this->actingAs($l)->post(route('tenancy-documents.reject', $doc), ['rejection_reason' => 'Blurry photo'])->assertSessionHasNoErrors();
        $this->assertSame('Rejected', $doc->fresh()->status);
        $this->assertTrue(TenancyDocuments::blocksSigning($r));

        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png('again.png')]);
        $this->assertSame('Pending', $doc->fresh()->status);
        $this->assertNull($doc->fresh()->rejection_reason);
        $this->assertFalse(TenancyDocuments::blocksSigning($r));
    }

    // ─── upload rules ──────────────────────────────────────────

    public function test_only_the_tenant_can_upload_and_only_pdf_or_images(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking([['key' => 'valid_id']]);
        $doc = $this->doc($this->send($r), 'valid_id');

        $this->actingAs($l)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()])->assertForbidden();
        $this->actingAs($this->makeTenant())->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()])->assertForbidden();
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame('Pending', $doc->fresh()->status);
    }

    public function test_replacing_a_file_deletes_the_old_one_and_an_approved_one_is_locked(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking([['key' => 'valid_id']]);
        $doc = $this->doc($this->send($r), 'valid_id');

        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()]);
        $first = $doc->fresh()->file_path;
        Storage::disk(TenancyDocuments::DISK)->assertExists($first);
        $this->assertStringNotContainsString('id.png', $first, 'the original filename is never on disk');

        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png('two.png')]);
        Storage::disk(TenancyDocuments::DISK)->assertMissing($first);

        $this->actingAs($l)->post(route('tenancy-documents.approve', $doc))->assertSessionHasNoErrors();
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png('three.png')])->assertSessionHasErrors('document');
    }

    // ─── review ────────────────────────────────────────────────

    public function test_the_landlord_approves_rejects_and_waives_and_the_tenant_is_told(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking();
        $r = $this->send($r);
        $id = $this->doc($r, 'valid_id');
        $letter = $this->doc($r, 'other_1');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $id), ['file' => $this->png()]);

        $this->actingAs($l)->post(route('tenancy-documents.approve', $id))->assertSessionHasNoErrors();
        $this->assertSame('Approved', $id->fresh()->status);
        $this->assertSame($l->user_id, $id->fresh()->reviewed_by);

        $this->actingAs($l)->post(route('tenancy-documents.waive', $letter))->assertSessionHasNoErrors();
        $this->assertSame('Waived', $letter->fresh()->status);
        $this->assertFalse(TenancyDocuments::blocksSigning($r));
        $this->assertGreaterThan(0, Notification::where('user_id', $tenant->user_id)->where('title', 'like', 'Document %')->count());

        $this->actingAs($l)->post(route('tenancy-documents.reopen', $id));
        $this->assertSame('Pending', $id->fresh()->status);
    }

    public function test_a_rejection_needs_a_reason_and_an_empty_requirement_cannot_be_approved(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking([['key' => 'valid_id']]);
        $doc = $this->doc($this->send($r), 'valid_id');

        $this->actingAs($l)->post(route('tenancy-documents.approve', $doc))->assertSessionHasErrors('document');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()]);
        $this->actingAs($l)->post(route('tenancy-documents.reject', $doc), [])->assertSessionHasErrors('rejection_reason');
    }

    public function test_the_tenant_and_other_landlords_cannot_review(): void
    {
        ['reservation' => $r, 'tenant' => $tenant] = $this->setupAsking([['key' => 'valid_id']]);
        $doc = $this->doc($this->send($r), 'valid_id');

        $this->actingAs($tenant)->post(route('tenancy-documents.approve', $doc))->assertForbidden();
        $this->actingAs($tenant)->post(route('tenancy-documents.waive', $doc))->assertForbidden();
        $this->actingAs($this->makeLandlord())->post(route('tenancy-documents.approve', $doc))->assertForbidden();
    }

    // ─── private files ─────────────────────────────────────────

    public function test_the_file_is_served_to_the_tenant_and_the_landlord_only_and_the_landlords_view_is_logged(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking([['key' => 'valid_id']]);
        $doc = $this->doc($this->send($r), 'valid_id');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()]);

        $this->actingAs($tenant)->get(route('tenancy-documents.file', $doc))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(0, \App\Models\AuditLog::where('action', 'tenancy_document.view')->count());

        $this->actingAs($l)->get(route('tenancy-documents.file', $doc))->assertOk();
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'tenancy_document.view')->count());

        $this->actingAs($this->makeLandlord())->get(route('tenancy-documents.file', $doc))->assertForbidden();
        $this->actingAs($this->makeTenant())->get(route('tenancy-documents.file', $doc))->assertForbidden();
        $admin = $this->makeUserWithRole('Admin');
        $this->actingAs($admin)->get(route('tenancy-documents.file', $doc))->assertForbidden();
    }

    // ─── retention ─────────────────────────────────────────────

    public function test_files_are_deleted_at_once_when_the_tenancy_ends_before_signing(): void
    {
        ['reservation' => $r, 'tenant' => $tenant] = $this->setupAsking([['key' => 'valid_id']]);
        $r = $this->send($r);
        $doc = $this->doc($r, 'valid_id');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()]);
        $path = $doc->fresh()->file_path;

        $r->fresh()->cancel();

        $this->assertNotNull($doc->fresh()->purged_at);
        $this->assertNull($doc->fresh()->file_path);
        Storage::disk(TenancyDocuments::DISK)->assertMissing($path);
    }

    public function test_after_a_signed_tenancy_ends_files_stay_30_days_then_the_command_deletes_them(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l] = $this->setupAsking([['key' => 'valid_id']]);
        $r = $this->send($r);
        $doc = $this->doc($r, 'valid_id');
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $doc), ['file' => $this->png()]);
        $this->actingAs($l)->post(route('tenancy-documents.approve', $doc));
        $r->fresh()->signAgreement('127.0.0.1');
        $path = $doc->fresh()->file_path;

        $r->fresh()->forceFill(['rental_status' => 'Completed'])->save();
        $this->assertSame(today()->addDays(30)->toDateString(), $doc->fresh()->purge_after->toDateString());

        $this->artisan('reservations:purge-tenant-documents')->assertSuccessful();
        Storage::disk(TenancyDocuments::DISK)->assertExists($path);

        $doc->update(['purge_after' => today()->subDay()]);
        $this->artisan('reservations:purge-tenant-documents')->assertSuccessful();
        Storage::disk(TenancyDocuments::DISK)->assertMissing($path);
        $this->assertSame('Approved', $doc->fresh()->status, 'the record stays');
        $this->assertNotNull($doc->fresh()->purged_at);
    }

    // ─── the lease editor and the landlord's view ──────────────

    public function test_the_template_editor_saves_required_documents(): void
    {
        ['landlord' => $l] = $this->setupAsking();

        $this->actingAs($l)->post(route('landlord.lease-templates.store'), [
            'name' => 'With docs', 'required_documents' => ['valid_id', 'police_clearance'], 'custom_documents' => ['Pay slip'],
        ])->assertSessionHasNoErrors();

        $t = LeaseTemplate::where('name', 'With docs')->firstOrFail();
        $this->assertSame([['key' => 'valid_id'], ['key' => 'police_clearance'], ['key' => 'other', 'label' => 'Pay slip']], $t->required_documents);
        $this->actingAs($l)->get(route('landlord.lease-templates.edit', $t))->assertOk()->assertSee('Documents tenants upload')->assertSee('Pay slip');
        $this->actingAs($l)->get(route('landlord.leases.index'))->assertOk();
        $this->actingAs($l)->post(route('landlord.lease-templates.store'), ['name' => 'X', 'required_documents' => ['nope']])->assertSessionHasErrors('required_documents.0');
    }

    public function test_the_landlord_sees_documents_in_the_chat_and_the_tenant_sees_what_to_have_ready(): void
    {
        ['reservation' => $r, 'tenant' => $tenant, 'landlord' => $l, 'conversation' => $c] = $this->setupAsking([['key' => 'valid_id']]);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        // Before sending: the tenant is told what to prepare.
        $this->actingAs($tenant)->get(route('conversations.show', $c), $ajax)->assertOk()->assertSee('Have these ready to upload')->assertSee('Valid government ID');

        $r = $this->send($r);
        $this->actingAs($tenant)->post(route('tenancy-documents.upload', $this->doc($r, 'valid_id')), ['file' => $this->png()]);

        $this->actingAs($l)->get(route('conversations.show', $c), $ajax)->assertOk()
            ->assertSee('1 to review')->assertSee('Valid government ID')->assertSee('Approve');
        $this->actingAs($tenant)->get(route('conversations.show', $c), $ajax)->assertOk()
            ->assertDontSee('1 to review')->assertSee('Documents uploaded');
        $this->actingAs($l)->get(route('landlord.leases.index', ['tab' => 'sent']))->assertOk()->assertSee('Docs 1/1');
    }
}
