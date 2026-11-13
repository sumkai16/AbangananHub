{{--
    The body of the Rental policies modal (landlord.leases.panel), fetched when the modal opens and again after every
    save. Two columns from lg up: where each property and unit gets its policy, and the policy library. Below lg the
    two are tabs (`tab` lives on the modal shell, _modal.blade.php). Every form here is saved in place by the shell
    (its submit handler), so a dropdown or switch never reloads the page.
--}}
@php
    $templateById = $templates->keyBy('lease_template_id');
    $templateOptions = $templates->pluck('name', 'lease_template_id')->mapWithKeys(fn ($n, $id) => [(string) $id => $n])->all();
    $selectClass = 'h-10 w-full pl-3.5 pr-9 rounded-xl border border-[#5B6A8E]/25 bg-white text-[13px] text-[#060D26]';
    // A dropdown here saves as soon as it is picked: the shell's submit handler takes it from there.
    $save = 'this.$nextTick(() => this.$refs.hiddenInput.form.requestSubmit())';
    $chev = 'M19.5 8.25l-7.5 7.5-7.5-7.5';
@endphp

@if($properties->isEmpty())
    <div class="px-6 py-14 text-center">
        <p class="text-[14px] font-semibold text-[#060D26]">No properties yet</p>
        <p class="mt-1 text-[12.5px] text-[#5B6A8E]">Add a property first, then choose its policy here.</p>
    </div>
@else
    {{-- Below lg: the two columns become two tabs --}}
    <div class="lg:hidden sticky top-0 z-10 flex gap-1 border-b border-[#E2E4EC] bg-white px-5" role="tablist">
        <button type="button" role="tab" @click="tab = 'assign'" :aria-selected="tab === 'assign'"
            :class="tab === 'assign' ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E]'"
            class="-mb-px h-11 border-b-2 px-3 text-[13px] font-semibold transition-colors">Assign</button>
        <button type="button" role="tab" @click="tab = 'policies'" :aria-selected="tab === 'policies'"
            :class="tab === 'policies' ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E]'"
            class="-mb-px h-11 border-b-2 px-3 text-[13px] font-semibold transition-colors">
            Your policies <span class="ml-1 rounded-full bg-[#ECEEF6] px-1.5 py-0.5 text-[11px] font-bold tabular-nums text-[#060D26]">{{ $templates->count() }}</span>
        </button>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:divide-x lg:divide-[#E2E4EC]">
        {{-- Assign: one row per property --}}
        <section :class="tab === 'assign' ? 'block' : 'hidden lg:block'" class="p-5 sm:p-6" aria-label="Assign policies">
            <p class="mb-3 hidden text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E] lg:block">Assign · {{ $properties->count() }} {{ Str::plural('property', $properties->count()) }}</p>
            <div class="flex flex-col gap-3">
                @foreach($properties as $property)
                    @php
                        $propertyPolicy = $templateById->get($property->lease_template_id);
                        $ownCount = $property->units->whereNotNull('lease_template_id')->count();
                    @endphp
                    <details data-id="{{ $property->property_id }}" @if(in_array((int) $property->property_id, $open, true)) open @endif
                        class="group overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3.5 [&::-webkit-details-marker]:hidden">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13.5px] font-semibold leading-snug text-[#060D26]">{{ $property->title }}</p>
                                <p class="mt-0.5 text-[12px] text-[#5B6A8E]">
                                    {{ $property->units->count() }} {{ Str::plural('unit', $property->units->count()) }}
                                    @if($ownCount > 0)
                                        · <span class="font-semibold text-[#B45309]">{{ $ownCount }} {{ $ownCount === 1 ? 'uses its own policy' : 'use their own policy' }}</span>
                                    @endif
                                </p>
                                <span class="mt-1.5 inline-block rounded-full bg-[#ECEEF6] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#060D26] sm:hidden">{{ $propertyPolicy?->name ?? 'Standard policy' }}</span>
                            </div>
                            <span class="hidden shrink-0 rounded-full bg-[#ECEEF6] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#060D26] sm:inline-block">{{ $propertyPolicy?->name ?? 'Standard policy' }}</span>
                            <svg class="shrink-0 text-[#5B6A8E] transition-transform duration-200 group-open:rotate-180" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chev }}" /></svg>
                        </summary>

                        <div class="flex flex-col gap-4 border-t border-[#E2E4EC] px-4 pb-4 pt-4">
                            <div>
                                <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">This property uses</p>
                                <form method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="mt-1.5">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="target" value="property">
                                    <input type="hidden" name="id" value="{{ $property->property_id }}">
                                    <label class="sr-only" for="lp-prop-{{ $property->property_id }}">Policy for {{ $property->title }}</label>
                                    <x-styled-select name="lease_template_id" id="lp-prop-{{ $property->property_id }}" :on-select="$save"
                                        :options="['' => 'Standard policy'] + $templateOptions" :selected="(string) ($property->lease_template_id ?? '')" class="{{ $selectClass }}" />
                                </form>
                                @if($propertyPolicy)
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach($propertyPolicy->summaryChips() as $chip)
                                            <span class="rounded-full bg-[#ECEEF6] px-2.5 py-0.5 text-[11.5px] font-semibold text-[#060D26]">{{ $chip }}</span>
                                        @endforeach
                                    </div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <a href="{{ route('landlord.lease-templates.edit', $propertyPolicy) }}" class="inline-flex h-9 items-center rounded-lg bg-[#060D26] px-3.5 text-[12.5px] font-bold text-white transition-colors hover:bg-[#1a2342]">Edit policy</a>
                                        <span class="text-[12px] text-[#5B6A8E]">v{{ $propertyPolicy->version }}</span>
                                    </div>
                                @else
                                    <p class="mt-2.5 text-[12.5px] leading-relaxed text-[#5B6A8E]">The standard policy: the built-in wording, with this property's own rent and house rules filled in.</p>
                                @endif
                            </div>

                            {{-- Units: follow the property, or use their own --}}
                            <div>
                                <p class="mb-1.5 text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Units</p>
                                <div class="overflow-hidden rounded-xl border border-[#E2E4EC]">
                                    @forelse($property->units as $unit)
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-[#E2E4EC] px-4 py-3 last:border-b-0" x-data="{ own: @js($unit->lease_template_id !== null) }">
                                            <p class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-[#060D26]">{{ $unit->unit_label }}</p>
                                            <span x-show="!own" class="text-[12px] text-[#5B6A8E]">Same as property</span>
                                            <form x-show="own" x-cloak method="POST" action="{{ route('landlord.lease-templates.assign') }}" class="order-last w-full sm:order-none sm:w-52">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="target" value="unit">
                                                <input type="hidden" name="id" value="{{ $unit->unit_id }}">
                                                <label class="sr-only" for="lp-unit-{{ $unit->unit_id }}">Policy for {{ $unit->unit_label }}</label>
                                                <x-styled-select name="lease_template_id" id="lp-unit-{{ $unit->unit_id }}" :on-select="$save" placeholder="Choose a policy"
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
                                                @click="own = !own; if (!own) $refs.clear.requestSubmit()"
                                                :class="own ? 'bg-[#FF8A66]' : 'bg-[#E2E4EC]'" class="relative h-5 w-9 shrink-0 cursor-pointer rounded-full transition-colors">
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
                    </details>
                @endforeach
            </div>
        </section>

        {{-- The policy library --}}
        <aside :class="tab === 'policies' ? 'block' : 'hidden lg:block'" class="bg-[#F7F8FC]/70 p-5 sm:p-6" aria-label="Your policies">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Your policies · {{ $templates->count() }}</p>
            <ul class="mt-2 divide-y divide-[#E2E4EC]">
                @foreach($templates as $template)
                    @php $uses = $template->properties_count + $template->units_count; @endphp
                    <li class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13.5px] font-semibold text-[#060D26]">{{ $template->name }}</p>
                            <p class="text-[12px] text-[#5B6A8E]">{{ $uses ? $uses.' in use' : 'Not used' }} · v{{ $template->version }}</p>
                        </div>
                        <a href="{{ route('landlord.lease-templates.edit', $template) }}" class="inline-flex h-9 shrink-0 items-center rounded-lg border border-[#060D26]/35 bg-white px-3.5 text-[12.5px] font-bold text-[#060D26] transition-colors hover:bg-[#ECEEF6]">Edit</a>
                    </li>
                @endforeach
                <li class="py-3">
                    <p class="text-[13.5px] font-semibold text-[#060D26]">Standard policy</p>
                    <p class="text-[12px] text-[#5B6A8E]">Built in. Used when nothing is set.</p>
                </li>
            </ul>
            @if($templates->isEmpty())
                <p class="mt-1 text-[12.5px] leading-relaxed text-[#5B6A8E]">You have not written a policy yet. Start one to set your own late fee, notice and house rules.</p>
            @endif
        </aside>
    </div>
@endif
