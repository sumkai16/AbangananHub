@props([
    'user',
    'avatarShape' => 'circle', // 'circle' | 'square'
])

@php
    $avatarRadius = $avatarShape === 'square' ? 'rounded-2xl' : 'rounded-full';
    $initials = strtoupper(substr($user->first_name, 0, 1)) . strtoupper(substr($user->last_name, 0, 1));
    $contactParts = array_filter([
        $user->email,
        $user->contact_number,
        'Member since ' . $user->created_at->format('F Y'),
    ]);
@endphp

<div class="relative overflow-hidden rounded-2xl bg-[#2AA7A1] p-5 sm:p-6 mb-5">
    <div class="absolute inset-0 opacity-[0.06] pointer-events-none"
        style="background-image: radial-gradient(circle at 22px 22px, white 1.5px, transparent 0); background-size: 30px 30px;"></div>

    <div class="relative flex items-start gap-4">
        {{-- Avatar --}}
        <div class="flex-shrink-0">
            @if($user->profile_picture)
                <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="w-14 h-14 {{ $avatarRadius }} object-cover ring-4 ring-white/20">
            @else
                <div class="w-14 h-14 {{ $avatarRadius }} bg-white/15 ring-4 ring-white/20 flex items-center justify-center text-white text-[18px] font-bold">
                    {{ $initials }}
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-[17px] font-bold text-white leading-tight">{{ $user->first_name }} {{ $user->last_name }}</h1>
                    {{ $badges ?? '' }}
                </div>

                @if(isset($actions))
                    <div class="flex-shrink-0">
                        {{ $actions }}
                    </div>
                @endif
            </div>

            <p class="text-[12.5px] text-white/85 mt-1.5">{{ implode(' · ', $contactParts) }}</p>
        </div>
    </div>

    @if($user->bio)
        <div class="relative mt-5 pt-5 border-t border-white/10">
            <p class="text-[13px] font-semibold text-white/75 mb-1">About</p>
            <p class="text-[14px] text-white/85 leading-relaxed">{{ $user->bio }}</p>
        </div>
    @endif
</div>
