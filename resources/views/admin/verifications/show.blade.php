@extends('layouts.admin')

@section('page-title', 'Verification Review')

@section('content')
@php
    // Face on the ID against the live selfie (App\Services\FaceMatch). A signal, never a decision: bad light or an old
    // ID photo can fail a real person, and a match does not prove the ID is genuine.
    $fm = $verification->face_match_status;
    $score = rtrim(rtrim(number_format((float) $verification->face_match_score, 2), '0'), '.');
    $fmTone = match ($fm) {
        'match' => 'bg-[#22C55E]/[0.07]',
        'review', 'no_face' => 'bg-[#FBBF24]/[0.10]',
        'mismatch' => 'bg-[#EF4444]/[0.07]',
        default => 'bg-[#F7F8FC]',
    };
    $fmText = match ($fm) {
        'match' => 'Face match: <strong>'.$score.'%</strong> similar to the selfie',
        'review' => 'Faces look <strong>somewhat similar</strong> ('.$score.'%). Compare the photos by eye',
        'mismatch' => 'Faces <strong>do not match</strong> ('.$score.'%). Compare the photos by eye',
        'no_face' => 'No clear face found in the ID or the selfie. Compare the photos by eye',
        default => 'Face match not checked. Compare the photos by eye',
    };

    $ocr = $verification->ocr_status;
    $ocrChip = match ($ocr) {
        'pass' => ['Name read from ID', 'green'],
        'partial' => ['Name partly read', 'amber'],
        'unreadable' => ['ID text unreadable', 'amber'],
        null => ['ID text not checked', 'neutral'],
        default => ['Name not found on ID', 'red'],
    };
@endphp
    <div class="admin-compact max-w-[1600px] mx-auto" x-data="{ previewImage: null }">

        <x-back-link href="{{ route('admin.verifications.index') }}" to="verifications" class="mb-2" />

        <x-admin.header :title="$verification->user->first_name.' '.$verification->user->last_name"
            :subtitle="$verification->user->email.' · submitted '.$verification->submitted_at->format('M j, Y')">
            <x-slot:badge><x-admin.status :status="$verification->verification_status" /></x-slot:badge>
        </x-admin.header>

        <div class="grid grid-cols-1 items-start gap-3 lg:grid-cols-[minmax(0,1fr)_300px]">

            <div class="min-w-0 space-y-3">

                {{-- The point of this page: is the person on the ID the person in the selfie? Whole images, side by side, same height. --}}
                <x-card>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">ID and selfie</h2>
                        <div class="flex flex-wrap items-center gap-1">
                            <x-admin.status :status="$verification->liveness_passed ? 'Liveness passed' : 'No liveness check'" :tone="$verification->liveness_passed ? 'green' : 'amber'" />
                            <x-admin.status :status="$ocrChip[0]" :tone="$ocrChip[1]" />
                        </div>
                    </div>

                    @if ($fm)
                        <p class="mb-2 rounded-lg px-3 py-2 text-[13px] text-[#060D26] {{ $fmTone }}">{!! $fmText !!}</p>
                    @endif

                    @unless ($verification->liveness_passed)
                        <p class="mb-2 rounded-lg bg-[#FBBF24]/[0.10] px-3 py-2 text-[13px] leading-5 text-[#060D26]">This selfie skipped the automatic liveness check because the applicant's device couldn't run it. Compare the face with the ID photo yourself.</p>
                    @endunless

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 {{ $verification->id_back ? 'xl:grid-cols-3' : '' }}">
                        @foreach(array_filter([
                            ['front', 'ID front'],
                            ['selfie', 'Selfie'],
                            $verification->id_back ? ['back', 'ID back'] : null,
                        ]) as [$side, $label])
                            <figure class="min-w-0">
                                <button type="button" aria-label="Enlarge {{ $label }}"
                                    @click="previewImage = '{{ route('verifications.preview', [$verification, $side]) }}'"
                                    class="block w-full cursor-zoom-in overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/50">
                                    <img src="{{ route('verifications.preview', [$verification, $side]) }}" alt="{{ $label }}" class="h-[17rem] w-full object-contain">
                                </button>
                                <figcaption class="mt-1 text-center text-[12px] text-[#5B6A8E]">{{ $label }}</figcaption>
                            </figure>
                        @endforeach
                    </div>

                    <div class="mt-3 border-t border-[#E2E4EC] pt-3">
                        <x-admin.facts compact :cols="4" :items="[
                            'Name on ID' => $verification->ocr_name ? $verification->ocr_name.($verification->ocr_confidence ? ' ('.$verification->ocr_confidence.'%)' : '') : null,
                            'Name on account' => trim($verification->user->first_name.' '.$verification->user->last_name),
                            'ID type' => $verification->id_type,
                            'ID number' => $verification->id_number,
                        ]" />
                        @if ($ocr === 'unreadable')
                            <p class="mt-2 text-[12.5px] text-[#5B6A8E]">Text could not be read automatically. Check the ID by eye.</p>
                        @elseif (! $ocr)
                            <p class="mt-2 text-[12.5px] text-[#5B6A8E]">OCR data not available, so review the ID photos yourself.</p>
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <h2 class="mb-2 text-[14px] font-semibold text-[#060D26]">Business</h2>
                    <x-admin.facts compact :cols="3" :items="[
                        'Business name' => $verification->business_name,
                        'Contact' => ['value' => $verification->contact_number, 'num' => true],
                        'Address' => $verification->business_address,
                    ]" />
                    @if ($verification->description)
                        <p class="mt-2.5 max-w-[75ch] text-[13px] leading-5 text-[#5B6A8E]">{{ $verification->description }}</p>
                    @endif
                </x-card>
            </div>

            {{-- Rail --}}
            <div class="space-y-3 lg:sticky lg:top-6">
                @if ($verification->verification_status === 'Pending')
                    <x-admin.decision
                        :since="$verification->submitted_at->format('M j, Y')"
                        :approve-action="route('admin.verifications.approve', $verification)"
                        approve-confirm="Approve this landlord application?"
                        approve-message="This will grant the applicant the Landlord role."
                        :reject-action="route('admin.verifications.reject', $verification)"
                        reject-confirm="Reject this application?"
                        reject-message="The applicant will see your reason. This cannot be undone."
                        field="admin_notes"
                        placeholder="The applicant will see this">
                        <p class="text-[12.5px] font-semibold text-[#060D26]">Before you approve</p>
                        <ol class="mt-1.5 space-y-1 text-[12.5px] leading-[18px] text-[#060D26]">
                            <li class="flex gap-2"><span class="font-semibold tabular-nums text-[#5B6A8E]">1</span><span>The face on the ID is the face in the selfie.</span></li>
                            <li class="flex gap-2"><span class="font-semibold tabular-nums text-[#5B6A8E]">2</span><span>The name on the ID matches the account.</span></li>
                            <li class="flex gap-2"><span class="font-semibold tabular-nums text-[#5B6A8E]">3</span><span>It is a real card, not a screen or a photocopy.</span></li>
                        </ol>
                    </x-admin.decision>
                @elseif ($verification->verification_status === 'Rejected')
                    <x-card>
                        <h2 class="text-[14px] font-semibold text-[#DC2626]">Rejected</h2>
                        @if ($verification->reviewed_at)
                            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">On {{ $verification->reviewed_at->format('M j, Y, g:i A') }}@if ($verification->reviewer) by {{ $verification->reviewer->first_name }} {{ $verification->reviewer->last_name }}@endif</p>
                        @endif
                        @if ($verification->admin_notes)
                            <p class="mt-1.5 text-[13px] leading-5 text-[#060D26]">{{ $verification->admin_notes }}</p>
                        @endif
                    </x-card>
                @elseif ($verification->verification_status === 'Approved')
                    <x-card>
                        <h2 class="text-[14px] font-semibold text-[#15803D]">Approved</h2>
                        @if ($verification->reviewed_at)
                            <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">On {{ $verification->reviewed_at->format('M j, Y, g:i A') }}@if ($verification->reviewer) by {{ $verification->reviewer->first_name }} {{ $verification->reviewer->last_name }}@endif</p>
                        @endif
                    </x-card>
                @endif

                <x-card>
                    <p class="text-[12px] text-[#5B6A8E]">Applicant</p>
                    <a href="{{ route('admin.users.show', $verification->user) }}" class="block text-[13px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $verification->user->first_name }} {{ $verification->user->last_name }}</a>
                    <p class="break-words text-[12px] text-[#5B6A8E]">{{ $verification->user->email }}</p>
                </x-card>
            </div>
        </div>

        {{-- Lightbox --}}
        <div x-show="previewImage" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="previewImage = null" @keydown.escape.window="previewImage = null"
            class="fixed inset-0 z-[999] flex items-center justify-center bg-black/70 p-4 cursor-pointer"
            style="display: none;">
            <button type="button" @click="previewImage = null" aria-label="Close preview"
                class="absolute top-4 right-4 flex h-9 w-10 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20">
                <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="previewImage" alt="Document preview" @click.stop class="max-h-[85vh] max-w-full cursor-default rounded-lg">
        </div>

    </div>
@endsection
