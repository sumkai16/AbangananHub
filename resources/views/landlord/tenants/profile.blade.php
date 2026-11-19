@extends('layouts.landlord')

@section('page-title', 'Tenant')

@section('content')
@php
    $detail = $tenant->tenantDetail;
    $name = trim($tenant->first_name.' '.$tenant->last_name);
    $pill = [
        'Active' => 'bg-[#15803D] text-white',
        'Pending' => 'bg-[#FBBF24] text-[#060D26]',
        'Former' => 'bg-[#ECEEF6] text-[#5B6A8E]',
    ][$status];
    $leasePill = ['ok' => 'text-[#15803D]', 'warn' => 'text-[#92400E]', 'bad' => 'text-[#B91C1C]', 'muted' => 'text-[#5B6A8E]'];
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '—';
    $hasEmergency = (bool) $detail?->emergency_contact_name;
    $current = $reservations->first(fn ($r) => in_array($r->rental_status, \App\Models\LandlordTenant::ACTIVE_STATUSES, true));
    $since = ($row->created_at ?? $tenant->created_at)?->format('F Y');
    $subtitle = $row->code().($since ? ' · Your tenant since '.$since : '');
    $tabs = ['details' => 'Details', 'rentals' => 'Rentals ('.$reservations->count().')'];
    $rw = 'flex items-start justify-between gap-4 py-3 border-b border-[#E2E4EC] last:border-b-0';
    $dt = 'text-[13px] text-[#5B6A8E] shrink-0';
    $dd = 'text-[14px] font-semibold text-[#060D26] text-right min-w-0 break-words';
    $none = 'font-normal text-[#5B6A8E]';
    $link = 'underline-offset-2 hover:underline focus-visible:underline';
    $label = 'text-[12px] font-semibold text-[#5B6A8E]';
    $big = 'mt-2 text-[26px] font-extrabold leading-none tabular-nums text-[#060D26]';
    $sub = 'mt-2 text-[12px] text-[#5B6A8E]';
    $bannerBtn = 'inline-flex h-10 items-center gap-2 rounded-xl px-4 text-[13px] font-semibold transition duration-200 cursor-pointer';
@endphp

<div class="min-h-[calc(100vh-72px)] pb-10" x-data="{
        tab: @js(array_keys($tabs)).includes(location.hash.slice(1)) ? location.hash.slice(1) : 'details',
        go(t) { this.tab = t; history.replaceState(null, '', '#' + t); }
    }">
    <x-profile-banner :user="$tenant" :subtitle="$subtitle" avatar-shape="square" :container="auth()->user()->shellContainerClass(true)">
        <x-slot:badges>
            <span class="rounded-full bg-[#ECEEF6] px-2.5 py-1 text-[11.5px] font-semibold text-[#060D26]">Tenant</span>
            <span class="rounded-full px-2.5 py-1 text-[11.5px] font-semibold {{ $pill }}">{{ $status }}</span>
        </x-slot:badges>
        <x-slot:actions>
            @if($chatWith)
                <a href="{{ route('tenancy.chat', $chatWith) }}" class="{{ $bannerBtn }} bg-[#FF8A66] text-[#060D26] hover:bg-[#E96F4F]">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 11.25c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 11.25 3 6.694 7.03 3 12 3s9 3.694 9 8.25Z" /></svg>
                    Message
                </a>
            @endif
            @if($status !== 'Active')
                <a href="{{ route('landlord.contracts.index', ['new' => 1, 'tenant' => $row->landlord_tenant_id]) }}" class="{{ $bannerBtn }} bg-white text-[#060D26] hover:brightness-95">Add rental</a>
            @endif
            @if($canInvite)
                <form method="POST" action="{{ route('landlord.tenants.invite', $row) }}" class="contents">
                    @csrf
                    <button type="submit" class="{{ $bannerBtn }} bg-white/10 text-white hover:bg-white/20">Resend invite</button>
                </form>
            @endif
        </x-slot:actions>

        {{-- What this page is, and how this tenant signs in. --}}
        <div class="mt-4 flex items-start gap-2 rounded-xl bg-[#ECEEF6] px-3.5 py-2.5 text-[12.5px] text-[#060D26]">
            <svg class="mt-px h-4 w-4 shrink-0 text-[#5B6A8E]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
            <p>
                <span class="font-semibold">Only you can see this.</span>
                @if(! $tenant->is_walk_in)
                    {{ $tenant->first_name }} signs in with their own AbangananHub account.
                @elseif($tenant->email)
                    You added them. They haven't accepted the invite yet.
                @else
                    You added them. Add an email to invite them to AbangananHub.
                @endif
            </p>
        </div>

        {{-- Tabs --}}
        <div class="mt-5 border-b border-[#E2E4EC]">
            <div role="tablist" aria-label="Tenant sections" class="flex gap-6 overflow-x-auto">
                @foreach($tabs as $key => $tabLabel)
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                        :aria-selected="tab === '{{ $key }}'" @click="go('{{ $key }}')"
                        :class="tab === '{{ $key }}' ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]'"
                        class="whitespace-nowrap border-b-2 pb-3 min-h-11 text-[13.5px] font-bold transition-colors duration-200 cursor-pointer">{{ $tabLabel }}</button>
                @endforeach
            </div>
        </div>

        <div class="py-6">
            {{-- ── Details ── --}}
            <div id="panel-details" role="tabpanel" aria-labelledby="tab-details" x-show="tab === 'details'" x-cloak class="space-y-5">
                @unless($hasEmergency)
                    <div class="rounded-lg border border-[#F59E0B]/50 bg-[#FFF6E5] px-3.5 py-2.5 text-[13px] text-[#7A3E00]" role="note">
                        <span class="font-semibold">Emergency contact needed.</span> Please add one for {{ $tenant->first_name }}@if($canEdit). Use the pencil button on the Tenants list.@else.@endif
                    </div>
                @endunless

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-2xl bg-[#060D26] p-4 text-white">
                        <p class="text-[12px] font-semibold text-white/70">Status</p>
                        <p class="mt-2 text-[26px] font-extrabold leading-none">{{ $status }}</p>
                        <p class="mt-2 text-[12px] text-white/65">{{ $current ? ($current->unit->unit_label ?? 'No unit') : ($status === 'Former' ? 'No longer renting' : 'No rental yet') }}</p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                        <p class="{{ $label }}">Rentals with you</p>
                        <p class="{{ $big }}">{{ $reservations->count() }}</p>
                        <p class="{{ $sub }}">{{ $reservations->where('rental_status', 'Completed')->count() }} finished</p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                        <p class="{{ $label }}">Tenant no.</p>
                        <p class="{{ $big }}">{{ $row->code() }}</p>
                        <p class="{{ $sub }}">On your list</p>
                    </div>
                    <div class="rounded-2xl border border-[#E2E4EC] bg-white p-4">
                        <p class="{{ $label }}">Move-in</p>
                        <p class="mt-2 text-[20px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ $current ? $fmt($current->target_move_in_date) : '—' }}</p>
                        <p class="{{ $sub }}">{{ $current ? 'Current rental' : 'No current rental' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-2">
                    <x-card>
                        <h2 class="text-[15px] font-bold text-[#060D26]">Contact</h2>
                        <dl class="mt-1">
                            <div class="{{ $rw }}"><dt class="{{ $dt }}">Name</dt><dd class="{{ $dd }}">{{ $name }}</dd></div>
                            <div class="{{ $rw }}"><dt class="{{ $dt }}">Mobile</dt><dd class="{{ $dd }}">@if($tenant->contact_number)<a href="tel:{{ preg_replace('/[^\d+]/', '', $tenant->contact_number) }}" class="{{ $link }}">{{ $tenant->contact_number }}</a>@else<span class="{{ $none }}">Not added</span>@endif</dd></div>
                            <div class="{{ $rw }}"><dt class="{{ $dt }}">Email</dt><dd class="{{ $dd }} break-all">@if($tenant->email)<a href="mailto:{{ $tenant->email }}" class="{{ $link }}">{{ $tenant->email }}</a>@else<span class="{{ $none }}">Not added</span>@endif</dd></div>
                        </dl>
                    </x-card>
                    <x-card>
                        <h2 class="text-[15px] font-bold text-[#060D26]">Emergency contact and ID</h2>
                        <dl class="mt-1">
                            <div class="{{ $rw }}">
                                <dt class="{{ $dt }}">Emergency contact</dt>
                                <dd class="{{ $dd }}">
                                    @if($hasEmergency)
                                        {{ $detail->emergency_contact_name }}@if($detail->emergency_contact_relationship) <span class="{{ $none }}">({{ $detail->emergency_contact_relationship }})</span>@endif
                                        @if($detail->emergency_contact_number)<a href="tel:{{ preg_replace('/[^\d+]/', '', $detail->emergency_contact_number) }}" class="block {{ $none }} {{ $link }}">{{ $detail->emergency_contact_number }}</a>@endif
                                    @else
                                        <span class="{{ $none }}">Not added</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="{{ $rw }}"><dt class="{{ $dt }}">ID</dt><dd class="{{ $dd }}">@if($detail?->id_type){{ $detail->id_type }} <span class="{{ $none }} tabular-nums">{{ $detail->maskedIdNumber() }}</span>@else<span class="{{ $none }}">Not added</span>@endif</dd></div>
                        </dl>
                    </x-card>
                </div>
            </div>

            {{-- ── Rentals ── --}}
            <div id="panel-rentals" role="tabpanel" aria-labelledby="tab-rentals" x-show="tab === 'rentals'" x-cloak>
                <x-card>
                    <h2 class="text-[15px] font-bold text-[#060D26]">Rentals with you</h2>
                    @if($reservations->isEmpty())
                        <p class="mt-2 text-[13.5px] text-[#5B6A8E]">No rental yet. Add one when {{ $tenant->first_name }} is ready to move in.</p>
                    @else
                        <ul class="mt-2 divide-y divide-[#E2E4EC]">
                            @foreach($reservations as $reservation)
                                @php
                                    $isLease = in_array($reservation->rental_status, \App\Http\Controllers\Landlord\ContractController::CONTRACT_STATUSES, true);
                                    $cs = $isLease ? $reservation->contractStatus() : null;
                                @endphp
                                <li>
                                    {!! $isLease ? '<a href="'.e(route('landlord.contracts.index', ['open' => $reservation->reservation_id])).'"' : '<div' !!} class="block py-3 {{ $isLease ? 'cursor-pointer hover:bg-[#F7F8FC] -mx-2 px-2 rounded-lg transition-colors duration-200' : '' }}">
                                        <span class="flex items-start justify-between gap-3">
                                            <span class="min-w-0 text-[14px] font-semibold text-[#060D26]">{{ $reservation->property->title }}<span class="font-normal text-[#5B6A8E]"> · {{ $reservation->unit->unit_label ?? 'No unit' }}</span></span>
                                            <span class="shrink-0 text-[12.5px] font-semibold {{ $cs ? $leasePill[$cs['tone']] : 'text-[#5B6A8E]' }}">{{ $cs['label'] ?? $reservation->rental_status }}</span>
                                        </span>
                                        <span class="mt-0.5 block text-[12.5px] text-[#5B6A8E] tabular-nums">{{ $fmt($reservation->target_move_in_date) }} to {{ $fmt($reservation->target_move_out_date) }}</span>
                                    </{{ $isLease ? 'a' : 'div' }}>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        </div>
    </x-profile-banner>
</div>
@endsection
