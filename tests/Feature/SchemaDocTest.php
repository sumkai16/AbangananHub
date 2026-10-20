<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * context/SCHEMA.md is a reference, and it drifted from the database for months. Every `### table`
 * heading followed by a column table must list exactly the columns the database has, and every
 * application table must be documented.
 */
class SchemaDocTest extends TestCase
{
    private const FRAMEWORK_TABLES = [
        'cache', 'cache_locks', 'failed_jobs', 'jobs', 'job_batches', 'migrations',
        'password_reset_tokens', 'personal_access_tokens', 'sessions',
    ];

    public function test_every_documented_table_matches_the_database(): void
    {
        foreach ($this->documentedTables() as $table => $documented) {
            $this->assertTrue(Schema::hasTable($table), "SCHEMA.md documents `{$table}`, which does not exist.");

            $actual = Schema::getColumnListing($table);
            $this->assertSame(
                [],
                array_values(array_diff($actual, $documented)),
                "`{$table}`: columns in the database but missing from SCHEMA.md."
            );
            $this->assertSame(
                [],
                array_values(array_diff($documented, $actual)),
                "`{$table}`: columns in SCHEMA.md that the database does not have."
            );
        }
    }

    public function test_every_application_table_is_documented(): void
    {
        // Scoped to this connection's database: on MySQL an unscoped call lists every database on the server.
        $tables = Schema::getTables(DB::connection()->getDatabaseName());
        $actual = array_diff(array_column($tables, 'name'), self::FRAMEWORK_TABLES);

        $this->assertSame(
            [],
            array_values(array_diff($actual, array_keys($this->documentedTables()))),
            'Tables in the database with no section in SCHEMA.md.'
        );
    }

    /** @return array<string, list<string>> table => documented column names */
    private function documentedTables(): array
    {
        $tables = [];
        $current = null;
        $inCode = false;

        foreach (file(base_path('context/SCHEMA.md'), FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, '```')) {
                $inCode = ! $inCode;
            }
            if ($inCode) {
                continue;
            }
            if (preg_match('/^### (\w+)\s*$/', $line, $m)) {
                $current = $m[1];

                continue;
            }
            if ($current && preg_match('/^\| `?(\w+)`? \|/', $line, $m) && $m[1] !== 'Column') {
                $tables[$current][] = $m[1];
            }
        }

        return $tables;
    }
}
