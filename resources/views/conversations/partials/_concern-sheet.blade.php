{{--
    "Report a concern": a bottom sheet on phones, a centred dialog from sm up.
    Behaviour lives in concernSheet() in conversations/index.blade.php (injected
    panels don't run their own scripts). Needs $conversation and $categories.
--}}
<div x-data="concernSheet({{ $conversation->conversation_id }})" :data-sheet-open="open" @keydown.escape.window="close()">
    <button type="button" @click="open = true"
        class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[12px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">
        <svg class="h-4 w-4 text-[#B35A3D]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437"/></svg>
        <span class="hidden sm:inline">Report a concern</span><span class="sm:hidden">Concern</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
        class="fixed inset-0 z-[200] flex items-end justify-center bg-black/50 sm:items-center sm:p-4" @click.self="close()"
        role="dialog" aria-modal="true" aria-labelledby="concern-title">
        <form @submit.prevent="submit()"
            class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-md sm:rounded-2xl">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 id="concern-title" class="text-lg font-normal text-[#060D26]">Report a concern</h2>
                    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">Your landlord gets a notification and can update the status.</p>
                </div>
                <button type="button" @click="close()" aria-label="Close"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#5B6A8E] transition-colors hover:bg-[#ECEEF6]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <fieldset class="mb-4">
                <legend class="mb-2 text-[12px] font-semibold text-[#060D26]">What is it about?</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach($categories as $cat)
                        <button type="button" @click="category = '{{ $cat }}'" :aria-pressed="category === '{{ $cat }}'"
                            :class="category === '{{ $cat }}' ? 'border-[#FF8A66] bg-[#FF8A66] text-[#060D26]' : 'border-[#E2E4EC] bg-white text-[#060D26] hover:bg-[#F7F8FC]'"
                            class="h-9 rounded-full border px-3.5 text-[12.5px] font-medium transition-colors">{{ $cat }}</button>
                    @endforeach
                </div>
                <p x-show="errors.category" x-text="errors.category" class="mt-1.5 text-[12px] text-[#EF4444]"></p>
            </fieldset>

            <div class="mb-4">
                <label for="concern-description" class="mb-2 block text-[12px] font-semibold text-[#060D26]">What is wrong?</label>
                <textarea id="concern-description" x-model="description" rows="4" maxlength="1000"
                    placeholder="e.g. The faucet in the bathroom has been dripping since yesterday."
                    class="w-full rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] px-3.5 py-2.5 text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20"></textarea>
                <div class="mt-1 flex justify-between">
                    <p x-show="errors.description" x-text="errors.description" class="text-[12px] text-[#EF4444]"></p>
                    <p class="ml-auto text-[11px] text-[#94A3B8]" x-text="description.length + '/1000'"></p>
                </div>
            </div>

            <div class="mb-5">
                <p class="mb-2 text-[12px] font-semibold text-[#060D26]">Photos <span class="font-normal text-[#5B6A8E]">(optional, up to 3)</span></p>
                <div class="flex flex-wrap gap-2.5">
                    <template x-for="(src, i) in previews" :key="src">
                        <div class="relative h-20 w-20">
                            <img :src="src" alt="" class="h-full w-full rounded-lg border border-[#E2E4EC] object-cover">
                            <button type="button" @click="remove(i)" aria-label="Remove photo"
                                class="absolute -right-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-[#060D26] text-white shadow">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                    <label x-show="files.length < 3" for="concern-photos"
                        class="flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-[#5B6A8E]/40 text-[11px] text-[#5B6A8E] transition-colors hover:border-[#FF8A66] hover:text-[#060D26]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Add
                    </label>
                    <input id="concern-photos" type="file" accept="image/*" multiple class="sr-only" @change="pick($event)">
                </div>
                <p x-show="errors.photos" x-text="errors.photos" class="mt-1.5 text-[12px] text-[#EF4444]"></p>
            </div>

            <p x-show="errors.general" x-text="errors.general" class="mb-3 text-[12px] text-[#EF4444]"></p>

            <button type="submit" :disabled="busy"
                class="h-11 w-full rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F] disabled:opacity-60">
                <span x-show="!busy">Send to landlord</span><span x-show="busy" x-cloak>Sending…</span>
            </button>
        </form>
    </div>
</div>
