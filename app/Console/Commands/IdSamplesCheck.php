<?php

namespace App\Console\Commands;

use App\Services\IdTypeDetector;
use App\Services\OcrService;
use App\Support\IdTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Tunes the ID templates (App\Support\IdTemplates) against real ID photos. Reads every image in
 * storage/app/id-samples/<type folder>/ with Google Vision and reports which type the detector names, every type's
 * score and the ID number it finds. A file named with "back" is checked as a back.
 *
 * The samples are real people's IDs: they and the text written to _text/ stay in that gitignored folder and never go
 * into the repo or the tests. Vision readings are cached for 30 minutes, so a re-run right after costs no quota.
 */
class IdSamplesCheck extends Command
{
    protected $signature = 'id-samples:check {--text : print the text Vision read for each file}';

    protected $description = 'Check the ID type templates against the local ID sample photos';

    /** Sample folder => the ID type its photos show. */
    private const FOLDERS = [
        'philsys' => IdTemplates::PHILSYS,
        'drivers-license' => IdTemplates::DRIVERS_LICENSE,
        'passport' => IdTemplates::PASSPORT,
        'prc' => IdTemplates::PRC,
        'umid' => IdTemplates::UMID,
        'postal' => IdTemplates::POSTAL,
        'sss' => IdTemplates::SSS,
    ];

    public function handle(OcrService $ocr, IdTypeDetector $detector): int
    {
        $root = storage_path('app/id-samples');

        if (! is_dir($root)) {
            $this->error("No samples: put ID photos in {$root}/<type>/ (folders: ".implode(', ', array_keys(self::FOLDERS)).').');

            return self::FAILURE;
        }

        $textDir = $root.'/_text';
        File::ensureDirectoryExists($textDir);

        $rows = [];
        $wrong = 0;

        foreach (self::FOLDERS as $folder => $expected) {
            foreach (glob("{$root}/{$folder}/*.{jpg,jpeg,png,webp}", GLOB_BRACE) ?: [] as $path) {
                $name = $folder.'/'.basename($path);
                $reading = $ocr->read((string) file_get_contents($path));

                if (! $reading->available) {
                    $rows[] = [$name, $expected, '—', 'Vision unavailable (see laravel.log)', '', '⚠'];
                    $wrong++;

                    continue;
                }

                File::put($textDir.'/'.str_replace('/', '__', $name).'.txt', $reading->text);

                $isBack = str_contains(strtolower(basename($path)), 'back') && ! str_contains(strtolower(basename($path)), 'front');
                $detected = $isBack ? $detector->detectBack($reading->text) : $detector->detectFront($reading->text);

                // A type detection never names (no samples yet) passes when the picked-type check calls it a match.
                $ok = in_array($expected, IdTemplates::detectable(), true)
                    ? $detected === $expected
                    : $detector->matchFront($reading->text, $expected)['status'] === IdTypeDetector::MATCH;
                $wrong += $ok ? 0 : 1;

                $scores = [];
                foreach (IdTemplates::names() as $type) {
                    $score = $detector->scoreFront($reading->text, $type);
                    if ($score > 0) {
                        $scores[] = "{$type} {$score}";
                    }
                }

                $rows[] = [
                    $name.($isBack ? ' (back)' : ''),
                    $expected,
                    $detected ?? '—',
                    implode(', ', $scores) ?: '—',
                    $isBack ? '' : ($detector->idNumber($reading->text, $expected) ?? '—'),
                    $ok ? '✅' : '❌',
                ];

                if ($this->option('text')) {
                    $this->line("── {$name}");
                    $this->line($reading->text);
                }
            }
        }

        if ($rows === []) {
            $this->warn('No images found under '.$root);

            return self::FAILURE;
        }

        $this->table(['File', 'Expected', 'Detected', 'Front scores', 'ID number', ''], $rows);
        $this->line("Vision's text for each file is in {$textDir}");

        if ($wrong > 0) {
            $this->error("{$wrong} file(s) not detected as their type.");

            return self::FAILURE;
        }

        $this->info('Every sample is detected as its own type.');

        return self::SUCCESS;
    }
}
