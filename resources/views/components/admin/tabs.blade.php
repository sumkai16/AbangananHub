@props(['tabs'])
{{-- The filter row on admin lists. Each tab: ['label' => string, 'href' => url, 'active' => bool, 'count' => int|null].
     The counts live here, so a list page needs no separate row of status tiles repeating them. --}}
<nav {{ $attributes->class('flex items-center gap-0.5 overflow-x-auto overflow-y-hidden border-b border-[#E2E4EC]') }} aria-label="Filter this list">
    @foreach($tabs as $tab)
        @php $isActive = ! empty($tab['active']); @endphp
        <a href="{{ $tab['href'] }}" @if($isActive) aria-current="true" @endif
            class="-mb-px border-b-2 px-3.5 py-2.5 text-[13px] font-semibold whitespace-nowrap transition-colors duration-200 {{ $isActive ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]' }}">
            {{ $tab['label'] }}
            @if(isset($tab['count']))
                <span class="ml-1 text-[12px] font-medium tabular-nums {{ $isActive ? 'text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ number_format($tab['count']) }}</span>
            @endif
        </a>
    @endforeach
</nav>
