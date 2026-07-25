@props([
    'title',
    'sub' => null,
    'rows',          // Collection of ['user'=>User,'avg','count'] or ['property'=>Property,'avg','count']
    'type' => 'user', // 'user' | 'property'
    'empty' => 'Nothing to show yet.',
])

<div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
    <div class="px-5 py-4 border-b border-[#E2E8F0]">
        <h2 class="text-[15px] font-bold text-[#1F2937]">{{ $title }}</h2>
        @if($sub)
            <p class="text-[12px] text-[#64748B] mt-0.5">{{ $sub }}</p>
        @endif
    </div>

    @if($rows->isEmpty())
        <div class="px-5 py-8 text-center text-[13px] text-[#94A3B8]">{{ $empty }}</div>
    @else
        <ul class="divide-y divide-[#E2E8F0]">
            @foreach($rows as $i => $row)
                @php
                    $isUser = $type === 'user';
                    $subject = $isUser ? $row['user'] : $row['property'];
                    if (! $subject) { continue; }
                    $name = $isUser
                        ? (trim(($subject->first_name ?? '') . ' ' . ($subject->last_name ?? '')) ?: 'Unknown')
                        : $subject->title;
                    $href = $isUser
                        ? route('admin.users.show', $subject)
                        : route('properties.show', $subject);
                    $initials = $isUser
                        ? strtoupper(substr($subject->first_name ?? '', 0, 1) . substr($subject->last_name ?? '', 0, 1))
                        : null;
                @endphp
                <li>
                    <a href="{{ $href }}" class="flex items-center gap-3 px-5 py-3 hover:bg-[#F7FCFC] transition-colors">
                        <span class="w-6 text-[13px] font-bold text-[#94A3B8] shrink-0">{{ $i + 1 }}</span>

                        @if($isUser)
                            <span class="w-9 h-9 rounded-full bg-[#EEF8F8] flex items-center justify-center text-[12px] font-bold text-[#156F8C] shrink-0">
                                {{ $initials ?: '?' }}
                            </span>
                        @else
                            <span class="w-9 h-9 rounded-lg bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </span>
                        @endif

                        <span class="min-w-0 flex-1">
                            <span class="block text-[13.5px] font-semibold text-[#1F2937] truncate">{{ $name }}</span>
                            <span class="block text-[11.5px] text-[#64748B]">{{ $row['count'] }} {{ \Illuminate\Support\Str::plural('rating', $row['count']) }}</span>
                        </span>

                        <x-star-rating :rating="$row['avg']" :show-value="true" class="shrink-0" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
