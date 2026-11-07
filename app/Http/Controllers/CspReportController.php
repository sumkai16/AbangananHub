<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Where the browser reports what the Content-Security-Policy blocked (or would block, in report-only mode).
 * Public and unauthenticated by nature, so: tiny body, only a few fields kept and shortened, throttled by the route.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $raw = substr((string) $request->getContent(), 0, 4096);
        $data = json_decode($raw, true);
        $report = is_array($data) ? ($data['csp-report'] ?? $data[0]['body'] ?? null) : null;

        if (is_array($report)) {
            Log::warning('CSP violation', [
                'directive' => self::clip($report['violated-directive'] ?? $report['effectiveDirective'] ?? ''),
                'blocked' => self::clip($report['blocked-uri'] ?? $report['blockedURL'] ?? ''),
                'page' => self::clip($report['document-uri'] ?? $report['documentURL'] ?? ''),
                'source' => self::clip($report['source-file'] ?? $report['sourceFile'] ?? ''),
                'line' => (int) ($report['line-number'] ?? $report['lineNumber'] ?? 0),
            ]);
        }

        return response()->noContent();
    }

    private static function clip(mixed $value): string
    {
        return mb_substr(is_scalar($value) ? (string) $value : '', 0, 200);
    }
}
