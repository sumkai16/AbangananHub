@extends('layouts.admin')

@section('page-title', 'User Profile')

@section('content')
@php
    $status      = $user->account_status ?? 'active';
    $statusCls   = match(strtolower($status)) {
        'active'    => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
        'suspended' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
        'inactive'  => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
        default     => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
    };
    $initials    = strtoupper(substr($user->first_name ?? $user->email, 0, 1))
                 . strtoupper(substr($user->last_name ?? '', 0, 1));
    $fullName    = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—';
    $daysAsMember = $user->created_at ? (int) \Carbon\Carbon::parse($user->created_at)->diffInDays(now()) : 0;
@endphp

<div class="max-w-5xl mx-auto">

    {{-- Back + actions --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <a href="{{ route('admin.users.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to users
        </a>

        <div class="flex flex-wrap items-center gap-2" x-data="{ deleteOpen: false }">
            {{-- Edit --}}
            <a href="{{ route('admin.users.edit', $user) }}"
                class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-semibold border border-[#E2E8F0] text-[#1F2937] rounded-xl hover:bg-[#F7FCFC] transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                </svg>
                Edit
            </a>

            {{-- Status toggle --}}
            @php $isActive = strtolower($user->account_status ?? 'active') === 'active'; @endphp
            <form action="{{ route('admin.users.updateStatus', $user) }}" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $isActive ? 'suspended' : 'active' }}">
                <button type="submit"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-semibold rounded-xl transition-colors {{ $isActive ? 'border border-[#FBBF24]/35 text-[#B45309] hover:bg-[#FBBF24]/[0.10]' : 'border border-[#22C55E]/25 text-[#15803D] hover:bg-[#22C55E]/[0.07]' }}">
                    @if($isActive)
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                        Suspend
                    @else
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Activate
                    @endif
                </button>
            </form>

            {{-- Delete --}}
            @if($user->user_id !== auth()->id())
                <button @click="deleteOpen = true"
                    class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-semibold border border-[#EF4444]/25 text-[#DC2626] rounded-xl hover:bg-[#EF4444]/[0.07] transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Delete
                </button>

                {{-- Delete confirmation modal --}}
                <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/40" @click="deleteOpen = false"></div>
                    <div class="relative bg-white rounded-3xl shadow-xl max-w-sm w-full p-7 z-10">
                        <div class="w-12 h-12 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-[#DC2626]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 class="text-[16px] font-extrabold text-[#1F2937] text-center mb-1">Delete User?</h3>
                        <p class="text-[13px] text-[#94A3B8] text-center mb-6">
                            This will permanently remove <strong class="text-[#1F2937]">{{ trim($user->first_name . ' ' . $user->last_name) }}</strong> and all their data. This cannot be undone.
                        </p>
                        <div class="flex gap-3">
                            <button @click="deleteOpen = false"
                                class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:bg-[#F7FCFC] transition-colors">
                                Cancel
                            </button>
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="flex-1">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="w-full h-10 text-[13.5px] font-bold bg-[#EF4444] text-white rounded-xl hover:bg-[#EF4444] transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Profile hero card --}}
    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden mb-6">
        <div class="px-7 py-6 border-b border-[#E2E8F0] flex flex-col sm:flex-row sm:items-center gap-5">

            {{-- Avatar --}}
            @if ($user->profile_picture)
                <img src="{{ $user->profile_picture }}"
                    alt="{{ $fullName }}"
                    class="w-16 h-16 rounded-2xl object-cover border border-[#E2E8F0] shadow-sm shrink-0" />
            @else
                <div class="w-16 h-16 rounded-2xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                    <span class="text-[#156F8C] text-[22px] font-extrabold">{{ $initials }}</span>
                </div>
            @endif

            {{-- Name & info --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2.5 mb-1">
                    <h1 class="text-[22px] font-extrabold text-[#1F2937] tracking-tight leading-none">{{ $fullName }}</h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusCls }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ strtolower($status) === 'active' ? 'bg-[#22C55E]' : (strtolower($status) === 'suspended' ? 'bg-[#EF4444]' : 'bg-[#94A3B8]') }}"></span>
                        {{ ucfirst($status) }}
                    </span>
                    @if ($user->is_walk_in)
                        {{-- Landlord-entered, not self-registered: no login, no verified
                             identity, and a legitimately blank email. --}}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309]"
                            title="Walk-in tenant added by a landlord — identity not verified by AbangananHub">Walk-in tenant</span>
                    @endif
                </div>
                <p class="text-[13.5px] text-[#94A3B8] mb-3">{{ $user->email ?: 'No email on file' }}</p>
                <div class="flex flex-wrap gap-2">
                    @forelse ($user->roles as $userRole)
                        @php
                            $roleCls = match($userRole->role) {
                                'Admin'    => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
                                'Landlord' => 'bg-[#EEF8F8] text-[#156F8C] border-[#2AA7A1]/25',
                                'Tenant'   => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
                                default    => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-xl text-[12px] font-bold border {{ $roleCls }}">
                            {{ $userRole->role }}
                        </span>
                    @empty
                        <span class="text-[13px] text-[#94A3B8] italic">No role assigned</span>
                    @endforelse
                </div>

                {{-- Ratings received — role-separated, shown only where there's a score. --}}
                @if($landlordRating['avg'] !== null || $tenantRating['avg'] !== null)
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-3">
                        @if($landlordRating['avg'] !== null)
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">As landlord</span>
                                <x-star-rating :rating="$landlordRating['avg']" :count="$landlordRating['count']" />
                            </div>
                        @endif
                        @if($tenantRating['avg'] !== null)
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide">As tenant</span>
                                <x-star-rating :rating="$tenantRating['avg']" :count="$tenantRating['count']" />
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Member stat --}}
            <div class="text-center bg-[#F7FCFC] border border-[#E2E8F0] rounded-2xl px-6 py-4 shrink-0">
                <p class="text-[32px] font-extrabold text-[#156F8C] leading-none">{{ $daysAsMember }}</p>
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mt-1">Days as member</p>
                <p class="text-[11px] text-[#94A3B8] mt-0.5">
                    Since {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M d, Y') : '—' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Two-col layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Personal info + Verification --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Personal Information --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h2 class="text-[14px] font-bold text-[#1F2937]">Personal Information</h2>
                </div>
                <div class="divide-y divide-[#E2E8F0]">

                    @foreach ([
                        ['Full Name', $fullName, 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['Email Address', $user->email, 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['Contact Number', $user->contact_number ?? '—', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                        ['Member Since', $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('F d, Y') : '—', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ] as [$label, $value, $icon])
                        <div class="px-6 py-4 flex items-center gap-4">
                            <div class="w-9 h-9 rounded-xl bg-[#F7FCFC] border border-[#E2E8F0] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#94A3B8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mb-0.5">{{ $label }}</p>
                                <p class="text-[13.5px] font-semibold text-[#1F2937] break-all">{{ $value }}</p>
                            </div>
                            @if ($label === 'Email Address')
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#15803D] bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-full px-2.5 py-1 shrink-0">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#B45309] bg-[#FBBF24]/[0.10] border border-[#FBBF24]/35 rounded-full px-2.5 py-1 shrink-0">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z"/></svg>
                                        Unverified
                                    </span>
                                @endif
                            @endif
                        </div>
                    @endforeach

                </div>
            </div>

            {{-- Landlord Verification (if applicable) --}}
            @if ($user->hasRole('Landlord') || $user->verificationApplication)
                <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-[#FBBF24]/[0.10] border border-[#FBBF24]/25 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#B45309]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </div>
                        <h2 class="text-[14px] font-bold text-[#1F2937]">Landlord Verification</h2>
                    </div>

                    @if ($user->verificationApplication)
                        @php
                            $v = $user->verificationApplication;
                            $vStatusCls = match($v->verification_status) {
                                'Approved' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
                                'Rejected' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25',
                                default    => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
                            };
                        @endphp
                        <div class="p-6">
                            <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[12px] font-bold border {{ $vStatusCls }}">
                                    {{ $v->verification_status }}
                                </span>
                                <a href="{{ route('admin.verifications.show', $v) }}"
                                    class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-[#156F8C] hover:brightness-95 transition-colors">
                                    View full application
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                @if ($v->submitted_at)
                                    <div class="rounded-2xl bg-[#F7FCFC] border border-[#E2E8F0] px-4 py-3">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1">Submitted</p>
                                        <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ \Carbon\Carbon::parse($v->submitted_at)->format('M d, Y') }}</p>
                                    </div>
                                @endif
                                @if ($v->reviewed_at)
                                    <div class="rounded-2xl bg-[#F7FCFC] border border-[#E2E8F0] px-4 py-3">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1">Reviewed</p>
                                        <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ \Carbon\Carbon::parse($v->reviewed_at)->format('M d, Y') }}</p>
                                    </div>
                                @endif
                            </div>

                            @if ($v->verification_status === 'Rejected' && $v->admin_notes)
                                <div class="mt-4 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 px-4 py-3">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#DC2626] mb-1.5">Rejection Reason</p>
                                    <p class="text-[13.5px] text-[#1F2937]">{{ $v->admin_notes }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="px-6 py-10 text-center">
                            <p class="text-[13.5px] text-[#94A3B8]">No verification application on file.</p>
                        </div>
                    @endif
                </div>
            @endif

        </div>

        {{-- Right: Activity stats --}}
        <div class="space-y-5">

            {{-- User ID --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2">User ID</p>
                <p class="text-[20px] font-extrabold text-[#156F8C] font-mono">#{{ $user->user_id }}</p>
            </div>

            {{-- Activity --}}
            <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center gap-3">
                    <div class="w-7 h-7 rounded-xl bg-[#2AA7A1]/10 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <h2 class="text-[13px] font-bold text-[#1F2937]">Activity</h2>
                </div>
                <div class="p-4 space-y-3">

                    @if ($user->hasRole('Landlord'))
                        <div class="rounded-2xl bg-[#EEF8F8] border border-[#2AA7A1]/20 px-4 py-4 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#2AA7A1] flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[24px] font-extrabold text-[#156F8C] leading-none">{{ $user->properties->count() }}</p>
                                <p class="text-[12px] text-[#94A3B8] mt-0.5">Properties listed</p>
                            </div>
                        </div>
                    @endif

                    @if ($user->hasRole('Tenant'))
                        <div class="rounded-2xl bg-[#22C55E]/[0.07] border border-[#22C55E]/20 px-4 py-4 flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-[#22C55E] flex items-center justify-center shrink-0 shadow-sm">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[24px] font-extrabold text-[#15803D] leading-none">{{ $user->reservations->count() }}</p>
                                <p class="text-[12px] text-[#94A3B8] mt-0.5">Reservations made</p>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl bg-[#F7FCFC] border border-[#E2E8F0] px-4 py-4 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-[#E2E8F0] flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-[24px] font-extrabold text-[#1F2937] leading-none">{{ $daysAsMember }}</p>
                            <p class="text-[12px] text-[#94A3B8] mt-0.5">Days as member</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection
