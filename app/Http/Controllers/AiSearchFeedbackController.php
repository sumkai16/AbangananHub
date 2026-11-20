<?php

namespace App\Http\Controllers;

use App\Models\AiSearchLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Thumbs up / down on a search's answer. The page holds an encrypted token for ITS search only (built when the search
 * was logged), so nobody can rate someone else's search or guess an id; a rating is accepted for 24 hours and can be
 * changed. It only ever sets one column on the log row.
 */
class AiSearchFeedbackController extends Controller
{
    public static function tokenFor(AiSearchLog $log): string
    {
        return Crypt::encryptString((string) $log->getKey());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:500'], 'value' => ['required', 'in:1,-1']]);

        try {
            $id = (int) Crypt::decryptString($data['token']);
        } catch (DecryptException) {
            return response()->json(['ok' => false], 422);
        }

        $updated = AiSearchLog::whereKey($id)->where('created_at', '>=', now()->subDay())->update(['feedback' => (int) $data['value']]);

        return response()->json(['ok' => $updated > 0]);
    }
}
