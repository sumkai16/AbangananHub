<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Browsers compile an input's `pattern` with the JavaScript `v` flag. Under that flag an unescaped
 * ( ) { } | / or [ inside a character class is a syntax error, and the browser then silently drops the
 * whole check (a phone of "12" slipped through the sign-up form for exactly that reason).
 */
class HtmlPatternAttributeTest extends TestCase
{
    public function test_pattern_attributes_stay_valid_under_the_v_flag(): void
    {
        $bad = [];

        foreach ($this->bladeFiles() as $file) {
            preg_match_all('/\spattern="([^"]*)"/', file_get_contents($file), $found);

            foreach ($found[1] as $pattern) {
                // Blade echoes inside the attribute are not part of the literal pattern.
                if (str_contains($pattern, '{{') || str_contains($pattern, '@')) {
                    continue;
                }

                foreach ($this->unescapedClassCharacters($pattern) as $char) {
                    $bad[] = str_replace(base_path().'/', '', $file)." — pattern=\"{$pattern}\" has an unescaped {$char} inside [...]";
                }
            }
        }

        $this->assertSame([], $bad, "Escape these with a backslash:\n".implode("\n", $bad));
    }

    /** The characters inside each [...] class that the v flag insists on escaping. */
    private function unescapedClassCharacters(string $pattern): array
    {
        $found = [];
        $inClass = false;
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $c = $pattern[$i];

            if ($c === '\\') {
                $i++; // an escaped character is always fine

                continue;
            }
            if (! $inClass) {
                $inClass = $c === '[';

                continue;
            }
            if ($c === ']') {
                $inClass = false;
            } elseif (str_contains('(){}|/[', $c)) {
                $found[] = $c;
            }
        }

        return $found;
    }

    private function bladeFiles(): iterable
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file->getPathname();
            }
        }
    }
}
