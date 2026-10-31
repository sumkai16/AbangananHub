@extends('layouts.landlord')

@section('page-title', 'Tenancy')

@section('content')
    @php
        $tenant = $reservation->tenant;
        $unit = $reservation->unit;
        $isWalkIn = (bool) $tenant?->is_walk_in;
        $isActive = $reservation->rental_status === 'Occupied';
        // Before move-in there is nothing to bill: the ledger derives periods from the move-in date, so showing it would report arrears for a tenant who has not arrived.
        $hasStarted = in_array($reservation->rental_status, ['Occupied', 'Completed'], true);
        $initials = strtoupper(substr($tenant->first_name ?? '', 0, 1) . substr($tenant->last_name ?? '', 0, 1));

        // Period pill styling, one map so the table and the tiles can't drift.
        $periodStyles = [
            'paid'    => ['pill' => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25', 'label' => 'Paid'],
            'partial' => ['pill' => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35', 'label' => 'Partial'],
            'overdue' => ['pill' => 'bg-[#EF4444]/[0.07] text-[#DC2626] border-[#EF4444]/25', 'label' => 'Overdue'],
            'due'     => ['pill' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]', 'label' => 'Due'],
            // A month settled before it arrived. Distinct from plain Paid so a
            // landlord can tell rent already banked from rent still to come.
            'advance' => ['pill' => 'bg-[#ECEEF6] text-[#060D26] border-[#FF8A66]/25', 'label' => 'Paid · Advance'],
            // Part-covered by an overpayment. Deliberately NOT the amber
            // Partial pill: nobody is behind on a month that hasn't arrived,
            // and amber here would read as a collection problem.
            'advance_part' => ['pill' => 'bg-[#F7F8FC] text-[#060D26] border-[#FF8A66]/20', 'label' => 'Advance · part'],
        ];

        // A future period only exists in the ledger because it was paid into,
        // so it never carries an arrears colour.
        $periodStyleFor = function (array $period) use ($periodStyles) {
            if ($period['is_future']) {
                return $period['status'] === 'paid' ? $periodStyles['advance'] : $periodStyles['advance_part'];
            }

            return $periodStyles[$period['status']] ?? $periodStyles['due'];
        };

        $statusPill = match ($reservation->rental_status) {
            'Occupied'  => 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25',
            'Completed' => 'bg-[#F7F8FC] text-[#5B6A8E] border-[#E2E4EC]',
            default     => 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35',
        };

        // Payment status — same five values and same colours as
        // landlord/payments/index.blade.php's $paymentStyles, so a tenancy
        // reads identically whether the landlord arrived from the portfolio
        // table or opened this ledger directly.
        $paymentStatusStyles = [
            'overdue'    => ['dot' => 'bg-[#DC2626]', 'text' => 'text-[#DC2626]', 'label' => 'Overdue'],
            'partial'    => ['dot' => 'bg-[#B45309]', 'text' => 'text-[#B45309]', 'label' => 'Partial'],
            'upcoming'   => ['dot' => 'bg-[#5B6A8E]', 'text' => 'text-[#5B6A8E]', 'label' => 'Upcoming'],
            'paid'       => ['dot' => 'bg-[#15803D]', 'text' => 'text-[#15803D]', 'label' => 'Paid'],
            'paid_ahead' => ['dot' => 'bg-[#060D26]', 'text' => 'text-[#060D26]', 'label' => 'Paid Ahead'],
        ];
        $paymentStatusStyle = $paymentStatusStyles[$summary['paymentStatus']] ?? $paymentStatusStyles['upcoming'];

        $inputClass = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
        $labelClass = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';

        $thumb = $unit?->media->firstWhere('media_type', 'Image');
    @endphp

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-16" x-data>

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#5B6A8E] mb-3">
            <a href="{{ route('landlord.contracts.index') }}"
                class="hover:text-[#060D26] transition-colors duration-200">Contracts</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#060D26] font-medium">{{ $tenant->name ?? 'Tenancy' }}</span>
        </div>

        {{-- Tenant profile — who this is comes first; every fact carries a visible label. --}}
        <x-card flush class="mb-5">
            <div class="p-5 sm:p-6 flex flex-col lg:flex-row lg:items-center gap-5">
                <div class="flex items-center gap-4 min-w-0 flex-1">
                    <div class="w-16 h-16 rounded-full bg-[#ECEEF6] flex items-center justify-center text-[20px] font-bold text-[#060D26] shrink-0" aria-hidden="true">
                        {{ $initials ?: '?' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[12px] font-semibold text-[#5B6A8E]">Tenant</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-[22px] sm:text-[24px] font-semibold tracking-tight text-[#060D26] truncate">
                                {{ trim(($tenant->first_name ?? '') . ' ' . ($tenant->last_name ?? '')) ?: 'Unknown tenant' }}
                            </h1>
                            <span class="inline-flex items-center h-6 px-2.5 rounded-full border text-[12px] font-semibold {{ $statusPill }}">
                                {{ $reservation->rental_status }}
                            </span>
                            @if($isWalkIn)
                                {{-- Not platform-verified. The landlord asserted this person exists. --}}
                                <span class="inline-flex items-center h-6 px-2.5 rounded-full border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309] text-[12px] font-semibold"
                                    title="Added by you — identity not verified by AbangananHub">
                                    Walk-in
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    @if($reservation->conversation)
                        <a href="{{ route('conversations.show', $reservation->conversation) }}"
                            class="h-11 px-4 inline-flex items-center gap-2 rounded-xl border border-[#060D26]/25 bg-white text-[#060D26] text-sm font-semibold hover:border-[#060D26] hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                            Message
                        </a>
                    @endif

                    <a href="{{ route('landlord.payments.ledger', $reservation) }}"
                        class="h-11 px-5 inline-flex items-center gap-2 rounded-xl bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-colors duration-200">
                        Open ledger
                    </a>
                </div>
            </div>

            {{-- Two blocks instead of eight equal cells: who to reach on the left, the lease on
                 the right, with the lease drawn as a timeline. Stacks on a phone. --}}
            @php
                $moveIn = $reservation->target_move_in_date;
                $moveOut = $reservation->target_move_out_date;
                // Progress through a fixed-term lease; open-ended or ended stays have nothing to measure.
                $leaseTotal = ($isActive && $moveIn && $moveOut) ? (int) round($moveIn->diffInMonths($moveOut)) : 0;
                $leaseMonth = $leaseTotal > 0 ? max(1, min($leaseTotal, (int) floor($moveIn->diffInMonths(now())) + 1)) : 0;
                $factLabel = 'text-[12px] font-semibold text-[#5B6A8E]';
                $factValue = 'text-[14px] font-semibold text-[#060D26]';
            @endphp
            <div class="grid lg:grid-cols-2 border-t border-[#E2E4EC] divide-y lg:divide-y-0 lg:divide-x divide-[#E2E4EC]">

                {{-- Contact --}}
                <div class="p-5 sm:p-6">
                    <h2 class="text-[12px] font-bold uppercase tracking-wide text-[#5B6A8E]">Contact</h2>
                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="min-w-0">
                            <dt class="{{ $factLabel }}">Phone</dt>
                            <dd class="mt-0.5 {{ $factValue }} truncate">
                                @if($tenant->contact_number)
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $tenant->contact_number) }}" class="hover:text-[#B35A3D] hover:underline">{{ $tenant->contact_number }}</a>
                                @else
                                    <span class="font-normal text-[#5B6A8E]">Not provided</span>
                                @endif
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="{{ $factLabel }}">Email</dt>
                            <dd class="mt-0.5 {{ $factValue }} truncate" title="{{ $tenant->email }}">
                                @if($tenant->email)
                                    <a href="mailto:{{ $tenant->email }}" class="hover:text-[#B35A3D] hover:underline">{{ $tenant->email }}</a>
                                @else
                                    <span class="font-normal text-[#5B6A8E]">Not provided</span>
                                @endif
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="{{ $factLabel }}">Account</dt>
                            <dd class="mt-0.5 {{ $factValue }}">{{ $isWalkIn ? 'Walk-in tenant' : 'Platform tenant' }}</dd>
                            <p class="text-[12px] text-[#5B6A8E]">
                                {{ $isWalkIn ? 'Added by you, not verified' : ($tenant->email_verified_at ? 'Email verified' : 'Email not verified') }}
                            </p>
                        </div>
                        <div class="min-w-0">
                            <dt class="{{ $factLabel }}">Occupants</dt>
                            <dd class="mt-0.5 {{ $factValue }}">
                                @if($reservation->occupants_count)
                                    {{ $reservation->occupants_count }} {{ \Illuminate\Support\Str::plural('person', $reservation->occupants_count) }}
                                @else
                                    <span class="font-normal text-[#5B6A8E]">Not provided</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Lease timeline --}}
                <div class="p-5 sm:p-6">
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="text-[12px] font-bold uppercase tracking-wide text-[#5B6A8E]">Lease</h2>
                        <p class="text-[12px] text-[#5B6A8E]">
                            {{ $reservation->duration_of_stay ?? 'Term not recorded' }} &middot; rent due day {{ $summary['dueDay'] }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-start justify-between gap-4">
                        <div>
                            <p class="{{ $factLabel }}">{{ $hasStarted ? 'Moved in' : 'Planned move-in' }}</p>
                            <p class="mt-0.5 {{ $factValue }}">{{ $moveIn?->format('M d, Y') ?? 'Not recorded' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="{{ $factLabel }}">{{ $isActive ? 'Planned move-out' : 'Moved out' }}</p>
                            <p class="mt-0.5 {{ $factValue }}">{{ $moveOut?->format('M d, Y') ?? ($isActive ? 'Open-ended' : 'Not recorded') }}</p>
                        </div>
                    </div>

                    @if($leaseTotal > 0)
                        <div class="mt-3 h-2 w-full rounded-full bg-[#ECEEF6] overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $leaseTotal }}" aria-valuenow="{{ $leaseMonth }}" aria-label="Lease progress">
                            <div class="h-full rounded-full bg-[#FF8A66]" style="width: {{ round($leaseMonth / $leaseTotal * 100) }}%"></div>
                        </div>
                        <p class="mt-1.5 text-[12px] text-[#5B6A8E]">
                            Month {{ $leaseMonth }} of {{ $leaseTotal }} &middot; {{ $moveOut->isPast() ? 'ended ' : 'ends ' }}{{ $moveOut->diffForHumans() }}
                        </p>
                    @elseif($isActive && ! $moveOut)
                        <p class="mt-3 text-[12px] text-[#5B6A8E]">No end date set. Rent keeps accruing until you end the tenancy.</p>
                    @endif

                </div>
            </div>
                    {{-- Lease document — printable lease + signed copy. See plans/formal-lease-agreement.md.
                         Lives here with the lease dates rather than in its own side card. --}}
                    @php
                        $leaseStatus = $reservation->leaseStatus();
                        $leaseTerms = \App\Support\LeaseTerms::for($reservation);
                        $leaseSignedOnline = $reservation->agreed_at !== null;
                    @endphp
                    <div class="px-5 sm:px-6 py-4 border-t border-[#E2E4EC] bg-[#F7F8FC] sm:flex sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-6 sm:gap-y-3"
                        x-data="{ uploading: {{ $errors->has('lease_file') ? 'true' : 'false' }}, viewingLease: false }">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            @if ($leaseStatus === 'missing')
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#FBBF24]/15 text-[#B45309]">Lease missing</span>
                            @else
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-[#22C55E]/10 text-[#15803D]">Signed</span>
                            @endif
                            <p class="text-[13px] text-[#060D26] min-w-0 flex-1">
                                @if ($leaseStatus === 'uploaded')
                                    Signed copy uploaded {{ $reservation->lease_uploaded_at->format('M j, Y') }}.
                                @elseif ($leaseStatus === 'signed_online')
                                    Signed online by the tenant {{ $reservation->agreed_at->format('M j, Y') }}.
                                @else
                                    Print the lease, have both of you sign it, then upload the signed copy here.
                                @endif
                            </p>
                        </div>

                        <div class="mt-3 sm:mt-0 flex flex-wrap items-center gap-2">
                            <button type="button" @click="viewingLease = true"
                                class="h-11 sm:h-9 px-4 sm:px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] hover:border-[#060D26]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] inline-flex items-center justify-center cursor-pointer transition-colors duration-200">
                                {{ $leaseStatus === 'missing' ? 'View & print lease' : 'View lease' }}
                            </button>
                            @if ($reservation->lease_file_path)
                                <a href="{{ route('landlord.leases.file', $reservation) }}" target="_blank" rel="noopener"
                                    class="h-11 sm:h-9 px-4 sm:px-3.5 rounded-lg border border-[#E2E4EC] bg-white text-[#060D26] text-[12.5px] font-semibold hover:bg-[#F7F8FC] hover:border-[#060D26]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] inline-flex items-center justify-center transition-colors duration-200">
                                    Open signed copy
                                </a>
                            @endif
                            <button type="button" @click="uploading = !uploading"
                                class="h-11 sm:h-9 px-4 sm:px-3.5 rounded-lg text-[12.5px] font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] inline-flex items-center justify-center cursor-pointer transition-colors duration-200
                                    {{ $leaseStatus === 'missing' ? 'bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26]' : 'text-[#060D26] hover:bg-[#ECEEF6]' }}">
                                {{ $reservation->lease_file_path ? 'Replace signed copy' : 'Upload signed copy' }}
                            </button>
                        </div>

                        <form x-show="uploading" x-cloak action="{{ route('landlord.leases.upload', $reservation) }}" method="POST" enctype="multipart/form-data"
                            class="mt-3 pt-3 border-t border-[#E2E4EC] sm:basis-full">
                            @csrf
                            <label for="lease_file" class="block text-[12px] font-semibold text-[#5B6A8E] mb-1.5">PDF, JPG or PNG · up to 10 MB</label>
                            <input type="file" id="lease_file" name="lease_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/*" required
                                class="block w-full text-[13px] text-[#060D26] file:mr-3 file:h-9 file:px-3 file:rounded-lg file:border-0 file:bg-[#ECEEF6] file:text-[#060D26] file:font-semibold file:cursor-pointer">
                            @error('lease_file')
                                <p class="mt-1.5 text-[12px] font-semibold text-[#DC2626]">{{ $message }}</p>
                            @enderror
                            <button type="submit"
                                class="mt-3 w-full sm:w-auto h-10 px-5 rounded-xl bg-[#060D26] hover:brightness-110 text-white text-[13px] font-bold cursor-pointer transition-all">
                                Upload
                            </button>
                        </form>

                    <x-picker-modal show="viewingLease" title="Lease Agreement" :subtitle="$leaseTerms['reference']">
                        <div class="max-h-[70vh] overflow-y-auto p-5 sm:p-6">
                            @include('leases._document', ['terms' => $leaseTerms])

                            @if ($leaseSignedOnline)
                                <div class="mt-6 rounded-xl border border-[#E2E4EC] p-5">
                                    <p class="text-[10px] font-bold text-[#5B6A8E] uppercase tracking-wider mb-3">Signatures</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-[12px]">
                                        <div>
                                            <p class="text-[13px] font-bold text-[#060D26]">{{ $leaseTerms['landlord']['name'] }}</p>
                                            <p class="text-[#5B6A8E]">Landlord</p>
                                            @if ($reservation->landlord_tc_accepted_at)
                                                <p class="text-[#15803D] font-semibold mt-1.5">Accepted {{ $reservation->landlord_tc_accepted_at->format('F j, Y \a\t g:i A') }}</p>
                                            @endif
                                        </div>
                                        <div class="sm:border-l sm:border-[#E2E4EC] sm:pl-4">
                                            <p class="text-[13px] font-bold text-[#060D26]">{{ $leaseTerms['tenant']['name'] }}</p>
                                            <p class="text-[#5B6A8E]">Tenant</p>
                                            <p class="text-[#15803D] font-semibold mt-1.5">Signed online {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}</p>
                                            @if ($reservation->agreed_ip)
                                                <p class="text-[10.5px] text-[#5B6A8E] mt-0.5">Recorded from {{ $reservation->agreed_ip }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Printing needs blank signature lines and print-specific CSS
                             that only the standalone page carries — send them there
                             rather than trying to print out of a fixed-position modal,
                             which browsers handle inconsistently. --}}
                        <div class="px-5 sm:px-6 py-3.5 border-t border-[#E2E4EC] flex justify-end">
                            <a href="{{ route('landlord.leases.show', $reservation) }}"
                                class="h-10 px-4 rounded-xl border border-[#E2E4EC] bg-white text-[#060D26] text-[13px] font-semibold hover:bg-[#F7F8FC] inline-flex items-center gap-1.5 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg>
                                {{ $leaseStatus === 'missing' ? 'Open full page to print & sign' : 'Open full page to print' }}
                            </a>
                        </div>
                    </x-picker-modal>
                    </div>

            @if($reservation->remarks)
                <div class="px-5 sm:px-6 py-4 border-t border-[#E2E4EC]">
                    <p class="text-[12px] font-semibold text-[#5B6A8E]">Notes</p>
                    <p class="mt-1 text-[13.5px] leading-relaxed text-[#060D26]">{{ $reservation->remarks }}</p>
                </div>
            @endif
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">

            {{-- ── Contract column ─────────────────────────────── --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Money lives under Payments; this strip says where things stand and links there. --}}
                @if($hasStarted)
                    <x-card>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold text-[#5B6A8E]">Rent</p>
                                <p class="text-[14px] text-[#060D26]">
                                    <span class="font-semibold tabular-nums">₱{{ number_format($summary['outstanding'], 2) }}</span> outstanding
                                    · <span class="inline-flex items-center gap-1.5 font-semibold {{ $paymentStatusStyle['text'] }}"><span class="w-1.5 h-1.5 rounded-full {{ $paymentStatusStyle['dot'] }}"></span>{{ $paymentStatusStyle['label'] }}</span>
                                </p>
                            </div>
                            <a href="{{ route('landlord.payments.ledger', $reservation) }}"
                                class="h-10 px-4 shrink-0 inline-flex items-center justify-center rounded-xl border border-[#060D26]/25 bg-white text-[#060D26] text-sm font-semibold hover:border-[#060D26] hover:bg-[#F7F8FC] transition-colors duration-200">
                                Open ledger
                            </a>
                        </div>
                    </x-card>
                @endif

                @include('landlord.tenancies._tenant-details')
            </div>

            {{-- ── Side column ────────────────────────────────── --}}
            <div class="space-y-5">

                {{-- Unit + rent terms (tenant facts live in the profile card above) --}}
                <x-card>
                    <p class="text-[12px] font-semibold text-[#5B6A8E] mb-4">Unit</p>

                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-14 h-14 rounded-xl bg-[#F7F8FC] overflow-hidden shrink-0 ring-1 ring-[#5B6A8E]/10">
                            @if($thumb)
                                <img loading="lazy" decoding="async" src="{{ $thumb->media_url }}" alt="{{ $unit->unit_label }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[14.5px] font-bold text-[#060D26] truncate">{{ $unit->unit_label ?? 'No unit' }}</p>
                            <p class="text-[13px] text-[#5B6A8E] truncate">{{ $reservation->property->title ?? '' }}</p>
                        </div>
                    </div>

                    <dl class="space-y-2.5 text-[13.5px]">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[#5B6A8E]">Monthly rent</dt>
                            <dd class="font-bold text-[#060D26] tabular-nums">&#8369;{{ number_format($summary['monthlyRent'], 2) }}</dd>
                        </div>
                        @if($isActive)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[#5B6A8E]">Payment status</dt>
                                <dd class="inline-flex items-center gap-1.5 font-semibold {{ $paymentStatusStyle['text'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $paymentStatusStyle['dot'] }}"></span>
                                    {{ $paymentStatusStyle['label'] }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-[#5B6A8E]">Next due</dt>
                                <dd class="font-semibold text-[#060D26]">
                                    {{ $summary['nextDueDate']?->format('M d, Y') ?? '—' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </x-card>

                {{-- End tenancy (the Contracts page's "End contract" links to #end-tenancy) --}}
                @if($isActive)
                    <x-card id="end-tenancy" class="scroll-mt-24">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] mb-2">End of tenancy</p>
                        <p class="text-[12.5px] text-[#5B6A8E] leading-relaxed mb-4">
                            Marks this tenancy complete and returns
                            <strong class="text-[#060D26]">{{ $unit->unit_label ?? 'the unit' }}</strong>
                            to your available units. The ledger stays readable but stops accepting new payments.
                        </p>

                        <form method="POST" action="{{ route('landlord.tenancies.end', $reservation) }}"
                            data-confirm="End this tenancy?"
                            data-confirm-message="The unit becomes available again and no further payments can be recorded against this ledger. This cannot be undone."
                            data-confirm-button="End tenancy"
                            data-confirm-type="warning">
                            @csrf
                            <label for="move_out_date" class="{{ $labelClass }}">Move-out date</label>
                            <div class="mb-3">
                                <x-date-picker name="move_out_date" id="move_out_date" placeholder="Move-out date"
                                    value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" />
                            </div>

                            <button type="submit"
                                class="w-full h-11 rounded-xl border border-[#EF4444]/30 text-[#DC2626] text-sm font-semibold hover:bg-[#EF4444]/[0.06] transition-all duration-200 cursor-pointer">
                                End tenancy
                            </button>
                        </form>
                    </x-card>
                @elseif($reservation->rental_status === 'Completed')
                    <x-card class="bg-[#F7F8FC]">
                        <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">Tenancy closed</p>
                        <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed">
                            @if($reservation->target_move_out_date)
                                This tenancy ended on <strong class="text-[#060D26]">{{ $reservation->target_move_out_date->format('M d, Y') }}</strong>.
                            @else
                                This tenancy has ended. No move-out date was recorded.
                            @endif
                            The ledger is kept as a record and no longer accepts payments.
                        </p>
                    </x-card>
                @elseif($reservation->rental_status === 'Reserved')
                    <x-card class="bg-[#F7F8FC]">
                        <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">Not moved in yet</p>
                        <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed mb-4">
                            This tenancy is <strong class="text-[#060D26]">Reserved</strong>
                            @if($reservation->target_move_in_date)
                                for {{ $reservation->target_move_in_date->format('M d, Y') }}.
                            @else
                                . No move-in date was recorded.
                            @endif
                            Confirm once the tenant has actually moved in.
                        </p>

                        <form method="POST" action="{{ route('landlord.reservations.confirmWalkInMoveIn', $reservation) }}"
                            data-confirm="Confirm move-in?"
                            data-confirm-type="confirm"
                            data-confirm-message="The unit will be marked Occupied."
                            data-confirm-button="Confirm move-in"
                            data-confirm-cancel="Not yet">
                            @csrf
                            <button type="submit"
                                class="w-full h-11 rounded-xl bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                                Confirm move-in
                            </button>
                        </form>
                    </x-card>
                @else
                    <x-card class="bg-[#F7F8FC]">
                        <p class="text-[12px] font-semibold text-[#5B6A8E] mb-2">Not moved in yet</p>
                        <p class="text-[13.5px] text-[#5B6A8E] leading-relaxed">
                            This tenancy is <strong class="text-[#060D26]">{{ $reservation->rental_status }}</strong>. Payments can be recorded once the tenant has moved in.
                        </p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>

    <script src="{{ asset('js/date-picker.js') }}"></script>
@endsection
