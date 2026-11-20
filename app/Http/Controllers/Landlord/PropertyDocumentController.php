<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Concerns\AuthorizesPropertyOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePropertyDocumentRequest;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PropertyDocumentController extends Controller
{
    use AuthorizesPropertyOwnership;

    public function index(Property $property)
    {
        $this->authorizeProperty($property);

        // Documents live in a tab on the property page; old links (admin notifications) land there.
        return redirect($this->documentsUrl($property));
    }

    /**
     * Where an upload, a replacement or a removal brings the landlord back to: the wizard's Documents step when the
     * form was in the wizard (`from=wizard`) and the listing is still a Draft, so they carry on with the next required
     * document; otherwise the property page's Documents tab. A non-draft never goes to a wizard URL.
     */
    private function documentsUrl(Property $property, ?Request $request = null): string
    {
        if ($request?->input('from') === 'wizard' && $property->isDraft()) {
            return route('properties.wizard.documents', $property);
        }

        return route('landlord.properties.show', $property) . '#documents';
    }

    public function store(StorePropertyDocumentRequest $request, Property $property)
    {
        $this->authorizeProperty($property);

        $file = $request->file('file');
        $path = $file->store("property_documents/{$property->property_id}", 'local');

        $property->documents()->create([
            'document_type'    => $request->validated('document_type'),
            'file_path'        => $path,
            'file_name'        => $file->getClientOriginalName(),
            'document_number'  => $request->validated('document_number'),
            'expiry_date'      => $request->validated('expiry_date'),
            'status'           => 'Pending',
        ]);

        return redirect($this->documentsUrl($property, $request))->with('success', 'Document uploaded and submitted for review.');
    }

    public function replace(StorePropertyDocumentRequest $request, Property $property, PropertyDocument $document)
    {
        $this->authorizeProperty($property);

        if ($document->property_id !== $property->property_id) {
            abort(404);
        }

        $file = $request->file('file');
        $path = $file->store("property_documents/{$property->property_id}", 'local');

        $oldPath = $document->file_path;

        $document->update([
            'document_type'    => $request->validated('document_type'),
            'file_path'        => $path,
            'file_name'        => $file->getClientOriginalName(),
            'document_number'  => $request->validated('document_number'),
            'expiry_date'      => $request->validated('expiry_date'),
            'status'           => 'Pending',
            'rejection_reason' => null,
            'verified_by'      => null,
            'verified_at'      => null,
        ]);

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect($this->documentsUrl($property, $request))->with('success', 'Document resubmitted for review.');
    }

    public function destroy(Request $request, Property $property, PropertyDocument $document)
    {
        $this->authorizeProperty($property);

        if ($document->property_id !== $property->property_id) {
            abort(404);
        }

        if ($document->status === 'Verified') {
            return redirect($this->documentsUrl($property, $request))->withErrors(['document' => 'A verified document cannot be removed — contact an admin if it needs to be reversed.']);
        }

        if ($document->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect($this->documentsUrl($property, $request))->with('success', 'Document removed.');
    }

    public function preview(Property $property, PropertyDocument $document)
    {
        Gate::authorize('view', $document);

        if ($document->property_id !== $property->property_id || ! $document->file_path) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($document->file_path),
            ['Content-Type' => Storage::disk('local')->mimeType($document->file_path)]
        );
    }

    public function download(Property $property, PropertyDocument $document)
    {
        Gate::authorize('view', $document);

        if ($document->property_id !== $property->property_id || ! $document->file_path) {
            abort(404);
        }

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
}
