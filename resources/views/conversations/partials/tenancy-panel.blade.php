{{--
    The chat between a tenant who has moved in and their landlord. Served instead of
    chat-panel.blade.php for tenancy threads: no deal stepper, no agreement buttons,
    no "Mark as Resolved" — just messages, concern cards and a way to report one.
    The element ids (#message-list, #message-form, #message-input) are the contract
    with the inbox script, same as the inquiry panel.
--}}
@php
    $isLandlord = auth()->id() === $conversation->landlord_id;
    $isTenant = auth()->id() === $conversation->tenant_id;
    $reservation = $conversation->reservation;
    $ended = $conversation->isResolved();
    $running = ! $ended && $reservation?->rental_status === 'Occupied';
    $msgs = $conversation->messages->values();
    $openConcerns = $conversation->concerns->whereIn('status', ['Open', 'In Progress'])->count();
    $since = ($reservation?->tenant_confirmed_move_in_at ?? $reservation?->target_move_in_date)?->format('M j, Y');
    $endedOn = $reservation?->target_move_out_date;
@endphp
<div class="flex flex-col h-full" id="chat-panel-root" data-conversation-id="{{ $conversation->conversation_id }}"
    x-data="{ photo: null }" @keydown.escape.window="photo = null">

    {{-- Header --}}
    <div class="px-5 py-3.5 border-b border-[#E2E4EC] flex items-center gap-3 flex-shrink-0">
        <div class="w-9 h-9 rounded-full bg-[#060D26] text-white flex items-center justify-center font-bold text-xs flex-shrink-0" aria-hidden="true">
            {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-[13px] font-normal text-[#060D26] truncate">{{ $otherParty->first_name }} {{ $otherParty->last_name }}</h2>
            <p class="text-[11px] text-[#5B6A8E] truncate">
                {{ $conversation->property->title }}@if($conversation->unit) &middot; {{ $conversation->unit->unit_label }}@endif
                @if($since) &middot; {{ $isLandlord ? 'Tenant' : 'Living here' }} since {{ $since }}@endif
            </p>
            @if($isLandlord && ($otherParty->email || $otherParty->contact_number))
                <p class="text-[11px] text-[#5B6A8E] truncate">{{ $otherParty->email }}{{ $otherParty->contact_number ? ' · '.$otherParty->contact_number : '' }}</p>
            @endif
        </div>
        @if($ended)
            <span class="inline-flex items-center h-8 px-3 text-[11px] font-bold text-[#5B6A8E] bg-[#E2E4EC]/60 rounded-lg">Tenancy ended</span>
        @endif
    </div>

    {{-- Open concerns --}}
    @if($openConcerns > 0)
        <div class="px-5 py-2.5 border-b border-[#E2E4EC] bg-[#FBBF24]/10 flex items-center justify-between gap-3 flex-shrink-0">
            <p class="text-[12px] text-[#060D26]"><span class="font-bold">{{ $openConcerns }}</span> open {{ Str::plural('concern', $openConcerns) }}</p>
            <button type="button"
                onclick="document.querySelector('[data-concern-open]')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                class="text-[12px] font-bold text-[#B35A3D] hover:underline">Jump to it</button>
        </div>
    @endif

    {{-- Messages --}}
    <div id="message-list" class="flex-1 px-4 sm:px-5 py-4 overflow-y-auto flex flex-col gap-1 scroll-smooth bg-[#F7F8FC]"
        data-other-avatar="{{ $otherParty->profile_picture }}"
        data-other-initial="{{ strtoupper(substr($otherParty->first_name, 0, 1)) }}">
        @if($msgs->isEmpty())
            <div class="m-auto max-w-[260px] text-center">
                <p class="text-[14px] font-semibold text-[#060D26]">{{ $isTenant ? 'Say hi to your landlord' : 'No messages yet' }}</p>
                <p class="mt-1 text-[13px] leading-5 text-[#5B6A8E]">
                    {{ $isTenant ? 'Ask a question or report something that needs fixing.' : 'Your tenant can message you or report a concern here.' }}
                </p>
            </div>
        @endif
        @foreach ($msgs as $i => $message)
            @if($message->is_system)
                <div class="self-stretch flex items-center gap-3 my-2 px-2">
                    <div class="flex-1 h-px bg-[#E2E4EC]"></div>
                    <p class="text-[11px] text-[#5B6A8E] text-center max-w-[70%] leading-relaxed">{{ $message->message }}</p>
                    <div class="flex-1 h-px bg-[#E2E4EC]"></div>
                </div>
            @elseif($message->concern_id && $message->concern)
                @include('conversations.partials._concern-card')
            @else
                @include('conversations.partials._bubble', ['message' => $message, 'msgs' => $msgs, 'i' => $i])
            @endif
        @endforeach
    </div>

    {{-- Composer --}}
    @if($ended)
        <div class="px-5 py-3 bg-[#E2E4EC]/40 border-t border-[#E2E4EC] flex-shrink-0">
            <p class="text-center text-[12px] text-[#5B6A8E] font-medium">
                This tenancy ended{{ $endedOn ? ' on '.$endedOn->format('M j, Y') : '' }}. The chat is read-only.
            </p>
        </div>
    @else
        <div class="px-3 sm:px-5 py-3 bg-white border-t border-[#E2E4EC] flex-shrink-0 flex items-center gap-2">
            @if($isTenant && $running)
                @include('conversations.partials._concern-sheet', ['categories' => \App\Models\TenantConcern::CATEGORIES])
            @endif
            <form id="message-form" class="flex min-w-0 flex-1 items-center gap-2">
                <label for="message-input" class="sr-only">Message {{ $otherParty->first_name }}</label>
                <input type="text" id="message-input" name="message" required maxlength="2000" autocomplete="off"
                    class="min-w-0 flex-1 bg-[#F7F8FC] border border-[#E2E4EC] focus:border-[#FF8A66] focus:bg-white focus:ring-2 focus:ring-[#FF8A66]/10 rounded-xl px-4 py-2.5 text-[13px] text-[#060D26] transition-all duration-200 outline-none"
                    placeholder="Message {{ $otherParty->first_name }}...">
                <button type="submit"
                    class="bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] font-bold text-[13px] px-4 h-10 rounded-xl shadow-sm cursor-pointer transition-all duration-200 inline-flex items-center gap-2">
                    <svg class="w-4 h-4 rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                    </svg>
                    <span class="hidden sm:inline">Send</span><span class="sr-only sm:hidden">Send</span>
                </button>
            </form>
        </div>
    @endif

    {{-- Photo lightbox --}}
    <div x-show="photo" x-cloak x-transition.opacity @click="photo = null"
        class="fixed inset-0 z-[210] flex cursor-pointer items-center justify-center bg-black/70 p-4">
        <button type="button" @click="photo = null" aria-label="Close photo"
            class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20">
            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
        <img :src="photo" alt="Concern photo" @click.stop class="max-h-[85vh] max-w-full cursor-default rounded-lg">
    </div>
</div>
