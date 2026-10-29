@extends('layouts.landlord')

@section('content')
    @php
        $docIcon = 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z';
        $peso = fn ($n) => '₱'.number_format((float) $n, 2);
        $templateById = $templates->keyBy('lease_template_id');
        $templateOptions = $templates->pluck('name', 'lease_template_id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
        // The few facts that matter about a policy, as small chips.
        $chipsFor = function ($t) use ($peso) {
            $chips = [];
            $chips[] = match ($t->late_fee_type) {
                'none' => 'No late fee',
                'fixed' => $peso($t->late_fee_value).' late fee',
                'percent' => rtrim(rtrim(number_format($t->late_fee_value, 2), '0'), '.').'% late fee',
                default => null,
            };
            $chips[] = ($t->notice_days ?? config('rentals.lease_notice_days')).' days notice';
            $ruleCount = collect(\App\Support\HouseRules::visible($t->house_rule_sections))->sum(fn ($s) => count($s['rules']));
            $chips[] = $ruleCount ? $ruleCount.' house '.\Illuminate\Support\Str::plural('rule', $ruleCount) : null;
            $chips[] = $t->required_documents ? count($t->required_documents).' '.\Illuminate\Support\Str::plural('document', count($t->required_documents)) : null;

            return array_values(array_filter($chips));
        };
        $selectClass = 'h-10 w-full pl-3.5 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13px] text-[#060D26]';
    @endphp

    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-16">
        <x-page-header title="Policies" subtitle="The rules and wording your tenants agree to, per property and unit." :icon-path="$docIcon">
            <x-slot:actions>
                <a href="{{ route('landlord.lease-templates.create') }}"
                    class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-[#FF8A66] px-4 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F]">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    New policy
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="mb-5 flex w-full gap-1 rounded-xl bg-[#ECEEF6] p-1 sm:inline-flex sm:w-auto" role="tablist">
            @foreach(['property' => 'By property', 'sent' => 'Sent & signed'] as $key => $tabLabel)
                <a href="{{ route('landlord.leases.index', ['tab' => $key]) }}" role="tab" @if($tab === $key) aria-selected="true" @endif
                    class="flex-1 whitespace-nowrap rounded-lg px-5 py-2 text-center text-[13px] font-semibold transition-colors sm:flex-none {{ $tab === $key ? 'bg-white text-[#060D26] shadow-sm' : 'text-[#5B6A8E] hover:text-[#060D26]' }}">{{ $tabLabel }}</a>
            @endforeach
        </div>

        @if($tab === 'property')
            @if($properties->isEmpty())
                <x-card><p class="text-[13px] text-[#5B6A8E]">Add a property first, then choose its policy here.</p></x-card>
            @else
                @php
                    $propertyPolicy = $templateById->get($selected->lease_template_id);
                    $propertyOptions = $properties->mapWithKeys(fn ($p) => [(string) $p->property_id => $p->title])->all();
                @endphp
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
                    {{-- The property, its policy, and its units --}}
                    <div class="flex min-w-0 flex-col gap-4">
                        <form method="GET" action="{{ route('landlord.leases.index') }}">
                            <p class="mb-1.5 text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Property</p>
                            <label class="sr-only" for="policy-property">Property</label>
                            <x-styled-select name="property" id="policy-property" autosubmit :options="$propertyOptions" :selected="(string) $selected->property_id"
                                class="h-11 w-full pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[14px] font-semibold text-[#060D26]" />
                        </form>

                        <x-card class="max-sm:!p-4">
                            <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">This property uses</p>
                            <form method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="mt-1.5">
                                @csrf @method('PATCH')
                                <input type="hidden" name="target" value="property">
                                <input type="hidden" name="id" value="{{ $selected->property_id }}">
                                <label class="sr-only" for="policy-for-property">Policy for {{ $selected->title }}</label>
                                <x-styled-select name="lease_template_id" id="policy-for-property" autosubmit
                                    :options="['' => 'Standard policy'] + $templateOptions" :selected="(string) ($selected->lease_template_id ?? '')" class="{{ $selectClass }} !h-11 !text-[14px]" />
                            </form>
                            @if($propertyPolicy)
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach($chipsFor($propertyPolicy) as $chip)
                                        <span class="rounded-full bg-[#ECEEF6] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#060D26]">{{ $chip }}</span>
                                    @endforeach
                                </div>
                                <div class="mt-4 flex items-center gap-2 border-t border-[#E2E4EC] pt-3.5">
                                    <a href="{{ route('landlord.lease-templates.edit', $propertyPolicy) }}" class="inline-flex h-9 items-center rounded-lg bg-[#060D26] px-3.5 text-[12px] font-bold text-white hover:opacity-90">Edit policy</a>
                                    <span class="text-[12px] text-[#5B6A8E]">v{{ $propertyPolicy->version }}</span>
                                </div>
                            @else
                                <p class="mt-3 text-[12.5px] leading-relaxed text-[#5B6A8E]">The standard policy: the built-in wording, with this property's own rent and house rules. <a href="{{ route('landlord.lease-templates.create') }}" class="font-semibold text-[#060D26] underline underline-offset-2">Write your own policy</a></p>
                            @endif
                        </x-card>

                        {{-- Units: follow the property, or use their own --}}
                        <div>
                            <p class="mb-1.5 text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Units</p>
                            <div class="overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
                                @forelse($selected->units as $unit)
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-[#E2E4EC] px-4 py-3 last:border-b-0" x-data="{ own: @js($unit->lease_template_id !== null) }">
                                        <p class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-[#060D26]">{{ $unit->unit_label }}</p>
                                        <span x-show="!own" class="text-[12px] text-[#5B6A8E]">Same as property</span>
                                        <form x-show="own" x-cloak method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="w-full sm:w-60 order-last sm:order-none">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="target" value="unit">
                                            <input type="hidden" name="id" value="{{ $unit->unit_id }}">
                                            <label class="sr-only" for="policy-unit-{{ $unit->unit_id }}">Policy for {{ $unit->unit_label }}</label>
                                            <x-styled-select name="lease_template_id" id="policy-unit-{{ $unit->unit_id }}" autosubmit placeholder="Choose a policy"
                                                :options="$templateOptions" :selected="(string) ($unit->lease_template_id ?? '')" class="{{ $selectClass }}" />
                                        </form>
                                        <form x-ref="clear" method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="hidden">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="target" value="unit">
                                            <input type="hidden" name="id" value="{{ $unit->unit_id }}">
                                            <input type="hidden" name="lease_template_id" value="">
                                        </form>
                                        {{-- On = this unit has its own policy. Turning it off sends it back to the property's. --}}
                                        <button type="button" role="switch" :aria-checked="own" aria-label="{{ $unit->unit_label }} uses its own policy"
                                            @click="own = !own; if (!own) $refs.clear.submit()"
                                            :class="own ? 'bg-[#FF8A66]' : 'bg-[#E2E4EC]'" class="relative h-5 w-9 shrink-0 rounded-full transition-colors">
                                            <span :class="own ? 'translate-x-4' : 'translate-x-0.5'" class="absolute left-0 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"></span>
                                        </button>
                                    </div>
                                @empty
                                    <p class="px-4 py-3 text-[12.5px] text-[#5B6A8E]">This property has no units yet.</p>
                                @endforelse
                            </div>
                            <p class="mt-1.5 text-[12px] text-[#5B6A8E]">Turn a unit on to give it a policy of its own.</p>
                        </div>
                    </div>

                    {{-- All policies --}}
                    <x-card class="max-sm:!p-4">
                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Your policies</p>
                        <ul class="mt-2 divide-y divide-[#E2E4EC]">
                            @foreach($templates as $template)
                                @php $uses = $template->properties_count + $template->units_count; @endphp
                                <li>
                                    <a href="{{ route('landlord.lease-templates.edit', $template) }}" class="flex items-center gap-2 py-2.5 hover:text-[#B35A3D]">
                                        <span class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-[#060D26]">{{ $template->name }}</span>
                                        <span class="shrink-0 text-[11.5px] text-[#5B6A8E]">{{ $uses ? $uses.' in use' : 'Not used' }}</span>
                                    </a>
                                </li>
                            @endforeach
                            <li class="py-2.5 text-[12.5px] text-[#5B6A8E]">Standard policy &middot; used when nothing is set</li>
                        </ul>
                        <a href="{{ route('landlord.lease-templates.create') }}" class="mt-3 inline-flex text-[12.5px] font-bold text-[#060D26] underline underline-offset-2">+ New policy</a>
                    </x-card>
                </div>
            @endif
        @else
            {{-- Sent & signed: the frozen copy of every agreement issued, whatever policy it came from. --}}
            <form method="GET" action="{{ route('landlord.leases.index') }}" class="mb-5">
                <input type="hidden" name="tab" value="sent">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative flex-1 min-w-[200px]">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" /></svg>
                        <label for="sent-search" class="sr-only">Search by tenant name</label>
                        <input id="sent-search" type="text" name="search" value="{{ request('search') }}" placeholder="Search by tenant name..."
                            x-on:input.debounce.400ms="$el.form.requestSubmit()"
                            class="w-full h-10 pl-10 pr-4 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[#060D26] placeholder-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] focus:bg-white">
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <x-styled-select name="property" autosubmit :options="['' => 'All Properties'] + $properties->pluck('title', 'property_id')->all()" :selected="(string) request('property', '')"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26] max-w-[220px]" />
                        <x-styled-select name="status" autosubmit :options="['' => 'All Status'] + collect(\App\Http\Controllers\Landlord\LeaseTemplateController::SENT_STATUSES)->map(fn ($s) => $s[0])->all()" :selected="(string) request('status', '')"
                            class="h-11 pl-4 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-[#F7F8FC] text-[13.5px] text-[#060D26]" />
                        @if(request()->hasAny(['search', 'property', 'status']))
                            <a href="{{ route('landlord.leases.index', ['tab' => 'sent']) }}" class="h-11 px-4 rounded-xl border border-[#5B6A8E]/25 text-[13.5px] text-[#5B6A8E] hover:text-[#060D26] hover:bg-[#ECEEF6] inline-flex items-center">Clear</a>
                        @endif
                    </div>
                </div>
            </form>

            @if($sent->isEmpty())
                <x-card><p class="text-[13px] text-[#5B6A8E]">{{ request()->hasAny(['search', 'property', 'status']) ? 'Nothing matches these filters.' : 'No agreements sent yet. When you send one from a chat, it appears here.' }}</p></x-card>
            @else
                <div class="overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
                    <div class="hidden grid-cols-[1.3fr_1.5fr_1.2fr_1fr_1fr_auto] gap-4 border-b border-[#E2E4EC] px-5 py-3 text-[11px] font-bold uppercase tracking-wider text-[#5B6A8E] lg:grid">
                        <span>Tenant</span><span>Property · unit</span><span>Policy</span><span>Status</span><span>Sent / signed</span><span class="text-right">Lease</span>
                    </div>
                    <ul class="divide-y divide-[#E2E4EC]">
                        @foreach($sent as $row)
                            @php
                                $snap = $row->lease_snapshot;
                                [$statusLabel, $statusClass] = \App\Http\Controllers\Landlord\LeaseTemplateController::SENT_STATUSES[\App\Http\Controllers\Landlord\LeaseTemplateController::sentStatusOf($row)];
                                $sentOn = isset($snap['issued_at']) ? \Illuminate\Support\Carbon::parse($snap['issued_at'])->format('M j, Y') : '—';
                                $tpl = $snap['template'] ?? null;
                            @endphp
                            <li class="grid grid-cols-1 gap-1.5 px-4 py-3.5 sm:px-5 lg:grid-cols-[1.3fr_1.5fr_1.2fr_1fr_1fr_auto] lg:items-center lg:gap-4">
                                <div class="min-w-0">
                                    <p class="truncate text-[13.5px] font-semibold text-[#060D26]">{{ trim(($row->tenant?->first_name ?? '').' '.($row->tenant?->last_name ?? '')) ?: 'Tenant' }}
                                        @if($row->tenant?->is_walk_in)<span class="ml-1 rounded-full bg-[#FBBF24]/[0.18] px-1.5 py-0.5 text-[10px] font-bold text-[#8A5A00]">Walk-in</span>@endif</p>
                                    <p class="text-[11.5px] text-[#5B6A8E]">{{ $snap['reference'] ?? '' }}</p>
                                </div>
                                <p class="min-w-0 truncate text-[13px] text-[#060D26]">{{ $row->property?->title }}@if($row->unit) <span class="text-[#5B6A8E]">· {{ $row->unit->unit_label }}</span>@endif</p>
                                <p class="text-[12.5px] text-[#060D26]">{{ $tpl ? $tpl['name'].' · v'.($tpl['version'] ?? 1) : 'Standard policy' }}</p>
                                <div><span class="inline-flex h-6 items-center rounded-full px-2.5 text-[11px] font-bold {{ $statusClass }}">{{ $statusLabel }}</span></div>
                                <p class="text-[12px] text-[#5B6A8E]">Sent {{ $sentOn }}@if($row->agreed_at)<br>Signed {{ $row->agreed_at->format('M j, Y') }}@endif
                                    @if($row->documents->isNotEmpty())<br>Docs {{ $row->documents->reject->isOutstanding()->count() }}/{{ $row->documents->count() }}@endif</p>
                                <div class="lg:text-right">
                                    <a href="{{ route('landlord.leases.show', $row) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[12px] font-bold text-[#060D26] hover:bg-[#F7F8FC]">View lease</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="mt-5">{{ $sent->links() }}</div>
            @endif
        @endif
    </div>
@endsection
