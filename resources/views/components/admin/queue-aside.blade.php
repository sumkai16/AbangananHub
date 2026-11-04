@props(['recent', 'checklist', 'checklistTitle' => 'Before you approve', 'note' => null])
{{--
    Under an approval queue: the last decisions made (so a mistake is easy to find and the history is visible) and a short
    checklist. $recent rows: ['name' => string, 'sub' => string, 'status' => string, 'href' => url].
--}}
<div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-5">
    <x-card flush class="lg:col-span-3">
        <div class="border-b border-[#E2E4EC] px-5 py-3.5">
            <h2 class="text-[15px] font-semibold text-[#060D26]">Recently decided</h2>
        </div>
        @if(empty($recent))
            <x-admin.empty title="No decisions yet" />
        @else
            <ul class="divide-y divide-[#E2E4EC]">
                @foreach($recent as $item)
                    <li>
                        <a href="{{ $item['href'] }}" class="flex items-center justify-between gap-3 px-5 py-3 transition-colors duration-200 hover:bg-[#F7F8FC]">
                            <span class="min-w-0">
                                <span class="block truncate text-[13.5px] font-semibold text-[#060D26]">{{ $item['name'] }}</span>
                                <span class="block truncate text-[12.5px] text-[#5B6A8E]">{{ $item['sub'] }}</span>
                            </span>
                            <x-admin.status :status="$item['status']" class="shrink-0" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <x-card class="lg:col-span-2">
        <h2 class="text-[15px] font-semibold text-[#060D26]">{{ $checklistTitle }}</h2>
        <ol class="mt-3 space-y-2.5 text-[13.5px] text-[#060D26]">
            @foreach($checklist as $i => $line)
                <li class="flex gap-3"><span class="font-bold tabular-nums text-[#FF8A66]">{{ $i + 1 }}</span><span>{{ $line }}</span></li>
            @endforeach
        </ol>
        @if($note)<p class="mt-4 text-[12.5px] text-[#5B6A8E]">{{ $note }}</p>@endif
    </x-card>
</div>
