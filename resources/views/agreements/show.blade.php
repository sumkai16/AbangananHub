@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    @php
        $landlord = $reservation->property->landlord;
        $tenant = $reservation->tenant;
        // Frozen when the landlord sent the agreement, so what the tenant
        // signed can't change when the property is edited later.
        $terms = \App\Support\LeaseTerms::for($reservation);
        $agreementRef = $terms['reference'];
        $heldPayment = $reservation->payments->where('status', 'Held')->first();
        // A checkout the tenant walked away from (Payment::isStalePending) is not a payment: counting it hid Pay Now
        // for good, and the tenant had no way to try again.
        $hasPayment = $reservation->payments->whereIn('status', ['Pending', 'Held', 'Paid', 'Released'])
            ->reject(fn ($p) => $p->isStalePending())->isNotEmpty();
        $hadAbandonedCheckout = ! $hasPayment && $reservation->payments->contains(fn ($p) => $p->isStalePending());
        $processingPayment = $hasPayment && !$heldPayment && !$reservation->isOccupied();
        // Direct payments: money paid to the landlord and confirmed (or recorded by them) sets move_in_paid_at.
        // $online = this landlord can be paid online too, so the tenant chooses; false = direct is the only way.
        $online = \App\Support\Payments::onlineFor($reservation);
        $directPaid = $reservation->move_in_paid_at !== null;
        // Where the tenant is, for the progress bar: reviewing and signing share the first two steps.
        $stage = $reservation->isOccupied() ? 4 : (($directPaid || $hasPayment) ? 3 : ($reservation->isAgreementSigned() ? 2 : 1));
        $stepLabels = ['Review lease', 'Sign', 'Pay', 'Move in'];
        $startDate = $terms['start'] ? \Illuminate\Support\Carbon::parse($terms['start']) : null;
        $endDate = $terms['end'] ? \Illuminate\Support\Carbon::parse($terms['end']) : null;
        $lengthMonths = $startDate && $endDate ? max(1, (int) round($startDate->floatDiffInMonths($endDate))) : null;
        $premises = trim(($terms['unit'] ? $terms['unit'].' · ' : '').($terms['property']['title'] ?? ''), ' ·');
        $depositText = $terms['deposit'] !== null && $terms['deposit'] > 0 ? '₱'.number_format($terms['deposit'], 2).' security deposit' : 'No security deposit';
        $ordinal = fn (int $n) => $n.(in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
    @endphp

    {{-- 1200 = 768 document + 400 action rail + gap. The document keeps the
         max-w-3xl reading measure it has always had; the rail fills what used
         to be dead margin, and carries the signing controls so they are on
         screen from the moment the page loads instead of below the fold. --}}
    <x-print-document>
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 py-8 min-h-[calc(100vh-72px)] print:max-w-none print:mx-0 print:p-0 print:min-h-0">

        {{-- Page chrome — never printed --}}
        <div class="flex items-center justify-between gap-3 mb-6 print:hidden">
            <a href="{{ $reservation->conversation_id ? route('conversations.index', ['active' => $reservation->conversation_id]) : route('reservations.index') }}"
                class="inline-flex items-center text-sm font-semibold text-[#5B6A8E] hover:text-[#060D26] transition-colors group">
                <svg class="w-4 h-4 mr-2 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                {{ $reservation->conversation_id ? 'Back to chat' : 'Back to Reservations' }}
            </a>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-[#E2E4EC] bg-white text-[12px] font-bold text-[#060D26] hover:bg-[#F7F8FC] cursor-pointer transition-all duration-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                </svg>
                Print / Save PDF
            </button>
        </div>

        {{-- Page header: the property, the reference and where the tenant is in the four steps. Never printed: the
             document carries its own cover page. --}}
        <x-card class="mb-6 p-5 sm:p-7 print:hidden">
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-[28px] font-bold text-[#060D26] tracking-tight">Lease Agreement</h1>
                    <p class="mt-1 text-[14px] text-[#5B6A8E]">{{ $premises }}@if($terms['property']['address'] ?? null) · {{ $terms['property']['address'] }}@endif</p>
                </div>
                <p class="text-[12px] font-bold text-[#5B6A8E] tracking-wider tabular-nums border border-[#E2E4EC] rounded-full px-3 py-1">{{ $agreementRef }}</p>
            </div>
            <ol class="mt-6 flex items-center" aria-label="Progress">
                @foreach($stepLabels as $i => $label)
                    @php $done = $i + 1 < $stage; $now = $i + 1 === $stage; @endphp
                    <li class="flex items-center gap-2.5 min-w-0 {{ $loop->last ? '' : 'flex-1' }}" @if($now) aria-current="step" @endif>
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-[12px] font-bold
                            {{ $done ? 'border-[#16A34A] bg-[#16A34A] text-white' : ($now ? 'border-[#FF8A66] bg-[#FF8A66] text-[#060D26]' : 'border-[#E2E4EC] bg-white text-[#5B6A8E]') }}">
                            @if($done)
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </span>
                        <span class="text-[14px] font-semibold whitespace-nowrap {{ $done || $now ? 'text-[#060D26]' : 'text-[#5B6A8E]' }} {{ $now ? '' : 'hidden sm:inline' }}">{{ $label }}</span>
                        @unless($loop->last)
                            <span class="mx-1 h-0.5 flex-1 rounded {{ $done ? 'bg-[#16A34A]' : 'bg-[#E2E4EC]' }}" aria-hidden="true"></span>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </x-card>

        {{-- print:block — on paper there is no rail, so the document must not
             be squeezed into a grid column that no longer has a sibling. --}}
        <div class="grid lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-8 lg:items-start print:block">

        <div class="space-y-6 min-w-0">

        {{-- ===== Key terms: the eight facts, grouped by meaning (money, dates, place, people) with space and one
             hairline instead of boxes. Screen only: the printed document has its own summary table. ===== --}}
        <x-card class="p-5 sm:p-8 print:hidden">
            <h2 class="mb-6 text-[19px] font-bold tracking-tight text-[#060D26]">Key terms</h2>
            <div class="space-y-8">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="text-[32px] font-extrabold leading-none tracking-tight text-[#060D26] tabular-nums">&#8369;{{ number_format($terms['monthly_rent'], 2) }}<span class="ml-1 text-[16px] font-medium tracking-normal text-[#5B6A8E]">/ month</span></p>
                    <p class="text-[15px] text-[#5B6A8E]">Due every {{ $ordinal((int) $terms['due_day']) }} of the month · {{ $depositText }}</p>
                </div>

                @if($startDate && $endDate)
                    <div class="grid grid-cols-[auto_1fr_auto] items-center gap-3 sm:gap-4">
                        <div><p class="text-[14px] text-[#5B6A8E]">Starts</p><p class="text-[17px] font-bold text-[#060D26]">{{ $startDate->format('F j, Y') }}</p></div>
                        <div class="flex items-center gap-3 text-[14px] font-semibold text-[#060D26]"><span class="h-0.5 flex-1 rounded bg-[#E2E4EC]"></span><span class="whitespace-nowrap">{{ $lengthMonths }} {{ \Illuminate\Support\Str::plural('month', $lengthMonths) }}</span><span class="h-0.5 flex-1 rounded bg-[#E2E4EC]"></span></div>
                        <div class="text-right"><p class="text-[14px] text-[#5B6A8E]">Ends</p><p class="text-[17px] font-bold text-[#060D26]">{{ $endDate->format('F j, Y') }}</p></div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-[14px] text-[#5B6A8E]">Starts</p><p class="text-[17px] font-bold text-[#060D26]">{{ $startDate ? $startDate->format('F j, Y') : 'On your move-in date' }}</p></div>
                        <div class="text-right"><p class="text-[14px] text-[#5B6A8E]">Ends</p><p class="text-[17px] font-bold text-[#060D26]">{{ $endDate ? $endDate->format('F j, Y') : 'Month-to-month' }}</p></div>
                    </div>
                @endif

                <div class="space-y-1">
                    <p class="text-[17px] font-bold text-[#060D26]">{{ $premises }}</p>
                    <p class="text-[14px] text-[#5B6A8E]">{{ $terms['property']['address'] ?? '' }}@if($terms['occupants']) · {{ $terms['occupants'] }} {{ \Illuminate\Support\Str::plural('person', $terms['occupants']) }}@endif</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-5 border-t border-[#E2E4EC] pt-6 sm:grid-cols-2 sm:gap-8">
                @foreach(['Landlord' => $terms['landlord'], 'Tenant' => $terms['tenant']] as $role => $person)
                    <div class="min-w-0">
                        <p class="text-[14px] text-[#5B6A8E]">{{ $role }}</p>
                        <p class="text-[17px] font-bold text-[#060D26]">{{ $person['name'] ?: '—' }}</p>
                        @if($person['email'])<p class="text-[14px] text-[#5B6A8E] break-words">{{ $person['email'] }}</p>@endif
                        @if($person['phone'])<p class="text-[14px] text-[#5B6A8E]">{{ $person['phone'] }}</p>@endif
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- flush: the card's default p-5 sm:p-6 would collide with the wider
             padding this document wants, and with print:p-0. --}}
        <x-card flush class="p-5 sm:p-8 print:border-none print:shadow-none print:p-0" x-data="{ all: false }">
            {{-- ===== The lease — frozen terms, see App\Support\LeaseTerms ===== --}}
            <div class="mb-3 flex items-center justify-between gap-3 print:hidden">
                <h2 class="text-[19px] font-bold tracking-tight text-[#060D26]">The lease</h2>
                {{-- Clauses are collapsible rows when the lease uses the standard wording; a landlord's own text is one block. --}}
                @if(empty($terms['body']))
                    <button type="button" class="inline-flex min-h-[40px] items-center rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]"
                        @click="all = ! all; $root.querySelectorAll('details[data-clause]').forEach(d => d.open = all)"
                        :aria-pressed="all" x-text="all ? 'Collapse all' : 'Expand all'">Expand all</button>
                @endif
            </div>
            @include('leases._document', ['terms' => $terms, 'screenSummary' => true])
        </x-card>

        {{-- ===== Signature block — the evidentiary record ===== --}}
        @if($reservation->agreed_at || $reservation->landlord_tc_accepted_at)
            @php $tick = '<svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>'; @endphp
            <x-card class="p-5 sm:p-8 print:border-none print:shadow-none print:p-0">
                <h2 class="mb-5 text-[19px] font-bold tracking-tight text-[#060D26]">Signatures</h2>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-8">
                    <div>
                        <p class="text-[14px] text-[#5B6A8E]">Landlord</p>
                        <p class="text-[17px] font-bold text-[#060D26]">{{ $landlord->first_name }} {{ $landlord->last_name }}</p>
                        @if($reservation->landlord_tc_accepted_at)
                            <p class="mt-1 flex items-center gap-1.5 text-[14px] font-semibold text-[#15803D]">{!! $tick !!} Accepted {{ $reservation->landlord_tc_accepted_at->format('F j, Y \a\t g:i A') }}</p>
                        @else
                            <p class="mt-1 text-[14px] text-[#5B6A8E]">Awaiting acceptance</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[14px] text-[#5B6A8E]">Tenant</p>
                        <p class="text-[17px] font-bold text-[#060D26]">{{ $tenant->first_name }} {{ $tenant->last_name }}</p>
                        @if($reservation->agreed_at)
                            <p class="mt-1 flex items-center gap-1.5 text-[14px] font-semibold text-[#15803D]">{!! $tick !!} Signed {{ $reservation->agreed_at->format('F j, Y \a\t g:i A') }}</p>
                            @if($reservation->agreed_ip)
                                <p class="mt-0.5 text-[13px] text-[#5B6A8E]">Recorded from {{ $reservation->agreed_ip }}</p>
                            @endif
                        @else
                            <p class="mt-1 text-[14px] text-[#5B6A8E]">Not yet signed</p>
                        @endif
                    </div>
                </div>
            </x-card>
        @endif

        </div>

        {{-- ===== Action rail — never printed ===== --}}
        <x-sticky-rail class="mt-6 lg:mt-0">

            {{-- What you are committing to, restated so the figures are in view while the controls are. The full
                 terms are in the Key terms card; this keeps only the money beside the buttons. --}}
            <div class="rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5 mb-4">
                <p class="text-[14px] font-medium text-[#5B6A8E]">Monthly rent</p>
                <p class="mt-1 text-[30px] font-extrabold leading-none tracking-tight text-[#060D26] tabular-nums">&#8369;{{ number_format($terms['monthly_rent'], 2) }}<span class="ml-1 text-[15px] font-medium tracking-normal text-[#5B6A8E]">/ month</span></p>
                <p class="mt-2 text-[14px] text-[#5B6A8E]">Due every {{ $ordinal((int) $terms['due_day']) }} · {{ $depositText }}</p>
            </div>

            <div>
                @include('agreements._required-documents', ['reservation' => $reservation])
                @if($reservation->rental_status === 'Pending Rental Agreement')
                    <form action="{{ route('agreements.sign', $reservation) }}" method="POST"
                        class="rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5">
                        @csrf

                        <div class="flex items-start gap-3 p-4 bg-[#EF4444]/10 border border-[#EF4444]/25 rounded-xl mb-5">
                            <svg class="w-5 h-5 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                            </svg>
                            <p class="text-[12px] text-[#EF4444] leading-relaxed">
                                Please read the entire agreement above before proceeding. Signing is a binding acknowledgment
                                of the terms stated.
                            </p>
                        </div>

                        <label for="agree" class="flex items-start gap-3 mb-4 cursor-pointer">
                            <input type="checkbox" name="agree" id="agree" required
                                class="mt-0.5 w-4 h-4 rounded border-[#5B6A8E]/40 text-[#060D26] focus:ring-[#FF8A66] focus:ring-offset-0 transition">
                            <span class="text-[13px] text-[#060D26] leading-relaxed">
                                I have read and agree to the terms of this Rental Agreement.
                                <x-payment-consent role="tenant" />
                            </span>
                        </label>
                        @error('agree')
                            <p class="text-[11px] font-semibold text-[#EF4444] -mt-2 mb-4 ml-7">{{ $message }}</p>
                        @enderror

                        {{-- The landlord keeps an emergency contact and your ID on file. Asked once; later agreements reuse it. --}}
                        @php
                            $signer = auth()->user()->load('tenantDetail');
                        @endphp
                        @if (\App\Models\TenantDetail::completeFor($signer))
                            <div class="mb-5 rounded-xl bg-[#ECEEF6] px-4 py-3">
                                <p class="text-[12px] font-semibold text-[#060D26]">Emergency contact on file</p>
                                <p class="text-[12px] text-[#5B6A8E] mt-0.5">
                                    {{ $signer->tenantDetail->emergency_contact_name }}
                                    @if ($signer->tenantDetail->emergency_contact_relationship) ({{ $signer->tenantDetail->emergency_contact_relationship }}) @endif
                                    &middot; <a href="{{ route('profile.edit') }}#emergency-contact" class="font-semibold text-[#060D26] underline underline-offset-2">Edit in Account Settings</a>
                                </p>
                            </div>
                        @else
                            <fieldset class="mb-5 rounded-xl border border-[#E2E4EC] p-4">
                                <legend class="px-1.5 text-[12px] font-bold text-[#060D26]">Emergency contact &amp; ID</legend>
                                <p class="text-[12px] text-[#5B6A8E] leading-relaxed mb-4">
                                    Your landlord keeps these on file in case of an emergency. Only the landlord of this property can see them, and your ID number is stored encrypted.
                                </p>
                                @php $missing = \App\Models\TenantDetail::missingFor($signer->tenantDetail); @endphp
                                @if ($signer->tenantDetail && count($missing) < 4)
                                    <p class="mb-4 rounded-lg bg-[#FBBF24]/[0.12] px-3 py-2 text-[12px] font-medium text-[#B45309]">
                                        Your saved details are filled in below. Still needed: {{ implode(', ', $missing) }}.
                                    </p>
                                @endif
                                <x-tenant-details-fields :detail="$signer->tenantDetail" id-prefix="sign" :required="true" />
                            </fieldset>
                        @endif

                        @php $stillNeeded = \App\Services\TenancyDocuments::missing($reservation); @endphp
                        <button type="submit" @disabled($stillNeeded->isNotEmpty())
                            class="w-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-bold text-sm py-3 rounded-xl shadow-sm cursor-pointer transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-[#FF8A66]">
                            Sign Agreement
                        </button>
                        @if($stillNeeded->isNotEmpty())
                            <p class="mt-2 text-center text-[12px] font-semibold text-[#8A5A00]">Upload {{ $stillNeeded->count() }} more {{ \Illuminate\Support\Str::plural('document', $stillNeeded->count()) }} above to sign.</p>
                        @endif
                    </form>

                @elseif($reservation->isAgreementSigned())
                    <div class="p-4 bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-xl flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#22C55E]/15 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#15803D]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#060D26]">Agreement Signed</p>
                            <p class="text-xs text-[#5B6A8E] mt-0.5">
                                Signed{{ $reservation->agreed_at ? ' on '.$reservation->agreed_at->format('F j, Y \a\t g:i A') : '' }}.
                            </p>
                        </div>
                    </div>

                    @if($directPaid && !$heldPayment)
                        <div class="mt-4 rounded-xl border border-[#22C55E]/25 bg-[#22C55E]/[0.07] p-4">
                            <p class="text-sm font-bold text-[#060D26]">Payment confirmed by {{ $landlord->first_name }}</p>
                            <p class="mt-0.5 text-xs text-[#5B6A8E]">Your move-in payment was received directly by your landlord. Once you have the keys and the unit is as listed, confirm your move-in.</p>
                            <form action="{{ route('agreements.confirmDirectMoveIn', $reservation) }}" method="POST" class="mt-3"
                                data-confirm="Confirm you have moved in?" data-confirm-message="This starts your tenancy and your rent schedule." data-confirm-button="I've moved in">
                                @csrf
                                <button type="submit" class="w-full rounded-xl bg-[#FF8A66] py-2.5 text-sm font-bold text-[#060D26] shadow-sm transition-all duration-200 hover:bg-[#E96F4F]">I've moved in</button>
                            </form>
                        </div>

                    @elseif(!$hasPayment)
                        <div class="mt-4">
                            @if($hadAbandonedCheckout)
                                <p class="mb-3 rounded-xl border border-[#FBBF24]/35 bg-[#FBBF24]/10 px-4 py-3 text-[13px] text-[#92600A]">
                                    Your last payment attempt wasn't completed. You can try again below.
                                </p>
                            @endif
                            <x-payment-route-choice :reservation="$reservation" type="MoveIn" />
                        </div>

                    @elseif($heldPayment)
                        <div class="mt-4 rounded-xl border border-[#FF8A66]/25 bg-[#ECEEF6]/60 p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-[#FF8A66]/15 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 text-[#060D26]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-[#060D26]">Payment received &mdash; held by AbangananHub</p>
                                    <p class="text-xs text-[#5B6A8E] mt-0.5">
                                        Your payment of &#8369;{{ number_format($reservation->heldTotal(), 2) }} is held until you confirm move-in.
                                    </p>
                                </div>
                            </div>

                            {{-- What the one online payment covered: the rent and deposit are held; the fee is not held for anyone. --}}
                            @php $heldRows = $reservation->heldPayments(); $paidFee = (float) $heldRows->sum('service_fee'); @endphp
                            @if($heldRows->count() > 1 || $paidFee > 0)
                                <dl class="mb-4 space-y-1 rounded-lg bg-white/70 px-3 py-2.5 text-[12.5px]">
                                    @foreach($heldRows as $row)
                                        <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">{{ $row->payment_type === 'Deposit' ? 'Security deposit' : 'First month’s rent' }}</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($row->amount, 2) }}</dd></div>
                                    @endforeach
                                    @if($paidFee > 0)
                                        <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Online payment fee</dt><dd class="font-semibold tabular-nums text-[#060D26]">&#8369;{{ number_format($paidFee, 2) }}</dd></div>
                                    @endif
                                </dl>
                            @endif

                            @php
                                $daysLeft = $reservation->daysUntilMoveInDeadline();
                            @endphp

                            @if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->move_in_disputed_at)
                                @if ($reservation->isTurnoverClock())
                                    {{-- Clock 1: nothing for the tenant to do yet. No countdown — showing one
                                         here would imply a deadline the tenant can miss, and this one is the
                                         landlord's. --}}
                                    <div class="rounded-lg border border-[#FF8A66]/25 bg-[#ECEEF6] p-4 mb-4">
                                        <p class="text-sm font-medium text-[#060D26]">Payment secured</p>
                                        <p class="mt-1 text-sm text-[#060D26]">
                                            Your deposit is held safely and is not released until you confirm your move-in.
                                            Your landlord will contact you to turn over the keys.
                                        </p>
                                    </div>
                                @elseif ($daysLeft !== null)
                                    {{-- Clock 2: live countdown. --}}
                                    <div class="rounded-lg border p-4 mb-4 {{ $daysLeft <= 1 ? 'border-[#EF4444]/25 bg-[#EF4444]/[0.07]' : 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10]' }}">
                                        <p class="text-sm font-semibold {{ $daysLeft <= 1 ? 'text-[#DC2626]' : 'text-[#B45309]' }}">
                                            @if ($daysLeft < 0)
                                                Your move-in confirmation window has passed
                                            @elseif ($daysLeft === 0)
                                                Today is your last day to confirm your move-in
                                            @else
                                                {{ $daysLeft }} {{ Str::plural('day', $daysLeft) }} left to confirm your move-in
                                            @endif
                                        </p>
                                        <p class="mt-1 text-sm {{ $daysLeft <= 1 ? 'text-[#DC2626]' : 'text-[#B45309]' }}">
                                            @if ($daysLeft < 0)
                                                Your deadline of {{ $reservation->move_in_deadline_at->format('M j, Y') }} has passed. Your deposit has not been released yet, but it will be released to the landlord automatically. You can still confirm your move-in now &mdash; if something isn't right, use "I haven't received the keys" below.
                                            @else
                                                Confirming releases your deposit to the landlord. If you don't confirm by
                                                {{ $reservation->move_in_deadline_at->format('M j, Y') }}, it is released automatically.
                                            @endif
                                        </p>
                                    </div>
                                @endif
                            @endif

                            @unless ($reservation->move_in_disputed_at)
                                <form action="{{ route('agreements.confirmMoveIn', $reservation) }}" method="POST"
                                    data-confirm="Confirm you have moved in?"
                                    data-confirm-type="warning"
                                    data-confirm-message="This releases your payment to the landlord and cannot be undone. Only confirm if you have physically moved in and verified the unit matches the listing."
                                    data-confirm-button="Yes, I have moved in">
                                    @csrf
                                    <div class="flex items-start gap-3 p-3 bg-[#EF4444]/10 border border-[#EF4444]/25 rounded-xl mb-4">
                                        <svg class="w-4 h-4 text-[#EF4444] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 3.75h.008v.008h-.008v-.008z" />
                                        </svg>
                                        <p class="text-[12px] text-[#EF4444] leading-relaxed">
                                            Only confirm after you have physically moved into the unit and verified it matches the listing. Once confirmed, the payment will be released to the landlord.
                                        </p>
                                    </div>
                                    <button type="submit"
                                        class="w-full bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-bold text-sm py-3 rounded-xl shadow-sm cursor-pointer transition-all duration-200">
                                        I Have Moved In — Confirm Occupancy
                                    </button>
                                </form>
                            @endunless

                            @if ($reservation->rental_status === 'Rental Agreement Signed' && ! $reservation->move_in_disputed_at)
                                <div x-data="{ show: false }" class="mt-3 text-center">
                                    <button type="button" @click="show = true"
                                            class="text-xs text-[#5B6A8E] underline hover:text-[#060D26] cursor-pointer">
                                        I haven't received the keys
                                    </button>

                                    <template x-teleport="body">
                                        <div x-show="show" x-cloak
                                             class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40 backdrop-blur-sm"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-end="opacity-0">
                                            <div @click.outside="show = false"
                                                 class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl mx-4"
                                                 x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                                                 x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                                                 x-transition:leave="transition ease-in duration-200"
                                                 x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0">
                                                <h3 class="text-lg font-normal text-[#060D26] text-left">Report a move-in issue</h3>
                                                <p class="mt-2 text-sm text-[#5B6A8E] text-left">
                                                    Your deposit stays on hold and an administrator will review this. Your landlord will be notified.
                                                </p>

                                                <form action="{{ route('agreements.disputeMoveIn', $reservation) }}" method="POST" class="mt-4 text-left">
                                                    @csrf
                                                    <textarea name="reason" rows="4" required minlength="10"
                                                              class="w-full rounded-xl border-[#E2E4EC] text-sm"
                                                              placeholder="Tell us what happened — for example, the landlord hasn't turned over the keys."></textarea>
                                                    @error('reason')
                                                        <p class="mt-1 text-sm text-[#EF4444]">{{ $message }}</p>
                                                    @enderror

                                                    <div class="mt-4 flex justify-end gap-2">
                                                        <button type="button" @click="show = false"
                                                                class="rounded-xl px-4 py-2 text-sm text-[#5B6A8E] cursor-pointer">Cancel</button>
                                                        <button type="submit"
                                                                class="rounded-xl bg-[#FF8A66] hover:bg-[#E96F4F] px-4 py-2 text-sm font-bold text-[#060D26] cursor-pointer transition-all duration-200">
                                                            Submit report
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            @elseif ($reservation->move_in_disputed_at)
                                <div class="mt-3 rounded-xl border border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] p-4">
                                    <p class="text-sm font-bold text-[#B45309]">Move-in issue under review</p>
                                    <p class="mt-1 text-xs text-[#B45309]">
                                        Reported {{ $reservation->move_in_disputed_at->diffForHumans() }}. Your deposit is on hold
                                        and the countdown is paused while an administrator reviews this.
                                    </p>
                                </div>
                            @endif
                        </div>

                    @else
                        <div class="mt-4">
                            @include('payments.partials._checkout-processing', ['reservation' => $reservation, 'type' => 'Initial'])
                        </div>
                    @endif

                @elseif($reservation->isOccupied())
                    <div class="p-4 bg-[#22C55E]/[0.07] border border-[#22C55E]/25 rounded-xl flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#22C55E]/15 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-[#15803D]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#060D26]">You're all moved in</p>
                            <p class="text-xs text-[#5B6A8E] mt-0.5">
                                Move-in confirmed on {{ $reservation->tenant_confirmed_move_in_at?->format('F j, Y \a\t g:i A') ?? 'N/A' }}.
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </x-sticky-rail>

        </div>{{-- /document + rail --}}

        <p class="text-[10.5px] text-[#5B6A8E] text-center mt-4 hidden print:block">
            {{ $agreementRef }} &middot; Generated {{ now()->format('F j, Y \a\t g:i A') }} &middot; AbangananHub
        </p>
    </div>
    </x-print-document>

    @if($processingPayment)
        @push('scripts')
            <script>
                // The webhook lands out-of-band, so without this the tenant sits on
                // the spinner forever. A full reload is deliberate: this page has
                // five mutually exclusive server-rendered states and re-rendering
                // one of them in JS would duplicate the Blade.
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
