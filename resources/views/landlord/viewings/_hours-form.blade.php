{{--
    The landlord's weekly viewing hours: which days, from and until when.
    Tenants can only request times inside them. See plans/unit-viewing-scheduling.md.

    @param array  $week       [dayOfWeek => [start, end]] as LandlordViewingHour::weekFor() returns
    @param string $closeExpr  Alpine expression that closes the modal
--}}
@php
    $earliest = config('rentals.viewing_hours_earliest');
    $latest = config('rentals.viewing_hours_latest');
    $defaultSpan = [config('rentals.viewing_first_hour'), config('rentals.viewing_last_hour') + 1];
    $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $hourLabel = fn (int $h) => \Illuminate\Support\Carbon::createFromTime($h % 24)->format('g A');

    // Monday first: that's how people read a work week.
    $order = [1, 2, 3, 4, 5, 6, 0];
    $rows = collect($order)->map(fn ($d) => [
        'day'   => $d,
        'name'  => $names[$d],
        'on'    => isset($week[$d]),
        'start' => $week[$d][0] ?? $defaultSpan[0],
        'end'   => $week[$d][1] ?? $defaultSpan[1],
    ])->values();
@endphp

<form action="{{ route('landlord.viewings.hours') }}" method="POST" x-data="{ rows: @js($rows) }">
    @csrf @method('PUT')

    <ul class="px-5 sm:px-6 py-3 divide-y divide-[#E2E4EC]">
        <template x-for="(r, i) in rows" :key="r.day">
            <li class="py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                <label class="flex items-center gap-2.5 w-36 cursor-pointer">
                    <input type="checkbox" value="1" x-model="r.on" :name="`days[${r.day}][on]`"
                        class="w-4.5 h-4.5 rounded border-[#5B6A8E]/40 text-[#060D26] focus:ring-[#FF8A66] focus:ring-offset-0">
                    <span class="text-[14px] font-semibold" :class="r.on ? 'text-[#060D26]' : 'text-[#94A3B8]'" x-text="r.name"></span>
                </label>

                <div x-show="r.on" class="flex items-center gap-2">
                    <label class="sr-only" :for="`start-${r.day}`" x-text="`${r.name} from`"></label>
                    <select :id="`start-${r.day}`" :name="`days[${r.day}][start]`" x-model.number="r.start"
                        class="h-10 rounded-lg border border-[#E2E4EC] bg-white pl-3 pr-8 text-[13.5px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
                        @for ($h = $earliest; $h < $latest; $h++)
                            <option value="{{ $h }}">{{ $hourLabel($h) }}</option>
                        @endfor
                    </select>
                    <span class="text-[13px] text-[#5B6A8E]">to</span>
                    <label class="sr-only" :for="`end-${r.day}`" x-text="`${r.name} until`"></label>
                    <select :id="`end-${r.day}`" :name="`days[${r.day}][end]`" x-model.number="r.end"
                        class="h-10 rounded-lg border border-[#E2E4EC] bg-white pl-3 pr-8 text-[13.5px] text-[#060D26] focus:border-[#FF8A66] focus:ring-1 focus:ring-[#FF8A66] outline-none">
                        @for ($h = $earliest + 1; $h <= $latest; $h++)
                            <option value="{{ $h }}" :disabled="{{ $h }} <= r.start">{{ $hourLabel($h) }}</option>
                        @endfor
                    </select>
                </div>
                <span x-show="!r.on" class="text-[13px] text-[#94A3B8]">No viewings</span>
                <span x-show="r.on && r.end <= r.start" x-cloak class="text-[12px] font-semibold text-[#DC2626]">End must be after start</span>
            </li>
        </template>
    </ul>

    <div class="px-5 sm:px-6 py-4 bg-[#F7F8FC] border-t border-[#E2E4EC] flex flex-wrap items-center gap-3">
        <button type="submit" :disabled="!rows.some(r => r.on) || rows.some(r => r.on && r.end <= r.start)"
            class="w-full sm:w-auto px-7 py-3 rounded-xl bg-[#FF8A66] text-[#060D26] text-[14px] font-bold hover:bg-[#E96F4F] disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all duration-200">
            Save viewing hours
        </button>
        <button type="button" @click="{{ $closeExpr }}"
            class="w-full sm:w-auto px-4 py-3 rounded-xl text-[14px] font-semibold text-[#060D26] hover:bg-[#ECEEF6] cursor-pointer transition-colors">
            Cancel
        </button>
        <p class="w-full text-[12px] text-[#5B6A8E]">
            The last viewing starts an hour before the end time. Viewings you've already booked stay as they are.
        </p>
    </div>
</form>
