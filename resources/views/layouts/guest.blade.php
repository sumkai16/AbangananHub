<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AbangananHub') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/AbangananHub-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased h-screen overflow-hidden bg-[#F7F8FC]">
    <div class="flex h-full">

        {{-- Left panel: form --}}
        <div class="w-full md:w-1/2 h-full overflow-y-auto bg-white flex flex-col">
            {{ $slot }}
        </div>
        {{-- Right panel: image + marketing --}}
        <div class="hidden md:flex md:w-1/2 h-full relative flex-col overflow-hidden">

            {{-- Background image with darker overlay --}}
            <div class="absolute inset-0">
                <img src="{{ asset('images/auth-bg.jpg') }}" class="w-full h-full object-cover" alt="" />
                {{-- Navy vignette overlay — mirrors the reference mockup's flat single-hue gradient --}}
                <div class="absolute inset-0 bg-gradient-to-b from-[#060D26]/55 to-[#060D26]/82"></div>
            </div>

            {{-- Content overlay --}}
            <div class="relative z-10 flex flex-col justify-between h-full p-10">

                {{-- Top: slot for page-specific action (e.g. "Already have an account?") --}}
                <div class="flex justify-end">
                    {{ $rightTopAction ?? '' }}
                </div>

                {{-- Middle: headline + feature list --}}
                <div class="flex flex-col gap-6">
                    {{ $rightContent ?? '' }}
                </div>

                {{-- Bottom SDG badge removed --}}

            </div>
        </div>

    </div>

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @include('partials.flash-modal')
</body>

</html>