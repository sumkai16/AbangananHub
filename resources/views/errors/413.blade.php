<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Upload too big · AbangananHub</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/brand/logo-app-icon.svg') }}">
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .font-display { font-family: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>

{{-- Shown when everything sent in one submit (all the photos or files together) is over PHP's post_max_size. PHP drops
     the whole request before the app sees it, so there is no form to show errors on: this page says what happened. --}}
<body class="antialiased bg-[#F7F8FC]">
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-16 text-center">

        <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 no-underline mb-10">
            <x-brand-logo mark="w-10 h-10" text="text-[18px]" />
        </a>

        <p class="font-display text-[96px] sm:text-[128px] font-extrabold leading-none tracking-tight text-[#060D26]">
            413
        </p>

        <h1 class="font-display text-[22px] sm:text-[26px] font-bold text-[#060D26] tracking-tight mt-2">
            That upload is too big
        </h1>
        <p class="text-[14.5px] sm:text-[15px] text-[#5B6A8E] mt-3 max-w-[460px] leading-relaxed">
            The files you chose add up to more than this server accepts in one go@if($limit = \App\Support\UploadMessages::requestLimitLabel()) ({{ $limit }} in total)@endif.
            Nothing was saved. Go back and upload fewer photos at a time, or choose smaller ones, then try again.
        </p>

        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
            class="inline-flex items-center gap-2 mt-8 px-6 py-3 bg-[#FF8A66] text-[#060D26] rounded-xl text-[14.5px] font-bold shadow-[0_4px_14px_rgba(6,13,38,0.28)] hover:bg-[#E96F4F] transition-all duration-200">
            Go back
        </a>
    </div>
</body>

</html>
