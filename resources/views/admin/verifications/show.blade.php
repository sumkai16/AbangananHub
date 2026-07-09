@extends('layouts.admin')

@section('page-title', 'Verification Review')

@section('content')
<div class="max-w-3xl">

    {{-- Back --}}
    <a href="{{ route('admin.verifications.index') }}"
        class="inline-flex items-center gap-2 text-[13px] font-bold text-gray-400 hover:text-[#156F8C] transition-colors mb-6">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to verifications
    </a>

    {{-- Header --}}
    <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden mb-5">

        {{-- Applicant header --}}
        <div class="px-4 sm:px-7 py-6 border-b border-gray-50 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                    <span class="text-[#156F8C] text-[16px] font-extrabold">
                        {{ strtoupper(substr($verification->user->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($verification->user->last_name ?? '', 0, 1)) }}
                    </span>
                </div>
                <div>
                    <h1 class="text-[18px] font-extrabold text-[#1A1A2E] leading-tight">
                        {{ $verification->user->first_name }} {{ $verification->user->last_name }}
                    </h1>
                    <p class="text-[13px] text-gray-400 mt-0.5">{{ $verification->user->email }}</p>
                </div>
            </div>
            <x-verification-status-badge :status="$verification->verification_status" />
        </div>

        {{-- Details grid --}}
        <div class="px-4 sm:px-7 py-5 grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-gray-50">
            @if ($verification->business_name)
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Business name</p>
                    <p class="text-[14px] font-semibold text-[#1A1A2E]">{{ $verification->business_name }}</p>
                </div>
            @endif
            @if ($verification->contact_number)
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Contact number</p>
                    <p class="text-[14px] font-semibold text-[#1A1A2E]">{{ $verification->contact_number }}</p>
                </div>
            @endif
            @if ($verification->business_address)
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Business address</p>
                    <p class="text-[14px] font-semibold text-[#1A1A2E]">{{ $verification->business_address }}</p>
                </div>
            @endif
            @if ($verification->description)
                <div class="sm:col-span-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Description</p>
                    <p class="text-[13.5px] text-gray-600 leading-relaxed">{{ $verification->description }}</p>
                </div>
            @endif
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Submitted</p>
                <p class="text-[14px] font-semibold text-[#1A1A2E]">
                    {{ \Carbon\Carbon::parse($verification->submitted_at)->format('M d, Y \a\t g:i A') }}
                </p>
            </div>
            @if ($verification->reviewed_at)
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Reviewed</p>
                    <p class="text-[14px] font-semibold text-[#1A1A2E]">
                        {{ \Carbon\Carbon::parse($verification->reviewed_at)->format('M d, Y \a\t g:i A') }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Government ID --}}
        <div class="px-7 py-5">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-3">Government-issued ID</p>
            <a href="{{ route('verifications.document', $verification) }}" target="_blank"
                class="inline-flex items-center gap-3 px-4 py-3 rounded-2xl bg-blue-50/60 border border-blue-100 hover:bg-blue-50 transition-colors group">
                <div class="w-9 h-9 rounded-xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[13.5px] font-bold text-[#1A1A2E] group-hover:text-[#156F8C] transition-colors">View submitted document</p>
                    <p class="text-[12px] text-gray-400">Opens in a new tab</p>
                </div>
                <svg class="w-4 h-4 text-gray-400 group-hover:text-[#156F8C] transition-colors ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>
        </div>

    </div>

    {{-- Rejection note (if rejected) --}}
    @if ($verification->verification_status === 'Rejected' && $verification->admin_notes)
        <div class="bg-red-50 border border-red-100 rounded-3xl p-5 mb-5 flex gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
            </svg>
            <div>
                <p class="text-[12px] font-bold uppercase tracking-wider text-red-600 mb-1">Rejection reason</p>
                <p class="text-[13.5px] text-[#1A1A2E]">{{ $verification->admin_notes }}</p>
            </div>
        </div>
    @endif

    {{-- Actions (pending only) --}}
    @if ($verification->verification_status === 'Pending')
        <div class="bg-white border border-gray-100 rounded-3xl shadow-sm p-6" x-data="{ showReject: false }">
            <h3 class="text-[14px] font-bold text-[#1A1A2E] mb-4">Admin Action</h3>

            <div class="flex gap-3 mb-4">
                {{-- Approve --}}
                <form method="POST" action="{{ route('admin.verifications.approve', $verification) }}"
                    onsubmit="return confirm('Approve this landlord application?');">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 h-11 px-6 rounded-2xl bg-[#22C55E] hover:brightness-95 text-white text-[13.5px] font-bold transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Approve
                    </button>
                </form>

                {{-- Toggle reject form --}}
                <button type="button" @click="showReject = !showReject"
                    class="inline-flex items-center gap-2 h-11 px-6 rounded-2xl bg-red-500 hover:bg-red-600 text-white text-[13.5px] font-bold transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reject
                </button>
            </div>

            {{-- Reject form --}}
            <div x-show="showReject" x-cloak x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                class="border-t border-gray-100 pt-4">
                <form method="POST" action="{{ route('admin.verifications.reject', $verification) }}"
                    onsubmit="return confirm('Reject this application? This cannot be undone.');">
                    @csrf
                    <label for="admin_notes" class="block text-[12px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                        Reason for rejection
                    </label>
                    <textarea name="admin_notes" id="admin_notes" rows="3" required
                        class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-[14px] text-[#1A1A2E] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all resize-none"
                        placeholder="Explain why — the applicant will see this message."></textarea>
                    @error('admin_notes')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="mt-3 flex gap-2">
                        <button type="submit"
                            class="h-10 px-5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-[13px] font-bold transition-colors">
                            Confirm rejection
                        </button>
                        <button type="button" @click="showReject = false"
                            class="h-10 px-5 rounded-xl border border-gray-200 text-[13px] font-semibold text-gray-500 hover:text-[#1A1A2E] transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
