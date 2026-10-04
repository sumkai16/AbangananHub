{{--
    Add or edit a viewing for a visitor who arranged it offline (phone,
    Facebook, walk-in). Rendered inside a modal; the caller owns the open flag.

    @param ViewingRequest|null $viewing     null when adding
    @param Collection          $properties  landlord's approved properties with approved units
    @param array               $blockedIsos
    @param string              $closeExpr   Alpine expression that closes the modal
--}}
@php
    $editing = $viewing !== null;
    $formId = $editing ? 'offline-' . $viewing->viewing_id : 'offline-new';
    $propertyOptions = $properties->map(fn ($p) => [
        'id' => $p->property_id,
        'title' => $p->title,
        'units' => $p->units->map(fn ($u) => ['id' => $u->unit_id, 'label' => $u->unit_label])->values(),
    ])->values();
    // Repopulate after a failed submit of THIS form only.
    $useOld = old('_form') === $formId;
    $field = fn (string $key, $fallback = null) => $useOld ? old($key) : $fallback;
@endphp

<form action="{{ $editing ? route('landlord.viewings.update', $viewing) : route('landlord.viewings.store') }}" method="POST"
    x-data="{
        properties: @js($propertyOptions),
        propertyId: @js((string) $field('property_id', $viewing?->property_id ?? ($propertyOptions->count() === 1 ? $propertyOptions[0]['id'] : ''))),
        unitId: @js((string) $field('unit_id', $viewing?->unit_id ?? '')),
        get units() {
            const p = this.properties.find(p => String(p.id) === String(this.propertyId));
            return p ? p.units : [];
        },
    }">
    @csrf
    @if ($editing) @method('PATCH') @endif
    <input type="hidden" name="_form" value="{{ $formId }}">

    <div class="px-5 sm:px-6 pt-5 grid sm:grid-cols-2 gap-4">
        <div>
            <label for="{{ $formId }}-name" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Visitor name</label>
            <input id="{{ $formId }}-name" name="visitor_name" required maxlength="120"
                value="{{ $field('visitor_name', $viewing?->visitor_name) }}" placeholder="e.g. Juan Dela Cruz"
                class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
        </div>
        <div>
            <label for="{{ $formId }}-phone" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Phone <span class="normal-case font-medium tracking-normal">(optional)</span></label>
            <input id="{{ $formId }}-phone" name="visitor_phone" type="tel" maxlength="30" inputmode="tel"
                value="{{ $field('visitor_phone', $viewing?->visitor_phone) }}" placeholder="09XX XXX XXXX"
                class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
        </div>
        <div>
            <label for="{{ $formId }}-property" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Property</label>
            <select id="{{ $formId }}-property" name="property_id" required x-model="propertyId" @change="unitId = ''"
                class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
                <option value="">Choose a property</option>
                <template x-for="p in properties" :key="p.id">
                    <option :value="String(p.id)" x-text="p.title" :selected="String(p.id) === String(propertyId)"></option>
                </template>
            </select>
        </div>
        <div>
            <label for="{{ $formId }}-unit" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Unit <span class="normal-case font-medium tracking-normal">(optional)</span></label>
            <select id="{{ $formId }}-unit" name="unit_id" x-model="unitId" :disabled="!units.length"
                class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[14px] text-[#060D26] disabled:bg-[#F7F8FC] disabled:text-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
                <option value="">Any / not decided</option>
                <template x-for="u in units" :key="u.id">
                    <option :value="String(u.id)" x-text="u.label" :selected="String(u.id) === String(unitId)"></option>
                </template>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="{{ $formId }}-note" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Note <span class="normal-case font-medium tracking-normal">(optional)</span></label>
            <input id="{{ $formId }}-note" name="note" maxlength="500"
                value="{{ $field('note', $viewing?->note) }}" placeholder="e.g. Called on Facebook, coming with a friend"
                class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
        </div>
    </div>

    <x-datetime-picker name="scheduled_at" :value="$viewing?->scheduled_at" :min="now()"
        :max="now()->addDays(config('rentals.viewing_max_days_ahead'))"
        :blocked="$blockedIsos" :slots="\App\Models\ViewingRequest::slotOptions()" :custom-time="false"
        heading="When are they coming?">
        <button type="submit" :disabled="!value"
            class="w-full sm:w-auto px-7 py-3 rounded-xl bg-[#FF8A66] text-[#060D26] text-[14px] font-bold hover:bg-[#E96F4F] disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all duration-200">
            {{ $editing ? 'Save changes' : 'Add viewing' }}
        </button>
        <button type="button" @click="{{ $closeExpr }}"
            class="w-full sm:w-auto px-4 py-3 rounded-xl text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] cursor-pointer transition-colors">
            Cancel
        </button>
    </x-datetime-picker>
</form>
