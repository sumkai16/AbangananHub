<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\StorePropertyDocumentRequest;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PropertyDocumentController extends Controller
{
    private function authorizeProperty(Property $property): void
    {
        if ($property->landlord_id !== Auth::user()->user_id) {
            abort(403);
        }
    }

    public function index(Property $property)
    {
        $this->authorizeProperty($property);

        $documents = $property->documents()->latest()->get();

        return view('landlord.documents.index', compact('property', 'documents'));
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

        return back()->with('success', 'Document uploaded and submitted for review.');
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

        return back()->with('success', 'Document resubmitted for review.');
    }

    public function destroy(Property $property, PropertyDocument $document)
    {
        $this->authorizeProperty($property);

        if ($document->property_id !== $property->property_id) {
            abort(404);
        }

        if ($document->status === 'Verified') {
            return back()->withErrors(['document' => 'A verified document cannot be removed — contact an admin if it needs to be reversed.']);
        }

        if ($document->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('success', 'Document removed.');
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
