{{--
    The house rules of a lease: the listing's rules first, then the policy's own titled sections. Leases sent before
    sections existed only have the flat `house_rules` list and render that. Needs $terms, $heading, $body; $title
    is the heading text (already numbered by the caller when the clauses are numbered).
--}}
@php
    $sections = $terms['house_rule_sections'] ?? null;
@endphp
@if (! empty($sections) || ! empty($terms['house_rules']))
    @unless ($hideTitle ?? false)
        <h3 class="{{ $heading }}">{{ $title }}</h3>
    @endunless
    <p class="{{ $body }}">The Tenant, their household and guests follow these rules:</p>
    @if (! empty($sections))
        @foreach ($sections as $section)
            <p class="mt-3 text-[13px] font-bold text-[#060D26] print:break-after-avoid">{{ $section['title'] }}</p>
            <ul class="mt-1 list-disc pl-5 space-y-0.5 {{ $body }}">
                @foreach ($section['rules'] as $rule)
                    <li>{{ $rule }}</li>
                @endforeach
            </ul>
        @endforeach
    @else
        <ul class="mt-1.5 list-disc pl-5 space-y-0.5 {{ $body }}">
            @foreach ($terms['house_rules'] as $rule)
                <li>{{ $rule }}</li>
            @endforeach
        </ul>
    @endif
@endif
