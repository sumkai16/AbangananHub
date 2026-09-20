@props(['status'])

{{--
    Listing visibility as a dot + word, the same treatment as the Verification row on the
    landlord property page. It used to be a grey pill with a tiny coral check for Published,
    which read as "neutral" and looked nothing like the other statuses around it.
    Published green, Suspended red, Unpublished and Draft slate — the app's status palette.
--}}
@php
    $dot = match ($status) {
        'Published' => 'bg-[#22C55E]',
        'Suspended' => 'bg-[#EF4444]',
        default => 'bg-[#5B6A8E]', // Unpublished, Draft
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2 text-[12.5px] font-semibold text-[#060D26] whitespace-nowrap']) }}>
    <span class="h-2 w-2 rounded-full shrink-0 {{ $dot }}" aria-hidden="true"></span>
    {{ $status }}
</span>
