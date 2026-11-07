<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Log;

/**
 * Compares the face on a landlord's ID with their live selfie (AWS Rekognition CompareFaces).
 *
 * It is a signal for the admin and never decides anything: a mismatch can be bad light or an old ID photo, and
 * a match does not prove the ID is genuine. Every failure (no keys, no face, AWS down) comes back as a status,
 * never an exception, so an application is never lost because this check could not run.
 */
class FaceMatch
{
    public const MATCH = 'match';
    public const REVIEW = 'review'; // similar, but below the match line: the admin compares by eye
    public const MISMATCH = 'mismatch';
    public const NO_FACE = 'no_face';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(private ?RekognitionClient $client = null)
    {
    }

    /**
     * @return array{status: string, similarity: ?float}
     */
    public function compare(string $idImageBytes, string $selfieBytes): array
    {
        $client = $this->client ?? $this->makeClient();

        if ($client === null) {
            return ['status' => self::UNAVAILABLE, 'similarity' => null];
        }

        $threshold = (float) config('services.rekognition.threshold', 80);
        $reviewFloor = (float) config('services.rekognition.review_floor', 60);

        try {
            $result = $client->compareFaces([
                'SourceImage' => ['Bytes' => $idImageBytes],
                'TargetImage' => ['Bytes' => $selfieBytes],
                // Ask for every match above a low floor so a weak one can be reported as a score.
                'SimilarityThreshold' => 0,
                'QualityFilter' => 'NONE',
            ]);
        } catch (AwsException $e) {
            // Rekognition answers InvalidParameterException when either picture holds no face.
            if ($e->getAwsErrorCode() === 'InvalidParameterException') {
                return ['status' => self::NO_FACE, 'similarity' => null];
            }

            Log::error('FaceMatch: Rekognition request failed', ['code' => $e->getAwsErrorCode(), 'message' => $e->getAwsErrorMessage()]);

            return ['status' => self::UNAVAILABLE, 'similarity' => null];
        } catch (\Throwable $e) {
            Log::error('FaceMatch: unexpected failure', ['message' => $e->getMessage()]);

            return ['status' => self::UNAVAILABLE, 'similarity' => null];
        }

        $matches = $result['FaceMatches'] ?? [];

        if ($matches === []) {
            // A face was found in both pictures (otherwise Rekognition would have refused) but they are not alike.
            return ['status' => self::MISMATCH, 'similarity' => 0.0];
        }

        $best = round((float) max(array_map(fn ($m) => (float) ($m['Similarity'] ?? 0), $matches)), 2);

        // Three bands, not two: an ID portrait is small, old and often under laminate, so a genuine match can land
        // in the seventies (a real applicant scored 77.4%). That is "similar, look yourself", not "different".
        $status = match (true) {
            $best >= $threshold => self::MATCH,
            $best >= $reviewFloor => self::REVIEW,
            default => self::MISMATCH,
        };

        return ['status' => $status, 'similarity' => $best];
    }

    private function makeClient(): ?RekognitionClient
    {
        $key = (string) config('services.rekognition.key', '');
        $secret = (string) config('services.rekognition.secret', '');

        if ($key === '' || $secret === '') {
            return null;
        }

        return new RekognitionClient([
            'version' => 'latest',
            'region' => (string) config('services.rekognition.region', 'ap-southeast-1'),
            'credentials' => ['key' => $key, 'secret' => $secret],
            'http' => ['timeout' => 15, 'connect_timeout' => 5],
        ]);
    }
}
