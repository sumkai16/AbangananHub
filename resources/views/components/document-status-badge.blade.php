@props(['document'])

@php
    $status = $document->isRequested() ? 'Requested' : $document->display_status;

    $config = match ($status) {
        'Verified'  => ['bg' => 'bg-[#22C55E]/[0.10]', 'text' => 'text-[#15803D]'],
        'Rejected'  => ['bg' => 'bg-[#EF4444]/[0.10]', 'text' => 'text-[#DC2626]'],
        'Expired'   => ['bg' => 'bg-[#64748B]/[0.10]', 'text' => 'text-[#64748B]'],
        'Requested' => ['bg' => 'bg-[#3B82F6]/[0.10]', 'text' => 'text-[#2563EB]'],
        default     => ['bg' => 'bg-[#FBBF24]/[0.10]', 'text' => 'text-[#B45309]'], // Pending
    };
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $config['bg'] }} {{ $config['text'] }}">
    {{ $status }}
</span>
