<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPropertyDocumentRequest;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropertyDocumentController extends Controller
{
    /**
     * Cross-property queue — lives under Verification & Approvals so review
     * doesn't require drilling into Catalogue → Properties → a specific
     * property first.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'Pending');

        $query = PropertyDocument::with([
            'property:property_id,title,landlord_id',
            'property.landlord:user_id,first_name,last_name,email',
        ]);

        match ($status) {
            'Pending'   => $query->where('status', 'Pending')->whereNotNull('file_path'),
            'Requested' => $query->whereNull('file_path'),
            'Verified'  => $query->where('status', 'Verified'),
            'Rejected'  => $query->where('status', 'Rejected'),
            default     => null, // 'All'
        };

        $documents = $query->latest('updated_at')->paginate(15)->withQueryString();

        $counts = [
            'Pending'   => PropertyDocument::where('status', 'Pending')->whereNotNull('file_path')->count(),
            'Requested' => PropertyDocument::whereNull('file_path')->count(),
            'Verified'  => PropertyDocument::where('status', 'Verified')->count(),
            'Rejected'  => PropertyDocument::where('status', 'Rejected')->count(),
        ];
        $counts['All'] = array_sum($counts);

        return view('admin.documents.index', compact('documents', 'status', 'counts'));
    }

    public function show(PropertyDocument $document)
    {
        $document->load(['property.landlord', 'verifier', 'requester']);

        return view('admin.documents.show', compact('document'));
    }

    public function verify(Property $property, PropertyDocument $document)
    {
        if ($document->property_id !== $property->property_id) {
            abort(404);
        }

        DB::transaction(function () use ($document) {
            $locked = PropertyDocument::whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            abort_if($locked->file_path === null, 409, 'This document has not been uploaded yet.');
            abort_if($locked->status === 'Verified', 409, 'This document has already been verified.');

            $locked->update([
                'status'           => 'Verified',
                'rejection_reason' => null,
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
            ]);

            AuditLog::record(
                'property_document.verify',
                "Verified {$locked->document_type} for property #{$locked->property_id}.",
                $locked,
                null,
                ['property_id' => $locked->property_id, 'document_type' => $locked->document_type],
            );
        });

        Notification::notify(
            $property->landlord_id,
            'property_document',
            'Document verified',
            "Your {$document->document_type} for \"{$property->title}\" has been verified.",
            route('landlord.properties.documents.index', $property),
        );

        return back()->with('success', 'Document verified.');
    }

    public function reject(RejectPropertyDocumentRequest $request, Property $property, PropertyDocument $document)
    {
        if ($document->property_id !== $property->property_id) {
            abort(404);
        }

        DB::transaction(function () use ($request, $document) {
            $locked = PropertyDocument::whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            abort_if($locked->file_path === null, 409, 'This document has not been uploaded yet.');

            $locked->update([
                'status'           => 'Rejected',
                'rejection_reason' => $request->validated('rejection_reason'),
                'verified_by'      => auth()->id(),
                'verified_at'      => now(),
            ]);

            AuditLog::record(
                'property_document.reject',
                "Rejected {$locked->document_type} for property #{$locked->property_id}.",
                $locked,
                $request->validated('rejection_reason'),
                ['property_id' => $locked->property_id, 'document_type' => $locked->document_type],
            );
        });

        $reason = $request->validated('rejection_reason');

        Notification::notify(
            $property->landlord_id,
            'property_document',
            'Document rejected',
            "Your {$document->document_type} for \"{$property->title}\" was rejected. Reason: {$reason}",
            route('landlord.properties.documents.index', $property),
        );

        return back()->with('success', 'Document rejected.');
    }

    public function request(Request $request, Property $property)
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(PropertyDocument::TYPES)],
        ]);

        $document = $property->documents()->create([
            'document_type' => $validated['document_type'],
            'status'        => 'Pending',
            'requested_by'  => auth()->id(),
        ]);

        AuditLog::record(
            'property_document.request',
            "Requested {$document->document_type} for property #{$property->property_id}.",
            $document,
            null,
            ['property_id' => $property->property_id, 'document_type' => $document->document_type],
        );

        Notification::notify(
            $property->landlord_id,
            'property_document',
            'Document requested',
            "An admin requested a {$document->document_type} for \"{$property->title}\".",
            route('landlord.properties.documents.index', $property),
        );

        return back()->with('success', 'Document requested from landlord.');
    }
}
