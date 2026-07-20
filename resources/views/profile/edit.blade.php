@extends(auth()->user()->hasRole('Admin') ? 'layouts.admin' : 'layouts.app', auth()->user()->hasRole('Admin') ? [] : ['searchBar' => false])
@section('title', 'Account Settings')
@section('page-title', 'Account Settings')
@section('content')

    @php
        $role = $user->hasRole('Landlord') ? 'Landlord' : ($user->hasRole('Tenant') ? 'Tenant' : ($user->hasRole('Admin') ? 'Admin' : 'Member'));

        $checks = [
            !empty($user->profile_picture),
            !empty($user->first_name) && !empty($user->last_name),
            !empty($user->contact_number),
            !empty($user->bio),
            $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail ? $user->hasVerifiedEmail() : true,
        ];
        $strength = (int) round((count(array_filter($checks)) / count($checks)) * 100);
    @endphp

    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 sm:py-10 min-h-[calc(100vh-72px)]">

        {{-- Back to profile --}}
        <a href="{{ route('profile.show') }}"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#156F8C] hover:brightness-90 transition-all mb-5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Profile
        </a>

        {{-- ID-card hero --}}
        <div class="relative overflow-hidden rounded-[28px] bg-[#2AA7A1] p-6 sm:p-8 mb-8 shadow-[0_12px_36px_rgba(21,111,140,0.28)]"
            data-animate>
            {{-- Security-paper dot texture --}}
            <div class="absolute inset-0 opacity-[0.08] pointer-events-none"
                style="background-image: radial-gradient(circle at 1px 1px, white 1.3px, transparent 0); background-size: 22px 22px;"></div>

            <div class="relative flex flex-col md:flex-row md:items-center gap-6 md:gap-8">

                {{-- Avatar --}}
                <div class="flex-shrink-0 relative">
                    @if($user->profile_picture)
                        <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}"
                            class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl object-cover ring-4 ring-white/20">
                    @else
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-white/15 ring-4 ring-white/20 flex items-center justify-center text-white text-[28px] font-black">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                    @endif
                    <span class="absolute -bottom-2 -right-2 w-7 h-7 rounded-full bg-white flex items-center justify-center shadow-md">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                    </span>
                </div>

                {{-- Identity --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1.5">
                        <span class="inline-flex items-center gap-1 bg-white/15 text-white text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full">
                            {{ $role }}
                        </span>
                        @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->hasVerifiedEmail())
                            <span class="inline-flex items-center gap-1 bg-white text-[#156F8C] text-[11px] font-bold px-2.5 py-1 rounded-full">
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Verified
                            </span>
                        @endif
                    </div>
                    <h1 class="text-[24px] sm:text-[28px] font-black tracking-tight text-white leading-tight truncate">
                        {{ $user->first_name }} {{ $user->last_name }}
                    </h1>
                    <p class="text-[13.5px] text-white/80 mt-1 truncate">{{ $user->email }}</p>
                    <p class="text-[12px] text-white/60 mt-2 flex items-center gap-1.5">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Member since {{ $user->created_at->format('F Y') }}
                    </p>
                </div>

                {{-- Profile strength meter --}}
                <div class="w-full md:w-[190px] flex-shrink-0 bg-white/10 rounded-2xl p-4 backdrop-blur-sm">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-white/70">Profile strength</span>
                        <span class="text-[13px] font-black text-white">{{ $strength }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-white/15 overflow-hidden">
                        <div class="h-full rounded-full bg-white transition-all duration-700 ease-out" style="width: {{ $strength }}%"></div>
                    </div>
                    <p class="text-[10.5px] text-white/60 mt-2 leading-snug">
                        @if($strength >= 100)
                            Your account is fully set up.
                        @else
                            Complete your details to build trust with the community.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[210px_1fr]">

            {{-- Sidebar rail --}}
            <aside class="lg:sticky lg:top-24 h-fit flex flex-col gap-4">
                <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-2 relative">
                    <div id="nav-indicator"
                        class="absolute left-2 right-2 rounded-xl bg-[#EEF8F8] transition-all duration-300 ease-out pointer-events-none"
                        style="top: 8px; height: 58px;"></div>
                    <nav class="relative flex flex-col gap-0.5">
                        <a href="#personal-info"
                            class="sidebar-link relative z-10 group flex items-start gap-2.5 rounded-xl px-3 py-2.5 transition-colors hover:text-[#1F2937]"
                            data-section="personal-info">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                class="shrink-0 mt-0.5 opacity-60 group-hover:opacity-100">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>
                                <span class="block text-sm font-medium leading-tight">Personal info</span>
                                <span class="block text-[11px] opacity-60 leading-tight mt-0.5">Name, contact, bio</span>
                            </span>
                        </a>
                        <a href="#security"
                            class="sidebar-link relative z-10 group flex items-start gap-2.5 rounded-xl px-3 py-2.5 transition-colors hover:text-[#1F2937]"
                            data-section="security">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                class="shrink-0 mt-0.5 opacity-60 group-hover:opacity-100">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>
                                <span class="block text-sm font-medium leading-tight">Password</span>
                                <span class="block text-[11px] opacity-60 leading-tight mt-0.5">Login &amp; security</span>
                            </span>
                        </a>
                        <a href="#danger-zone"
                            class="sidebar-link relative z-10 group flex items-start gap-2.5 rounded-xl px-3 py-2.5 transition-colors hover:text-[#DC2626]"
                            data-section="danger-zone">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                class="shrink-0 mt-0.5 opacity-60 group-hover:opacity-100">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span>
                                <span class="block text-sm font-medium leading-tight">Danger zone</span>
                                <span class="block text-[11px] opacity-60 leading-tight mt-0.5">Delete account</span>
                            </span>
                        </a>
                    </nav>
                </div>

                {{-- Account overview --}}
                <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-5">
                    <h3 class="text-[11px] font-bold uppercase tracking-widest text-[#64748B] mb-3.5">Account overview</h3>
                    <dl class="flex flex-col gap-3.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->hasVerifiedEmail())
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @else
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-[#64748B] leading-tight">Email status</dt>
                                <dd class="text-[13px] font-semibold text-[#1F2937] leading-tight mt-0.5">
                                    {{ $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}
                                </dd>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-[#64748B] leading-tight">Member since</dt>
                                <dd class="text-[13px] font-semibold text-[#1F2937] leading-tight mt-0.5">{{ $user->created_at->format('M d, Y') }}</dd>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-[#64748B] leading-tight">Last updated</dt>
                                <dd class="text-[13px] font-semibold text-[#1F2937] leading-tight mt-0.5">{{ $user->updated_at->diffForHumans() }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>

                {{-- Trust tip --}}
                <div class="rounded-2xl bg-[#EEF8F8] p-5">
                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center mb-3 shadow-sm">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253" />
                        </svg>
                    </div>
                    <p class="text-[12.5px] font-semibold text-[#1F2937] leading-snug">A complete, verified profile builds trust</p>
                    <p class="text-[11.5px] text-[#156F8C]/80 leading-relaxed mt-1.5">
                        Landlords and tenants are more likely to respond to accounts with a photo, bio, and verified email.
                    </p>
                </div>
            </aside>

            {{-- Main content --}}
            <div class="flex flex-col gap-6">
                <div id="personal-info" class="scroll-mt-28" data-animate>
                    @include('profile.partials.update-profile-information-form')
                </div>
                <div id="security" class="scroll-mt-28" data-animate>
                    @include('profile.partials.update-password-form')
                </div>
                <div id="danger-zone" class="scroll-mt-28" data-animate>
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (prefers-reduced-motion: no-preference) {
            [data-animate] {
                animation: settings-fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
            }
            @keyframes settings-fade-up {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
    </style>

    @push('scripts')
        <script>
            (function () {
                const sections = ['personal-info', 'security', 'danger-zone'];
                const indicator = document.getElementById('nav-indicator');

                function setActive(id) {
                    document.querySelectorAll('.sidebar-link').forEach(link => {
                        const active = link.dataset.section === id;
                        link.classList.toggle('text-[#1F2937]', active);
                        link.classList.toggle('font-semibold', active);
                        link.classList.toggle('text-[#DC2626]', active && id === 'danger-zone');
                        link.classList.toggle('text-[#64748B]', !active);

                        if (active && indicator) {
                            indicator.style.top = link.offsetTop + 'px';
                            indicator.style.height = link.offsetHeight + 'px';
                            indicator.classList.toggle('bg-[#EF4444]/[0.07]', id === 'danger-zone');
                            indicator.classList.toggle('bg-[#EEF8F8]', id !== 'danger-zone');
                        }
                    });
                }

                window.addEventListener('scroll', () => {
                    let current = sections[0];
                    sections.forEach(id => {
                        const el = document.getElementById(id);
                        if (el && el.getBoundingClientRect().top <= 140) current = id;
                    });
                    setActive(current);
                }, { passive: true });

                setActive('personal-info');
            })();
        </script>
    @endpush
@endsection
