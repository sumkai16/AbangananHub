@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-2xl mx-auto px-4 py-10 sm:py-16">

        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-semibold text-[#2A2523]">Landlord Verification</h1>
            <p class="mt-2 text-sm text-[#9B9F98]">
                Upload a valid government-issued ID so we can verify your identity before you start listing properties.
            </p>
        </div>

        @if ($verification?->isPending())
            <div class="rounded-xl bg-[#D7E8F3] border border-[#61B2F0]/30 p-5 flex gap-3">
                <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-[#61B2F0]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="font-medium text-[#2A2523]">Your application is under review</p>
                    <p class="mt-1 text-sm text-[#5E6968]">
                        Submitted on {{ $verification->submitted_at->format('M j, Y \a\t g:i A') }}. We'll notify you once it's
                        been reviewed — this usually takes 1–2 business days.
                    </p>
                </div>
            </div>
        @else
            @if ($verification?->isRejected())
                <div class="rounded-xl bg-[#BD5434]/10 border border-[#BD5434]/30 p-5 flex gap-3 mb-6">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-[#BD5434]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-medium text-[#2A2523]">Your previous application was not approved</p>
                        <p class="mt-1 text-sm text-[#5E6968]">{{ $verification->admin_notes }}</p>
                        <p class="mt-2 text-sm text-[#5E6968]">Please address the issue above and resubmit your ID below.</p>
                    </div>
                </div>
            @endif

            <form action="{{ route('landlord.verification.store') }}" method="POST" enctype="multipart/form-data"
                class="space-y-5" x-data="{ fileName: null }">
                @csrf

                <div>
                    <label for="government_id" class="block text-sm font-medium text-[#2A2523] mb-2">
                        Government-issued ID
                    </label>

                    <label for="government_id"
                        class="flex items-center justify-between gap-4 rounded-lg border border-dashed border-[#9B9F98] px-4 py-4 cursor-pointer hover:bg-[#D7E8F3]/40 transition">
                        <span class="flex items-center gap-3 text-sm">
                            <svg class="w-5 h-5 text-[#61B2F0]" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12l-4.5-4.5L7.5 12M12 7.5v9" />
                            </svg>
                            <span x-text="fileName || 'Click to choose a file (JPG, PNG, or PDF)'"
                                :class="fileName ? 'text-[#2A2523]' : 'text-[#9B9F98]'"></span>
                        </span>
                        <span class="text-xs text-[#9B9F98] whitespace-nowrap">Max 5MB</span>
                    </label>

                    <input type="file" name="government_id" id="government_id" accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                        @change="fileName = $event.target.files[0]?.name">

                    @error('government_id')
                        <p class="mt-2 text-sm text-[#BD5434]">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-lg bg-[#61B2F0] px-6 py-3 text-sm font-medium text-white hover:brightness-95 transition">
                    Submit for Review
                </button>
            </form>
        @endif

    </div>
@endsection