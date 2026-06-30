@extends('layouts.app', ['searchBar' => false])

@section('content')
@php
    $status      = $user->account_status ?? 'active';
    $statusCls   = match(strtolower($status)) {
        'active'    => 'bg-green-100 text-green-700 border-green-300',
        'suspended' => 'bg-red-100 text-red-700 border-red-300',
        'inactive'  => 'bg-gray-100 text-gray-600 border-gray-300',
        default     => 'bg-gray-100 text-gray-600 border-gray-300',
    };
    $roleColors  = [
        'Admin'    => 'bg-purple-100 text-purple-700 border-purple-300',
        'Landlord' => 'bg-white/30 text-white border-white/50',
        'Tenant'   => 'bg-white/30 text-white border-white/50',
    ];
    $initials    = strtoupper(substr($user->first_name ?? $user->email, 0, 1))
                 . strtoupper(substr($user->last_name ?? '', 0, 1));
    $fullName    = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—';
    $daysAsMember = $user->created_at ? (int) \Carbon\Carbon::parse($user->created_at)->diffInDays(now()) : 0;
@endphp

{{-- Hero --}}
<div class="relative overflow-hidden" style="background: linear-gradient(135deg, #0f3580 0%, #1a5bc4 35%, #286CD2 65%, #4a9de8 100%); min-height: 200px;">

    {{-- Grid pattern overlay --}}
    <div class="absolute inset-0 opacity-[0.04]"
        style="background-image: linear-gradient(rgba(255,255,255,0.8) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.8) 1px, transparent 1px); background-size: 40px 40px;"></div>

    {{-- Glow orbs --}}
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full" style="background: radial-gradient(circle, rgba(97,178,240,0.35) 0%, transparent 65%);"></div>
    <div class="absolute -bottom-16 left-1/4 w-72 h-72 rounded-full" style="background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 65%);"></div>
    <div class="absolute top-0 right-1/3 w-48 h-48 rounded-full" style="background: radial-gradient(circle, rgba(74,157,232,0.2) 0%, transparent 70%);"></div>

    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-16">

        {{-- Back link --}}
        <a href="{{ route('admin.users.index') }}"
            class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] mb-10 transition-all px-4 py-2 rounded-full"
            style="color: rgba(255,255,255,0.65); background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); backdrop-filter: blur(12px);"
            onmouseover="this.style.background='rgba(255,255,255,0.18)'; this.style.color='#fff'; this.style.borderColor='rgba(255,255,255,0.35)';"
            onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.color='rgba(255,255,255,0.65)'; this.style.borderColor='rgba(255,255,255,0.18)';">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to users
        </a>

        <div class="flex flex-col sm:flex-row items-center sm:items-center gap-7">

            {{-- Avatar --}}
            @if ($user->profile_picture)
                <div class="relative flex-shrink-0">
                    <div class="absolute -inset-1 rounded-3xl opacity-50" style="background: linear-gradient(135deg, rgba(255,255,255,0.5), rgba(97,178,240,0.5)); filter: blur(6px);"></div>
                    <img src="{{ asset('storage/' . $user->profile_picture) }}"
                        alt="{{ $fullName }}"
                        class="relative w-28 h-28 rounded-2xl object-cover"
                        style="box-shadow: 0 12px 40px rgba(0,0,0,0.3), 0 0 0 3px rgba(255,255,255,0.25);" />
                </div>
            @else
                <div class="relative flex-shrink-0">
                    {{-- Glow ring --}}
                    <div class="absolute -inset-1.5 rounded-3xl opacity-40" style="background: linear-gradient(135deg, rgba(255,255,255,0.6), rgba(97,178,240,0.4)); filter: blur(8px);"></div>
                    <div class="relative w-28 h-28 rounded-2xl flex items-center justify-center"
                        style="background: linear-gradient(135deg, rgba(255,255,255,0.22) 0%, rgba(255,255,255,0.08) 100%); backdrop-filter: blur(16px); box-shadow: 0 12px 40px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.3), 0 0 0 1px rgba(255,255,255,0.2);">
                        <span class="text-white font-black tracking-tight select-none" style="font-size: 2.25rem; text-shadow: 0 2px 12px rgba(0,0,0,0.2);">{{ $initials }}</span>
                    </div>
                </div>
            @endif

            {{-- Name block --}}
            <div class="text-center sm:text-left flex-1 min-w-0">

                {{-- Name + status row --}}
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 mb-2 flex-wrap justify-center sm:justify-start">
                    <h1 class="font-black text-white leading-none"
                        style="font-size: clamp(1.6rem, 4vw, 2.25rem); text-shadow: 0 2px 16px rgba(0,0,0,0.2); letter-spacing: -0.02em;">
                        {{ $fullName }}
                    </h1>
                    @php
                        $heroStatusStyle = match(strtolower($status)) {
                            'active'    => 'background: linear-gradient(135deg, rgba(74,222,128,0.3), rgba(34,197,94,0.2)); color: #bbf7d0; border: 1px solid rgba(74,222,128,0.45); box-shadow: 0 0 12px rgba(74,222,128,0.2);',
                            'suspended' => 'background: linear-gradient(135deg, rgba(248,113,113,0.3), rgba(239,68,68,0.2)); color: #fecaca; border: 1px solid rgba(248,113,113,0.45); box-shadow: 0 0 12px rgba(248,113,113,0.2);',
                            default     => 'background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.2);',
                        };
                        $heroStatusDot = match(strtolower($status)) {
                            'active'    => '#4ade80',
                            'suspended' => '#f87171',
                            default     => 'rgba(255,255,255,0.4)',
                        };
                    @endphp
                    <span class="inline-flex items-center self-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-[0.1em]"
                        style="{{ $heroStatusStyle }} backdrop-filter: blur(12px);">
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: {{ $heroStatusDot }}; box-shadow: 0 0 6px {{ $heroStatusDot }};"></span>
                        {{ ucfirst($status) }}
                    </span>
                </div>

                {{-- Email --}}
                <div class="inline-flex items-center gap-2 mb-4">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: rgba(147,197,253,0.6);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span class="text-sm font-medium" style="color: rgba(191,219,254,0.8); letter-spacing: 0.005em;">{{ $user->email }}</span>
                </div>

                {{-- Role badges --}}
                <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                    @forelse ($user->roles as $userRole)
                        @php
                            $roleStyle = match($userRole->role) {
                                'Admin'    => 'background: linear-gradient(135deg, rgba(168,85,247,0.3), rgba(139,92,246,0.2)); color: #e9d5ff; border: 1px solid rgba(168,85,247,0.45);',
                                'Landlord' => 'background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(255,255,255,0.08)); color: #fff; border: 1px solid rgba(255,255,255,0.28);',
                                'Tenant'   => 'background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(255,255,255,0.08)); color: #fff; border: 1px solid rgba(255,255,255,0.28);',
                                default    => 'background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.2);',
                            };
                        @endphp
                        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-[11px] font-bold uppercase tracking-[0.08em]"
                            style="{{ $roleStyle }} backdrop-filter: blur(12px); box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            @if ($userRole->role === 'Landlord')
                                <svg class="w-3 h-3 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            @elseif ($userRole->role === 'Tenant')
                                <svg class="w-3 h-3 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            @elseif ($userRole->role === 'Admin')
                                <svg class="w-3 h-3 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            @endif
                            {{ $userRole->role }}
                        </span>
                    @empty
                        <span class="text-white/40 text-xs italic">No role assigned</span>
                    @endforelse
                </div>
            </div>

            {{-- Days as member card --}}
            <div class="hidden sm:flex flex-col items-center justify-center rounded-2xl px-7 py-5 text-center flex-shrink-0 min-w-[140px]"
                style="background: linear-gradient(160deg, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0.06) 100%); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.22); box-shadow: 0 8px 32px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.25);">
                <p class="font-black text-white leading-none" style="font-size: 3.5rem; text-shadow: 0 4px 20px rgba(0,0,0,0.2); letter-spacing: -0.04em;">{{ $daysAsMember }}</p>
                <p class="text-[9px] font-black uppercase mt-2" style="color: rgba(147,197,253,0.6); letter-spacing: 0.2em;">Days as Member</p>
                <div class="mt-3 pt-3 w-full" style="border-top: 1px solid rgba(255,255,255,0.1);">
                    <div class="inline-flex items-center gap-1.5 justify-center">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: rgba(147,197,253,0.45);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-[11px] font-semibold" style="color: rgba(147,197,253,0.55);">
                            {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M d, Y') : '—' }}
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Body lifted over hero --}}
<div class="bg-[#F0EDE8] -mt-6 rounded-t-3xl">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ── Left column ─────────────────────────────── --}}
            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Personal Information --}}
                <div class="bg-white rounded-2xl overflow-hidden" style="box-shadow: 0 2px 16px rgba(40,108,210,0.08), 0 1px 4px rgba(0,0,0,0.04);">

                    {{-- Card header --}}
                    <div class="px-6 py-4 flex items-center gap-3" style="background: linear-gradient(90deg, #f0f6ff 0%, #ffffff 100%); border-bottom: 1px solid #e8f0fb;">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #286CD2, #61B2F0); box-shadow: 0 2px 8px rgba(40,108,210,0.3);">
                            <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-semibold text-[#2A2523]">Personal Information</h2>
                    </div>

                    {{-- Rows --}}
                    <div class="divide-y divide-[#F0EDE8]">

                        <div class="px-6 py-4 flex items-center gap-4">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-[#286CD2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-0.5">Full Name</p>
                                <p class="text-sm font-semibold text-[#2A2523]">{{ $fullName }}</p>
                            </div>
                        </div>

                        <div class="px-6 py-4 flex items-center gap-4">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-[#286CD2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-0.5">Email Address</p>
                                <p class="text-sm font-semibold text-[#2A2523] break-all">{{ $user->email }}</p>
                            </div>
                            @if ($user->email_verified_at)
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-green-700 bg-green-50 border border-green-200 rounded-full px-2.5 py-1 flex-shrink-0">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Verified
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#BD5434] bg-orange-50 border border-orange-200 rounded-full px-2.5 py-1 flex-shrink-0">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Unverified
                                </span>
                            @endif
                        </div>

                        <div class="px-6 py-4 flex items-center gap-4">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-[#286CD2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-0.5">Contact Number</p>
                                <p class="text-sm font-semibold text-[#2A2523]">{{ $user->contact_number ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="px-6 py-4 flex items-center gap-4">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-[#286CD2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-0.5">Member Since</p>
                                <p class="text-sm font-semibold text-[#2A2523]">
                                    {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('F d, Y') : '—' }}
                                </p>
                            </div>
                        </div>

                        <div class="px-6 py-4 flex items-center gap-4">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-[#286CD2]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-0.5">Account Status</p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusCls }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Landlord Verification --}}
                @if ($user->hasRole('Landlord') || $user->verificationApplication)
                    <div class="bg-white rounded-2xl overflow-hidden" style="box-shadow: 0 2px 16px rgba(40,108,210,0.08), 0 1px 4px rgba(0,0,0,0.04);">
                        <div class="px-6 py-4 flex items-center gap-3" style="background: linear-gradient(90deg, #fffbf0 0%, #ffffff 100%); border-bottom: 1px solid #fef3d0;">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 2px 8px rgba(245,158,11,0.3);">
                                <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-semibold text-[#2A2523]">Landlord Verification</h2>
                        </div>

                        @if ($user->verificationApplication)
                            @php
                                $v = $user->verificationApplication;
                                $vStatusCls = match($v->verification_status) {
                                    'Approved' => 'bg-green-50 text-green-700 border-green-200',
                                    'Rejected' => 'bg-red-50 text-red-700 border-red-200',
                                    default    => 'bg-amber-50 text-amber-700 border-amber-200',
                                };
                            @endphp
                            <div class="p-6">
                                <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border {{ $vStatusCls }}">
                                        @if ($v->verification_status === 'Approved')
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @elseif ($v->verification_status === 'Rejected')
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        @else
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        @endif
                                        {{ $v->verification_status }}
                                    </span>
                                    <a href="{{ route('admin.verifications.show', $v) }}"
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#286CD2] hover:text-[#1d57b0] transition-colors">
                                        View full application
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    @if ($v->submitted_at)
                                        <div class="rounded-xl px-4 py-3" style="background: linear-gradient(135deg, #f0f6ff, #e8f0fb); border: 1px solid #dbeafe;">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-1">Submitted</p>
                                            <p class="text-sm font-semibold text-[#2A2523]">{{ \Carbon\Carbon::parse($v->submitted_at)->format('M d, Y') }}</p>
                                        </div>
                                    @endif
                                    @if ($v->reviewed_at)
                                        <div class="rounded-xl px-4 py-3" style="background: linear-gradient(135deg, #f0f6ff, #e8f0fb); border: 1px solid #dbeafe;">
                                            <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98] mb-1">Reviewed</p>
                                            <p class="text-sm font-semibold text-[#2A2523]">{{ \Carbon\Carbon::parse($v->reviewed_at)->format('M d, Y') }}</p>
                                        </div>
                                    @endif
                                </div>

                                @if ($v->verification_status === 'Rejected' && $v->admin_notes)
                                    <div class="mt-4 rounded-xl px-4 py-3 bg-red-50 border border-red-200">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-[#BD5434] mb-1.5">Rejection Reason</p>
                                        <p class="text-sm text-[#2A2523]">{{ $v->admin_notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="px-6 py-10 text-center">
                                <svg class="mx-auto mb-2 text-[#9B9F98]/40" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm text-[#9B9F98]">No verification application on file.</p>
                            </div>
                        @endif
                    </div>
                @endif

            </div>

            {{-- ── Right column ─────────────────────────────── --}}
            <div class="flex flex-col gap-6">

                {{-- Days as member — mobile only --}}
                <div class="sm:hidden rounded-2xl px-6 py-5 text-center" style="background: linear-gradient(135deg, #1a4fa0 0%, #286CD2 60%, #61B2F0 100%); box-shadow: 0 4px 20px rgba(40,108,210,0.3);">
                    <p class="text-4xl font-bold text-white">{{ $daysAsMember }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-blue-100/60 mt-1">Days as member</p>
                    <p class="text-xs text-blue-100/40 mt-1">Since {{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('M d, Y') : '—' }}</p>
                </div>

                {{-- Activity Stats --}}
                <div class="bg-white rounded-2xl overflow-hidden" style="box-shadow: 0 2px 16px rgba(40,108,210,0.08), 0 1px 4px rgba(0,0,0,0.04);">
                    <div class="px-6 py-4 flex items-center gap-3" style="background: linear-gradient(90deg, #f0f6ff 0%, #ffffff 100%); border-bottom: 1px solid #e8f0fb;">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #286CD2, #61B2F0); box-shadow: 0 2px 8px rgba(40,108,210,0.3);">
                            <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h2 class="text-sm font-semibold text-[#2A2523]">Activity</h2>
                    </div>

                    <div class="p-4 flex flex-col gap-3">

                        @if ($user->hasRole('Landlord'))
                            <div class="rounded-xl px-4 py-4 flex items-center gap-4" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 1px solid #bfdbfe; box-shadow: 0 1px 4px rgba(40,108,210,0.08);">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #286CD2, #61B2F0); box-shadow: 0 2px 8px rgba(40,108,210,0.25);">
                                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-[#286CD2]">{{ $user->properties->count() }}</p>
                                    <p class="text-xs text-[#9B9F98] mt-0.5">Properties listed</p>
                                </div>
                            </div>
                        @endif

                        @if ($user->hasRole('Tenant'))
                            <div class="rounded-xl px-4 py-4 flex items-center gap-4" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #bbf7d0; box-shadow: 0 1px 4px rgba(34,197,94,0.08);">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500 flex items-center justify-center flex-shrink-0" style="box-shadow: 0 2px 8px rgba(34,197,94,0.25);">
                                    <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-emerald-600">{{ $user->reservations->count() }}</p>
                                    <p class="text-xs text-[#9B9F98] mt-0.5">Reservations made</p>
                                </div>
                            </div>
                        @endif

                        <div class="rounded-xl px-4 py-4 flex items-center gap-4" style="background: linear-gradient(135deg, #f8f8f6, #f0ede8); border: 1px solid #e2ddd7; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                            <div class="w-10 h-10 rounded-xl bg-[#9B9F98] flex items-center justify-center flex-shrink-0" style="box-shadow: 0 2px 8px rgba(155,159,152,0.25);">
                                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-[#2A2523]">{{ $daysAsMember }}</p>
                                <p class="text-xs text-[#9B9F98] mt-0.5">Days as member</p>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- User ID --}}
                <div class="bg-white rounded-2xl overflow-hidden" style="box-shadow: 0 2px 16px rgba(40,108,210,0.08), 0 1px 4px rgba(0,0,0,0.04);">
                    <div class="px-5 py-4 flex items-center justify-between" style="background: linear-gradient(90deg, #f0f6ff 0%, #ffffff 100%); border-bottom: 1px solid #e8f0fb;">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-[#9B9F98]">User ID</p>
                        <span class="text-xs text-[#9B9F98]">#</span>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-xl font-bold text-[#286CD2] font-mono">#{{ $user->user_id }}</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
