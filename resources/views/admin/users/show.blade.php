@extends('layouts.admin')

@section('page-title', 'User Profile')

@section('content')
@php
    $status      = $user->account_status ?? 'active';
    $initials    = strtoupper(substr($user->first_name ?? $user->email, 0, 1))
                 . strtoupper(substr($user->last_name ?? '', 0, 1));
    $fullName    = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—';
    $daysAsMember = $user->created_at ? (int) \Carbon\Carbon::parse($user->created_at)->diffInDays(now()) : 0;
@endphp

<div class="max-w-6xl mx-auto" x-data="{ userFormOpen: @js(old('_form') === 'edit-user') }">

    @include('admin.users._form-modal', ['user' => $user])

    {{-- Back + actions --}}
    <div class="mb-4">
        <x-back-link href="{{ route('admin.users.index') }}" to="users" />

    </div>

    {{-- Profile hero: cover banner, overlapping avatar, actions, stat strip --}}
    <x-card flush class="mb-6">
        <div class="h-24 sm:h-28 bg-gradient-to-br from-[#0B1437] via-[#1B2A66] to-[#FF8A66]"></div>
        <div class="px-5 sm:px-7 py-5 flex flex-col sm:flex-row sm:items-start gap-4">

            {{-- Avatar --}}
            @if ($user->profile_picture)
                <img loading="lazy" decoding="async" src="{{ $user->profile_picture }}"
                    alt="{{ $fullName }}"
                    class="w-20 h-20 -mt-14 rounded-2xl object-cover border-4 border-white shadow-sm shrink-0 bg-white" />
            @else
                <div class="w-20 h-20 -mt-14 rounded-2xl bg-[#FFF1EC] border-4 border-white flex items-center justify-center shrink-0">
                    <span class="text-[#060D26] text-[26px] font-extrabold">{{ $initials }}</span>
                </div>
            @endif

            {{-- Name & info --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2.5 mb-1">
                    <h1 class="text-[22px] font-semibold text-[#060D26] tracking-tight leading-tight">{{ $fullName }}</h1>
                    <x-admin.status :status="ucfirst($status)" />
                    @if ($user->is_walk_in)
                        {{-- Landlord-entered, not self-registered: no login, no verified
                             identity, and a legitimately blank email. --}}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309]"
                            title="Walk-in tenant added by a landlord — identity not verified by AbangananHub">Walk-in tenant</span>
                    @endif
                    @forelse ($user->roles as $userRole)
                        @php
                            $roleCls = match($userRole->role) {
                                'Admin', 'Landlord' => 'bg-[#ECEEF6] text-[#060D26] border-[#FF8A66]/25',
                                'Tenant'            => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
                                default             => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[11.5px] font-bold border {{ $roleCls }}">{{ $userRole->role }}</span>
                    @empty
                        <span class="text-[12.5px] text-[#5B6A8E] italic">No role assigned</span>
                    @endforelse
                </div>
                <p class="text-[13.5px] text-[#5B6A8E] break-all">{{ $user->email ?: 'No email on file' }} · Member since {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M j, Y') : '—' }}</p>

                {{-- Ratings received — role-separated, shown only where there's a score. --}}
                @if($landlordRating['avg'] !== null || $tenantRating['avg'] !== null)
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-2.5">
                        @if($landlordRating['avg'] !== null)
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">As landlord</span>
                                <x-star-rating :rating="$landlordRating['avg']" :count="$landlordRating['count']" />
                            </div>
                        @endif
                        @if($tenantRating['avg'] !== null)
                            <div class="flex items-center gap-2">
                                <span class="text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wide">As tenant</span>
                                <x-star-rating :rating="$tenantRating['avg']" :count="$tenantRating['count']" />
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Actions --}}
    <div class="flex flex-wrap items-center gap-2" x-data="{ deleteOpen: false }">
        {{-- Edit --}}
        <button type="button" @click="userFormOpen = true"
            class="inline-flex items-center gap-1.5 h-9 px-4 text-[13px] font-semibold border border-[#E2E4EC] text-[#060D26] rounded-xl hover:bg-[#F7F8FC] transition-colors cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
            </svg>
            Edit
        </button>

        {{-- Status toggle --}}
        @php $isActive = strtolower($user->account_status ?? 'active') === 'active'; @endphp
        <form action="{{ route('admin.users.updateStatus', $user) }}" method="POST"
            data-confirm="{{ $isActive ? 'Suspend this account?' : 'Reactivate this account?' }}"
            data-confirm-type="{{ $isActive ? 'warning' : 'confirm' }}"
            data-confirm-message="{{ $isActive ? 'They will not be able to use AbangananHub until you reactivate them.' : 'They will be able to sign in and use AbangananHub again.' }}"
            data-confirm-button="{{ $isActive ? 'Suspend account' : 'Reactivate' }}">
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
            <template x-teleport="body">
            <div x-show="deleteOpen" x-cloak class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="deleteOpen = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-7 z-10">
                    <div class="w-12 h-12 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-[#DC2626]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <h3 class="text-[16px] font-semibold text-[#060D26] text-center mb-1">Delete User?</h3>
                    <p class="text-[13px] text-[#5B6A8E] text-center mb-6">
                        This will permanently remove <strong class="text-[#060D26]">{{ trim($user->first_name . ' ' . $user->last_name) }}</strong> and all their data. This cannot be undone.
                    </p>
                    <div class="flex gap-3">
                        <button @click="deleteOpen = false"
                            class="flex-1 h-10 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:bg-[#F7F8FC] transition-colors">
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
            </template>
        @endif
    </div>
        </div>

        {{-- Stat strip --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 border-t border-[#E2E4EC] divide-[#E2E4EC] [&>*:nth-child(even)]:border-l lg:[&>*]:border-l lg:[&>*:first-child]:border-l-0 [&>*:nth-child(n+3)]:border-t lg:[&>*]:border-t-0">
            <div class="px-5 py-3.5"><p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">User ID</p><p class="text-[20px] font-extrabold text-[#060D26] font-mono mt-0.5">#{{ $user->user_id }}</p></div>
            @if ($user->hasRole('Landlord'))
                <div class="px-5 py-3.5"><p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Properties listed</p><p class="text-[20px] font-extrabold text-[#060D26] tabular-nums mt-0.5">{{ $user->properties->count() }}</p></div>
            @endif
            @if ($user->hasRole('Tenant'))
                <div class="px-5 py-3.5"><p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Reservations made</p><p class="text-[20px] font-extrabold text-[#060D26] tabular-nums mt-0.5">{{ $user->reservations->count() }}</p></div>
            @endif
            <div class="px-5 py-3.5"><p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Days as member</p><p class="text-[20px] font-extrabold text-[#060D26] tabular-nums mt-0.5">{{ $daysAsMember }}</p></div>
            <div class="px-5 py-3.5"><p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E]">Email</p>
                <p class="mt-1.5">@if ($user->email_verified_at)<span class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#15803D] bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-md px-2 h-6">Verified</span>@else<span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#B45309] bg-[#FBBF24]/[0.10] border border-[#FBBF24]/35 rounded-md px-2.5 py-1">Unverified</span>@endif</p></div>
        </div>
    </x-card>

    {{-- Two-col layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Personal info + Verification --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Personal Information --}}
            <x-card flush>
                <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center gap-3">
                    <h2 class="text-[14px] font-semibold text-[#060D26]">Personal Information</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 [&>*]:border-b [&>*]:border-[#E2E4EC] [&>*:nth-last-child(-n+2)]:border-b-0 sm:[&>*:nth-child(odd)]:border-r">

                    @foreach ([
                        ['Full Name', $fullName, 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['Email Address', $user->email, 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                        ['Contact Number', $user->contact_number ?? '—', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                        ['Member Since', $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('F d, Y') : '—', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ] as [$label, $value, $icon])
                        <div class="px-5 py-4 flex items-center gap-3.5">
                            <div class="w-9 h-9 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-[#5B6A8E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-0.5">{{ $label }}</p>
                                <p class="text-[13.5px] font-semibold text-[#060D26] break-all">{{ $value }}</p>
                            </div>
                            @if ($label === 'Email Address')
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center gap-1 text-[12px] font-semibold text-white bg-[#FF8A66] rounded-md px-2 h-6 shrink-0">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#B45309] bg-[#FBBF24]/[0.10] border border-[#FBBF24]/35 rounded-md px-2.5 py-1 shrink-0">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.008v.008H12v-.008z"/></svg>
                                        Unverified
                                    </span>
                                @endif
                            @endif
                        </div>
                    @endforeach

                </div>
            </x-card>

            {{-- Landlord Verification (if applicable) --}}
            @if ($user->hasRole('Landlord') || $user->verificationApplication)
                <x-card flush>
                    <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center gap-3">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Landlord Verification</h2>
                    </div>

                    @if ($user->verificationApplication)
                        @php
                            $v = $user->verificationApplication;
                        @endphp
                        <div class="p-6">
                            <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                                <x-admin.status :status="$v->verification_status" />
                                <a href="{{ route('admin.verifications.show', $v) }}"
                                    class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-[#060D26] hover:brightness-95 transition-colors">
                                    View full application
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                @if ($v->submitted_at)
                                    <div class="rounded-2xl bg-[#F7F8FC] border border-[#E2E4EC] px-4 py-3">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-1">Submitted</p>
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ \Carbon\Carbon::parse($v->submitted_at)->format('M d, Y') }}</p>
                                    </div>
                                @endif
                                @if ($v->reviewed_at)
                                    <div class="rounded-2xl bg-[#F7F8FC] border border-[#E2E4EC] px-4 py-3">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-[#5B6A8E] mb-1">Reviewed</p>
                                        <p class="text-[13.5px] font-semibold text-[#060D26]">{{ \Carbon\Carbon::parse($v->reviewed_at)->format('M d, Y') }}</p>
                                    </div>
                                @endif
                            </div>

                            @if ($v->verification_status === 'Rejected' && $v->admin_notes)
                                <div class="mt-4 rounded-2xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 px-4 py-3">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#DC2626] mb-1.5">Rejection Reason</p>
                                    <p class="text-[13.5px] text-[#060D26]">{{ $v->admin_notes }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="px-6 py-10 text-center">
                            <p class="text-[13.5px] text-[#5B6A8E]">No verification application on file.</p>
                        </div>
                    @endif
                </x-card>
            @endif

            {{-- Recent reservations (tenants) --}}
            @if ($user->hasRole('Tenant'))
                <x-card flush>
                    <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center justify-between">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Recent reservations</h2>
                        <span class="text-[12.5px] text-[#5B6A8E]">{{ $user->reservations->count() }} total</span>
                    </div>
                    @forelse ($recentReservations as $reservation)
                        <a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center justify-between gap-3 px-6 py-3 border-b border-[#E2E4EC] last:border-b-0 hover:bg-[#F7F8FC] transition-colors">
                            <span class="min-w-0"><span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $reservation->property->title ?? 'Property removed' }}</span>
                                <span class="block text-[12px] text-[#5B6A8E]">{{ $reservation->created_at?->format('M j, Y') }}</span></span>
                            <x-admin.status :status="$reservation->rental_status" class="shrink-0" />
                        </a>
                    @empty
                        <p class="px-6 py-6 text-[13px] text-[#5B6A8E]">No reservations yet.</p>
                    @endforelse
                </x-card>
            @endif

            {{-- Properties (landlords) --}}
            @if ($user->hasRole('Landlord'))
                <x-card flush>
                    <div class="px-6 py-4 border-b border-[#E2E4EC] flex items-center justify-between">
                        <h2 class="text-[14px] font-semibold text-[#060D26]">Properties</h2>
                        <span class="text-[12.5px] text-[#5B6A8E]">{{ $user->properties->count() }} listed</span>
                    </div>
                    @forelse ($recentProperties as $property)
                        <a href="{{ route('admin.catalogue.properties.show', $property) }}" class="flex items-center justify-between gap-3 px-6 py-3 border-b border-[#E2E4EC] last:border-b-0 hover:bg-[#F7F8FC] transition-colors">
                            <span class="min-w-0"><span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $property->title }}</span>
                                <span class="block text-[12px] text-[#5B6A8E]">Listed {{ $property->created_at?->format('M j, Y') }}</span></span>
                            <x-admin.status :status="$property->verification_status ?? 'Pending'" class="shrink-0" />
                        </a>
                    @empty
                        <p class="px-6 py-6 text-[13px] text-[#5B6A8E]">No properties listed yet.</p>
                    @endforelse
                </x-card>
            @endif
        </div>

        {{-- Right: Security --}}
        <div class="space-y-5">
            <x-card flush>
                <div class="px-5 py-4 border-b border-[#E2E4EC]"><h2 class="text-[13px] font-semibold text-[#060D26]">Security</h2></div>
                <div class="p-5">
                    <p class="text-[13px] text-[#5B6A8E] mb-3">Admins can't set a password. Email the user a link so they choose their own.</p>
                    @if ($user->email && ! $user->is_walk_in)
                        <form action="{{ route('admin.users.passwordReset', $user) }}" method="POST"
                            data-confirm="Send a password reset link?"
                            data-confirm-type="confirm"
                            data-confirm-message="A reset link will be emailed to {{ $user->email }}."
                            data-confirm-button="Send link">
                            @csrf
                            <button type="submit" class="w-full h-10 inline-flex items-center justify-center gap-2 text-[13px] font-semibold border border-[#E2E4EC] text-[#060D26] rounded-xl hover:bg-[#F7F8FC] transition-colors cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Send password reset link
                            </button>
                        </form>
                    @else
                        <p class="text-[12.5px] font-medium text-[#B45309]">No sign-in email on this account, so there is nothing to send a link to.</p>
                    @endif

                    <div class="mt-5 pt-4 border-t border-[#E2E4EC]">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-[12px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Recent sign-ins</h3>
                            @if ($recentAuthEvents->isNotEmpty())
                                <a href="{{ route('admin.security-events.index', ['search' => $user->email]) }}" class="text-[12px] font-semibold text-[#060D26] underline underline-offset-2 hover:text-[#5B6A8E]">View all</a>
                            @endif
                        </div>
                        @forelse ($recentAuthEvents as $e)
                            <div class="py-2 border-b border-[#E2E4EC] last:border-b-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[13px] font-semibold text-[#060D26] truncate">{{ $e->label() }}</p>
                                    @if ($e->isWarning())
                                        <span class="shrink-0 px-1.5 py-0.5 rounded-md text-[10.5px] font-semibold text-[#B45309] bg-[#FBBF24]/[0.10] border border-[#FBBF24]/35">Warning</span>
                                    @endif
                                </div>
                                <p class="text-[12px] text-[#5B6A8E] truncate">{{ \App\Support\UserAgentSummary::from($e->user_agent) }}@if ($e->ip_address) · {{ $e->ip_address }}@endif</p>
                                <p class="text-[11.5px] text-[#5B6A8E]">{{ $e->created_at?->timezone(config('rentals.timezone'))->format('M j, g:i A') }}</p>
                            </div>
                        @empty
                            <p class="text-[13px] text-[#5B6A8E]">No sign-ins recorded yet.</p>
                        @endforelse
                    </div>
                </div>
            </x-card>

            <x-card flush>
                <div class="px-5 py-4 border-b border-[#E2E4EC]"><h2 class="text-[13px] font-semibold text-[#060D26]">Admin activity</h2></div>
                @forelse ($adminActivity as $log)
                    <div class="px-5 py-3 border-b border-[#E2E4EC] last:border-b-0">
                        <p class="text-[13px] font-semibold text-[#060D26]">{{ $log->actionLabel() }}</p>
                        <p class="text-[12px] text-[#5B6A8E]">{{ $log->actor_name }} · {{ $log->created_at?->timezone(config('rentals.timezone'))->format('M j, Y g:i A') }}</p>
                    </div>
                @empty
                    <p class="px-5 py-5 text-[13px] text-[#5B6A8E]">No admin actions on this account yet.</p>
                @endforelse
                <a href="{{ route('admin.audit-logs.index') }}" class="block px-5 py-3 text-[12.5px] font-semibold text-[#B35A3D] border-t border-[#E2E4EC] hover:bg-[#F7F8FC] transition-colors">Open audit log</a>
            </x-card>
        </div>
    </div>

</div>
@endsection
