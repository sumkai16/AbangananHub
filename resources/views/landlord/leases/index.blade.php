@extends('layouts.landlord')

@section('content')
    @php
        $docIcon = 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z';
        $templateById = $templates->keyBy('lease_template_id');
        $templateOptions = $templates->pluck('name', 'lease_template_id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
        $selectClass = 'h-10 w-full pl-3.5 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13px] text-[#060D26]';
    @endphp

    <div class="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-16">
        @include('landlord.leases.partials._header', ['docIcon' => $docIcon])
        <div data-swap-region="tab">
            @include('landlord.leases.partials._tabs', ['active' => 'policies'])

        @if($properties->isEmpty())
            <x-card><p class="text-[13px] text-[#5B6A8E]">Add a property first, then choose its policy here.</p></x-card>
        @else
            @php
                $propertyPolicy = $templateById->get($selected->lease_template_id);
                $propertyOptions = $properties->mapWithKeys(fn ($p) => [(string) $p->property_id => $p->title])->all();
            @endphp
            <div class="grid grid-cols-1 gap-10 xl:grid-cols-[minmax(0,1fr)_340px] xl:items-start">

                {{-- Your policies: the library. Scrolls sideways on a phone; each card opens the editor. --}}
                <section aria-labelledby="library-title" class="xl:col-start-2 xl:row-start-1 xl:sticky xl:top-6">
                    <h2 id="library-title" class="text-[16px] font-semibold text-[#060D26]">Your policies</h2>
                    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">The wording and rules a tenant agrees to. Anything without a policy uses the standard one.</p>

                    <ul class="mt-4 -mx-4 sm:-mx-6 lg:mx-0 flex gap-3 overflow-x-auto px-4 sm:px-6 lg:px-0 pb-2 snap-x snap-mandatory xl:flex-col xl:overflow-visible xl:pb-0 xl:snap-none">
                        <li class="snap-start shrink-0 w-[240px] xl:w-full">
                            <div class="h-full rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4">
                                <p class="text-[14px] font-semibold text-[#060D26]">Standard policy</p>
                                <p class="mt-1 text-[12.5px] leading-snug text-[#5B6A8E]">Built in. Used wherever nothing else is set.</p>
                            </div>
                        </li>
                        @foreach($templates as $template)
                            @php $uses = $template->properties_count + $template->units_count; @endphp
                            <li class="snap-start shrink-0 w-[240px] xl:w-full">
                                <a href="{{ route('landlord.lease-templates.edit', $template) }}" data-page-swap="nav"
                                    class="group flex h-full flex-col rounded-2xl border border-[#E2E4EC] bg-white p-4 transition-[border-color,box-shadow] duration-200 hover:border-[#5B6A8E]/40 hover:shadow-[0_4px_16px_rgba(6,13,38,0.07)] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="min-w-0 truncate text-[14px] font-semibold text-[#060D26]">{{ $template->name }}</span>
                                        <span class="shrink-0 text-[12px] tabular-nums {{ $uses ? 'font-semibold text-[#15803D]' : 'text-[#5B6A8E]' }}">{{ $uses ? $uses.' in use' : 'Not used' }}</span>
                                    </span>
                                    <span class="mt-2.5 flex flex-wrap gap-1.5">
                                        @foreach(array_slice($template->summaryChips(), 0, 3) as $chip)
                                            <span class="rounded-full bg-[#ECEEF6] px-2 py-0.5 text-[11.5px] font-medium text-[#060D26]">{{ $chip }}</span>
                                        @endforeach
                                    </span>
                                    <span class="mt-3 text-[12.5px] font-semibold text-[#B35A3D] group-hover:underline underline-offset-2">Edit policy</span>
                                </a>
                            </li>
                        @endforeach
                        <li class="snap-start shrink-0 w-[200px] xl:w-full">
                            <a href="{{ route('landlord.lease-templates.create') }}" data-page-swap="nav" class="flex h-full min-h-[72px] flex-col items-center justify-center gap-1.5 rounded-2xl border border-dashed border-[#5B6A8E]/40 p-4 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#ECEEF6] focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                {{ $templates->isEmpty() ? 'Write your first policy' : 'New policy' }}
                            </a>
                        </li>
                    </ul>
                </section>

                {{-- Where each policy is used: properties on the left (a dropdown on a phone), the chosen one on the right. --}}
                <section aria-labelledby="assign-title" class="min-w-0 xl:col-start-1 xl:row-start-1">
                    <h2 id="assign-title" class="text-[16px] font-semibold text-[#060D26]">Where they apply</h2>
                    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">Pick a property, choose its policy, then give any unit a different one if it needs it.</p>

                    <div data-swap-region="assign" class="mt-4 grid grid-cols-1 gap-5 lg:grid-cols-[380px_minmax(0,1fr)] lg:items-start">

                        {{-- Phone and tablet: the dropdown --}}
                        <form method="GET" action="{{ route('landlord.leases.index') }}" class="lg:hidden">
                            <label class="mb-1.5 block text-[13px] font-semibold text-[#060D26]" for="policy-property">Property</label>
                            <x-styled-select name="property" id="policy-property" autosubmit :options="$propertyOptions" :selected="(string) $selected->property_id"
                                class="h-11 w-full pl-4 pr-9 rounded-xl border border-[#E2E4EC] bg-white text-[14px] font-semibold text-[#060D26]" />
                        </form>

                        {{-- Desktop: every property at a glance, with the policy it follows --}}
                        <nav class="hidden lg:block lg:sticky lg:top-6 max-h-[70vh] overflow-y-auto rounded-2xl border border-[#E2E4EC] bg-white p-1.5" aria-label="Properties">
                            <ul class="grid gap-0.5">
                                @foreach($properties as $p)
                                    @php
                                        $isSelected = $p->property_id === $selected->property_id;
                                        $ownUnits = $p->units->whereNotNull('lease_template_id')->count();
                                    @endphp
                                    <li>
                                        <a href="{{ route('landlord.leases.index', ['property' => $p->property_id]) }}" data-page-swap="nav" data-page-swap data-swap-only="assign" @if($isSelected) aria-current="true" @endif
                                            class="block rounded-xl px-3.5 py-2.5 transition-colors duration-150 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#FF8A66]/30 {{ $isSelected ? 'bg-[#ECEEF6]' : 'hover:bg-[#F7F8FC]' }}">
                                            <span class="block text-[13.5px] leading-snug text-[#060D26] {{ $isSelected ? 'font-semibold' : 'font-medium' }}">{{ $p->title }}</span>
                                            <span class="mt-0.5 block truncate text-[12px] text-[#5B6A8E]">
                                                {{ $templateById->get($p->lease_template_id)?->name ?? 'Standard policy' }}@if($ownUnits) · {{ $ownUnits }} {{ \Illuminate\Support\Str::plural('unit', $ownUnits) }} different @endif
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>

                        {{-- The chosen property: a header bar (who and which policy), then the units as a table --}}
                        <div class="min-w-0 overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white">
                            <div class="flex flex-col gap-5 p-4 sm:p-6 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0">
                                    <h3 class="text-[18px] font-semibold leading-snug text-[#060D26]">{{ $selected->title }}</h3>
                                    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">{{ $selected->units->count() }} {{ \Illuminate\Support\Str::plural('unit', $selected->units->count()) }}</p>

                                    @if($propertyPolicy)
                                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                            @foreach($propertyPolicy->summaryChips() as $chip)
                                                <span class="rounded-full bg-[#ECEEF6] px-2.5 py-0.5 text-[12px] font-medium text-[#060D26]">{{ $chip }}</span>
                                            @endforeach
                                            <a href="{{ route('landlord.lease-templates.edit', $propertyPolicy) }}" data-page-swap="nav" class="ml-1 inline-flex h-9 items-center text-[13px] font-semibold text-[#B35A3D] underline-offset-2 hover:underline">Edit this policy</a>
                                        </div>
                                    @else
                                        <p class="mt-3 max-w-md text-[13px] leading-relaxed text-[#5B6A8E]">The built-in wording, filled in with this property's rent and house rules.</p>
                                    @endif
                                </div>

                                <form method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="w-full xl:w-80 xl:shrink-0">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="target" value="property">
                                    <input type="hidden" name="id" value="{{ $selected->property_id }}">
                                    <label class="mb-1.5 block text-[13px] font-semibold text-[#060D26]" for="policy-for-property">Policy for the whole property</label>
                                    <x-styled-select name="lease_template_id" id="policy-for-property" autosubmit
                                        :options="['' => 'Standard policy'] + $templateOptions" :selected="(string) ($selected->lease_template_id ?? '')" class="{{ $selectClass }} !h-11 !text-[14px]" />
                                </form>
                            </div>

                            {{-- Units table: Unit | Policy | Status. Header row from sm up; on a phone each row stacks. --}}
                            <div class="border-t border-[#E2E4EC]">
                                <div class="hidden grid-cols-[minmax(0,1fr)_280px] gap-4 bg-[#F7F8FC] px-6 py-2.5 text-[12px] font-semibold text-[#5B6A8E] sm:grid">
                                    <span>Unit</span>
                                    <span>Policy</span>
                                </div>
                                <div class="divide-y divide-[#E2E4EC]">
                                    @forelse($selected->units as $unit)
                                        <form method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_280px] sm:items-center sm:gap-4 sm:px-6">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="target" value="unit">
                                            <input type="hidden" name="id" value="{{ $unit->unit_id }}">
                                            <p class="min-w-0 truncate text-[14px] font-semibold text-[#060D26]">{{ $unit->unit_label }}</p>
                                            <label class="sr-only" for="policy-unit-{{ $unit->unit_id }}">Policy for {{ $unit->unit_label }}</label>
                                            <div>
                                                <x-styled-select name="lease_template_id" id="policy-unit-{{ $unit->unit_id }}" autosubmit
                                                    :options="['' => 'Same as property'] + $templateOptions" :selected="(string) ($unit->lease_template_id ?? '')" class="{{ $selectClass }} !h-10" />
                                            </div>
                                        </form>
                                    @empty
                                        <p class="px-4 py-4 text-[13px] text-[#5B6A8E] sm:px-6">This property has no units yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        @endif
        </div>
    </div>
@endsection
