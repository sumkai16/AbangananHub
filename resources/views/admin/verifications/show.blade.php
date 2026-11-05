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
    <div class="max-w-[1600px] mx-auto" x-data="{ previewImage: null }">

        <x-back-link href="{{ route('admin.verifications.index') }}" to="verifications" class="mb-4" />

        <x-page-header :title="$verification->user->first_name.' '.$verification->user->last_name"
            :subtitle="$verification->user->email.' · submitted '.$verification->submitted_at->format('M j, Y')">
            <x-slot:badge><x-admin.status :status="$verification->verification_status" /></x-slot:badge>
        </x-page-header>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px] items-start">

            <div class="min-w-0 space-y-5">

                {{-- The point of this page: is the person on the ID the person in the selfie? Whole images, side by side, same height. --}}
                <x-card>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-[15px] font-semibold text-[#060D26]">ID and selfie</h2>
                        <div class="flex flex-wrap items-center gap-1">
                            <x-admin.status :status="$verification->liveness_passed ? 'Liveness passed' : 'No liveness check'" :tone="$verification->liveness_passed ? 'green' : 'amber'" />
                            <x-admin.status :status="$ocrChip[0]" :tone="$ocrChip[1]" />
                        </div>
                    </div>

                    @if ($fm)
                        <p class="mb-3 rounded-lg px-3 py-2.5 text-[13.5px] text-[#060D26] {{ $fmTone }}">{!! $fmText !!}</p>
                    @endif

                    @unless ($verification->liveness_passed)
                        <p class="mb-3 rounded-lg bg-[#FBBF24]/[0.10] px-3 py-2.5 text-[13.5px] leading-relaxed text-[#060D26]">This selfie was captured without the automatic liveness check, because the applicant's device couldn't run it. Confirm the face matches the ID photo yourself before approving.</p>
                    @endunless

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 {{ $verification->id_back ? 'xl:grid-cols-3' : '' }}">
                        @foreach(array_filter([
                            ['front', 'ID front'],
                            ['selfie', 'Selfie'],
                            $verification->id_back ? ['back', 'ID back'] : null,
                        ]) as [$side, $label])
                            <figure class="min-w-0">
                                <button type="button" aria-label="Enlarge {{ $label }}"
                                    @click="previewImage = '{{ route('verifications.preview', [$verification, $side]) }}'"
                                    class="block w-full cursor-zoom-in overflow-hidden rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] transition-colors duration-200 hover:border-[#FF8A66]">
                                    <img src="{{ route('verifications.preview', [$verification, $side]) }}" alt="{{ $label }}" class="h-72 w-full object-contain">
                                </button>
                                <figcaption class="mt-1.5 text-center text-[12.5px] font-medium text-[#5B6A8E]">{{ $label }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </x-card>

                <x-card>
                    <h2 class="mb-4 text-[15px] font-semibold text-[#060D26]">What the ID says</h2>
                    <x-admin.facts :items="[
                        'ID type' => $verification->id_type,
                        'Name on ID' => $verification->ocr_name ? $verification->ocr_name.($verification->ocr_confidence ? ' ('.$verification->ocr_confidence.'%)' : '') : null,
                        'ID number' => $verification->id_number,
                        'Name on account' => trim($verification->user->first_name.' '.$verification->user->last_name),
                    ]" :cols="4" />
                    @if ($ocr === 'unreadable')
                        <p class="mt-3 text-[13px] text-[#5B6A8E]">Text could not be read automatically. Check the ID by eye</p>
                    @elseif (! $ocr)
                        <p class="mt-3 text-[13px] text-[#5B6A8E]">OCR data not available, so review the ID photos yourself.</p>
                    @endif
                </x-card>

                <x-card>
                    <h2 class="mb-4 text-[15px] font-semibold text-[#060D26]">Business</h2>
                    <x-admin.facts :cols="3" :items="[
                        'Business name' => $verification->business_name,
                        'Contact' => ['value' => $verification->contact_number, 'num' => true],
                        'Address' => $verification->business_address,
                    ]" />
                    @if ($verification->description)
                        <div class="mt-5 border-t border-[#E2E4EC] pt-4">
                            <p class="text-[12.5px] text-[#5B6A8E]">Description</p>
                            <p class="mt-0.5 text-[14px] leading-relaxed text-[#060D26]">{{ $verification->description }}</p>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- Rail --}}
            <div class="space-y-5 lg:sticky lg:top-6">
                @if ($verification->verification_status === 'Pending')
                    <x-card x-data="{ showReject: {{ $errors->has('admin_notes') ? 'true' : 'false' }} }">
                        <h2 class="text-[15px] font-semibold text-[#060D26]">Your decision</h2>
                        <p class="mt-1 text-[13px] text-[#5B6A8E]">Waiting since {{ $verification->submitted_at->format('M j, Y') }}.</p>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <form method="POST" action="{{ route('admin.verifications.approve', $verification) }}"
                                data-confirm="Approve this landlord application?"
                                data-confirm-type="confirm"
                                data-confirm-message="This will grant the applicant the Landlord role."
                                data-confirm-button="Approve">
                                @csrf
                                <button type="submit" class="h-10 w-full rounded-lg bg-[#15803D] text-[13.5px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">Approve</button>
                            </form>
                            <button type="button" @click="showReject = !showReject" :aria-expanded="showReject"
                                class="h-10 rounded-lg border border-[#EF4444]/30 text-[13.5px] font-semibold text-[#DC2626] transition-colors duration-200 hover:bg-[#EF4444]/[0.06] cursor-pointer">Reject</button>
                        </div>

                        <form x-show="showReject" x-cloak method="POST" action="{{ route('admin.verifications.reject', $verification) }}" class="mt-4 border-t border-[#E2E4EC] pt-4"
                            data-confirm="Reject this application?"
                            data-confirm-type="warning"
                            data-confirm-message="The applicant will see your reason. This cannot be undone."
                            data-confirm-button="Reject">
                            @csrf
                            <label for="admin_notes" class="mb-1.5 block text-[13px] font-medium text-[#060D26]">Reason for rejection</label>
                            <textarea name="admin_notes" id="admin_notes" rows="3" required placeholder="The applicant will see this"
                                class="w-full resize-none rounded-lg border border-[#E2E4EC] px-3 py-2.5 text-[13.5px] text-[#060D26] transition-all focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">{{ old('admin_notes') }}</textarea>
                            @error('admin_notes')
                                <p class="mt-1 text-[12.5px] text-[#DC2626]">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="mt-2 h-10 w-full rounded-lg bg-[#DC2626] text-[13.5px] font-semibold text-white transition-all duration-200 hover:brightness-95 cursor-pointer">Confirm rejection</button>
                        </form>
                    </x-card>
                @elseif ($verification->verification_status === 'Rejected')
                    <x-card>
                        <h2 class="text-[15px] font-semibold text-[#DC2626]">Rejected</h2>
                        @if ($verification->reviewed_at)
                            <p class="mt-1 text-[13px] text-[#5B6A8E]">On {{ $verification->reviewed_at->format('M j, Y, g:i A') }}@if ($verification->reviewer) by {{ $verification->reviewer->first_name }} {{ $verification->reviewer->last_name }}@endif</p>
                        @endif
                        @if ($verification->admin_notes)
                            <p class="mt-2 text-[14px] leading-relaxed text-[#060D26]">{{ $verification->admin_notes }}</p>
                        @endif
                    </x-card>
                @elseif ($verification->verification_status === 'Approved')
                    <x-card>
                        <h2 class="text-[15px] font-semibold text-[#15803D]">Approved</h2>
                        @if ($verification->reviewed_at)
                            <p class="mt-1 text-[13px] text-[#5B6A8E]">On {{ $verification->reviewed_at->format('M j, Y, g:i A') }}@if ($verification->reviewer) by {{ $verification->reviewer->first_name }} {{ $verification->reviewer->last_name }}@endif</p>
                        @endif
                    </x-card>
                @endif

                <x-card>
                    <h2 class="text-[15px] font-semibold text-[#060D26]">Before you approve</h2>
                    <ol class="mt-3 space-y-2.5 text-[13.5px] text-[#060D26]">
                        <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">1</span><span>The face on the ID is the face in the selfie.</span></li>
                        <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">2</span><span>The name on the ID matches the account.</span></li>
                        <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">3</span><span>It is a real card, not a screen or a photocopy.</span></li>
                    </ol>
                </x-card>

                <x-card>
                    <p class="text-[12.5px] text-[#5B6A8E]">Applicant</p>
                    <a href="{{ route('admin.users.show', $verification->user) }}" class="mt-0.5 block text-[14px] font-semibold text-[#060D26] underline-offset-2 hover:underline">{{ $verification->user->first_name }} {{ $verification->user->last_name }}</a>
                    <p class="break-words text-[12.5px] text-[#5B6A8E]">{{ $verification->user->email }}</p>
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
                class="absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20">
                <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
            <img :src="previewImage" alt="Document preview" @click.stop class="max-h-[85vh] max-w-full cursor-default rounded-lg">
        </div>

    </div>
@endsection
