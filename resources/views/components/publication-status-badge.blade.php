@props(['status'])

@php
    $config = match ($status) {
        'Published' => ['bg' => 'bg-[#EEF8F8]', 'icon' => 'text-[#2AA7A1]'],
        'Suspended' => ['bg' => 'bg-[#EF4444]/10', 'icon' => 'text-[#EF4444]'],
        'Unpublished' => ['bg' => 'bg-[#E2E8F0]', 'icon' => 'text-[#64748B]'],
        default => ['bg' => 'bg-[#E2E8F0]', 'icon' => 'text-[#64748B]'], // Draft
    };
@endphp

<span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-[#1F2937] {{ $config['bg'] }}">
    @if ($status === 'Published')
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    @elseif ($status === 'Suspended')
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
    @elseif ($status === 'Unpublished')
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
    @else
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
        </svg>
    @endif
    {{ $status }}
</span>
