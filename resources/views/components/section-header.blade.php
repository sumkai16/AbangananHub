@props([
    'title',
    'sub' => null,
    'href' => null,
    'cta' => 'View all',
])

<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
        <h2 class="text-[22px] font-semibold text-[#060D26] tracking-tight">{{ $title }}</h2>
    @if($sub)

           <p class="text-[14px] text-[#5B6A8E] mt-1">{{ $sub }}</p>
    @endif
    </div>
    @if($href)
<<<<<<< HEAD
        <a href="{{ $href }}" class="text-[14px] font-semibold text-[#060D26] px-4 py-2 border border-[#DA8E77]/30 rounded-full hover:bg-[#ECEEF6] transition-colors">
=======
        <a href="{{ $href }}" class="text-[14px] font-semibold text-[#060D26] px-4 py-2 border border-[#FF8A66]/30 rounded-full hover:bg-[#ECEEF6] transition-colors">
>>>>>>> 092fb1454a20ae889717d4d8b1bee67f9c0c8eaa
            {{ $cta }}
        </a>
    @endif
</div>