@extends('layouts.landlord')

@section('content')
<div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-[1200px] mx-auto">

        <a href="{{ route('landlord.properties.index') }}"
            class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors w-fit mb-6">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to My Properties
        </a>

        @if($errors->any())
            <div class="max-w-2xl mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-11">

            <x-property-wizard-stepper current="review" :property="$property" :checklist="$checklist ?? null" />

            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Step 6 of 6</p>
                <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">Review & submit</h1>
                <p class="mt-2 text-sm text-[#64748B] leading-relaxed max-w-md">One last look before it goes to our team for review.</p>

                <div class="mt-7 max-w-2xl bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] divide-y divide-[#E2E8F0]">
                    @foreach($checklist as $item)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 {{ $item['complete'] ? 'bg-[#22C55E]/15 text-[#15803D]' : 'bg-[#FBBF24]/15 text-[#B45309]' }}">
                                    @if($item['complete'])
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @else
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.007M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13.5px] font-semibold text-[#1F2937]">{{ $item['label'] }}</p>
                                    <p class="text-[11.5px] {{ $item['complete'] ? 'text-[#64748B]' : 'text-[#B45309]' }}">
                                        {{ $item['detail'] ?? ($item['complete'] ? 'Complete' : 'Incomplete') }}
                                    </p>
                                </div>
                            </div>
                            <a href="{{ $item['edit'] }}" class="shrink-0 text-[12.5px] font-semibold text-[#156F8C] hover:text-[#0E5670] transition-colors">Edit</a>
                        </div>
                    @endforeach
                </div>

                @if($canSubmit)
                    <div class="mt-5 max-w-2xl rounded-xl bg-[#EEF8F8]/70 border border-[#2AA7A1]/20 p-4 flex gap-3">
                        <svg class="w-5 h-5 text-[#2AA7A1] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <p class="text-[12.5px] text-[#64748B] leading-relaxed">Once submitted, your property will be reviewed by our team. You'll be notified by email.</p>
                    </div>
                @else
                    <div class="mt-5 max-w-2xl rounded-xl bg-[#FBBF24]/[0.08] border border-[#FBBF24]/25 p-4 flex gap-3">
                        <svg class="w-5 h-5 text-[#B45309] shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <p class="text-[12.5px] text-[#B45309] leading-relaxed">Finish the sections marked incomplete above before you can submit for review.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('properties.wizard.submit', $property) }}"
                    x-data="{ submitting: false, canSubmit: @js($canSubmit) }" x-on:submit="submitting = true"
                    class="mt-7 pt-5 border-t border-[#E2E8F0] max-w-2xl flex items-center gap-3">
                    @csrf
                    <a href="{{ route('properties.wizard.units', $property) }}"
                        class="px-5 py-3 rounded-xl text-sm font-semibold text-[#1F2937] bg-white border border-[#E2E8F0] hover:bg-[#EEF8F8] transition-colors duration-150">
                        Back
                    </a>
                    <button type="submit" :disabled="submitting || ! canSubmit"
                        class="ml-auto inline-flex items-center gap-2 px-9 py-3 rounded-xl text-sm font-semibold text-white bg-[#16A34A] hover:brightness-95 transition-all duration-150 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:brightness-100">
                        <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="submitting ? 'Submitting…' : 'Submit for Review'"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
