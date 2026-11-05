<?php

namespace App\Observers;

use App\Models\PropertyActivity;
use App\Models\PropertyDocument;

/**
 * The listing's paperwork: requested by an admin, uploaded or replaced by the landlord, then verified or
 * rejected by an admin, or removed. Never the file itself or its number. Quiet while the property is a draft.
 */
class PropertyDocumentObserver
{
    public function created(PropertyDocument $document): void
    {
        $this->note($document, fn () => $document->isRequested()
            ? ['document.requested', $document->document_type.' requested']
            : ['document.uploaded', 'Uploaded '.$document->document_type]);
    }

    public function updated(PropertyDocument $document): void
    {
        $this->note($document, function () use ($document) {
            if ($document->wasChanged('file_path') && $document->file_path !== null) {
                return $document->getOriginal('file_path') === null
                    ? ['document.uploaded', 'Uploaded '.$document->document_type]
                    : ['document.replaced', 'Replaced '.$document->document_type];
            }

            if (! $document->wasChanged('status')) {
                return null;
            }

            return match ($document->status) {
                'Verified' => ['document.verified', $document->document_type.' verified'],
                'Rejected' => ['document.rejected', $document->document_type.' rejected'],
                default    => null,
            };
        });
    }

    public function deleted(PropertyDocument $document): void
    {
        $this->note($document, fn () => ['document.removed', 'Removed '.$document->document_type]);
    }

    /** @param  \Closure(): ?array{0: string, 1: string}  $describe */
    private function note(PropertyDocument $document, \Closure $describe): void
    {
        PropertyActivity::safely(function () use ($document, $describe) {
            if (PropertyActivity::isDraft($document->property_id) || ! ($line = $describe())) {
                return;
            }

            PropertyActivity::record($document->property_id, $line[0], $line[1], ['metadata' => ['document_type' => $document->document_type]]);
        });
    }
}
