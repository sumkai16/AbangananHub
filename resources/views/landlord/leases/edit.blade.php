@extends('layouts.landlord')

@section('content')
    @php
        $exists = $template->exists;
        $label = 'block text-[13px] font-semibold text-[#060D26] mb-1.5';
        $input = 'w-full h-11 px-3.5 rounded-xl border border-[#E2E4EC] bg-white text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66]';
        $hint = 'text-[12px] text-[#5B6A8E] mt-1';
        $error = 'text-[12px] text-[#DC2626] mt-1.5';

        // What each section currently holds, so a closed row can say it in one line.
        $storedDocs = collect(old('required_documents') !== null || old('custom_documents') !== null ? [] : ($template->required_documents ?? []));
        $assigned = $exists ? $properties->where('lease_template_id', $template->lease_template_id)->pluck('property_id')->all() : [];
        $state = [
            'welcome' => (string) old('welcome_message', $template->welcome_message),
            'grace' => (string) old('rent_grace_days', $template->rent_grace_days),
            'feeType' => (string) old('late_fee_type', $template->late_fee_type),
            'feeValue' => (string) old('late_fee_value', $template->late_fee_value),
            'notice' => (string) old('notice_days', $template->notice_days),
            'sections' => collect(old('house_rule_sections', $template->house_rule_sections ?? []))->map(fn ($sec) => $sec + ['hidden' => false, 'collapsed' => ! empty($sec['hidden'])])->values()->all(),
            'suggested' => \App\Support\HouseRules::SUGGESTED,
            'docs' => array_values(old('required_documents', $storedDocs->pluck('key')->filter(fn ($k) => $k !== 'other')->values()->all())),
            'custom' => array_values(old('custom_documents', $storedDocs->where('key', 'other')->pluck('label')->values()->all())),
            'ids' => array_map('intval', old('assigned_properties', $assigned)),
            'all' => $properties->pluck('property_id')->map(fn ($id) => (int) $id)->all(),
            'docLabels' => \App\Support\RequiredDocuments::TYPES,
            'defaultNotice' => (int) config('rentals.lease_notice_days'),
            'propertyId' => (string) ($properties->first()?->property_id ?? ''),
            'listedRules' => $properties->mapWithKeys(fn ($p) => [$p->property_id => array_values(array_filter($p->house_rules ?? []))])->all(),
        ];
        // Open on the section that has a validation error, so the message is not hidden in a closed row.
        $openOnError = match (true) {
            $errors->hasAny(['welcome_message']) => 'welcome',
            $errors->hasAny(['rent_grace_days', 'late_fee_type', 'late_fee_value', 'notice_days']) => 'rules',
            $errors->has('house_rule_sections') || $errors->has('house_rule_sections.*') => 'houserules',
            $errors->hasAny(['required_documents', 'required_documents.*', 'custom_documents', 'custom_documents.*']) => 'docs',
            $errors->hasAny(['assigned_properties', 'assigned_properties.*']) => 'applies',
            default => '',
        };
        $row = 'flex w-full items-center gap-3 px-4 py-3.5 text-left';
        $chev = 'text-[11px] text-[#5B6A8E]';
    @endphp

    <div class="max-w-[860px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-24"
        x-data="{
            ...@js($state),
            open: @js($openOnError),
            toggle(key) { this.open = this.open === key ? '' : key; },
            get welcomeSummary() { const w = this.welcome.trim(); return w ? (w.length > 60 ? w.slice(0, 60) + '…' : w) : 'None'; },
            get rulesSummary() {
                const parts = [];
                parts.push(this.grace !== '' ? this.grace + ' grace ' + (Number(this.grace) === 1 ? 'day' : 'days') : 'Property grace days');
                parts.push(this.feeType === 'none' ? 'No late fee' : this.feeType === 'fixed' ? '₱' + (this.feeValue || '—') + ' late fee' : this.feeType === 'percent' ? (this.feeValue || '—') + '% late fee' : 'Property late fee');
                parts.push((this.notice !== '' ? this.notice : this.defaultNotice) + ' days notice');
                return parts.join(' · ');
            },
            get houseRulesSummary() {
                const own = this.sections.filter(s => !s.hidden).map(s => (s.rules || []).filter(r => r.trim()).length).filter(n => n > 0);
                const hiddenCount = this.sections.filter(s => s.hidden).length;
                const listed = this.ids.reduce((n, id) => n + (this.listedRules[id] || []).length, 0);
                const bits = [];
                if (own.length) bits.push(own.length + (own.length === 1 ? ' section' : ' sections') + ', ' + own.reduce((a, b) => a + b, 0) + ' rules');
                if (hiddenCount) bits.push(hiddenCount + ' hidden');
                if (listed) bits.push('listing rules included');
                return bits.length ? bits.join(' · ') : 'Only the listing\'s rules';
            },
            addSection(title = '', rules = ['']) { this.sections.push({ title, rules: [...rules], hidden: false }); },
            addSuggested(title) { if (!this.sections.some(s => s.title === title)) this.addSection(title, this.suggested[title]); },
            get docsSummary() {
                const names = [...this.docs.map(k => this.docLabels[k]).filter(Boolean), ...this.custom.filter(c => c.trim())];
                return names.length ? names.join(', ') : 'None required';
            },
            get appliesSummary() { return this.all.length === 0 ? 'No properties yet' : (this.ids.length ? this.ids.length + (this.ids.length === 1 ? ' property' : ' properties') : 'No properties'); },
            previewOpen: false, previewBusy: false, previewHtml: '', previewError: null,
            async preview() {
                this.previewOpen = true;
                this.previewBusy = true;
                this.previewError = null;
                const data = new FormData(this.$refs.form);
                data.delete('_method');
                if (!data.get('name')) data.set('name', 'Preview');
                data.set('property_id', this.propertyId);
                try {
                    const res = await fetch(@js(route('landlord.lease-templates.preview')), {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: data,
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(Object.values(json.errors || {})[0]?.[0] || 'The preview could not load.');
                    this.previewHtml = json.html;
                } catch (e) {
                    this.previewError = e.message;
                }
                this.previewBusy = false;
            },
        }">

        <nav aria-label="Breadcrumb" class="mb-3 flex items-center gap-1.5 text-sm text-[#5B6A8E]">
            <a href="{{ route('landlord.leases.index') }}" class="hover:text-[#060D26]">Policies</a>
            <span aria-hidden="true">/</span>
            <span class="truncate font-medium text-[#060D26]">{{ $exists ? $template->name : 'New policy' }}</span>
        </nav>

        <x-page-header :title="$exists ? 'Edit policy' : 'New policy'" subtitle="Changes apply to agreements you send from now on. Agreements already sent don't change.">
            @if($exists)
                <x-slot:badge>
                    <span class="rounded-full bg-[#ECEEF6] px-2.5 py-0.5 text-[11px] font-bold text-[#060D26]">Version {{ $template->version }}</span>
                </x-slot:badge>
            @endif
        </x-page-header>

        @if($starter)
            {{-- Where a new policy starts: the standard wording with a clause for the property type, or a blank page --}}
            <div class="mb-5">
                <p class="mb-2 text-[13px] font-semibold text-[#060D26]">Start from</p>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    @foreach(\App\Support\LeaseBody::STARTERS as $key => $starterLabel)
                        <a href="{{ route('landlord.lease-templates.create', ['starter' => $key]) }}"
                            @if($starter === $key) aria-current="true" @endif
                            class="shrink-0 rounded-full border px-3.5 py-1.5 text-[12.5px] font-semibold transition-colors {{ $starter === $key ? 'border-[#060D26] bg-[#060D26] text-white' : 'border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#F7F8FC]' }}">{{ $starterLabel }}</a>
                    @endforeach
                </div>
                <p class="mt-1.5 text-[12px] text-[#5B6A8E]">Switching starts the wording over. Choose before you edit.</p>
            </div>
        @endif

        <form x-ref="form" method="POST" action="{{ $exists ? route('landlord.lease-templates.update', $template) : route('landlord.lease-templates.store') }}" class="flex flex-col gap-4">
            @csrf
            @if($exists) @method('PUT') @endif

            <div>
                <label for="lease-name" class="{{ $label }}">Policy name</label>
                <input id="lease-name" name="name" value="{{ old('name', $template->name) }}" required maxlength="100"
                    placeholder="e.g. Bedspace policy" class="{{ $input }}">
                <p class="{{ $hint }}">Only you see this name.</p>
                @error('name')<p class="{{ $error }}">{{ $message }}</p>@enderror
            </div>

            {{-- One row per part of the policy. A closed row says what it holds; open one at a time. --}}
            <div class="overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)] divide-y divide-[#E2E4EC]">

                {{-- Wording: opens the full-screen editor --}}
                <div class="px-4 py-3.5">
                    <x-lease-editor name="body" row row-title="Wording" button="Open editor"
                        :title="($exists ? $template->name : 'New policy').' · wording'"
                        :value="old('body', $template->body ?? '')"
                        :initial="\App\Support\LeaseBody::starter()"
                        :starter="\App\Support\LeaseBody::starter()"
                        use-template-label="Go back to the standard wording" />
                    @error('body')<p class="{{ $error }}">{{ $message }}</p>@enderror
                </div>

                {{-- Welcome message --}}
                <div>
                    <button type="button" @click="toggle('welcome')" :aria-expanded="open === 'welcome'" class="{{ $row }}">
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-semibold text-[#060D26]">Welcome message</span><span class="block truncate text-[12.5px] text-[#5B6A8E]" x-text="welcomeSummary"></span></span>
                        <span class="{{ $chev }}" x-text="open === 'welcome' ? '▴' : '▾'" aria-hidden="true"></span>
                    </button>
                    <div x-show="open === 'welcome'" x-cloak class="border-t border-[#E2E4EC] bg-[#FBFBFE] px-4 pb-4 pt-3">
                        <label for="lease-welcome" class="sr-only">Welcome message</label>
                        <textarea id="lease-welcome" name="welcome_message" x-model="welcome" rows="3" maxlength="1500"
                            placeholder="e.g. Welcome to our boarding house! Please read the rules below and message us anytime."
                            class="w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 py-3 text-[14px] leading-relaxed text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25"></textarea>
                        <p class="{{ $hint }}">Shown at the top of the lease, after the names of both parties.</p>
                        @error('welcome_message')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- House rules: the listing's rules always apply; the policy adds titled sections after them --}}
                <div>
                    <button type="button" @click="toggle('houserules')" :aria-expanded="open === 'houserules'" class="{{ $row }}">
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-semibold text-[#060D26]">House rules</span><span class="block truncate text-[12.5px] text-[#5B6A8E]" x-text="houseRulesSummary"></span></span>
                        <span class="{{ $chev }}" x-text="open === 'houserules' ? '▴' : '▾'" aria-hidden="true"></span>
                    </button>
                    <div x-show="open === 'houserules'" x-cloak class="border-t border-[#E2E4EC] bg-[#FBFBFE] px-4 pb-5 pt-4" x-data="{ listed: false }">
                        <p class="text-[12.5px] leading-relaxed text-[#5B6A8E]">
                            Each property's own listed rules always print first. Add your own below, grouped under headings.
                            <button type="button" x-show="ids.length" @click="listed = !listed" class="ml-1 font-semibold text-[#060D26] underline underline-offset-2" x-text="listed ? 'Hide listed rules' : 'See listed rules'"></button>
                        </p>

                        <div x-show="listed" x-cloak class="mt-3 flex flex-col gap-2">
                            @foreach($properties as $p)
                                @php $listed = array_values(array_filter($p->house_rules ?? [])); @endphp
                                <div x-show="ids.includes({{ $p->property_id }})" x-cloak class="rounded-xl bg-white px-3.5 py-2.5 ring-1 ring-[#E2E4EC]">
                                    <p class="text-[12.5px] font-semibold text-[#060D26]">{{ $p->title }}
                                        <a href="{{ route('properties.edit', $p) }}" class="ml-1 text-[11.5px] font-medium text-[#5B6A8E] underline underline-offset-2">edit</a></p>
                                    @if($listed)
                                        <ul class="mt-1 list-disc pl-5 text-[12.5px] leading-relaxed text-[#060D26]">@foreach($listed as $rule)<li>{{ $rule }}</li>@endforeach</ul>
                                    @else
                                        <p class="mt-0.5 text-[12px] text-[#5B6A8E]">No rules listed.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Jump list: one pill per section, so a long list is easy to move around --}}
                        <nav x-show="sections.length > 1" x-cloak class="mt-4 flex flex-wrap gap-1.5" aria-label="Rule sections">
                            <template x-for="(section, i) in sections" :key="'nav' + i">
                                <button type="button" @click="section.collapsed = false; $nextTick(() => document.getElementById('rules-section-' + i)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                                    :class="section.hidden ? 'text-[#5B6A8E]' : 'text-[#060D26]'"
                                    class="rounded-full bg-[#F1F3F9] px-3 py-1 text-[12px] font-semibold hover:bg-[#E7EAF3]" x-text="section.title || 'Untitled'"></button>
                            </template>
                        </nav>

                        <div class="mt-4 flex flex-col gap-3">
                            <template x-for="(section, i) in sections" :key="i">
                                <section :id="'rules-section-' + i" class="scroll-mt-24 overflow-hidden rounded-xl bg-white ring-1 ring-[#E2E4EC]"
                                    :class="section.hidden ? 'opacity-75' : ''">
                                    {{-- Header: title (editable), how many rules, fold / hide / delete --}}
                                    <div class="flex items-center gap-2 px-3 py-2.5">
                                        <button type="button" @click="section.collapsed = !section.collapsed" :aria-expanded="!section.collapsed" aria-label="Show or fold this section"
                                            class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-[11px] text-[#5B6A8E] hover:bg-[#F1F3F9]" x-text="section.collapsed ? '▸' : '▾'"></button>
                                        <label class="sr-only" :for="'rules-title-' + i">Section title</label>
                                        <input :id="'rules-title-' + i" :name="'house_rule_sections[' + i + '][title]'" x-model="section.title" maxlength="80" placeholder="Section title"
                                            class="min-w-0 flex-1 border-0 bg-transparent px-0 text-[15px] font-bold text-[#060D26] placeholder-[#5B6A8E]/60 focus:outline-none focus:ring-0">
                                        <span class="shrink-0 text-[12px] font-semibold text-[#5B6A8E]" x-text="section.rules.filter(r => r.trim()).length + ' rules' + (section.hidden ? ' · hidden' : '')"></span>
                                        <input type="hidden" :name="'house_rule_sections[' + i + '][hidden]'" :value="section.hidden ? 1 : 0">
                                        <button type="button" @click="section.hidden = !section.hidden" :aria-pressed="!!section.hidden" class="shrink-0 text-[12px] font-semibold text-[#5B6A8E] hover:text-[#060D26]"
                                            x-text="section.hidden ? 'Show' : 'Hide'" :aria-label="(section.hidden ? 'Show ' : 'Hide ') + (section.title || 'section') + ' on the lease'"></button>
                                        <button type="button" x-show="section.hidden" x-cloak @click="sections.splice(i, 1)" class="shrink-0 text-[12px] font-semibold text-[#5B6A8E] hover:text-[#DC2626]" :aria-label="'Delete ' + (section.title || 'section')">Delete</button>
                                    </div>
                                    {{-- Rules: numbered, one per row. Folded (not removed) so every field still posts. --}}
                                    <div x-show="!section.collapsed">
                                        <p x-show="section.hidden" x-cloak class="border-t border-[#E2E4EC] px-4 py-2 text-[12px] text-[#5B6A8E]">Kept here but left off the lease.</p>
                                        <ol class="flex flex-col">
                                            <template x-for="(rule, j) in section.rules" :key="j">
                                                <li class="group flex items-center gap-3 border-t border-[#E2E4EC] px-4">
                                                    <span class="w-5 shrink-0 text-right text-[12.5px] font-semibold tabular-nums text-[#5B6A8E]" x-text="j + 1" aria-hidden="true"></span>
                                                    <label class="sr-only" :for="'rule-' + i + '-' + j">Rule</label>
                                                    <input :id="'rule-' + i + '-' + j" :name="'house_rule_sections[' + i + '][rules][' + j + ']'" x-model="section.rules[j]" maxlength="200" placeholder="Write a rule"
                                                        class="h-10 min-w-0 flex-1 border-0 bg-transparent px-0 text-[14px] text-[#060D26] placeholder-[#5B6A8E]/60 focus:outline-none focus:ring-0">
                                                    <button type="button" @click="section.rules.splice(j, 1)" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[#A3ADC5] hover:bg-[#F1F3F9] hover:text-[#DC2626] sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100" aria-label="Remove rule">&times;</button>
                                                </li>
                                            </template>
                                        </ol>
                                        <div class="border-t border-[#E2E4EC] bg-[#FBFBFE] px-4 py-2.5">
                                            <button type="button" @click="section.rules.push('')" x-show="section.rules.length < {{ \App\Support\HouseRules::MAX_RULES }}" class="text-[12.5px] font-semibold text-[#060D26] underline underline-offset-2">Add a rule</button>
                                        </div>
                                    </div>
                                </section>
                            </template>
                        </div>

                        <div class="mt-6 border-t border-[#E2E4EC] pt-4">
                            <button type="button" @click="addSection()" x-show="sections.length < {{ \App\Support\HouseRules::MAX_SECTIONS }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3.5 text-[12.5px] font-bold text-[#060D26] hover:bg-[#F7F8FC]">+ New section</button>
                            <template x-if="Object.keys(suggested).some(t => !sections.some(s => s.title === t))">
                                <p class="mt-3 text-[12.5px] leading-7 text-[#5B6A8E]">
                                    Suggested:
                                    <template x-for="title in Object.keys(suggested).filter(t => !sections.some(s => s.title === t))" :key="title">
                                        <button type="button" @click="addSuggested(title)" class="mr-3 inline font-semibold text-[#060D26] underline decoration-[#E2E4EC] underline-offset-4 hover:decoration-[#FF8A66]" x-text="title"></button>
                                    </template>
                                </p>
                            </template>
                        </div>
                        @error('house_rule_sections')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        @error('house_rule_sections.*')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Rent rules --}}
                <div>
                    <button type="button" @click="toggle('rules')" :aria-expanded="open === 'rules'" class="{{ $row }}">
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-semibold text-[#060D26]">Rent rules</span><span class="block truncate text-[12.5px] text-[#5B6A8E]" x-text="rulesSummary"></span></span>
                        <span class="{{ $chev }}" x-text="open === 'rules' ? '▴' : '▾'" aria-hidden="true"></span>
                    </button>
                    <div x-show="open === 'rules'" x-cloak class="border-t border-[#E2E4EC] bg-[#FBFBFE] px-4 pb-4 pt-3">
                        <p class="mb-3 text-[12px] text-[#5B6A8E]">Leave a box empty to use the property's setting.</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="lease-grace" class="{{ $label }}">Grace days for rent</label>
                                <input id="lease-grace" type="number" name="rent_grace_days" x-model="grace" min="0" max="30" inputmode="numeric" placeholder="Use property setting" class="{{ $input }}">
                                @error('rent_grace_days')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="lease-notice" class="{{ $label }}">Notice to end the tenancy (days)</label>
                                <input id="lease-notice" type="number" name="notice_days" x-model="notice" min="1" max="180" inputmode="numeric" :placeholder="defaultNotice + ' (platform default)'" class="{{ $input }}">
                                @error('notice_days')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="lease-fee-type" class="{{ $label }}">Late fee</label>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <select id="lease-fee-type" name="late_fee_type" x-model="feeType" class="{{ $input }}">
                                        <option value="">Use property rule</option>
                                        <option value="none">No late fee</option>
                                        <option value="fixed">Fixed amount (₱)</option>
                                        <option value="percent">Percent of rent (%)</option>
                                    </select>
                                    <div x-show="feeType === 'fixed' || feeType === 'percent'" x-cloak>
                                        <label for="lease-fee-value" class="sr-only">Late fee amount</label>
                                        <input id="lease-fee-value" type="number" step="0.01" min="0.01" name="late_fee_value" x-model="feeValue" inputmode="decimal" :placeholder="feeType === 'percent' ? 'e.g. 5' : 'e.g. 200'" class="{{ $input }}">
                                    </div>
                                </div>
                                <p class="{{ $hint }}">Charged once per month after the grace days, never daily.</p>
                                @error('late_fee_value')<p class="{{ $error }}">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Documents --}}
                <div>
                    <button type="button" @click="toggle('docs')" :aria-expanded="open === 'docs'" class="{{ $row }}">
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-semibold text-[#060D26]">Documents tenants upload</span><span class="block truncate text-[12.5px] text-[#5B6A8E]" x-text="docsSummary"></span></span>
                        <span class="{{ $chev }}" x-text="open === 'docs' ? '▴' : '▾'" aria-hidden="true"></span>
                    </button>
                    <div x-show="open === 'docs'" x-cloak class="border-t border-[#E2E4EC] bg-[#FBFBFE] px-4 pb-4 pt-3">
                        <p class="mb-2 text-[12px] text-[#5B6A8E]">The tenant cannot sign until each one is uploaded. Only you and the tenant can see them, and they are deleted 30 days after the tenancy ends.</p>
                        <div class="flex flex-col gap-1">
                            @foreach(\App\Support\RequiredDocuments::TYPES as $key => $docLabel)
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg px-1.5 py-1.5 hover:bg-white">
                                    <input type="checkbox" name="required_documents[]" value="{{ $key }}" x-model="docs"
                                        class="mt-0.5 h-4 w-4 rounded border-[#5B6A8E]/40 text-[#060D26] focus:ring-[#FF8A66]">
                                    <span class="text-[13px] leading-snug text-[#060D26]">{{ $docLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <template x-for="(doc, i) in custom" :key="i">
                            <div class="mt-2 flex gap-2">
                                <label class="sr-only" :for="'lease-doc-' + i">Other document</label>
                                <input :id="'lease-doc-' + i" name="custom_documents[]" x-model="custom[i]" maxlength="100" placeholder="e.g. Letter from employer" class="{{ $input }}">
                                <button type="button" @click="custom.splice(i, 1)" class="h-11 shrink-0 rounded-xl border border-[#E2E4EC] px-3 text-[12px] font-semibold text-[#DC2626]" aria-label="Remove document">Remove</button>
                            </div>
                        </template>
                        <button type="button" @click="custom.push('')" x-show="custom.length < {{ \App\Support\RequiredDocuments::MAX_CUSTOM }}"
                            class="mt-2 text-[12.5px] font-bold text-[#060D26] underline underline-offset-2">Add another document</button>
                        @error('required_documents.*')<p class="{{ $error }}">{{ $message }}</p>@enderror
                        @error('custom_documents.*')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Applies to: ticked properties use this policy; unticking one sends it back to the standard policy --}}
                <div>
                    <input type="hidden" name="assign_present" value="1">
                    <button type="button" @click="toggle('applies')" :aria-expanded="open === 'applies'" class="{{ $row }}">
                        <span class="min-w-0 flex-1"><span class="block text-[14px] font-semibold text-[#060D26]">Applies to</span><span class="block truncate text-[12.5px] text-[#5B6A8E]" x-text="appliesSummary"></span></span>
                        <span class="{{ $chev }}" x-text="open === 'applies' ? '▴' : '▾'" aria-hidden="true"></span>
                    </button>
                    <div x-show="open === 'applies'" x-cloak class="border-t border-[#E2E4EC] bg-[#FBFBFE] px-4 pb-4 pt-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[12px] text-[#5B6A8E]">Units with their own policy keep it. Set units on the Policies page.</p>
                            @if($properties->isNotEmpty())
                                <div class="flex shrink-0 gap-3 text-[12px] font-semibold">
                                    <button type="button" @click="ids = [...all]" class="text-[#060D26] underline underline-offset-2">Select all</button>
                                    <button type="button" @click="ids = []" class="text-[#5B6A8E] underline underline-offset-2">Clear</button>
                                </div>
                            @endif
                        </div>
                        <div class="mt-2 flex max-h-64 flex-col gap-1 overflow-y-auto">
                            @forelse($properties as $p)
                                @php $other = $p->leaseTemplate && $p->lease_template_id !== $template->lease_template_id ? $p->leaseTemplate->name : null; @endphp
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg px-1.5 py-1.5 hover:bg-white">
                                    <input type="checkbox" name="assigned_properties[]" value="{{ $p->property_id }}" x-model.number="ids"
                                        class="mt-0.5 h-4 w-4 rounded border-[#5B6A8E]/40 text-[#060D26] focus:ring-[#FF8A66]">
                                    <span class="min-w-0 text-[13px] leading-snug text-[#060D26]">{{ $p->title }}
                                        <span class="block text-[11.5px] text-[#5B6A8E]">{{ $p->property_type }}@if($other) &middot; now on {{ $other }}@endif</span></span>
                                </label>
                            @empty
                                <p class="text-[12px] text-[#5B6A8E]">No properties yet.</p>
                            @endforelse
                        </div>
                        @error('assigned_properties.*')<p class="{{ $error }}">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Save bar --}}
            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-between gap-2 border-t border-[#E2E4EC] bg-[#F7F8FC]/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
                @if($properties->isNotEmpty())
                    <button type="button" @click="preview()" class="inline-flex h-10 items-center rounded-xl px-3 text-[13px] font-bold text-[#060D26] underline underline-offset-4 hover:bg-white">Preview as tenant</button>
                @else
                    <span></span>
                @endif
                <div class="flex items-center gap-2">
                    <a href="{{ route('landlord.leases.index') }}" class="inline-flex h-10 items-center rounded-xl px-3 text-[13px] font-semibold text-[#5B6A8E] hover:text-[#060D26]">Cancel</a>
                    <button type="submit" class="h-10 rounded-xl bg-[#FF8A66] px-6 text-[13px] font-bold text-[#060D26] hover:bg-[#E96F4F]">Save policy</button>
                </div>
            </div>
        </form>

        @if($exists)
            <div class="mt-4 flex flex-wrap items-center gap-4 text-[13px]">
                <a href="{{ route('landlord.lease-templates.create', ['from' => $template->lease_template_id]) }}" class="font-semibold text-[#060D26] underline underline-offset-2">Duplicate this policy</a>
                <form method="POST" action="{{ route('landlord.lease-templates.destroy', $template) }}"
                    data-confirm="Delete &quot;{{ $template->name }}&quot;?"
                    data-confirm-message="Properties and units using it will send the standard policy instead. Agreements already sent don't change."
                    data-confirm-button="Delete policy">
                    @csrf @method('DELETE')
                    <button type="submit" class="font-semibold text-[#DC2626] underline underline-offset-2">Delete this policy</button>
                </form>
            </div>
        @endif

        {{-- Preview: the lease as a tenant reads it, with a sample tenant --}}
        <div x-show="previewOpen" x-cloak class="fixed inset-0 z-[80] flex items-stretch justify-center bg-[#060D26]/60 sm:p-6" role="dialog" aria-modal="true" aria-label="Policy preview"
            @keydown.escape.window="previewOpen = false">
            <div class="flex w-full max-w-3xl flex-col overflow-hidden bg-white sm:rounded-2xl sm:shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-[#E2E4EC] px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-[14px] font-semibold text-[#060D26]">Preview as tenant</p>
                        <p class="text-[11.5px] text-[#B45309]">Sample tenant. Rent, deposit and dates come from the property's first unit.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <label class="sr-only" for="lease-preview-property">Fill the preview in with</label>
                        <select id="lease-preview-property" x-model="propertyId" @change="preview()" class="h-10 max-w-[180px] rounded-xl border border-[#E2E4EC] bg-white px-3 text-[12.5px] text-[#060D26]">
                            @foreach($properties as $p)<option value="{{ $p->property_id }}">{{ $p->title }}</option>@endforeach
                        </select>
                        <button type="button" @click="previewOpen = false" class="h-10 rounded-xl border border-[#E2E4EC] px-4 text-[13px] font-semibold text-[#060D26]">Close</button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <p x-show="previewBusy" class="text-[13px] text-[#5B6A8E]">Loading preview…</p>
                    <p x-show="previewError" x-text="previewError" class="text-[13px] font-semibold text-[#DC2626]"></p>
                    <div x-show="!previewBusy && !previewError" x-html="previewHtml"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
