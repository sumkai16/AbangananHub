@extends('layouts.admin')

@section('page-title', 'System Settings')

@section('content')
@php
    // Group the whitelist for rendering; the map itself stays the single source of
    // truth for which keys exist, their labels, help text and validation.
    $definitions = collect(App\Models\Setting::DEFINITIONS);
@endphp

<div class="max-w-[1000px] mx-auto">

    <x-page-header title="System Settings"
        subtitle="Business rules that used to need a code deploy. These decide when escrowed money moves — every change is recorded in the audit log.">
        <x-slot:icon>
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </x-slot:icon>
    </x-page-header>

    @if($errors->any())
        <div class="rounded-2xl border border-[#EF4444]/25 bg-[#EF4444]/[0.05] px-5 py-4 mb-5">
            <p class="text-[13px] font-semibold text-[#DC2626]">Nothing was saved — please fix the fields below.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}"
        data-confirm="Save system settings?"
        data-confirm-type="warning"
        data-confirm-message="These values control escrow and rent timing. The change applies immediately, including to scheduled jobs."
        data-confirm-button="Save settings"
        class="space-y-5">
        @csrf
        @method('PATCH')

        @foreach($definitions->groupBy('group') as $group => $fields)
            <x-card>
                <h2 class="text-[15px] font-bold text-[#1F2937]">{{ $group }}</h2>
                <p class="text-[12.5px] text-[#64748B] mt-0.5">
                    {{ $group === App\Models\Setting::GROUP_ESCROW
                        ? 'Deadlines that govern turnover, move-in confirmation, and when a deposit is released or escalated.'
                        : 'When rent falls due, when it reads as overdue, and how reminders repeat.' }}
                </p>

                <div class="mt-5 divide-y divide-[#E2E8F0]">
                    @foreach($fields as $key => $definition)
                        @php
                            $current = App\Models\Setting::effective($key);
                            $default = App\Models\Setting::default($key);
                            $isOverridden = array_key_exists($key, $overrides);
                            $old = old("settings.$key");
                            $value = $old === null
                                ? App\Models\Setting::display($key, $current)
                                : (is_array($old) ? implode(', ', $old) : $old);
                        @endphp

                        <div class="py-4 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-[1fr_200px] sm:items-start gap-3 sm:gap-5">
                            <div>
                                <label for="setting-{{ $key }}" class="block text-[13.5px] font-semibold text-[#1F2937]">
                                    {{ $definition['label'] }}
                                    @if($isOverridden)
                                        <span class="ml-1.5 inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-[#EEF8F8] text-[#156F8C] align-middle">Customized</span>
                                    @endif
                                </label>
                                <p class="text-[12.5px] text-[#64748B] mt-1 leading-relaxed">{{ $definition['help'] }}</p>
                                <p class="text-[11.5px] text-[#94A3B8] mt-1">
                                    Default: <span class="font-semibold text-[#64748B]">{{ App\Models\Setting::display($key, $default) }}</span>
                                    {{ $definition['unit'] }} · clear the field to go back to it
                                </p>
                            </div>

                            <div>
                                <div class="relative">
                                    <input
                                        @class(['w-full rounded-xl border bg-white pl-4 pr-4 py-2.5 text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] transition-colors',
                                            'border-[#EF4444]' => $errors->has("settings.$key") || $errors->has("settings.$key.*"),
                                            'border-[#E2E8F0]' => ! ($errors->has("settings.$key") || $errors->has("settings.$key.*")),
                                        ])
                                        type="{{ $definition['type'] === 'integer' ? 'number' : 'text' }}"
                                        @if($definition['type'] === 'integer') min="0" step="1" inputmode="numeric" @endif
                                        name="settings[{{ $key }}]"
                                        id="setting-{{ $key }}"
                                        value="{{ $value }}"
                                        placeholder="{{ App\Models\Setting::display($key, $default) }}" />
                                </div>
                                <p class="text-[11px] text-[#94A3B8] mt-1">{{ $definition['unit'] }}</p>

                                @error("settings.$key")
                                    <p class="text-[12px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                                @foreach($errors->get("settings.$key.*") as $itemErrors)
                                    <p class="text-[12px] text-[#EF4444] mt-1">{{ $itemErrors[0] }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white rounded-2xl border border-[#E2E8F0] shadow-[0_1px_3px_rgba(15,23,42,0.06)] px-5 py-4">
            <p class="text-[12.5px] text-[#64748B]">
                Changes apply immediately — including to the scheduled jobs that process move-in deadlines and rent reminders.
            </p>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.audit-logs.index', ['action' => 'settings.update']) }}"
                    class="px-4 py-2.5 rounded-xl border border-[#E2E8F0] text-[13px] font-medium text-[#64748B] hover:bg-[#F7FCFC] transition-colors">
                    View change history
                </a>
                <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#2AA7A1] text-white text-[13px] font-semibold hover:brightness-95 transition-all">
                    Save Settings
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
