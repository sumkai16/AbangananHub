<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Edits the whitelisted `rentals` config overrides. These values decide when
 * escrowed money moves, so every change is written to the audit log with its
 * before/after inside the same transaction as the write itself.
 */
class SettingController extends Controller
{
    public function edit()
    {
        // The view renders straight from Setting::DEFINITIONS; `overrides` is only
        // needed so a customized key can be badged as diverging from the default.
        return view('admin.settings.edit', ['overrides' => Setting::overrides()]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $submitted = $request->validated()['settings'] ?? [];

        // Snapshot everything before the first write, so the diff isn't read back
        // through values this same request has already changed.
        $before = [];
        foreach (array_keys(Setting::DEFINITIONS) as $key) {
            $before[$key] = Setting::effective($key);
        }

        $changes = [];

        DB::transaction(function () use ($submitted, $before, &$changes) {
            foreach (Setting::DEFINITIONS as $key => $definition) {
                if (! array_key_exists($key, $submitted)) {
                    continue;
                }

                Setting::put($key, $submitted[$key]);

                // A cleared field falls back to the file default, which may well
                // equal what was there — so compare rendered values, not inputs.
                $after = Setting::effective($key);

                if (Setting::display($key, $before[$key]) !== Setting::display($key, $after)) {
                    $changes[$definition['label']] = Setting::display($key, $before[$key])
                        . ' → ' . Setting::display($key, $after);
                }
            }

            if ($changes) {
                AuditLog::record(
                    action: 'settings.update',
                    summary: 'Updated ' . count($changes) . ' system ' . str('setting')->plural(count($changes))
                        . ': ' . implode(', ', array_keys($changes)),
                    metadata: $changes,
                );
            }
        });

        return redirect()->route('admin.settings.edit')->with(
            'success',
            $changes ? 'System settings updated.' : 'No changes to save.'
        );
    }
}
