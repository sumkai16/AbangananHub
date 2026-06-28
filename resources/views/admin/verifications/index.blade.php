@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <div class="mb-8">
            <h1 class="text-2xl font-semibold text-[#2A2523]">Landlord Verifications</h1>
            <p class="mt-1 text-sm text-[#9B9F98]">Review identity verification applications submitted by landlords.</p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-lg bg-[#D7E8F3] border border-[#61B2F0] px-4 py-3 text-sm text-[#2A2523]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex gap-2 border-b border-[#9B9F98]/30 mb-6">
            @foreach (['Pending', 'Approved', 'Rejected', 'All'] as $tab)
                <a href="{{ route('admin.verifications.index', ['status' => $tab]) }}" class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                            {{ $status === $tab
                ? 'border-[#61B2F0] text-[#2A2523]'
                : 'border-transparent text-[#9B9F98] hover:text-[#2A2523]' }}">
                    {{ $tab }}
                </a>
            @endforeach
        </div>

        @if ($verifications->isEmpty())
            <div class="rounded-xl border border-[#9B9F98]/20 bg-[#F0EDE8] px-6 py-16 text-center">
                <p class="text-sm font-medium text-[#2A2523]">Nothing here</p>
                <p class="mt-1 text-sm text-[#9B9F98]">No applications match this tab right now.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-[#9B9F98]/20">
                <table class="min-w-full divide-y divide-[#9B9F98]/20">
                    <thead class="bg-[#F0EDE8]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Applicant
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Status
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-[#9B9F98]">Submitted
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#9B9F98]/10 bg-white">
                        @foreach ($verifications as $verification)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-[#2A2523]">
                                        {{ $verification->user->first_name }} {{ $verification->user->last_name }}
                                    </div>
                                    <div class="text-xs text-[#9B9F98]">{{ $verification->user->email }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <x-verification-status-badge :status="$verification->verification_status" />
                                </td>
                                <td class="px-4 py-3 text-sm text-[#9B9F98]">
                                    {{ \Carbon\Carbon::parse($verification->submitted_at)->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.verifications.show', $verification) }}"
                                        class="text-sm font-medium text-[#BD5434] hover:brightness-95">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $verifications->links() }}
            </div>
        @endif

    </div>
@endsection