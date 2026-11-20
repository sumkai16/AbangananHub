<?php

namespace Tests\Feature;

use App\Models\PropertyDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Uploading, replacing or removing a property document from the add-property wizard's Documents step brings the
 * landlord back to that step (to carry on with Tax Declaration, Business Permit...), not to the property page.
 * From the property page's own Documents tab it still returns to the property page.
 */
class WizardDocumentsRedirectTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    /** A real 1x1 PNG, so the file passes the image checks. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function png(string $name = 'doc.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(self::PNG));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function draft($landlord)
    {
        return $this->makeProperty($landlord, ['publication_status' => 'Draft', 'verification_status' => 'Pending']);
    }

    private function document($property, string $type = 'Proof of Ownership', string $status = 'Pending'): PropertyDocument
    {
        return $property->documents()->create([
            'document_type' => $type, 'file_path' => 'property_documents/'.$property->property_id.'/old.png', 'file_name' => 'old.png', 'status' => $status,
        ]);
    }

    private function wizardUrl($property): string
    {
        return route('properties.wizard.documents', $property);
    }

    private function pageUrl($property): string
    {
        return route('landlord.properties.show', $property).'#documents';
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    public function test_an_upload_from_the_wizard_returns_to_the_wizard_documents_step(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.documents.store', $property), [
            'from' => 'wizard', 'document_type' => 'Proof of Ownership', 'file' => $this->png(),
        ])->assertRedirect($this->wizardUrl($property))->assertSessionHas('success');

        $this->assertSame(1, $property->documents()->count());
    }

    public function test_an_upload_from_the_property_page_still_returns_to_the_property_page(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.documents.store', $property), [
            'document_type' => 'Tax Declaration', 'file' => $this->png(),
        ])->assertRedirect($this->pageUrl($property));
    }

    public function test_the_wizard_value_is_ignored_once_the_listing_is_not_a_draft(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord); // Approved and Published

        $this->actingAs($landlord)->post(route('landlord.properties.documents.store', $property), [
            'from' => 'wizard', 'document_type' => 'Proof of Ownership', 'file' => $this->png(),
        ])->assertRedirect($this->pageUrl($property));
    }

    public function test_any_other_from_value_means_the_property_page(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.documents.store', $property), [
            'from' => 'somewhere-else', 'document_type' => 'Proof of Ownership', 'file' => $this->png(),
        ])->assertRedirect($this->pageUrl($property));
    }

    public function test_a_refused_file_from_the_wizard_goes_back_to_the_page_it_came_from(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);

        $this->actingAs($landlord)->from($this->wizardUrl($property))->post(route('landlord.properties.documents.store', $property), [
            'from' => 'wizard', 'document_type' => 'Proof of Ownership',
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text, not a document'),
        ])->assertRedirect($this->wizardUrl($property))->assertSessionHasErrors('file');

        $this->assertSame(0, $property->documents()->count());
    }

    // ── Replace and remove ────────────────────────────────────────────────────

    public function test_a_replacement_follows_the_same_rule(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);
        $document = $this->document($property);
        $url = route('landlord.properties.documents.replace', [$property, $document]);

        $this->actingAs($landlord)->post($url, ['from' => 'wizard', 'document_type' => 'Proof of Ownership', 'file' => $this->png()])
            ->assertRedirect($this->wizardUrl($property));

        $this->actingAs($landlord)->post($url, ['document_type' => 'Proof of Ownership', 'file' => $this->png('again.png')])
            ->assertRedirect($this->pageUrl($property));
    }

    public function test_a_removal_follows_the_same_rule(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);

        $fromWizard = $this->document($property, 'Proof of Ownership');
        $this->actingAs($landlord)->delete(route('landlord.properties.documents.destroy', [$property, $fromWizard]), ['from' => 'wizard'])
            ->assertRedirect($this->wizardUrl($property))->assertSessionHas('success');
        $this->assertNull(PropertyDocument::find($fromWizard->document_id));

        $fromPage = $this->document($property, 'Tax Declaration');
        $this->actingAs($landlord)->delete(route('landlord.properties.documents.destroy', [$property, $fromPage]))
            ->assertRedirect($this->pageUrl($property));
    }

    public function test_refusing_to_remove_a_verified_document_also_returns_to_the_wizard(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);
        $document = $this->document($property, 'Proof of Ownership', 'Verified');

        $this->actingAs($landlord)->delete(route('landlord.properties.documents.destroy', [$property, $document]), ['from' => 'wizard'])
            ->assertRedirect($this->wizardUrl($property))->assertSessionHasErrors('document');

        $this->assertNotNull(PropertyDocument::find($document->document_id));
    }

    public function test_another_landlord_is_refused(): void
    {
        $property = $this->draft($this->makeLandlord());
        $document = $this->document($property);
        $stranger = $this->makeLandlord();

        $this->actingAs($stranger)->post(route('landlord.properties.documents.store', $property), [
            'from' => 'wizard', 'document_type' => 'Proof of Ownership', 'file' => $this->png(),
        ])->assertForbidden();
        $this->actingAs($stranger)->delete(route('landlord.properties.documents.destroy', [$property, $document]), ['from' => 'wizard'])->assertForbidden();
    }

    // ── The forms carry the marker ────────────────────────────────────────────

    public function test_every_form_on_the_wizard_documents_step_carries_the_marker(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);
        $this->document($property, 'Proof of Ownership'); // a row with a Remove form; Tax Declaration and others show their upload forms

        $html = $this->actingAs($landlord)->get($this->wizardUrl($property))->assertOk()->getContent();

        // Count the forms that post to a document route and the markers: one marker per form.
        $forms = preg_match_all('#<form[^>]*landlord/properties/\d+/documents#', $html);
        $markers = substr_count($html, 'name="from" value="wizard"');

        $this->assertGreaterThanOrEqual(2, $forms);
        $this->assertSame($forms, $markers, "Expected one hidden from=wizard input per document form ({$forms}), found {$markers}.");
    }

    public function test_the_property_pages_documents_tab_does_not_carry_the_marker(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->draft($landlord);
        $this->document($property);

        $this->actingAs($landlord)->get(route('landlord.properties.show', $property))->assertOk()
            ->assertDontSee('name="from" value="wizard"', false);
    }
}
