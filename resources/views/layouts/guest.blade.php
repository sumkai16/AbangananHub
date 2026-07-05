<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AbangananHub') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased h-screen overflow-hidden">
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
                {{-- Darker gradient overlay: dark at bottom where text is, slightly lighter at top --}}
<<<<<<< HEAD
                <div class="absolute inset-0 bg-gradient-to-t from-[#156F8C]/90 via-[#156F8C]/60 to-[#156F8C]/30"></div>
=======
                <div class="absolute inset-0 bg-gradient-to-t from-[#1F2937]/90 via-[#1F2937]/60 to-[#1F2937]/30"></div>
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
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
</body>

</html>