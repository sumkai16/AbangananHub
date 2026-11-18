<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The live face check loads its two neural-net models from this site (public/models/face-api) instead of a CDN.
 * A manifest without its shard, or a shard that was cut short, breaks the face check silently in the browser.
 */
class FaceApiModelsTest extends TestCase
{
    private const MODELS = ['tiny_face_detector_model', 'face_landmark_68_model'];

    public function test_every_model_manifest_has_its_weight_files_in_full(): void
    {
        foreach (self::MODELS as $model) {
            $manifestPath = public_path("models/face-api/{$model}-weights_manifest.json");
            $this->assertFileExists($manifestPath);

            $groups = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            foreach ($groups as $group) {
                $expectedBytes = 0;

                foreach ($group['weights'] as $weight) {
                    $expectedBytes += array_product($weight['shape'] ?? [1]) * $this->bytesPerValue($weight);
                }

                $shardBytes = 0;
                foreach ($group['paths'] as $shard) {
                    $this->assertFileExists(public_path("models/face-api/{$shard}"));
                    $shardBytes += filesize(public_path("models/face-api/{$shard}"));
                }

                $this->assertSame($expectedBytes, $shardBytes, "{$model}: the weight files are not the size the manifest describes.");
            }
        }
    }

    public function test_the_wizard_page_tells_the_script_where_the_models_are(): void
    {
        $tenant = \App\Models\User::factory()->create();
        $tenant->assignRole('Tenant');

        $this->actingAs($tenant)
            ->get(route('landlord.verification.create'))
            ->assertOk()
            ->assertSee("modelUrl: '".asset('models/face-api')."'", false);
    }

    /** Bytes one value takes in the shard: quantized weights are stored as 1 or 2 bytes, the rest as 4. */
    private function bytesPerValue(array $weight): int
    {
        return match ($weight['quantization']['dtype'] ?? null) {
            'uint8', 'int8' => 1,
            'uint16', 'int16' => 2,
            default => 4,
        };
    }
}
