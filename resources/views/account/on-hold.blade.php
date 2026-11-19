{{--
    Tenant page for an unpaid-rent hold. Mobile-first: one card per hold, full-width 44px buttons.
    Needs $holds (open TenantHolds with landlord, reservation.property, reservation.unit).
    A hold with no reservation is one an admin placed; it gets a neutral message and nothing that hints why.
--}}
@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('title', 'Account on hold')

@section('content')
    @php
        $peso = fn (float $amount) => '₱'.number_format($amount, fmod($amount, 1) ? 2 : 0);
        $inputClass = 'w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 py-3 text-[15px] text-[#060D26] outline-none transition focus:border-[#FF8A66] focus:bg-white focus:ring-2 focus:ring-[#FF8A66]/15';
    @endphp

    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto max-w-2xl px-4 sm:px-6 py-8 pb-14 min-h-[calc(100vh-72px)]">

        <x-page-header title="Your account is on hold" subtitle="You can still sign in, pay and message the landlord you owe.">
            <x-slot:icon>
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
            </x-slot:icon>
        </x-page-header>

        <p class="mb-5 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] p-3.5 text-[13.5px] leading-relaxed text-[#5B6A8E]">
            <span class="font-semibold text-[#060D26]">While this lasts</span> you cannot make new reservations, request viewings or start new chats.
            You can still pay your rent, message the landlord you owe, and see your notifications.
        </p>

        <div class="space-y-5">
            @foreach($holds as $hold)
                @php
                    $reservation = $hold->reservation;
                    $landlordName = trim(($hold->landlord->first_name ?? '').' '.($hold->landlord->last_name ?? '')) ?: 'your landlord';
                    $place = collect([$reservation?->unit?->unit_label, $reservation?->property?->title])->filter()->implode(' · ');
                    $isPending = $hold->status === \App\Models\TenantHold::PENDING;
                @endphp

                <section class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-6 shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
                    @if(! $reservation)
                        {{-- An admin's hold: say nothing about why. --}}
                        <h2 class="text-[16px] font-semibold text-[#060D26]">Your account is on hold while we review it.</h2>
                        <p class="mt-1.5 text-[14px] leading-relaxed text-[#5B6A8E]">Please contact AbangananHub support and we will help you sort it out.</p>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex h-6 items-center rounded-full border px-2.5 text-[12px] font-semibold {{ $isPending ? 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10] text-[#B45309]' : 'border-[#EF4444]/25 bg-[#EF4444]/[0.07] text-[#DC2626]' }}">
                                {{ $isPending ? 'Pending review' : 'On hold' }}
                            </span>
                            @if($place)<span class="text-[13px] text-[#5B6A8E]">{{ $place }}</span>@endif
                        </div>

                        <p class="mt-3 text-[28px] font-semibold leading-none tracking-tight text-[#060D26]">{{ $peso((float) $hold->amount_owed) }}</p>
                        <p class="mt-1 text-[13px] text-[#5B6A8E]">unpaid rent reported by {{ $landlordName }}</p>

                        <p class="mt-4 text-[14px] leading-relaxed text-[#060D26]">
                            {{ $isPending
                                ? 'An admin is reviewing this report. You can still pay now to clear it.'
                                : 'Your account is on hold until this rent is paid.' }}
                        </p>

                        <div class="mt-4 rounded-xl bg-[#F7F8FC] p-3.5">
                            <p class="text-[11px] font-bold uppercase tracking-widest text-[#5B6A8E]">What the landlord wrote</p>
                            <p class="mt-1 break-words text-[14px] leading-relaxed text-[#060D26]">{{ $hold->reason }}</p>
                        </div>

                        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                            <a href="{{ route('tenancy.show', $reservation) }}"
                                class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-[#FF8A66] px-5 text-[14px] font-semibold text-[#060D26] transition hover:bg-[#E96F4F] active:scale-[0.98] sm:w-auto">
                                Pay now
                            </a>
                            <a href="{{ route('tenancy.chat', $reservation) }}"
                                class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-[#E2E4EC] px-5 text-[14px] font-semibold text-[#060D26] transition hover:bg-[#F7F8FC] sm:w-auto">
                                Message your landlord
                            </a>
                        </div>
                    @endif

                    {{-- The tenant's side. Once sent it shows read-only; they can send a new note to replace it. --}}
                    <form method="POST" action="{{ route('account.on-hold.dispute', $hold) }}" class="mt-5 border-t border-[#E2E4EC] pt-4">
                        @csrf
                        @if($hold->tenant_note)
                            <p class="text-[11px] font-bold uppercase tracking-widest text-[#5B6A8E]">Your note</p>
                            <p class="mt-1 mb-3 break-words rounded-xl bg-[#F7F8FC] p-3.5 text-[14px] leading-relaxed text-[#060D26]">{{ $hold->tenant_note }}</p>
                        @endif
                        <label for="note-{{ $hold->hold_id }}" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                            {{ $hold->tenant_note ? 'Send a new note' : 'Think this is a mistake? Tell us.' }}
                        </label>
                        <textarea id="note-{{ $hold->hold_id }}" name="note" rows="3" maxlength="1000" required class="{{ $inputClass }}"
                            placeholder="Explain what happened. The AbangananHub team reads this."></textarea>
                        @if(old('note') !== null || $errors->has('note'))
                            @error('note')<span class="mt-1.5 block text-xs font-medium text-[#DC2626]" role="alert">{{ $message }}</span>@enderror
                        @endif
                        <button type="submit"
                            class="mt-3 inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-[#E2E4EC] px-5 text-[14px] font-semibold text-[#060D26] transition hover:bg-[#F7F8FC] sm:w-auto">
                            Send note
                        </button>
                    </form>
                </section>
            @endforeach
        </div>
    </div>
@endsection
