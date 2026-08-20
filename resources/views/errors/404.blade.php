<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Page not found · AbangananHub</title>
    <link rel="icon" type="image/png" href="{{ asset('images/AbangananHub-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        .font-display { font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif; }
    </style>
</head>

<body class="antialiased bg-[#F7FCFC]">
    <div class="min-h-screen flex flex-col items-center justify-center px-6 py-16 text-center">

        <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 no-underline mb-10">
            <img src="{{ asset('images/AbangananHub-icon.png') }}" alt="AbangananHub" class="w-10 h-10 object-contain">
            <span class="font-display text-[18px] font-extrabold text-[#156F8C] tracking-tight">
                Abanganan<span class="text-[#156F8C]">Hub</span>
            </span>
        </a>

        <p class="font-display text-[96px] sm:text-[128px] font-extrabold leading-none tracking-tight bg-gradient-to-r from-[#2AA7A1] to-[#156F8C] bg-clip-text text-transparent">
            404
        </p>

        <h1 class="font-display text-[22px] sm:text-[26px] font-bold text-[#1F2937] tracking-tight mt-2">
            This page went off the map
        </h1>
        <p class="text-[14.5px] sm:text-[15px] text-[#64748B] mt-3 max-w-[420px] leading-relaxed">
            The listing or page you're looking for doesn't exist, was moved, or may have been taken down.
        </p>

        <a href="{{ url('/') }}"
            class="inline-flex items-center gap-2 mt-8 px-6 py-3 bg-[#2AA7A1] text-white rounded-xl text-[14.5px] font-bold shadow-[0_4px_14px_rgba(42,167,161,0.28)] hover:brightness-95 transition-all">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Back to Browse
        </a>
    </div>
</body>

</html>
