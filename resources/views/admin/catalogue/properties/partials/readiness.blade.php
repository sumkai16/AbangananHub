{{-- Readiness summary: one segment per check, then one sentence. Shared by the decision card and the Listing health card. --}}
<div class="flex h-1.5 gap-0.5" role="img" aria-label="{{ $passing }} of {{ count($checks) }} checks pass">
    @foreach($checks as $ok)
        <span class="flex-1 rounded-full {{ $ok ? 'bg-[#22C55E]' : 'bg-[#FBBF24]' }}"></span>
    @endforeach
</div>
<p class="mt-1.5 text-[12.5px] leading-5 text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">{{ $passing }} of {{ count($checks) }} checks pass.</span>
    @if($missing->isEmpty())
        Nothing is missing.
    @else
        Missing: {{ $missing->map(fn ($m) => \Illuminate\Support\Str::lower($m))->implode(', ') }}. You can still decide.
    @endif
</p>
