@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-12">

        <a href="{{ route('dashboard') }}"
            class="inline-flex items-center gap-1 text-sm text-[#9B9F98] hover:text-[#0F172A] mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Back to dashboard
        </a>

        <h1 class="text-2xl font-semibold text-[#0F172A] mb-1">Landlord Verification</h1>
        <p class="text-sm text-[#9B9F98] mb-8">Status of your identity verification application.</p>

        @if ($verification->verification_status === 'Approved')

            <div class="rounded-xl border border-[#DBEAFE] bg-white p-8">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-[#DBEAFE] flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="#3B82F6" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-[#0F172A]">You're a verified landlord</h2>
                        @if ($verification->reviewed_at)
                            <p class="text-sm text-[#9B9F98] mt-1">
                                Approved on {{ \Carbon\Carbon::parse($verification->reviewed_at)->format('F j, Y') }}
                            </p>
                        @endif
                        <p class="text-sm text-[#0F172A] mt-3">
                            You can now create and manage property listings on AbangananHub.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <a href="{{ route('landlord.listings.index') }}"
                        class="inline-flex items-center px-4 py-2 rounded-md bg-[#3B82F6] text-white text-sm font-medium brightness-95 hover:brightness-90">
                        Go to your listings
                    </a>
                    <a href="{{ route('verifications.document', $verification) }}"
                        class="text-sm text-[#9B9F98] hover:underline">
                        View submitted document
                    </a>
                </div>
            </div>

        @elseif ($verification->verification_status === 'Rejected')

            <div class="rounded-xl border border-[#BD5434]/30 bg-white p-8">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-[#BD5434]/10 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="#BD5434" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-[#0F172A]">Application rejected</h2>
                        @if ($verification->reviewed_at)
                            <p class="text-sm text-[#9B9F98] mt-1">
                                Reviewed on {{ \Carbon\Carbon::parse($verification->reviewed_at)->format('F j, Y') }}
                            </p>
                        @endif
                    </div>
                </div>

                @if ($verification->admin_notes)
                    <div class="mt-5 rounded-md bg-[#DBEAFE]/40 border border-[#DBEAFE] p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-[#9B9F98] mb-1">Reason from admin</p>
                        <p class="text-sm text-[#0F172A]">{{ $verification->admin_notes }}</p>
                    </div>
                @endif

                <div class="mt-6 flex items-center gap-4">
                    <a href="{{ route('landlord.verification.create') }}"
                        class="inline-flex items-center px-4 py-2 rounded-md bg-[#3B82F6] text-white text-sm font-medium brightness-95 hover:brightness-90">
                        Update &amp; resubmit
                    </a>
                    <a href="{{ route('verifications.document', $verification) }}"
                        class="text-sm text-[#9B9F98] hover:underline">
                        View submitted document
                    </a>
                </div>
            </div>

        @else {{-- Pending --}}

            <div class="rounded-xl border border-[#DBEAFE] bg-white p-8">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-[#5E6968]/10 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="#5E6968" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-[#0F172A]">Application under review</h2>
                        @if ($verification->submitted_at)
                            <p class="text-sm text-[#9B9F98] mt-1">
                                Submitted on {{ \Carbon\Carbon::parse($verification->submitted_at)->format('F j, Y') }}
                            </p>
                        @endif
                        <p class="text-sm text-[#0F172A] mt-3">
                            An admin will review your government ID and notify you once a decision is made.
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <a href="{{ route('verifications.document', $verification) }}"
                        class="text-sm text-[#9B9F98] hover:underline">
                        View submitted document
                    </a>
                </div>
            </div>

        @endif

    </div>
@endsection