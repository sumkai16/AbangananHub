<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Baseline harness for the performance work: prints query count, DB time and
 * repeated-query shapes per route. Run before and after an optimisation to
 * prove it moved a number. Read-only GETs against the dev database.
 */
class PerformanceBaselineTest extends TestCase
{
    public function test_profile_routes(): void
    {
        $role = fn (string $r) => User::whereHas('roles', fn ($q) => $q->where('role', $r))->first();

        $targets = [
            ['/',                            null],
            ['/?location=Cebu',              null],
            ['/areas',                       null],
            ['/landlord/dashboard',          'Landlord'],
            ['/landlord/properties',         'Landlord'],
            ['/landlord/reservations',       'Landlord'],
            ['/landlord/tenants',            'Landlord'],
            ['/landlord/payments',           'Landlord'],
            ['/landlord/analytics',          'Landlord'],
            ['/admin/dashboard',             'Admin'],
            ['/admin/users',                 'Admin'],
            ['/admin/listings',              'Admin'],
            ['/admin/catalogue/properties',  'Admin'],
            ['/admin/reports',               'Admin'],
            ['/tenant/reservations',         'Tenant'],
            ['/favorites',                   'Tenant'],
            ['/conversations',               'Tenant'],
            ['/notifications',               'Tenant'],
            ['/profile',                     'Tenant'],
        ];

        if ($p = DB::table('properties')->where('publication_status', 'Published')->first()) {
            $targets[] = ['/properties/'.$p->property_id, null];
        }

        $rows = [];
        printf("%-40s %6s %8s %9s  %s\n", 'ROUTE', 'CODE', 'QUERIES', 'DB(ms)', 'SLOWEST QUERY');
        echo str_repeat('-', 140)."\n";

        foreach ($targets as [$uri, $r]) {
            $user = $r ? $role($r) : null;
            if ($r && ! $user) { echo "$uri — no $r user\n"; continue; }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $t = microtime(true);
            try {
                $res = $user ? $this->actingAs($user)->get($uri) : $this->get($uri);
                $code = $res->getStatusCode();
            } catch (\Throwable $e) {
                $code = 'EXC';
                $err = substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 90);
            }
            $ms = (microtime(true) - $t) * 1000;
            $log = DB::getQueryLog();
            DB::disableQueryLog();

            $dbms = array_sum(array_column($log, 'time'));
            $sorted = $log;
            usort($sorted, fn ($a, $b) => $b['time'] <=> $a['time']);
            $slow = $sorted ? substr(preg_replace('/\s+/', ' ', $sorted[0]['query']), 0, 62).' ['.$sorted[0]['time'].'ms]' : ($err ?? '-');

            printf("%-40s %6s %8d %9.1f  %s\n", $uri.($r ? " ($r)" : ''), $code, count($log), $dbms, $slow);
            $rows[$uri] = ['log' => $log, 'n' => count($log), 'wall' => $ms];
            $err = null;
        }

        echo "\n\n=== REPEATED QUERIES (N+1 signal: same shape run 3+ times) ===\n";
        foreach ($rows as $uri => $row) {
            $norm = [];
            foreach ($row['log'] as $q) {
                $k = preg_replace(['/\s+/', '/in \([^)]*\)/i', '/= \?/'], [' ', 'in (?)', '= ?'], $q['query']);
                $norm[$k] = ($norm[$k] ?? 0) + 1;
            }
            arsort($norm);
            $d = array_filter($norm, fn ($c) => $c >= 3);
            if (! $d) continue;
            echo "\n--- $uri  ({$row['n']} queries)\n";
            foreach (array_slice($d, 0, 6, true) as $q => $c) {
                echo "   x$c  ".substr($q, 0, 110)."\n";
            }
        }

        echo "\n=== SELECT * (over-fetch) count per route ===\n";
        foreach ($rows as $uri => $row) {
            $star = count(array_filter($row['log'], fn ($q) => str_contains($q['query'], 'select * from')));
            printf("%-40s %3d of %3d queries are SELECT *\n", $uri, $star, $row['n']);
        }

        $this->assertTrue(true);
    }
}
