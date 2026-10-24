{{--
    One plain chat bubble (mine, or theirs with avatar). Shared by the inquiry panel
    and the tenancy panel. Needs $message, $msgs (the whole thread) and $i (its index).
    A run of same-sender messages shows one avatar, on the last of the run.
--}}
@php
    $isSelf = $message->sender_id === auth()->id();
    $next = $msgs[$i + 1] ?? null;
    // Messenger convention: the avatar sits on the LAST message
    // of a consecutive run, not every one. A run ends at the
    // thread end, at a system divider, an inquiry summary card,
    // or a sender change.
    $endsRun = !$next || $next->is_system || $next->is_inquiry_summary || $next->concern_id || $next->kind === 'payment_qr' || $next->sender_id !== $message->sender_id;
    $prev = $msgs[$i - 1] ?? null;
    $startsRun = !$prev || $prev->is_system || $prev->is_inquiry_summary || $prev->concern_id || $prev->kind === 'payment_qr' || $prev->sender_id !== $message->sender_id;
@endphp

@if($isSelf)
    {{-- The timestamp is absolutely positioned outside the
         bubble so hiding it costs no vertical space. It stays
         in the DOM (opacity only) so screen readers keep it. --}}
    <div class="group relative max-w-[75%] self-end {{ $endsRun ? 'mb-1.5' : '' }}" data-bubble>
        <div class="bg-[#060D26] text-white rounded-2xl {{ $startsRun ? 'rounded-tr-sm' : '' }} px-4 py-2.5 shadow-sm cursor-default">
            <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
        </div>
        <p class="message-time absolute right-full top-1/2 -translate-y-1/2 mr-2 whitespace-nowrap text-[10px] tracking-wide text-[#5B6A8E] opacity-0 group-hover:opacity-100 transition-opacity duration-150 pointer-events-none"
            data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
    </div>
@else
    <div class="flex items-end gap-2 self-start max-w-[85%] {{ $endsRun ? 'mb-1.5' : '' }}"
        data-msg-sender="{{ $message->sender_id }}">
        {{-- Spacer keeps every bubble in the run on the same
             left edge as the one that carries the avatar. --}}
        <span data-avatar-slot class="contents">
        @if($endsRun)
            @if($message->sender?->profile_picture)
                <img loading="lazy" decoding="async" src="{{ $message->sender->profile_picture }}"
                    alt="{{ $message->sender->first_name }} {{ $message->sender->last_name }}"
                    class="w-7 h-7 rounded-full object-cover shrink-0 mb-0.5">
            @else
                <div class="w-7 h-7 rounded-full bg-[#060D26] text-white flex items-center justify-center text-[11px] font-bold shrink-0 mb-0.5"
                    aria-hidden="true">
                    {{ strtoupper(substr($message->sender->first_name, 0, 1)) }}
                </div>
            @endif
        @else
            <div class="w-7 shrink-0" aria-hidden="true"></div>
        @endif
        </span>

        <div class="group relative min-w-0" data-bubble>
            <div class="bg-white text-[#060D26] border border-[#E2E4EC] rounded-2xl {{ $startsRun ? 'rounded-tl-sm' : '' }} px-4 py-2.5 shadow-sm cursor-default">
                @if($startsRun)
                    <p class="text-[11px] font-bold text-[#060D26] mb-1">
                        {{ $message->sender->first_name }}
                    </p>
                @endif
                <p class="text-[13px] leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
            </div>
            <p class="message-time absolute left-full top-1/2 -translate-y-1/2 ml-2 whitespace-nowrap text-[10px] tracking-wide text-[#5B6A8E] opacity-0 group-hover:opacity-100 transition-opacity duration-150 pointer-events-none"
                data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
        </div>
    </div>
@endif
