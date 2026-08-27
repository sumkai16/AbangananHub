@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    @php
        $unit = $reservation->unit;
        $isActive = $reservation->rental_status === 'Occupied';

        $periodStyles = [
            'paid'    => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Paid'],
            'partial' => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Partial'],
            'overdue' => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Overdue'],
            'due'     => ['pill' => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]', 'label' => 'Due'],
        ];

        $statusPill = match ($reservation->rental_status) {
            'Occupied'  => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
            'Completed' => 'bg-[#F7FCFC] text-[#64748B] border-[#E2E8F0]',
            default     => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        };

        $thumb = $unit?->media->firstWhere('media_type', 'Image');

        // Review::canReview() already accepts 'Occupied' or 'Completed' — a
        // tenant can review while living there or after moving out; this page
        // is one of the few places that CTA is actually reachable from.
        $canReview = \App\Models\Review::canReview(auth()->id(), $reservation->property_id);
    @endphp

    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#64748B] mb-3">
            <a href="{{ route('reservations.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">My Reservations</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">{{ $unit->unit_label ?? 'Rent' }}</span>
        </div>

        @if($errors->any())
            <div class="mb-5 bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Header --}}
        <x-card class="mb-5">
            <div class="flex flex-col lg:flex-row lg:items-center gap-5">
                <div class="flex items-center gap-4 min-w-0 flex-1">
                    <div class="w-14 h-14 rounded-xl bg-[#F7FCFC] overflow-hidden shrink-0 ring-1 ring-[#64748B]/10">
                        @if($thumb)
                            <img src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-[#1F2937] truncate">{{ $unit->unit_label ?? 'Your rent' }}</h1>
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $statusPill }}">
                                {{ $reservation->rental_status }}
                            </span>
                        </div>
                        <p class="text-[12.5px] text-[#64748B] mt-1">{{ $reservation->property->title ?? '' }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    @if($canReview)
                        <a href="{{ route('properties.show', $reservation->property) }}#reviews"
                            class="h-11 px-4 inline-flex items-center gap-2 rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m11.48 3.499 1.681 3.408 3.762.545a.6.6 0 0 1 .333 1.023l-2.723 2.652.643 3.746a.6.6 0 0 1-.87.632L12 13.75l-3.306 1.755a.6.6 0 0 1-.87-.632l.643-3.746-2.723-2.652a.6.6 0 0 1 .333-1.023l3.762-.545 1.68-3.408a.6.6 0 0 1 1.08 0Z" />
                            </svg>
                            Leave a review
                        </a>
                    @endif
                    @if($reservation->conversation)
                        <a href="{{ route('conversations.show', $reservation->conversation) }}"
                            class="h-11 px-4 inline-flex items-center gap-2 rounded-full border border-[#E2E8F0] bg-white text-[#1F2937] text-sm font-semibold hover:bg-[#F7FCFC] transition-all duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                            Message
                        </a>
                    @endif
                </div>
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            {{-- ── Ledger column ──────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Summary tiles --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @php
                        $tiles = [
                            ['label' => 'Paid to date', 'value' => $summary['collected'],     'tone' => 'text-[#15803D]', 'sub' => 'Rent + deposits recorded'],
                            ['label' => 'Outstanding',  'value' => $summary['outstanding'],   'tone' => 'text-[#1F2937]', 'sub' => 'Unpaid rent to date'],
                            ['label' => 'Overdue',      'value' => $summary['overdueAmount'], 'tone' => $summary['overdueCount'] > 0 ? 'text-[#DC2626]' : 'text-[#1F2937]', 'sub' => $summary['overdueCount'] . ' ' . \Illuminate\Support\Str::plural('month', $summary['overdueCount']) . ' behind'],
                        ];
                    @endphp
                    @foreach($tiles as $tile)
                        <x-card class="!p-4">
                            <p class="text-[11px] font-bold text-[#64748B] uppercase tracking-wide mb-2">{{ $tile['label'] }}</p>
                            <p class="text-2xl font-extrabold {{ $tile['tone'] }}">
                                ₱{{ number_format($tile['value'], 2) }}
                            </p>
                            <p class="text-[11px] text-[#64748B] mt-1">{{ $tile['sub'] }}</p>
                        </x-card>
                    @endforeach
                </div>

                {{-- Pay now --}}
                @if($isActive && $payablePeriod)
                    <x-card class="!bg-[#EEF8F8] !border-[#2AA7A1]/25">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-bold text-[#156F8C] uppercase tracking-wide mb-1">
                                    {{ $payablePeriod['status'] === 'overdue' ? 'Overdue' : 'Due' }} — {{ $payablePeriod['label'] }}
                                </p>
                                <p class="text-2xl font-extrabold text-[#1F2937]">
                                    ₱{{ number_format(max(0, $payablePeriod['balance']), 2) }}
                                </p>
                                <p class="text-[12px] text-[#64748B] mt-1">
                                    Due on {{ $payablePeriod['due_on']->format('M d, Y') }}. Pay securely via GCash.
                                </p>
                            </div>
                            <form method="POST" action="{{ route('payments.rentCheckout', $reservation) }}" class="shrink-0">
                                @csrf
                                <button type="submit"
                                    class="h-11 px-6 w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200 cursor-pointer">
                                    Pay ₱{{ number_format(max(0, $payablePeriod['balance']), 2) }} now
                                </button>
                            </form>
                        </div>
                    </x-card>
                @endif

                {{-- Rent ledger --}}
                <x-card flush>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-[#E2E8F0]">
                        <div>
                            <h2 class="text-[15px] font-bold text-[#1F2937]">Rent ledger</h2>
                            <p class="text-[12px] text-[#64748B] mt-0.5">
                                ₱{{ number_format($summary['monthlyRent'], 2) }} per month, due on day {{ $summary['dueDay'] }}.
                            </p>
                        </div>
                    </div>

                    @if($periods->isEmpty())
                        <div class="flex flex-col items-center justify-center py-10 px-6 text-center">
                            <p class="text-[14px] font-semibold text-[#1F2937]">No billing periods yet</p>
                            <p class="text-[13px] text-[#64748B] mt-1">
                                Rent starts accruing from your move-in date.
                            </p>
                        </div>
                    @else
                        {{-- Mobile card list — same data as the table below, stacked for a phone screen --}}
                        <div class="lg:hidden divide-y divide-[#E2E8F0]">
                            @foreach($periods as $period)
                                @php $mStyle = $periodStyles[$period['status']] ?? $periodStyles['due']; @endphp
                                <div class="px-5 py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ $period['label'] }}</p>
                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold shrink-0 {{ $mStyle['pill'] }}">
                                            {{ $mStyle['label'] }}
                                        </span>
                                    </div>
                                    @if($period['payments']->isNotEmpty())
                                        <p class="text-[11px] text-[#64748B] mt-0.5">
                                            {{ $period['payments']->pluck('payment_method')->unique()->join(', ') }}
                                        </p>
                                    @endif
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 mt-2.5 text-[12.5px]">
                                        <p class="text-[#64748B]">Due on <span class="text-[#1F2937] font-medium">{{ $period['due_on']->format('M d, Y') }}</span></p>
                                        <p class="text-[#64748B] text-right">Expected <span class="text-[#1F2937] font-medium">₱{{ number_format($period['expected'], 2) }}</span></p>
                                        <p class="text-[#64748B]">Paid <span class="text-[#1F2937] font-medium">₱{{ number_format($period['paid'], 2) }}</span></p>
                                        <p class="text-[#64748B] text-right">Balance <span class="font-medium {{ $period['balance'] > 0 ? 'text-[#DC2626]' : 'text-[#1F2937]' }}">₱{{ number_format(max(0, $period['balance']), 2) }}</span></p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="hidden lg:block overflow-x-auto">
                            <table class="w-full min-w-[640px]">
                                <thead class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Period</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Due on</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Expected</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Paid</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Balance</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E8F0]">
                                    @foreach($periods as $period)
                                        @php $style = $periodStyles[$period['status']] ?? $periodStyles['due']; @endphp
                                        <tr class="hover:bg-[#F7FCFC] transition-colors duration-150">
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ $period['label'] }}</p>
                                                @if($period['payments']->isNotEmpty())
                                                    <p class="text-[11px] text-[#64748B] mt-0.5">
                                                        {{ $period['payments']->pluck('payment_method')->unique()->join(', ') }}
                                                    </p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B] whitespace-nowrap">
                                                {{ $period['due_on']->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['expected'], 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1F2937] text-right whitespace-nowrap">
                                                ₱{{ number_format($period['paid'], 2) }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-right whitespace-nowrap {{ $period['balance'] > 0 ? 'font-semibold text-[#DC2626]' : 'text-[#64748B]' }}">
                                                ₱{{ number_format(max(0, $period['balance']), 2) }}
                                            </td>
                                            <td class="px-5 sm:px-6 py-3.5">
                                                <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold {{ $style['pill'] }}">
                                                    {{ $style['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

                {{-- Other charges --}}
                @if($otherCharges->isNotEmpty())
                    <x-card flush>
                        <div class="px-5 sm:px-6 py-4 border-b border-[#E2E8F0]">
                            <h2 class="text-[15px] font-bold text-[#1F2937]">Deposits &amp; other payments</h2>
                            <p class="text-[12px] text-[#64748B] mt-0.5">Money outside the monthly rent cycle.</p>
                        </div>
                        {{-- Mobile card list — same data as the table below, stacked for a phone screen --}}
                        <div class="lg:hidden divide-y divide-[#E2E8F0]">
                            @foreach($otherCharges as $charge)
                                <div class="px-5 py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[13px] font-semibold text-[#1F2937]">{{ $charge->payment_type }}</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937]">₱{{ number_format((float) $charge->amount, 2) }}</p>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 mt-1 text-[12px] text-[#64748B]">
                                        <span>{{ optional($charge->paid_at)->format('M d, Y') ?? '—' }} · {{ $charge->payment_method }}</span>
                                    </div>
                                    <div class="mt-2">
                                        @if($charge->isManuallyRecorded())
                                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] text-[#64748B] text-[11px] font-bold">
                                                Recorded by landlord
                                            </span>
                                        @else
                                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#2AA7A1]/25 bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold">
                                                Paid online
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="hidden lg:block overflow-x-auto">
                            <table class="w-full min-w-[600px]">
                                <thead class="bg-[#F7FCFC] border-b border-[#E2E8F0]">
                                    <tr>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Type</th>
                                        <th scope="col" class="px-4 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Method</th>
                                        <th scope="col" class="px-4 py-3 text-right text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Amount</th>
                                        <th scope="col" class="px-5 sm:px-6 py-3 text-left text-[11px] font-bold text-[#64748B] uppercase tracking-wide">Source</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#E2E8F0]">
                                    @foreach($otherCharges as $charge)
                                        <tr class="hover:bg-[#F7FCFC] transition-colors duration-150">
                                            <td class="px-5 sm:px-6 py-3.5 text-[13px] text-[#1F2937] whitespace-nowrap">
                                                {{ optional($charge->paid_at)->format('M d, Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B]">{{ $charge->payment_type }}</td>
                                            <td class="px-4 py-3.5 text-[13px] text-[#64748B]">{{ $charge->payment_method }}</td>
                                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#1F2937] text-right whitespace-nowrap">
                                                ₱{{ number_format((float) $charge->amount, 2) }}
                                            </td>
                                            <td class="px-5 sm:px-6 py-3.5">
                                                @if($charge->isManuallyRecorded())
                                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#E2E8F0] bg-[#F7FCFC] text-[#64748B] text-[11px] font-bold">
                                                        Recorded by landlord
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#2AA7A1]/25 bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold">
                                                        Paid online
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endif
            </div>

            {{-- ── Side column ────────────────────────────────── --}}
            <div class="space-y-5">
                <x-card>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-4">Unit</p>

                    <dl class="space-y-2.5 text-[13px]">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">Monthly rent</dt>
                            <dd class="font-bold text-[#2AA7A1]">₱{{ number_format($summary['monthlyRent'], 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">Moved in</dt>
                            <dd class="font-semibold text-[#1F2937]">
                                {{ optional($reservation->target_move_in_date)->format('M d, Y') ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#64748B]">{{ $isActive ? 'Move-out' : 'Moved out' }}</dt>
                            <dd class="font-semibold text-[#1F2937]">
                                {{ optional($reservation->target_move_out_date)->format('M d, Y') ?? 'Open-ended' }}
                            </dd>
                        </div>
                    </dl>
                </x-card>

                @unless($isActive)
                    <x-card class="bg-[#F7FCFC]">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#94A3B8] mb-2">Tenancy closed</p>
                        <p class="text-[12.5px] text-[#64748B] leading-relaxed">
                            This tenancy ended on
                            <strong class="text-[#1F2937]">{{ optional($reservation->target_move_out_date)->format('M d, Y') ?? 'an unrecorded date' }}</strong>.
                            The ledger is kept as a record of what you paid.
                        </p>
                    </x-card>
                @endunless
            </div>
        </div>
    </div>

    @if($isActive)
        {{-- The landlord may record an offline payment while this page is open
             — mirrors landlord/tenancies/show.blade.php's listener so both
             sides of the ledger stay in sync without a manual refresh. --}}
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    if (!window.Echo) return;
                    window.Echo.private('user.{{ auth()->id() }}')
                        .listen('.PaymentStatusUpdated', (e) => {
                            if (e.reservation_id === {{ $reservation->reservation_id }}) {
                                window.location.reload();
                            }
                        });
                });
            </script>
        @endpush
    @endif
@endsection
