@props(['status'])

@php
    $config = match ($status) {
        'Approved' => ['bg' => 'bg-[#D7E8F3]', 'icon' => 'text-[#61B2F0]'],
        'Rejected' => ['bg' => 'bg-[#BD5434]/10', 'icon' => 'text-[#BD5434]'],
        default => ['bg' => 'bg-[#F0EDE8]', 'icon' => 'text-[#9B9F98]'],
    };
@endphp

<span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-[#2A2523] {{ $config['bg'] }}">
    @if ($status === 'Approved')
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
    @elseif ($status === 'Rejected')
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    @else
        <svg class="h-3.5 w-3.5 {{ $config['icon'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    @endif
    {{ $status }}
</span>