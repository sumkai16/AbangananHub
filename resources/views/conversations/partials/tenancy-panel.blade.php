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
    // Both sides see the chat and the stayed property as two separate cards.
    $sheet = true;
    $stay = $conversation->property;
    $rent = $sheet && $reservation ? \App\Services\RentLedger::for($reservation)->summary() : null;
    $rentLabel = match ($rent['paymentStatus'] ?? null) {
        'paid' => ['Paid', 'text-[#16A34A]'],
        'paid_ahead' => ['Paid ahead', 'text-[#16A34A]'],
        'partial' => ['Partly paid', 'text-[#8A5A00]'],
        'overdue' => ['Overdue ₱'.number_format($rent['overdueAmount'] ?? 0, 2), 'text-[#DC2626]'],
        default => ['Not due yet', 'text-[#5B6A8E]'],
    };
    $tenantUser = $conversation->tenant;
    $canRemind = $isLandlord && ! $ended && $tenantUser && ! $tenantUser->is_walk_in && $tenantUser->account_status === 'active'
        && ($rent['outstanding'] ?? 0) > 0;
    $unresolved = $conversation->concerns->whereIn('status', ['Open', 'In Progress'])->sortByDesc('created_at');
    $stayPhoto = $conversation->unit?->media->firstWhere('media_type', 'Image')?->media_url
        ?? $stay?->media->firstWhere('media_type', 'Image')?->media_url;
@endphp
<div class="flex h-full {{ $sheet ? 'gap-4' : 'flex-col' }}" id="chat-panel-root" data-conversation-id="{{ $conversation->conversation_id }}"
    x-data="{ photo: null }" @keydown.escape.window="photo = null">
<div class="flex flex-col min-h-0 min-w-0 flex-1 {{ $sheet ? 'overflow-hidden rounded-2xl border border-[#E2E4EC] bg-white shadow-[0_1px_3px_rgba(6,13,38,0.06)]' : '' }}">

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
        @if($isTenant && $reservation)
            <a href="{{ route('tenancy.show', $reservation) }}"
                class="inline-flex h-8 flex-shrink-0 items-center gap-1.5 rounded-lg border border-[#E2E4EC] px-3 text-[11px] font-bold text-[#5B6A8E] transition-colors duration-200 hover:bg-[#F7F8FC] hover:text-[#060D26]">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                Tenancy &amp; rent
            </a>
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
            @elseif($message->kind === 'payment_qr')
                @include('conversations.partials._payment-qr-card')
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
            @if($isLandlord && $running)
                @include('conversations.partials._payment-qr-sheet')
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

@if($sheet && $stay)
    {{-- Where this tenant lives now --}}
    <x-property-side-card :property="$stay" :unit="$conversation->unit" :photo="$stayPhoto" eyebrow="Currently staying at">
        <div class="border-t border-[#E2E4EC] pt-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#5B6A8E]">Tenancy</p>
            <dl class="mt-2 flex flex-col gap-1.5 text-[13px]">
                @if($rent)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Monthly rent</dt><dd class="font-semibold text-[#060D26]">₱{{ number_format($rent['monthlyRent'], 2) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Due every</dt><dd class="font-semibold text-[#060D26]">{{ \Illuminate\Support\Number::ordinal($rent['dueDay']) }}</dd></div>
                    @if(! $ended)
                        <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Rent status</dt><dd class="font-semibold {{ $rentLabel[1] }}">{{ $rentLabel[0] }}</dd></div>
                        @if($rent['nextDueDate'] && $rent['paymentStatus'] !== 'overdue')
                            <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Next due</dt><dd class="font-semibold text-[#060D26]">{{ $rent['nextDueDate']->format('M j, Y') }}</dd></div>
                        @endif
                    @endif
                @endif
                @if($since)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">{{ $isLandlord ? 'Tenant since' : 'Living here since' }}</dt><dd class="font-semibold text-[#060D26]">{{ $since }}</dd></div>
                @endif
                @if($ended && $endedOn)
                    <div class="flex justify-between gap-3"><dt class="text-[#5B6A8E]">Ended</dt><dd class="font-semibold text-[#060D26]">{{ $endedOn->format('M j, Y') }}</dd></div>
                @endif
            </dl>
            @if($canRemind)
                {{-- Rent reminder: the same notification the Tenants cards send, posted by fetch so the chat stays open. --}}
                <div class="relative mt-3" x-data="{
                        busy: false, note: null, ok: true,
                        async send() {
                            if (this.busy) return;
                            this.busy = true;
                            try {
                                const r = await fetch('{{ route('landlord.tenancies.remind', $reservation) }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                });
                                const d = await r.json().catch(() => ({}));
                                this.ok = r.ok;
                                this.note = d.message || (r.ok ? 'Reminder sent.' : 'Could not send the reminder.');
                            } catch (e) {
                                this.ok = false;
                                this.note = 'Could not send the reminder.';
                            }
                            this.busy = false;
                            setTimeout(() => this.note = null, 4000);
                        }
                    }">
                    <button type="button" @click="send()" :disabled="busy"
                        title="Notify {{ $otherParty->first_name }} that rent is due"
                        class="inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg border px-3 text-[12px] font-bold transition-colors duration-200 disabled:opacity-60 {{ ($rent['overdueCount'] ?? 0) > 0 ? 'border-[#EF4444]/40 text-[#DC2626] hover:bg-[#EF4444]/[0.06]' : 'border-[#E2E4EC] text-[#060D26] hover:bg-[#F7F8FC]' }}">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                        <span>Remind about rent</span>
                    </button>
                    <p x-show="note" x-cloak x-text="note" role="status"
                        :class="ok ? 'bg-[#060D26] text-white' : 'bg-[#EF4444] text-white'"
                        class="absolute bottom-[calc(100%+6px)] left-0 right-0 z-10 rounded-lg px-3 py-1.5 text-center text-[12px] font-medium shadow-lg"></p>
                </div>
            @endif
        </div>
        <div class="border-t border-[#E2E4EC] pt-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-[#5B6A8E]">Open concerns</p>
            @forelse($unresolved->take(3) as $concern)
                <div class="mt-2 rounded-xl border border-[#E2E4EC] px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[12px] font-semibold text-[#060D26]">{{ $concern->category }}</p>
                        <span class="rounded-full px-2 text-[10px] font-bold {{ $concern->status === 'Open' ? 'bg-[#FBBF24]/20 text-[#8A5A00]' : 'bg-[#ECEEF6] text-[#5B6A8E]' }}">{{ $concern->status }}</span>
                    </div>
                    <p class="mt-0.5 line-clamp-2 text-[12px] leading-5 text-[#5B6A8E]">{{ $concern->description }}</p>
                    <p class="mt-1 text-[11px] text-[#5B6A8E]">{{ $concern->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <p class="mt-1.5 text-[12px] text-[#5B6A8E]">Nothing to fix right now.</p>
            @endforelse
            @if($unresolved->count() > 3)
                <p class="mt-2 text-[11px] text-[#5B6A8E]">+ {{ $unresolved->count() - 3 }} more in the chat</p>
            @endif
        </div>
    </x-property-side-card>
@endif
</div>
