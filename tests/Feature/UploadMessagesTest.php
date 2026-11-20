<?php

namespace Tests\Feature;

use App\Support\UploadMessages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * A file that does not upload says which file and why ("The file failed to upload." and "may not be greater than
 * 10240 kilobytes" said neither). No database: these run the validator directly with real UploadedFile objects,
 * including ones PHP itself marked as failed.
 */
class UploadMessagesTest extends TestCase
{
    private const DOC_RULES = ['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']];

    /** A file as PHP hands it over when the upload itself went wrong (error code set, nothing on disk). */
    private function failedUpload(int $code, string $name = 'IMG_2034.jpg'): UploadedFile
    {
        return new UploadedFile(sys_get_temp_dir().'/does-not-exist-'.$name, $name, 'image/jpeg', $code, true);
    }

    private function firstMessage(array $data, array $rules, string $key = 'file'): string
    {
        $validator = Validator::make($data, $rules);
        $this->assertTrue($validator->fails());

        return $validator->errors()->first($key);
    }

    public function test_a_file_over_the_servers_limit_names_the_file_and_the_limit(): void
    {
        $message = $this->firstMessage(['file' => $this->failedUpload(UPLOAD_ERR_INI_SIZE)], self::DOC_RULES);

        $this->assertStringContainsString('“IMG_2034.jpg”', $message);
        $this->assertStringContainsString('bigger than this server accepts', $message);
        $this->assertStringContainsString('('.UploadMessages::serverLimitLabel().' at most)', $message);
        $this->assertStringNotContainsString('failed to upload', $message);
    }

    public function test_a_dropped_connection_is_said_to_be_a_dropped_connection(): void
    {
        $message = $this->firstMessage(['file' => $this->failedUpload(UPLOAD_ERR_PARTIAL)], self::DOC_RULES);

        $this->assertStringContainsString('only partly uploaded', $message);
        $this->assertStringContainsString('connection dropped', $message);
    }

    public function test_a_server_side_problem_is_not_blamed_on_the_file(): void
    {
        foreach ([UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE] as $code) {
            $message = $this->firstMessage(['file' => $this->failedUpload($code)], self::DOC_RULES);

            $this->assertStringContainsString('problem on our side', $message);
        }
    }

    public function test_a_file_over_the_apps_own_limit_says_its_size_and_the_limit_in_megabytes(): void
    {
        $big = UploadedFile::fake()->create('lease-scan.pdf', 11000, 'application/pdf'); // 11000 KB

        $message = $this->firstMessage(['file' => $big], self::DOC_RULES);

        $this->assertStringContainsString('“lease-scan.pdf” is 10.7 MB', $message);
        $this->assertStringContainsString('The most allowed here is 10 MB', $message);
        $this->assertStringNotContainsString('kilobytes', $message);
    }

    public function test_a_wrong_type_names_the_type_and_lists_what_is_accepted(): void
    {
        $message = $this->firstMessage(['file' => UploadedFile::fake()->create('notes.docx', 50)], self::DOC_RULES);

        $this->assertStringContainsString('“notes.docx” is a .DOCX file', $message);
        $this->assertStringContainsString('Use a PDF, JPG, PNG or WebP file', $message);
    }

    public function test_an_iphone_heic_photo_gets_the_practical_advice(): void
    {
        $message = $this->firstMessage(['file' => UploadedFile::fake()->create('IMG_0042.HEIC', 50, 'image/heic')], self::DOC_RULES);

        $this->assertStringContainsString('HEIC', $message);
        $this->assertStringContainsString('Most Compatible', $message);
    }

    public function test_a_photo_field_gets_the_photo_wording_per_file(): void
    {
        $rules = ['photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120']];

        $message = $this->firstMessage(['photos' => [UploadedFile::fake()->image('ok.jpg'), UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf')]], $rules, 'photos.1');

        $this->assertStringContainsString('“plan.pdf”', $message);
        $this->assertStringContainsString('JPG, PNG or WebP', $message);
        $this->assertFalse(Validator::make(['photos' => [UploadedFile::fake()->image('ok.jpg')]], $rules)->fails());
    }

    public function test_values_that_are_not_files_keep_laravels_own_wording(): void
    {
        $this->assertSame('The name field must not be greater than 3 characters.', $this->firstMessage(['name' => 'abcdef'], ['name' => ['max:3']], 'name'));
        $this->assertSame('The n field must not be greater than 5.', $this->firstMessage(['n' => 9], ['n' => ['numeric', 'max:5']], 'n'));
        $this->assertStringContainsString('3', $this->firstMessage(['tags' => [1, 2, 3, 4]], ['tags' => ['array', 'max:3']], 'tags'));
    }

    public function test_the_limits_are_read_from_php_not_hardcoded(): void
    {
        $bytes = UploadMessages::serverLimitBytes();
        $smaller = min(array_filter([
            $this->iniBytes(ini_get('upload_max_filesize')),
            $this->iniBytes(ini_get('post_max_size')),
        ]));

        $this->assertSame($smaller, $bytes);
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? (KB|MB)$/', UploadMessages::serverLimitLabel());
    }

    private function iniBytes(string $v): int
    {
        return (int) $v * match (strtolower(substr(trim($v), -1))) { 'k' => 1024, 'm' => 1048576, 'g' => 1073741824, default => 1 };
    }
}
