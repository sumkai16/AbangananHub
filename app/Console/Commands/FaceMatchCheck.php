<?php

namespace App\Console\Commands;

use App\Services\FaceMatch;
use Illuminate\Console\Command;

/**
 * Tries the face match on two pictures, so the AWS keys and the IAM permission can be checked without going
 * through a whole landlord application. Prints the result and never the keys.
 */
class FaceMatchCheck extends Command
{
    protected $signature = 'face-match:check
        {id : Path to a photo that shows the ID (or any face photo)}
        {selfie : Path to a second photo of a face}';

    protected $description = 'Compare two photos with AWS Rekognition to check the face match setup';

    public function handle(FaceMatch $faceMatch): int
    {
        $keys = config('services.rekognition.key') && config('services.rekognition.secret');
        $this->line('Keys set: '.($keys ? 'yes' : 'NO (the check is skipped without them)'));
        $this->line('Region: '.config('services.rekognition.region'));
        $this->line('Threshold: '.config('services.rekognition.threshold').'%');

        foreach (['id', 'selfie'] as $arg) {
            $path = (string) $this->argument($arg);

            if (! is_file($path) || ! is_readable($path)) {
                $this->error("Cannot read {$arg}: {$path}");

                return self::INVALID;
            }
        }

        $result = $faceMatch->compare(file_get_contents($this->argument('id')), file_get_contents($this->argument('selfie')));

        $meaning = [
            FaceMatch::MATCH => 'Rekognition worked: the faces match.',
            FaceMatch::MISMATCH => 'Rekognition worked: the faces do not match.',
            FaceMatch::NO_FACE => 'Rekognition worked (keys and permission are fine) but found no clear face in a photo.',
            FaceMatch::UNAVAILABLE => 'NOT working: no keys, or AWS refused the call. Look at the newest "FaceMatch:" line in storage/logs/laravel.log.',
        ];

        $this->newLine();
        $this->line('Status: '.$result['status']);
        $this->line('Similarity: '.($result['similarity'] === null ? '-' : $result['similarity'].'%'));
        $this->line($meaning[$result['status']] ?? '');

        return $result['status'] === FaceMatch::UNAVAILABLE ? self::FAILURE : self::SUCCESS;
    }
}
