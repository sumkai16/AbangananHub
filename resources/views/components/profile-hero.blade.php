@props([
    'user',
    'avatarShape' => 'circle', // 'circle' | 'square'
    'showContact' => true,
    'showBio' => true,
    'subtitle' => null, // replaces the contact line when given
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

<div class="overflow-hidden rounded-2xl bg-[#060D26] p-5 sm:p-7 mb-5">

    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
        <div class="flex items-center gap-4 sm:gap-5 min-w-0 flex-1">
            {{-- Avatar --}}
            <div class="shrink-0">
                @if($user->profile_picture)
                    <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="w-[72px] h-[72px] sm:w-20 sm:h-20 {{ $avatarRadius }} object-cover ring-4 ring-white/15">
                @else
                    <div class="w-[72px] h-[72px] sm:w-20 sm:h-20 {{ $avatarRadius }} bg-white/10 ring-4 ring-white/15 flex items-center justify-center text-white text-[24px] sm:text-[26px] font-bold" aria-hidden="true">
                        {{ $initials }}
                    </div>
                @endif
            </div>

            {{-- Identity --}}
            <div class="min-w-0">
                <div class="flex items-center gap-x-3 gap-y-2 flex-wrap">
                    <h1 class="text-[24px] sm:text-[28px] font-semibold tracking-tight text-white leading-tight">{{ $user->first_name }} {{ $user->last_name }}</h1>
                    <div class="flex items-center gap-2 flex-wrap">{{ $badges ?? '' }}</div>
                </div>

                @if($subtitle)
                    <p class="mt-1.5 text-[14px] sm:text-[15px] text-white/75">{{ $subtitle }}</p>
                @elseif($showContact)
                    <p class="mt-1.5 text-[13.5px] text-white/85">{{ implode(' · ', $contactParts) }}</p>
                @endif
            </div>
        </div>

        @if(isset($actions))
            <div class="shrink-0 [&>a]:justify-center sm:[&>a]:justify-start">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if($showBio && $user->bio)
        <div class="mt-5 pt-5 border-t border-white/10">
            <p class="text-[13px] font-semibold text-white/75 mb-1">About</p>
            <p class="text-[14px] text-white/85 leading-relaxed">{{ $user->bio }}</p>
        </div>
    @endif
</div>
