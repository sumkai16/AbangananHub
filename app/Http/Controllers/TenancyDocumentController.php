<?php

namespace App\Http\Controllers;

use App\Models\TenancyDocument;
use App\Services\TenancyDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The documents a lease asks the tenant to upload before signing. Symmetric like the viewings routes: the tenant
 * uploads, the landlord reviews, and either may open the file. Rules live in App\Services\TenancyDocuments.
 */
class TenancyDocumentController extends Controller
{
    public function upload(Request $request, TenancyDocument $document)
    {
        Gate::authorize('upload', $document);

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.TenancyDocument::MIMES, 'max:'.TenancyDocument::MAX_KB],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.mimes' => 'Upload a PDF, JPG, PNG or WebP file.',
            'file.max' => 'The file must be 10 MB or smaller.',
        ]);

        TenancyDocuments::upload($document, $request->user(), $request->file('file'));

        return back()->with('success', $document->label.' uploaded. Your landlord will review it.');
    }

    /** Opens the private file inline. The landlord's views are written to the audit log. */
    public function file(Request $request, TenancyDocument $document)
    {
        Gate::authorize('view', $document);

        abort_unless($document->hasFile() && Storage::disk(TenancyDocuments::DISK)->exists($document->file_path), 404, 'This file is no longer stored.');

        if (Gate::allows('review', $document)) {
            TenancyDocuments::recordView($document, $request->user());
        }

        return Storage::disk(TenancyDocuments::DISK)->response($document->file_path, $document->file_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($document->file_name ?? 'document').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function approve(Request $request, TenancyDocument $document)
    {
        Gate::authorize('review', $document);
        TenancyDocuments::approve($document, $request->user());

        return back()->with('success', $document->label.' approved.');
    }

    public function reject(Request $request, TenancyDocument $document)
    {
        Gate::authorize('review', $document);

        $data = $request->validate(
            ['rejection_reason' => ['required', 'string', 'min:3', 'max:255']],
            ['rejection_reason.required' => 'Tell your tenant what to fix, for example "The photo is blurry".'],
        );

        TenancyDocuments::reject($document, $request->user(), $data['rejection_reason']);

        return back()->with('success', $document->label.' sent back. Your tenant was told why.');
    }

    public function waive(Request $request, TenancyDocument $document)
    {
        Gate::authorize('review', $document);
        TenancyDocuments::waive($document, $request->user());

        return back()->with('success', $document->label.' is no longer required for this tenant.');
    }

    public function reopen(Request $request, TenancyDocument $document)
    {
        Gate::authorize('review', $document);
        TenancyDocuments::reopen($document, $request->user());

        return back()->with('success', $document->label.' reopened.');
    }
}
