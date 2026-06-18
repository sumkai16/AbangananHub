@extends('layouts.app', ['searchBar' => false])

@section('title', 'Account Settings')

@section('content')
    <div class="max-w-5xl mx-auto px-6 py-10">

        {{-- Profile Banner --}}
        <div class="relative overflow-hidden rounded-2xl mb-8 px-10 py-9"
             style="background: linear-gradient(135deg, #1A3A6E 0%, #286CD2 55%, #61B2F0 100%);">
            <div class="absolute -right-8 -top-8 w-44 h-44 rounded-full bg-white/5 pointer-events-none"></div>
            <div class="absolute -right-2 bottom-0 w-24 h-24 rounded-full bg-white/5 pointer-events-none"></div>

            <div class="relative z-10 flex items-center gap-6">
                <div class="w-[72px] h-[72px] rounded-full bg-white flex items-center justify-center text-[#286CD2] text-2xl font-black shrink-0 shadow-lg ring-[3px] ring-white/30">
                    {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-white text-xl font-black tracking-tight leading-snug">
                        {{ Auth::user()->first_name }} {{ Auth::user()->last_name }}
                    </h1>
                    <p class="text-white/75 text-sm font-medium mt-0.5">{{ Auth::user()->email }}</p>
                    <div class="flex gap-2 mt-3 flex-wrap">
                        <span class="text-white text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full bg-white/20 backdrop-blur-sm">
                            Member since {{ Auth::user()->created_at?->format('M Y') ?? 'Recently joined' }}
                        </span>
                        @if(Auth::user()->account_status)
                            <span class="text-white text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full bg-white/25 backdrop-blur-sm">
                                {{ Auth::user()->account_status }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Two-column layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-7">

            {{-- Sidebar --}}
            <aside class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm h-fit lg:sticky lg:top-24">
                <p class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-3 px-1">
                    Profile Settings
                </p>
                <nav class="flex flex-col gap-0.5">
                    <a href="#personal-info"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] font-semibold text-gray-500 transition-colors hover:bg-blue-50 hover:text-[#286CD2]"
                       data-section="personal-info">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Personal Details
                    </a>
                    <a href="#security"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] font-semibold text-gray-500 transition-colors hover:bg-blue-50 hover:text-[#286CD2]"
                       data-section="security">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Security & Password
                    </a>
                    <a href="#danger-zone"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13.5px] font-semibold text-gray-500 transition-colors hover:bg-blue-50 hover:text-[#286CD2]"
                       data-section="danger-zone">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Danger Zone
                    </a>
                </nav>
            </aside>

            {{-- Form panels --}}
            <div class="flex flex-col gap-6">
                <div id="personal-info">
                    @include('profile.partials.update-profile-information-form')
                </div>
                <div id="security">
                    @include('profile.partials.update-password-form')
                </div>
                <div id="danger-zone">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
        <script>
        (function () {
            const sections = ['personal-info', 'security', 'danger-zone'];

            function setActive(id) {
                document.querySelectorAll('.sidebar-link').forEach(link => {
                    const active = link.dataset.section === id;
                    link.classList.toggle('bg-blue-50', active);
                    link.classList.toggle('text-[#286CD2]', active);
                    link.classList.toggle('text-gray-500', !active);
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