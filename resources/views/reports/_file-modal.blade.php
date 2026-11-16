{{-- The report form as a modal, shared by the tenant's My Reports page and the landlord's My Complaints page.
     Opened by any button that does $dispatch('open-file-report'). A plain form post to reports.store: success
     lands back on the list (the new report shows at the top), a validation error reopens the modal with the
     answers kept.
     Needs $reportProperties, $reportUsers, $reportCategories.
     Optional: $returnTo ('complaints' for landlords), $defaultTarget ('user'|'property'), $openOnLoad (bool),
     $prefillProperty / $prefillUser (a listing or person picked from elsewhere: shown as a fixed card). --}}
@php
    $returnTo = $returnTo ?? null;
    $defaultTarget = $defaultTarget ?? 'property';
    $openOnLoad = $openOnLoad ?? false;
    $prefillProperty = $prefillProperty ?? null;
    $prefillUser = $prefillUser ?? null;
    $locked = $prefillProperty ? 'property' : ($prefillUser ? 'user' : null);

    $propertyOptions = ['' => 'Choose a listing'] + $reportProperties->pluck('title', 'property_id')->all();
    $userOptions = ['' => 'Choose a person'] + $reportUsers->mapWithKeys(fn ($u) => [(string) $u->user_id => trim($u->first_name.' '.$u->last_name)])->all();
    $field = 'h-11 w-full rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[16px] sm:text-[14px] text-[#060D26]';
    $label = 'mb-1.5 block text-[13px] font-semibold text-[#060D26]';
@endphp

<template x-teleport="body">
    <div x-data="{ open: @js($errors->any() || $openOnLoad), target: @js(old('target_type', $locked ?? $defaultTarget)) }"
        x-on:open-file-report.window="open = true; $nextTick(() => $refs.dialog.focus())"
        x-on:keydown.escape.window="open = false"
        x-init="if (open) $nextTick(() => $refs.dialog.focus())"
        x-show="open" x-cloak class="fixed inset-0 z-[200] bg-[#060D26]/45"
        x-transition:enter="transition-opacity duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex h-full items-end justify-center sm:items-center sm:p-6">
            <form method="POST" action="{{ route('reports.store') }}" x-ref="dialog" tabindex="-1"
                x-on:click.outside="open = false" role="dialog" aria-modal="true" aria-labelledby="file-report-title"
                x-show="open"
                x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-[0.98]" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                class="flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-[0_24px_48px_-12px_rgba(6,13,38,0.35)] outline-none sm:max-h-[85vh] sm:max-w-lg sm:rounded-2xl">
                @csrf
                @if ($returnTo)
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif

                {{-- Header stays put; only the body scrolls. --}}
                <div class="flex items-start gap-3 border-b border-[#E2E4EC] px-5 py-4 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <h2 id="file-report-title" class="text-[16px] font-semibold text-[#060D26]">{{ $modalTitle ?? 'Report a problem' }}</h2>
                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">Confidential: whoever you report is not told it was you.</p>
                    </div>
                    <button type="button" x-on:click="open = false" aria-label="Close"
                        class="-mr-2 flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-[#5B6A8E] transition-colors hover:bg-[#ECEEF6] hover:text-[#060D26] cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                    @if ($errors->any())
                        <div class="rounded-xl bg-[#EF4444]/[0.07] px-3.5 py-2.5 text-[13px] font-medium text-[#DC2626]" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @if ($locked === 'property')
                        {{-- Reported from a listing: the target is fixed. --}}
                        <input type="hidden" name="target_type" value="property">
                        <input type="hidden" name="property_id" value="{{ $prefillProperty->property_id }}">
                        <div>
                            <p class="{{ $label }}">Listing</p>
                            <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3 ring-1 ring-[#5B6A8E]/15">
                                <p class="truncate text-[13.5px] font-semibold text-[#060D26]">{{ $prefillProperty->title }}</p>
                                @if ($prefillProperty->landlord)
                                    <p class="mt-0.5 text-[12px] text-[#5B6A8E]">by {{ trim($prefillProperty->landlord->first_name.' '.$prefillProperty->landlord->last_name) }}</p>
                                @endif
                            </div>
                        </div>
                    @elseif ($locked === 'user')
                        {{-- Reported from a profile: the target is fixed. --}}
                        <input type="hidden" name="target_type" value="user">
                        <input type="hidden" name="reported_user_id" value="{{ $prefillUser->user_id }}">
                        <div>
                            <p class="{{ $label }}">Person</p>
                            <div class="rounded-xl bg-[#F7F8FC] px-3.5 py-3 ring-1 ring-[#5B6A8E]/15">
                                <p class="text-[13.5px] font-semibold text-[#060D26]">{{ trim($prefillUser->first_name.' '.$prefillUser->last_name) }}</p>
                            </div>
                        </div>
                    @else
                        <fieldset>
                            <legend class="{{ $label }}">What are you reporting?</legend>
                            <div class="grid grid-cols-2 gap-1 rounded-xl bg-[#F1F3F8] p-1">
                                @foreach (['property' => 'A listing', 'user' => 'A person'] as $value => $text)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="target_type" value="{{ $value }}" x-model="target" class="peer sr-only">
                                        <span class="flex h-10 items-center justify-center rounded-lg text-[13.5px] font-semibold text-[#5B6A8E] transition-colors peer-checked:bg-white peer-checked:text-[#060D26] peer-checked:shadow-[0_1px_2px_rgba(6,13,38,0.10)] peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66]/40">{{ $text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div x-show="target === 'user'" x-cloak>
                            <label class="{{ $label }}">Who</label>
                            @if ($reportUsers->isEmpty())
                                <p class="rounded-xl bg-[#F7F8FC] px-3.5 py-3 text-[13px] text-[#5B6A8E]">Nobody to choose yet: this lists people you have messaged. To report anyone else, use the Report button on their profile.</p>
                            @else
                                <x-styled-select name="reported_user_id" :options="$userOptions" :selected="(string) old('reported_user_id', '')" class="{{ $field }}" />
                                <p class="mt-1.5 text-[12px] text-[#5B6A8E]">People you have messaged.</p>
                            @endif
                        </div>

                        <div x-show="target === 'property'" x-cloak>
                            <label class="{{ $label }}">Which listing</label>
                            <x-styled-select name="property_id" :options="$propertyOptions" :selected="(string) old('property_id', '')" class="{{ $field }}" />
                        </div>
                    @endif

                    <fieldset>
                        <legend class="{{ $label }}">Reason</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($reportCategories as $category)
                                <label class="cursor-pointer">
                                    <input type="radio" name="category" value="{{ $category }}" class="peer sr-only" required @checked(old('category') === $category)>
                                    <span class="inline-flex h-10 items-center rounded-full border border-[#E2E4EC] bg-white px-4 text-[13.5px] text-[#060D26] transition-colors hover:border-[#5B6A8E]/50 peer-checked:border-[#060D26] peer-checked:bg-[#060D26] peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-[#FF8A66]/40">{{ $category }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div>
                        <label for="file-report-details" class="{{ $label }}">Details <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                        <textarea id="file-report-details" name="details" rows="4" maxlength="1000" placeholder="What happened, and when?"
                            class="w-full rounded-xl border border-[#E2E4EC] px-3.5 py-3 text-[16px] sm:text-[14px] text-[#060D26] placeholder-[#5B6A8E]/70 focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">{{ old('details') }}</textarea>
                    </div>

                    <p class="text-[12px] leading-relaxed text-[#5B6A8E]">An admin reviews every report and marks it Resolved once action is taken. False reports may affect your account standing.</p>
                </div>

                {{-- Footer stays put. Phones: full-width buttons, Submit on top. --}}
                <div class="flex flex-col-reverse gap-2 border-t border-[#E2E4EC] px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" x-on:click="open = false"
                        class="inline-flex h-11 items-center justify-center rounded-full px-5 text-[14px] font-semibold text-[#5B6A8E] transition-colors hover:text-[#060D26] cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-full bg-[#FF8A66] px-6 text-[14px] font-semibold text-[#060D26] transition-colors hover:bg-[#E96F4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26]/30 cursor-pointer">
                        Submit report
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
