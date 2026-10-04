{{--
    Pick a viewing slot and post it: a tenant's new request, or either
    party's reschedule. Used by the chat card and the landlord's Viewings tab.

    @param string       $action
    @param Carbon|null  $value       current slot when rescheduling
    @param int          $landlordId  whose blocked days to grey out
    @param string       $heading
    @param string       $submitLabel
    @param string       $closeExpr   Alpine expression that closes the modal
    @param string|null  $noteId      set to show the optional note field (new requests only)
--}}
<form action="{{ $action }}" method="POST">
    @csrf
    <x-datetime-picker name="scheduled_at" :value="$value" :min="now()"
        :max="now()->addDays(config('rentals.viewing_max_days_ahead'))"
        :blocked="\App\Models\ViewingRequest::blockedIsosFor($landlordId)"
        :week-slots="\App\Models\LandlordViewingHour::slotsFor($landlordId)" :custom-time="false"
        :heading="$heading">
        @if (! empty($noteId))
            <div class="w-full">
                <label for="{{ $noteId }}" class="block text-[11px] font-bold uppercase tracking-[0.11em] text-[#5B6A8E] mb-1.5">Note for the landlord (optional)</label>
                <input id="{{ $noteId }}" name="note" maxlength="500" placeholder="e.g. I'll bring my sister"
                    class="w-full h-11 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[13px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
            </div>
        @endif
        <button type="submit" :disabled="!value"
            class="w-full sm:w-auto px-7 py-3 rounded-xl bg-[#FF8A66] text-[#060D26] text-[14px] font-bold hover:bg-[#E96F4F] disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all duration-200">
            {{ $submitLabel }}
        </button>
        <button type="button" @click="{{ $closeExpr }}"
            class="w-full sm:w-auto px-4 py-3 rounded-xl text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] cursor-pointer transition-colors">
            Cancel
        </button>
    </x-datetime-picker>
</form>
