<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.theme-init')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign up as a landlord' }} · {{ config('app.name', 'AbangananHub') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/logo-app-icon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{-- The shell for step 3 of the landlord sign-up (the ID and face check). Same look as the account steps
     (logo on top, no site menu), but wide, because the camera steps need room. --}}
<body class="font-sans antialiased min-h-screen bg-white text-[#060D26]">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:font-semibold">Skip to main content</a>

    <header class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 pt-6 flex items-center justify-between gap-4">
        <a href="/" class="inline-flex items-center gap-2">
            <x-brand-logo mark="w-9 h-9" text="text-[18px]" />
        </a>
        @auth
            {{-- Signed in and mid sign-up: a way out that is not the browser's back button. --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="min-h-[44px] px-3 text-[14px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors cursor-pointer">Log out</button>
            </form>
        @else
            <a href="{{ route('login') }}"
                class="inline-flex items-center min-h-[44px] px-3 text-[14px] font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors">Log in</a>
        @endauth
    </header>

    <main id="main">
        @yield('content')
    </main>

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @include('partials.flash-modal')
    @stack('scripts')
</body>

</html>
