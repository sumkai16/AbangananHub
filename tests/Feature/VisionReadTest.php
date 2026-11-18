<?php

namespace Tests\Feature;

use App\Services\OcrService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionProperty;
use Tests\TestCase;

/**
 * One Google Vision request per photo returns the text, any faces and the labels together. The reading is kept for a
 * short while so the preview and the final submit read the same photo once, and an outage is never kept.
 */
class VisionReadTest extends TestCase
{
    private const IMAGE = 'fake-jpeg-bytes-one';

    private function visionOk(array $overrides = []): void
    {
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [array_merge([
            'fullTextAnnotation' => ['text' => 'REPUBLIKA NG PILIPINAS'],
            'faceAnnotations' => [['detectionConfidence' => 0.98], ['detectionConfidence' => 0.91]],
            'labelAnnotations' => [['description' => 'Identity document', 'score' => 0.91]],
        ], $overrides)]])]);
    }

    private function service(string $key = 'test-key'): OcrService
    {
        config(['services.google_vision.key' => $key]);

        return new OcrService;
    }

    /** Everything the array cache holds, as one string, so a test can look for readable ID text in it. */
    private function cacheContents(): string
    {
        $storage = (new ReflectionProperty(ArrayStore::class, 'storage'))->getValue(Cache::getStore());

        return serialize($storage);
    }

    public function test_one_request_asks_for_text_faces_and_labels(): void
    {
        $this->visionOk();

        $this->service()->read(self::IMAGE);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $types = array_column($request['requests'][0]['features'], 'type');

            return $types === ['DOCUMENT_TEXT_DETECTION', 'FACE_DETECTION', 'LABEL_DETECTION'];
        });
    }

    public function test_reading_maps_text_faces_and_labels(): void
    {
        $this->visionOk();

        $reading = $this->service()->read(self::IMAGE);

        $this->assertSame('REPUBLIKA NG PILIPINAS', $reading->text);
        $this->assertSame(2, $reading->faceCount);
        $this->assertSame(['identity document' => 0.91], $reading->labels);
        $this->assertTrue($reading->available);
    }

    public function test_a_photo_with_no_text_faces_or_labels_reads_as_empty_but_available(): void
    {
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [new \stdClass]])]);

        $reading = $this->service()->read(self::IMAGE);

        $this->assertSame('', $reading->text);
        $this->assertSame(0, $reading->faceCount);
        $this->assertSame([], $reading->labels);
        $this->assertTrue($reading->available);
    }

    public function test_same_image_is_read_once_and_cached_returns_it(): void
    {
        $this->visionOk();
        $service = $this->service();

        $first = $service->read(self::IMAGE);
        $second = $service->read(self::IMAGE);

        Http::assertSentCount(1);
        $this->assertEquals($first, $second);
        $this->assertEquals($first, $service->cached(self::IMAGE));
    }

    public function test_cached_on_an_unseen_image_is_null_and_sends_nothing(): void
    {
        $this->visionOk();

        $this->assertNull($this->service()->cached('some-other-bytes'));

        Http::assertNothingSent();
    }

    public function test_the_id_text_is_not_kept_readable_in_the_cache(): void
    {
        $this->visionOk(['fullTextAnnotation' => ['text' => 'CABUSAS AXCEE 6813-2017-2150-9536']]);

        $this->service()->read(self::IMAGE);

        $this->assertNotSame('', $this->cacheContents());
        $this->assertStringNotContainsString('CABUSAS', $this->cacheContents());
        $this->assertStringNotContainsString('6813-2017-2150-9536', $this->cacheContents());
    }

    public function test_failed_request_is_unavailable_and_not_cached(): void
    {
        Http::fake(['vision.googleapis.com/*' => Http::response('boom', 500)]);
        $service = $this->service();

        $first = $service->read(self::IMAGE);
        $second = $service->read(self::IMAGE);

        $this->assertFalse($first->available);
        $this->assertFalse($second->available);
        $this->assertNull($service->cached(self::IMAGE));
        Http::assertSentCount(2);
    }

    public function test_an_error_inside_a_200_response_is_unavailable(): void
    {
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [['error' => ['code' => 8, 'message' => 'Quota exceeded']]]])]);

        $this->assertFalse($this->service()->read(self::IMAGE)->available);
    }

    public function test_a_connection_failure_is_unavailable(): void
    {
        Http::fake(['vision.googleapis.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->assertFalse($this->service()->read(self::IMAGE)->available);
    }

    public function test_missing_key_is_unavailable_without_a_request(): void
    {
        Http::fake();

        $reading = $this->service('')->read(self::IMAGE);

        $this->assertFalse($reading->available);
        Http::assertNothingSent();
    }
}
