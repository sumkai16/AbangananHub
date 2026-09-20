<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'AbangananHub') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/AbangananHub-icon-256.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased h-screen overflow-hidden bg-[#F7F8FC]">
    <div class="flex h-full">

        {{-- Left panel: form --}}
        <div class="w-full md:w-1/2 h-full overflow-y-auto bg-white flex flex-col">
            {{-- Optional back button, pinned top-left of the form panel (pages opt in via the `backLink` slot). --}}
            @isset($backLink)
                <div class="px-4 sm:px-6 pt-5 shrink-0">
                    {{ $backLink }}
                </div>
            @endisset
            {{ $slot }}
        </div>
        {{-- Right panel: image + marketing --}}
        <div class="hidden md:flex md:w-1/2 h-full relative flex-col overflow-hidden">

            {{-- Background image with darker overlay --}}
            <div class="absolute inset-0">
                <img src="{{ asset('images/auth-bg-1600.jpg') }}" class="w-full h-full object-cover" alt="" />
                {{-- Photo shows through at the top; navy builds toward the bottom where the copy sits --}}
                <div class="absolute inset-0 bg-gradient-to-t from-[#060D26]/95 via-[#060D26]/70 to-[#060D26]/15"></div>
            </div>

            {{-- Content overlay --}}
            <div class="relative z-10 flex flex-col justify-between h-full p-10 xl:p-14">

                {{-- Top: slot for page-specific action (e.g. "Back to login") --}}
                <div class="flex justify-end">
                    {{ $rightTopAction ?? '' }}
                </div>

                {{-- Bottom: headline + supporting copy --}}
                <div>
                    {{ $rightContent ?? '' }}
                </div>

            </div>
        </div>

    </div>

    <x-confirm-modal />
    <script src="{{ asset('js/modal-confirm.js') }}"></script>
    @include('partials.flash-modal')
</body>

</html>