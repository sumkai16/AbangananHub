@props(['status', 'label' => null, 'kind' => 'general', 'tone' => null])
{{--
    The one status chip on admin pages, so a status has the same colour everywhere. Green means settled or good,
    amber means something is open, red means refused or failed, grey means over and needing nothing.
    kind="unit" uses the unit colours fixed in DESIGN.md §7 (Occupied is red there: the unit is taken).
    Pass tone (green, amber, red, neutral) only when the text is not a status, such as an audit action.
--}}
@php
    $key = strtolower(trim((string) $status));

    $unitTones = ['available' => 'green', 'reserved' => 'amber', 'occupied' => 'red', 'maintenance' => 'neutral'];

    $tones = [
        'green' => ['approved', 'verified', 'released', 'occupied', 'active', 'resolved', 'paid', 'sent', 'published', 'live', 'succeeded', 'confirmed', 'visible'],
        'amber' => ['pending', 'inquiry', 'under negotiation', 'pending rental agreement', 'rental agreement signed', 'reserved', 'held', 'processing', 'in transit', 'under review', 'needs review', 'unverified'],
        'red' => ['rejected', 'suspended', 'failed', 'disputed', 'banned', 'expired'],
    ];

    $tone ??= $kind === 'unit' ? ($unitTones[$key] ?? 'neutral') : null;
    if ($tone === null) {
        $tone = 'neutral';
        foreach ($tones as $name => $statuses) {
            if (in_array($key, $statuses, true)) {
                $tone = $name;
                break;
            }
        }
    }

    $classes = [
        // Good and settled states use the brand orange with white text, the same fill as the logo's "Hub".
        'green' => 'bg-[#FF8A66] text-white',
        'amber' => 'bg-[#FBBF24]/[0.16] text-[#B45309]',
        'red' => 'bg-[#EF4444]/[0.08] text-[#DC2626]',
        'neutral' => 'bg-[#ECEEF6] text-[#5B6A8E]',
    ][$tone];
@endphp
<span {{ $attributes->class("inline-flex items-center h-6 px-2 rounded-md text-[12px] font-semibold whitespace-nowrap {$classes}") }}>{{ $label ?? $status }}</span>
