@props([
    'user',
    'subtitle' => null,
    'avatarShape' => 'circle', // 'circle' | 'square'
    'container' => 'max-w-[1400px]', // page max-width; sidebar shells pass their own
])

{{--
    Profile page header shared by the landlord and tenant profiles: a full-bleed navy banner
    (actions pinned to the content column's right edge), the avatar straddling the banner edge,
    and the identity block. The slot is everything below it (tabs + panels), rendered inside the
    same content column so it lines up with the banner's actions.

    Slots: `badges` (pills next to the name), `actions` (buttons on the banner).
--}}
@php
    $wrap = $container . ' mx-auto px-4 sm:px-6 lg:px-8';
    $avatarRadius = $avatarShape === 'square' ? 'rounded-2xl' : 'rounded-full';
    $initials = mb_strtoupper(mb_substr($user->first_name, 0, 1) . mb_substr($user->last_name, 0, 1));
@endphp

{{-- The coral glow is the one decorative flourish, kept to a corner so the page stays calm. --}}
<div class="relative h-32 sm:h-44 overflow-hidden bg-[#060D26]">
    <div class="pointer-events-none absolute -top-20 right-0 sm:right-[6%] h-80 w-80 rounded-full bg-[radial-gradient(circle,rgba(255,138,102,0.35),transparent_70%)]" aria-hidden="true"></div>
    <div class="{{ $wrap }} relative h-full">
        <div class="absolute top-4 sm:top-6 right-4 sm:right-6 lg:right-8 flex items-center gap-2">
            {{ $actions ?? '' }}
        </div>
    </div>
</div>

<div class="{{ $wrap }}">
    {{-- The avatar straddles the banner edge (relative + z-10 so the positioned banner doesn't paint over it);
         the name block starts below the banner so text never lands on navy. --}}
    <div class="flex flex-col sm:flex-row sm:gap-5">
        <div class="relative z-10 -mt-10 sm:-mt-12 shrink-0">
            @if($user->profile_picture)
                <img loading="lazy" decoding="async" src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="h-20 w-20 sm:h-24 sm:w-24 {{ $avatarRadius }} object-cover ring-4 ring-[#F7F8FC] bg-[#060D26]">
            @else
                <div class="h-20 w-20 sm:h-24 sm:w-24 {{ $avatarRadius }} bg-[#060D26] ring-4 ring-[#F7F8FC] flex items-center justify-center text-[26px] sm:text-[30px] font-bold text-white" aria-hidden="true">{{ $initials }}</div>
            @endif
        </div>
        <div class="mt-3 sm:mt-4 min-w-0">
            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                <h1 class="text-[22px] sm:text-[24px] font-bold tracking-tight leading-tight text-[#060D26]">{{ $user->first_name }} {{ $user->last_name }}</h1>
                {{ $badges ?? '' }}
            </div>
            @if($subtitle)
                <p class="mt-1 text-[13.5px] text-[#5B6A8E]">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    {{ $slot }}
</div>
