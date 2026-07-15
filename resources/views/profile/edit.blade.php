@extends(auth()->user()->hasRole('Admin') ? 'layouts.admin' : 'layouts.app', auth()->user()->hasRole('Admin') ? [] : ['searchBar' => false])
@section('title', 'Account Settings')
@section('page-title', 'Account Settings')
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
        <div class="flex items-center gap-3.5 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.43.991a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a7.78 7.78 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-[#1F2937]">Account Settings</h1>
                <p class="text-sm text-[#64748B] mt-0.5">Manage your personal information, password, and account.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[200px_1fr]">

            {{-- Sidebar nav --}}
            <aside class="lg:sticky lg:top-24 h-fit bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-2">
                <nav class="flex flex-col gap-0.5">
                    <a href="#personal-info"
                        class="sidebar-link group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#EEF8F8] hover:text-[#1F2937]"
                        data-section="personal-info">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="shrink-0 opacity-60 group-hover:opacity-100">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Personal info
                    </a>
                    <a href="#security"
                        class="sidebar-link group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition-colors hover:bg-[#EEF8F8] hover:text-[#1F2937]"
                        data-section="security">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            class="shrink-0 opacity-60 group-hover:opacity-100">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Password
                    </a>
                    <a href="#danger-zone"
                        class="sidebar-link group flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium text-[#64748B] transition-colors hover:bg-red-50 hover:text-red-600"
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