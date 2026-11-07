<?php

namespace Tests\Feature;

use App\Models\PropertyDocument;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * File uploads are the way a site is usually taken over: a script saved as "photo.jpg", a page that runs in
 * the admin's browser, or a path like ../../ in the file name. Each upload route is sent files that pretend
 * to be something else. A real image or PDF is kept under a random name with a safe extension; anything
 * else is refused and nothing is written to disk.
 */
class UploadAttackTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Storage::fake('local');
        Storage::fake('public');
    }

    /** @return array<string, array{string, string}> name the browser claims, real content */
    public static function disguisedFiles(): array
    {
        return [
            'php script named .jpg'        => ['shell.jpg', '<?php system($_GET["c"]); ?>'],
            'php script named .pdf'        => ['invoice.pdf', "<?php echo shell_exec('id'); ?>"],
            'php script named .php'        => ['shell.php', '<?php system($_GET["c"]); ?>'],
            'html page named .png'         => ['photo.png', '<html><script>alert(document.cookie)</script></html>'],
            'svg with a script, named .png' => ['logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'svg named .svg'               => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'gif header then php'          => ['pic.jpg', "GIF89a<?php system('id'); ?>"],
            'executable named .pdf'        => ['report.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00"],
            'shell script named .jpeg'     => ['x.jpeg', "#!/bin/sh\nrm -rf /\n"],
            'empty file'                   => ['empty.jpg', ''],
        ];
    }

    /**
     * A real file on disk. UploadedFile::fake() decides the type from the *name*, which would hide the very thing
     * under test: validation reads the content, so the content has to be real.
     */
    private function fake(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /** A real 1x1 PNG, under a name an attacker chose. */
    private function realPng(string $name): UploadedFile
    {
        return $this->fake($name, base64_decode(self::PNG));
    }

    private function nothingWasStored(): void
    {
        $this->assertSame([], Storage::disk('local')->allFiles(), 'A refused upload left a file on the private disk.');
        $this->assertSame([], Storage::disk('public')->allFiles(), 'A refused upload left a file on the public disk.');
    }

    // ── Landlord: signed agreement (a PDF, JPG or PNG) ────────────────────────

    #[DataProvider('disguisedFiles')]
    public function test_the_signed_agreement_refuses_disguised_files(string $name, string $content): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        $this->actingAs($landlord)
            ->post(route('landlord.leases.upload', $reservation), ['lease_file' => $this->fake($name, $content)])
            ->assertSessionHasErrors('lease_file');

        $this->assertNull($reservation->fresh()->lease_file_path);
        $this->nothingWasStored();
    }

    public function test_the_signed_agreement_is_kept_under_a_random_safe_name_whatever_the_browser_called_it(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        foreach (['a/b/c.png', 'x.php.png', "line\r\nbreak.png", 'sp ace;rm -rf.png'] as $clientName) {
            $this->actingAs($landlord)
                ->post(route('landlord.leases.upload', $reservation), ['lease_file' => $this->realPng($clientName)])
                ->assertSessionDoesntHaveErrors();

            $path = $reservation->fresh()->lease_file_path;
            $this->assertNotNull($path, "A real image named [$clientName] was refused.");
            $this->assertMatchesRegularExpression('#^leases/\d+/[A-Za-z0-9]{40}\.(png|jpg|jpeg|pdf)$#', $path, "Stored path for [$clientName] is not a random safe name.");
            Storage::disk('local')->assertExists($path);
        }
    }

    public function test_a_real_image_that_claims_to_be_a_php_script_is_refused(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        foreach (['evil.php', '../../../public/evil.php', 'photo.phtml', 'x.php5', 'x.phar', 'x.PHP'] as $clientName) {
            $this->actingAs($landlord)
                ->post(route('landlord.leases.upload', $reservation), ['lease_file' => $this->realPng($clientName)])
                ->assertSessionHasErrors('lease_file');
        }

        $this->assertNull($reservation->fresh()->lease_file_path);
        $this->nothingWasStored();
    }

    public function test_a_real_image_named_like_a_web_page_is_saved_as_a_png_and_served_as_one(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        foreach (['page.html', 'drawing.svg', 'x.php.'] as $clientName) {
            $this->actingAs($landlord)->post(route('landlord.leases.upload', $reservation), ['lease_file' => $this->realPng($clientName)]);

            // The name the browser sent never reaches the disk: the extension comes from the content.
            $this->assertMatchesRegularExpression('#^leases/\d+/[A-Za-z0-9]{40}\.png$#', $reservation->fresh()->lease_file_path);

            $this->actingAs($landlord)->get(route('landlord.leases.file', $reservation))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_an_oversized_agreement_is_refused(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        $this->actingAs($landlord)
            ->post(route('landlord.leases.upload', $reservation), ['lease_file' => UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')])
            ->assertSessionHasErrors('lease_file');

        $this->nothingWasStored();
    }

    public function test_an_agreement_with_an_odd_file_name_can_still_be_opened_without_a_server_error(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        foreach (['quote"name.png', "line\r\nbreak.png", 'é ñ 日本語.png', str_repeat('a', 300).'.png'] as $clientName) {
            $this->actingAs($landlord)->post(route('landlord.leases.upload', $reservation), ['lease_file' => $this->realPng($clientName)]);

            $this->actingAs($landlord)->get(route('landlord.leases.file', $reservation))
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    // ── Landlord: property documents ──────────────────────────────────────────

    #[DataProvider('disguisedFiles')]
    public function test_property_documents_refuse_disguised_files(string $name, string $content): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)
            ->post(route('landlord.properties.documents.store', $property), ['document_type' => PropertyDocument::TYPES[0], 'file' => $this->fake($name, $content)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $property->documents()->count());
        $this->nothingWasStored();
    }

    public function test_a_property_document_is_served_with_its_real_type_and_nosniff(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)->post(route('landlord.properties.documents.store', $property), [
            'document_type' => PropertyDocument::TYPES[0], 'file' => $this->realPng('title.png'),
        ])->assertSessionDoesntHaveErrors();

        $document = $property->documents()->firstOrFail();
        $this->assertMatchesRegularExpression('#^property_documents/\d+/[A-Za-z0-9]{40}\.png$#', $document->file_path);

        $this->actingAs($landlord)->get(route('landlord.properties.documents.preview', [$property, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // ── Profile photo ─────────────────────────────────────────────────────────

    #[DataProvider('disguisedFiles')]
    public function test_the_profile_photo_refuses_disguised_files(string $name, string $content): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($tenant)
            ->post(route('profile.photo'), ['photo' => $this->fake($name, $content)])
            ->assertSessionHasErrors('photo');

        $this->nothingWasStored();
    }

    // ── ID and selfie from the camera (base64 text, not a file) ───────────────

    /** @return array<string, array{string}> */
    public static function notAnImage(): array
    {
        return [
            'php as base64'       => [base64_encode('<?php system($_GET["c"]); echo str_repeat("x", 200); ?>')],
            'html as base64'      => [base64_encode('<html><script>alert(document.cookie)</script></html>'.str_repeat(' ', 200))],
            'text with a jpeg label' => ['data:image/jpeg;base64,'.base64_encode(str_repeat('not really a photo ', 20))],
            'svg with a png label'   => ['data:image/png;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'.str_repeat(' ', 200))],
        ];
    }

    #[DataProvider('notAnImage')]
    public function test_the_id_photo_check_never_sends_a_non_image_to_the_ocr_service(string $payload): void
    {
        $this->mock(OcrService::class)->shouldNotReceive('extractText');

        $response = $this->actingAs($this->makeTenant())
            ->postJson(route('landlord.verification.ocrCheck'), ['id_image' => $payload, 'id_type' => 'Passport']);

        $response->assertOk()->assertJsonPath('status', 'fail');
        $this->assertSame([], glob(storage_path('app/private/temp_ocr_*')), 'A temp file was left behind.');
    }

    #[DataProvider('notAnImage')]
    public function test_the_verification_application_refuses_non_image_id_and_selfie_data(string $payload): void
    {
        $this->mock(OcrService::class)->shouldNotReceive('extractText');

        $this->actingAs($this->makeTenant())
            ->post(route('landlord.verification.store'), [
                'id_type' => 'Passport', 'id_image' => $payload, 'selfie' => $payload, 'liveness_passed' => true,
                'business_name' => 'Test Rentals', 'contact_number' => '09171234567', 'business_address' => 'Cebu City',
            ])
            ->assertSessionHasErrors();

        $this->nothingWasStored();
    }
}
