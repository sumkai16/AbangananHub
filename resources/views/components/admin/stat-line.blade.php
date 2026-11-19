@props(['cells'])
{{--
    The headline numbers as one thin line in the page header, for a queue page: ['label' => string, 'value' => int|string,
    'note' => string]. Replaces a four-cell x-stat-strip when the numbers repeat the tab counts and the screen is better
    spent on the list. Hairlines between cells; wraps under the title on a narrow screen.
--}}
<dl {{ $attributes->class('flex flex-wrap items-stretch') }}>
    @foreach($cells as $cell)
        <div class="flex flex-col gap-px border-l border-[#E2E4EC] px-5 first:border-l-0 first:pl-0 last:pr-0">
            <dt class="text-[12px] font-medium text-[#5B6A8E]">{{ $cell['label'] }}</dt>
            <dd class="text-[20px] font-semibold leading-6 tabular-nums text-[#060D26]">{{ $cell['value'] }}</dd>
            @if(! empty($cell['note']))<dd class="text-[12px] text-[#5B6A8E]">{{ $cell['note'] }}</dd>@endif
        </div>
    @endforeach
</dl>
