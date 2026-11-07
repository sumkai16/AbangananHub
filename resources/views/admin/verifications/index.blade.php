@extends('layouts.admin')

@section('page-title', 'Landlord Verifications')

@section('content')
@php
    $empty = [
        'Pending' => ['check', 'No applications waiting', 'New landlord applications show here once someone applies.'],
        'Approved' => ['inbox', 'No approved landlords yet', 'Applications you approve show here.'],
        'Rejected' => ['inbox', 'No rejected applications', 'Applications you reject show here, with your reason.'],
        'All' => ['inbox', 'No applications yet', 'Landlord applications show here.'],
    ][$status] ?? ['inbox', 'No applications here', null];

    // Face on the ID against the live selfie. A signal, never a decision: bad light or an old ID photo can fail a real
    // person, and a match does not prove the ID is genuine. Returns [tone, short label, sentence].
    $faceOf = function ($v) {
        $score = rtrim(rtrim(number_format((float) $v->face_match_score, 2), '0'), '.');

        return match ($v->face_match_status) {
            'match' => ['ok', 'Match', 'Similar to the selfie ('.$score.'%).'],
            'review' => ['warn', 'Compare', 'Somewhat similar ('.$score.'%). Compare the photos by eye.'],
            'mismatch' => ['bad', 'Mismatch', 'Does not match ('.$score.'%). Compare the photos by eye.'],
            'no_face' => ['warn', 'No face', 'No clear face found in the ID or the selfie. Compare by eye.'],
            default => ['off', 'Not checked', 'Not checked. Compare the photos by eye.'],
        };
    };
    $textOf = fn ($v) => match ($v->ocr_status) {
        'pass' => ['ok', 'Read', 'Name read from the ID.'],
        'partial' => ['warn', 'Partly read', 'Name partly read.'],
        'unreadable' => ['warn', 'Unclear', 'ID text unreadable.'],
        null => ['off', 'Not checked', 'ID text not checked.'],
        default => ['bad', 'Name differs', 'Name not found on the ID.'],
    };
    $tones = ['ok' => 'bg-[#E6F4EA] text-[#166534]', 'warn' => 'bg-[#FEF3D6] text-[#92400E]', 'bad' => 'bg-[#FDECEC] text-[#B91C1C]', 'off' => 'bg-[#ECEEF6] text-[#4A5A7E]'];
    $icons = ['ok' => 'M5 13l4 4L19 7', 'warn' => 'M12 8v5m0 4h.01', 'bad' => 'M6 6l12 12M18 6L6 18', 'off' => 'M6 12h12'];
    $chipTone = ['ok' => 'green', 'warn' => 'amber', 'bad' => 'red', 'off' => 'neutral'];
@endphp
<div class="admin-compact admin-fit" x-data="{ previewImage: null }" @keydown.escape.window="previewImage = null">

    <x-admin.header title="Landlord Verifications" subtitle="Check each applicant's ID and selfie before they can list properties.">
        <x-slot:actions>
            <x-admin.stat-line :cells="[
                ['label' => 'Waiting', 'value' => number_format($counts['Pending']), 'note' => $summary['oldest'] ? 'Oldest '.$summary['oldest']->diffForHumans() : 'Nothing in the queue'],
                ['label' => 'Need a closer look', 'value' => number_format($summary['flagged']), 'note' => 'Face match did not clear'],
                ['label' => 'Approved', 'value' => number_format($summary['approved']), 'note' => 'Last 30 days'],
                ['label' => 'Rejected', 'value' => number_format($summary['rejected']), 'note' => 'Last 30 days'],
            ]" />
        </x-slot:actions>
    </x-admin.header>

    <x-admin.tabs class="mb-3" :tabs="collect(['All', 'Pending', 'Approved', 'Rejected'])->map(fn ($tab) => [
        'label' => $tab,
        'count' => $counts[$tab],
        'href' => route('admin.verifications.index', ['status' => $tab]),
        'active' => $status === $tab,
    ])->all()" />

    @if (! $selected)
        <x-card flush fill>
            <x-empty-state flat fill :symbol="$empty[0]" :title="$empty[1]" :note="$empty[2]">
                @if ($status !== 'All')
                    <a href="{{ route('admin.verifications.index', ['status' => 'All']) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">See all applications</a>
                @endif
            </x-empty-state>
        </x-card>
    @else
        @php
            $v = $selected;
            $name = trim($v->user->first_name.' '.$v->user->last_name);
            [$faceTone, , $faceText] = $faceOf($v);
            [$textTone, , $textLine] = $textOf($v);
            $liveTone = $v->liveness_passed ? 'ok' : 'warn';
            $flagged = in_array($v->face_match_status, ['mismatch', 'review', 'no_face'], true);
            $sides = collect([
                'front' => $v->id_type ? $v->id_type.' (front)' : 'ID (front)',
                'selfie' => 'Selfie',
            ])->filter(fn ($label, $side) => $side === 'front' ? $v->government_id : $v->selfie);
        @endphp

        <div class="flex min-h-0 flex-1 flex-col gap-3 lg:flex-row">

            {{-- Queue --}}
            <x-card flush fill class="lg:w-[20rem] lg:flex-none">
                <div class="flex items-center justify-between border-b border-[#E2E4EC] px-4 py-2.5">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">{{ number_format($verifications->total()) }} {{ $status === 'Pending' ? 'waiting' : 'applications' }}</h2>
                    <span class="text-[12px] text-[#5B6A8E]">{{ match ($status) { 'Approved', 'Rejected' => 'Newest first', 'All' => 'Waiting first', default => 'Oldest first' } }}</span>
                </div>
                <ul class="min-h-0 flex-1 overflow-y-auto">
                    @foreach ($verifications as $row)
                        @php
                            $isOpen = $row->verification_id === $v->verification_id;
                            $rowName = trim($row->user->first_name.' '.$row->user->last_name);
                            $rowFace = $faceOf($row);
                            $rowFlag = in_array($row->face_match_status, ['mismatch', 'review', 'no_face'], true);
                        @endphp
                        <li class="border-b border-[#E2E4EC] last:border-b-0">
                            <a href="{{ route('admin.verifications.index', array_filter(['status' => $status, 'open' => $row->verification_id, 'page' => $verifications->currentPage() > 1 ? $verifications->currentPage() : null])) }}"
                                @if ($isOpen) aria-current="true" @endif
                                class="flex gap-2.5 px-3.5 py-2 transition-colors duration-200 {{ $isOpen ? 'bg-[#FFF3EE] ring-2 ring-inset ring-[#FF8A66]' : 'hover:bg-[#F7F8FC]' }}">
                                <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-[#ECEEF6] text-[11px] font-bold text-[#060D26]" aria-hidden="true">{{ strtoupper(substr($row->user->first_name, 0, 1).substr($row->user->last_name, 0, 1)) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] font-semibold text-[#060D26]">{{ $rowName }}</span>
                                    <span class="block truncate text-[12px] leading-4 text-[#5B6A8E]">{{ $row->business_name ?: '—' }}</span>
                                    <span class="block truncate text-[12px] leading-4 text-[#5B6A8E]">Sent {{ $row->submitted_at?->diffForHumans() }}</span>
                                </span>
                                @if ($row->verification_status === 'Pending')
                                    <x-admin.status :status="$rowFace[1]" :tone="$chipTone[$rowFace[0]]" class="self-start" />
                                @else
                                    <x-admin.status :status="$row->verification_status" class="self-start" />
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-auto border-t border-[#E2E4EC] bg-[#F7F8FC] px-4 py-2.5 text-[13px] text-[#5B6A8E]">
                    @if ($verifications->hasPages())
                        {{ $verifications->links() }}
                    @else
                        Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $verifications->firstItem() }}–{{ $verifications->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($verifications->total()) }}</span>
                    @endif
                </div>
            </x-card>

            {{-- The selected application --}}
            <x-card flush fill class="min-w-0 flex-1">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#E2E4EC] px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2.5">
                            <h2 class="text-[17px] font-semibold leading-6 tracking-tight text-[#060D26]">{{ $name }}</h2>
                            <x-admin.status :status="$v->verification_status" />
                            @if ($flagged && $v->verification_status === 'Pending')<x-admin.status status="Needs a closer look" tone="red" />@endif
                        </div>
                        <p class="text-[13px] leading-5 text-[#5B6A8E]">{{ $v->user->email }}@if ($v->contact_number) · {{ $v->contact_number }}@endif · Sent {{ $v->submitted_at?->format('M j, Y') }}, {{ $v->submitted_at?->diffForHumans() }}</p>
                    </div>
                    <a href="{{ route('admin.verifications.show', $v) }}" class="inline-flex h-8 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">Open full page</a>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
                    @unless ($v->liveness_passed)
                        <p class="rounded-lg bg-[#FBBF24]/[0.10] px-3 py-2 text-[13px] leading-5 text-[#060D26]">This selfie skipped the automatic liveness check because the applicant's device could not run it. Compare the face with the ID photo yourself.</p>
                    @endunless

                    <div class="grid gap-3 sm:grid-cols-2">
                        @forelse ($sides as $side => $label)
                            <figure>
                                <figcaption class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">{{ $label }}</figcaption>
                                <button type="button" @click="previewImage = '{{ route('verifications.preview', [$v, $side]) }}'" aria-label="Enlarge: {{ $label }}"
                                    class="mt-1.5 block w-full overflow-hidden rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66] cursor-zoom-in">
                                    <img src="{{ route('verifications.preview', [$v, $side]) }}" alt="{{ $label }}" loading="lazy" class="h-[12.5rem] w-full object-contain">
                                </button>
                            </figure>
                        @empty
                            <p class="text-[13px] text-[#5B6A8E] sm:col-span-2">No ID or selfie was uploaded.</p>
                        @endforelse
                    </div>
                    @if ($v->id_back)
                        <p class="-mt-3 text-[12.5px]"><button type="button" @click="previewImage = '{{ route('verifications.preview', [$v, 'back']) }}'" class="font-semibold text-[#B35A3D] underline-offset-2 hover:underline cursor-pointer">Show the back of the ID</button></p>
                    @endif

                    <div>
                        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Checks the system ran</h3>
                        <ul class="mt-1 divide-y divide-[#E2E4EC]">
                            @foreach ([['Face match', $faceTone, $faceText], ['ID text', $textTone, $textLine.($v->ocr_name ? ' "'.$v->ocr_name.'"'.($v->ocr_confidence ? ' ('.$v->ocr_confidence.'%)' : '') : '')], ['Liveness', $liveTone, $v->liveness_passed ? 'Passed.' : 'Not done.']] as [$what, $tone, $line])
                                <li class="flex items-center gap-3 py-2 text-[13px]">
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full {{ $tones[$tone] }}" aria-hidden="true"><svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$tone] }}" /></svg></span>
                                    <span class="w-24 shrink-0 font-semibold text-[#060D26]">{{ $what }}</span>
                                    <span class="text-[#5B6A8E]">{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <dl class="flex flex-wrap gap-y-3">
                        <div class="border-l border-[#E2E4EC] px-4 first:border-l-0 first:pl-0"><dt class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Business</dt><dd class="text-[13px] font-semibold text-[#060D26]">{{ $v->business_name ?: '—' }}</dd></div>
                        <div class="border-l border-[#E2E4EC] px-4"><dt class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Address</dt><dd class="max-w-[22rem] truncate text-[13px] text-[#060D26]" title="{{ $v->business_address }}">{{ $v->business_address ?: '—' }}</dd></div>
                        <div class="border-l border-[#E2E4EC] px-4"><dt class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">ID type</dt><dd class="text-[13px] text-[#060D26]">{{ $v->id_type ?: 'Not given' }}</dd></div>
                    </dl>

                    @if ($v->verification_status === 'Pending')
                        <div>
                            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Before you approve</h3>
                            <p class="mt-0.5 text-[12px] text-[#5B6A8E]">A memory aid only; nothing is saved or blocked.</p>
                            <div class="mt-2 grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                                @foreach (['The face on the ID is the face in the selfie.', 'The ID is a real card, not a screen or a photocopy.', "The name on the ID matches the applicant's account.", 'No liveness check or a weak face match means you compare by eye.'] as $line)
                                    <label class="flex items-start gap-2.5 text-[13px] leading-5 text-[#060D26]"><input type="checkbox" class="mt-[3px] h-4 w-4 accent-[#15803D]">{{ $line }}</label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="rounded-lg bg-[#F7F8FC] px-3 py-2 text-[13px] leading-5 text-[#060D26]">
                            <span class="font-semibold">{{ $v->verification_status }}</span>
                            @if ($v->reviewed_at) {{ $v->reviewed_at->format('M j, Y') }}@endif
                            @if ($v->reviewer) by {{ $v->reviewer->first_name }}@endif.
                            @if ($v->admin_notes) Reason: {{ $v->admin_notes }}@endif
                        </p>
                    @endif
                </div>

                @if ($v->verification_status === 'Pending')
                    <x-admin.decision-bar
                        :approve-action="route('admin.verifications.approve', $v)"
                        approve-label="Approve landlord"
                        approve-confirm="Approve this landlord application?"
                        approve-message="This will grant the applicant the Landlord role."
                        :reject-action="route('admin.verifications.reject', $v)"
                        reject-confirm="Reject this application?"
                        reject-message="The applicant will see your reason. This cannot be undone."
                        field="admin_notes"
                        note="A rejection needs a reason; the applicant will see it."
                        placeholder="The applicant will see this" />
                @else
                    <div class="mt-auto border-t border-[#E2E4EC] bg-[#F7F8FC] px-5 py-3 text-[13px] text-[#5B6A8E]">Decided. Open the full page to see the whole application.</div>
                @endif
            </x-card>
        </div>

        {{-- Enlarged ID / selfie --}}
        <div x-show="previewImage" x-cloak x-transition.opacity.duration.200ms @click="previewImage = null" role="dialog" aria-modal="true" aria-label="Image preview"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-6">
            <button type="button" @click="previewImage = null" aria-label="Close preview" class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 cursor-pointer">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <img :src="previewImage" alt="Enlarged image" @click.stop class="max-h-[85vh] max-w-full cursor-default rounded-lg">
        </div>
    @endif

</div>
@endsection
