@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <a href="{{ route('admin.verifications.index') }}"
            class="inline-flex items-center gap-1.5 text-sm text-[#9B9F98] hover:text-[#2A2523] mb-6">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to verifications
        </a>

        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#D7E8F3] border border-[#61B2F0] px-4 py-3 text-sm text-[#2A2523]">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-xl border border-[#9B9F98]/20 bg-white p-6 sm:p-8">

            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-[#2A2523]">
                        {{ $verification->user->first_name }} {{ $verification->user->last_name }}
                    </h1>
                    <p class="text-sm text-[#9B9F98] mt-0.5">{{ $verification->user->email }}</p>
                </div>
                <x-verification-status-badge :status="$verification->verification_status" />
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-6 pb-6 border-b border-[#9B9F98]/15">
                <div>
                    <dt class="text-[#9B9F98]">Submitted</dt>
                    <dd class="text-[#2A2523] mt-0.5">
                        {{ \Carbon\Carbon::parse($verification->submitted_at)->format('M d, Y \a\t g:i A') }}</dd>
                </div>
                @if ($verification->reviewed_at)
                    <div>
                        <dt class="text-[#9B9F98]">Reviewed</dt>
                        <dd class="text-[#2A2523] mt-0.5">
                            {{ \Carbon\Carbon::parse($verification->reviewed_at)->format('M d, Y \a\t g:i A') }}</dd>
                    </div>
                @endif
            </dl>

            <div class="mb-6 pb-6 border-b border-[#9B9F98]/15">
                <p class="text-sm text-[#9B9F98] mb-2">Government ID</p>
                <a href="{{ route('verifications.document', $verification) }}" target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#F0EDE8] px-4 py-2.5 text-sm font-medium text-[#2A2523] hover:brightness-95">
                    <svg class="h-4 w-4 text-[#BD5434]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m-9 8h12a2 2 0 002-2V8a2 2 0 00-2-2h-4l-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    View submitted document
                </a>
            </div>

            @if ($verification->verification_status === 'Rejected' && $verification->admin_notes)
                <div class="mb-6 rounded-lg bg-[#BD5434]/10 border border-[#BD5434]/30 px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-[#BD5434] mb-1">Rejection reason</p>
                    <p class="text-sm text-[#2A2523]">{{ $verification->admin_notes }}</p>
                </div>
            @endif

            @if ($verification->verification_status === 'Pending')
                <div x-data="{ showReject: false }" class="flex flex-col gap-3">
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.verifications.approve', $verification) }}"
                            onsubmit="return confirm('Approve this landlord application?');">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-[#61B2F0] px-4 py-2.5 text-sm font-medium text-white hover:brightness-95">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Approve
                            </button>
                        </form>

                        <button type="button" @click="showReject = !showReject"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#BD5434] px-4 py-2.5 text-sm font-medium text-white hover:brightness-95">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Reject
                        </button>
                    </div>

                    <form x-show="showReject" x-cloak method="POST"
                        action="{{ route('admin.verifications.reject', $verification) }}"
                        onsubmit="return confirm('Reject this application? This cannot be undone.');" class="mt-2">
                        @csrf
                        <label for="admin_notes" class="block text-sm font-medium text-[#2A2523] mb-1.5">
                            Reason for rejection
                        </label>
                        <textarea name="admin_notes" id="admin_notes" rows="3" required
                            class="w-full rounded-lg border border-[#9B9F98]/30 px-3 py-2 text-sm text-[#2A2523] focus:border-[#61B2F0] focus:ring-1 focus:ring-[#61B2F0]"
                            placeholder="Explain why this application is being rejected — the applicant will see this."></textarea>
                        @error('admin_notes')
                            <p class="mt-1 text-xs text-[#BD5434]">{{ $message }}</p>
                        @enderror
                        <div class="mt-3 flex gap-2">
                            <button type="submit"
                                class="rounded-lg bg-[#BD5434] px-4 py-2 text-sm font-medium text-white hover:brightness-95">
                                Confirm rejection
                            </button>
                            <button type="button" @click="showReject = false"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-[#9B9F98] hover:text-[#2A2523]">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>
    </div>
@endsection