@props(['status'])

@php
    $config = match ($status) {
<<<<<<< HEAD
        'Approved' => ['bg' => 'bg-[#F7FCFC]', 'icon' => 'text-[#FF8A65]'],
        'Rejected' => ['bg' => 'bg-[#DC2626]/10', 'icon' => 'text-[#DC2626]'],
        default => ['bg' => 'bg-[#F7FCFC]', 'icon' => 'text-[#9B9F98]'],
=======
        'Approved' => ['bg' => 'bg-[#EEF8F8]', 'icon' => 'text-[#2AA7A1]'],
        'Rejected' => ['bg' => 'bg-[#EF4444]/10', 'icon' => 'text-[#EF4444]'],
        default => ['bg' => 'bg-[#E2E8F0]', 'icon' => 'text-[#64748B]'],
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
    };
@endphp

<span
<<<<<<< HEAD
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-[#156F8C] {{ $config['bg'] }}">
=======
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-[#1F2937] {{ $config['bg'] }}">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
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