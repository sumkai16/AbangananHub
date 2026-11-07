@props([
    // The saved TenantDetail (or null). old() input wins after a failed submit.
    'detail' => null,
    // Prefix for element ids, so the same fields can appear twice on one page.
    'idPrefix' => 'td',
    'required' => false,
    // 44px fields in the Add lease / Add tenant modal style; the default 40px suits the sign form and settings.
    'tall' => false,
    // Inside the Add/Edit tenant modal: show errors from its Alpine err(field) helper and outline the bad field.
    'inline' => false,
])

{{--
    Emergency contact and ID reference. One set of fields for every place a tenant's details are entered
    (sign form, Account Settings, walk-in form, landlord tenancy page); the matching rules live in
    App\Models\TenantDetail::rules(), so the form and the server cannot disagree.
--}}
@php
    $value = fn (string $field) => old($field, $detail?->{$field});
    $input = $tall
        ? 'h-11 w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[14px] text-[#060D26] placeholder-[#94A3B8] hover:border-[#5B6A8E]/40 focus:outline-none focus:border-[#FF8A66]/60 focus:ring-4 focus:ring-[#FF8A66]/10 transition-all duration-200'
        : 'h-10 w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-sm text-[#060D26] placeholder-[#5B6A8E]/70 outline-none transition focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/15';
    $label = $tall ? 'mb-1.5 block text-[12.5px] font-semibold text-[#060D26]' : 'mb-1.5 block text-[12px] font-semibold text-[#060D26]';
    $req = $required ? '<span class="text-[#EF4444]">*</span>' : '<span class="font-normal text-[#5B6A8E]">(optional)</span>';
    $bad = fn (string $f) => $inline ? ':class="err(\'' . $f . '\') && \'!border-[#DC2626]\'"' : '';
    $msg = fn (string $f) => $inline ? '<p x-show="err(\'' . $f . '\')" x-cloak class="mt-1 text-[11.5px] font-semibold text-[#DC2626]" x-text="err(\'' . $f . '\')"></p>' : '';
@endphp

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 sm:grid-cols-2 gap-4']) }}>
    <div>
        <label for="{{ $idPrefix }}_emergency_contact_name" class="{{ $label }}">Emergency contact name {!! $req !!}</label>
        <input type="text" id="{{ $idPrefix }}_emergency_contact_name" name="emergency_contact_name" {!! $bad('emergency_contact_name') !!} maxlength="150"
            value="{{ $value('emergency_contact_name') }}" autocomplete="off" class="{{ $input }}">
        @error('emergency_contact_name')<p class="mt-1 text-[11.5px] text-[#DC2626]">{{ $message }}</p>@enderror
        {!! $msg('emergency_contact_name') !!}
    </div>

    <div>
        <label for="{{ $idPrefix }}_emergency_contact_relationship" class="{{ $label }}">Relationship <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
        <select id="{{ $idPrefix }}_emergency_contact_relationship" name="emergency_contact_relationship" {!! $bad('emergency_contact_relationship') !!} class="{{ $input }}">
            <option value="">Select</option>
            @foreach (\App\Models\TenantDetail::RELATIONSHIPS as $option)
                <option value="{{ $option }}" @selected($value('emergency_contact_relationship') === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('emergency_contact_relationship')<p class="mt-1 text-[11.5px] text-[#DC2626]">{{ $message }}</p>@enderror
        {!! $msg('emergency_contact_relationship') !!}
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $idPrefix }}_emergency_contact_number" class="{{ $label }}">Emergency contact number {!! $req !!}</label>
        <input type="tel" id="{{ $idPrefix }}_emergency_contact_number" name="emergency_contact_number" {!! $bad('emergency_contact_number') !!} maxlength="20"
            inputmode="tel" placeholder="0917 123 4567" value="{{ $value('emergency_contact_number') }}" autocomplete="off" class="{{ $input }}">
        @error('emergency_contact_number')<p class="mt-1 text-[11.5px] text-[#DC2626]">{{ $message }}</p>@enderror
        {!! $msg('emergency_contact_number') !!}
    </div>

    <div>
        <label for="{{ $idPrefix }}_id_type" class="{{ $label }}">ID type {!! $req !!}</label>
        <select id="{{ $idPrefix }}_id_type" name="id_type" {!! $bad('id_type') !!} class="{{ $input }}">
            <option value="">Select</option>
            @foreach (\App\Models\TenantDetail::ID_TYPES as $option)
                <option value="{{ $option }}" @selected($value('id_type') === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @error('id_type')<p class="mt-1 text-[11.5px] text-[#DC2626]">{{ $message }}</p>@enderror
        {!! $msg('id_type') !!}
    </div>

    <div>
        <label for="{{ $idPrefix }}_id_number" class="{{ $label }}">ID number {!! $req !!}</label>
        {{-- Shown in full to the tenant who owns it, or the landlord who recorded the walk-in; stored encrypted. --}}
        <input type="text" id="{{ $idPrefix }}_id_number" name="id_number" {!! $bad('id_number') !!} maxlength="50"
            value="{{ $value('id_number') }}" autocomplete="off" class="{{ $input }}">
        @error('id_number')<p class="mt-1 text-[11.5px] text-[#DC2626]">{{ $message }}</p>@enderror
        {!! $msg('id_number') !!}
    </div>
</div>
