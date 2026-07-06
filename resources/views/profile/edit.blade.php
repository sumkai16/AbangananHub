@extends('layouts.app', ['searchBar' => false])

@section('title', 'Account Settings')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-10">

        {{-- Back to profile --}}
        <a href="{{ route('profile.show') }}"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#156F8C] hover:brightness-90 transition-all mb-5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Profile
        </a>

        {{-- Page header --}}
        <div class="mb-8">
            <h1 class="text-xl font-bold tracking-tight text-[#1F2937]">Account Settings</h1>
            <p class="text-sm text-[#64748B] mt-1">Manage your personal information, password, and account.</p>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[200px_1fr]">

            {{-- Sidebar nav --}}
            <aside class="lg:sticky lg:top-24 h-fit">
                <nav class="flex flex-col gap-0.5">
                    <a href="#personal-info"
                        class="sidebar-link group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#EEF8F8] hover:text-[#1F2937]"
                        data-section="personal-info">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="shrink-0 opacity-60 group-hover:opacity-100">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Personal info
                    </a>
                    <a href="#security"
                        class="sidebar-link group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#EEF8F8] hover:text-[#1F2937]"
                        data-section="security">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="shrink-0 opacity-60 group-hover:opacity-100">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Password
                    </a>
                    <a href="#danger-zone"
                        class="sidebar-link group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#EEF8F8] hover:text-[#1F2937]"
                        data-section="danger-zone">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="shrink-0 opacity-60 group-hover:opacity-100">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Danger zone
                    </a>
                </nav>
            </aside>

            {{-- Main content --}}
            <div class="flex flex-col gap-6">
                <div id="personal-info" class="scroll-mt-28">
                    @include('profile.partials.update-profile-information-form')
                </div>
                <div id="security" class="scroll-mt-28">
                    @include('profile.partials.update-password-form')
                </div>
                <div id="danger-zone" class="scroll-mt-28">
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
                        link.classList.toggle('bg-[#EEF8F8]', active);
                        link.classList.toggle('text-[#1F2937]', active);
                        link.classList.toggle('font-semibold', active);
                        link.classList.toggle('text-[#64748B]', !active);
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