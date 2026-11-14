{{--
    "Where you're signed in": every browser session and mobile-app login for this account, with a way to end them.
    One password box serves both actions (sign out one device, sign out all the others), so a stolen open tab can't
    sign the owner out of everything without knowing the password. Mobile-first: rows stack, buttons are 44px tall.
    Inside Account Settings the x-settings-panel draws the card; on the admin profile this partial does.
    Optional: $sessions (a SessionManager::forUser() collection), $inPanel.
--}}
@php
    $sessions ??= app(\App\Services\Security\SessionManager::class)->forUser(auth()->user(), session()->getId());
    $bag = $errors->sessions;
    $others = $sessions->where('is_current', false)->count();
    $inputClass = 'h-11 w-full rounded-xl border border-[#E2E4EC] bg-[#E2E4EC]/30 px-3.5 text-sm text-[#060D26] outline-none transition focus:border-[#FF8A66] focus:bg-white focus:ring-2 focus:ring-[#FF8A66]/15';
@endphp

@unless($inPanel ?? false)
<div class="rounded-2xl border border-[#E2E4EC] bg-white p-4 sm:p-6 shadow-[0_1px_3px_rgba(6,13,38,0.06)]">
    <h4 class="text-[15px] font-semibold text-[#060D26]">Where you're signed in</h4>
    <p class="mt-0.5 mb-4 text-[13px] text-[#5B6A8E]">Phones, tablets and computers that are signed in to your account.</p>
@endunless

    <div x-data="{ target: @js($bag->isNotEmpty() ? 'others' : null) }"
         data-session-url="{{ route('account.sessions.destroy', ['id' => '__ID__']) }}">

        <ul class="divide-y divide-[#E2E4EC] rounded-xl border border-[#E2E4EC]">
            @foreach($sessions as $item)
                <li class="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 text-[14px] font-semibold text-[#060D26]">
                            <span class="break-words">{{ $item->device }}</span>
                            @if($item->is_current)
                                <span class="rounded-full bg-[#ECEEF6] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#060D26]">This device</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">
                            @if($item->ip){{ $item->ip }} · @endif
                            {{ $item->is_current || $item->last_active->gt(now()->subMinutes(5)) ? 'Active now' : 'Active '.$item->last_active->diffForHumans() }}
                        </p>
                    </div>
                    @unless($item->is_current)
                        <button type="button" x-on:click="target = @js($item->id)"
                            class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-[#E2E4EC] px-4 text-[13px] font-semibold text-[#060D26] transition hover:bg-[#F7F8FC] sm:w-auto">
                            Sign out
                        </button>
                    @endunless
                </li>
            @endforeach
        </ul>

        @if($others > 0)
            <button type="button" x-show="target === null" x-on:click="target = 'others'"
                class="mt-4 inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-[#FF8A66] px-5 text-[13px] font-semibold text-[#060D26] transition hover:bg-[#E96F4F] active:scale-[0.98] sm:w-auto">
                Sign out other devices
            </button>
        @else
            <p class="mt-3 text-[13px] text-[#5B6A8E]">You're only signed in on this device.</p>
        @endif

        {{-- Password check: shared by "Sign out" on one row and "Sign out other devices". --}}
        <form method="POST" x-cloak x-show="target !== null" novalidate
            :action="target === 'others' ? @js(route('account.sessions.destroyOthers')) : $root.dataset.sessionUrl.replace('__ID__', encodeURIComponent(target ?? ''))"
            class="mt-4 rounded-xl bg-[#F7F8FC] p-4">
            @csrf
            @method('DELETE')
            <label for="sessions_password" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#5B6A8E]">
                Enter your password to continue
            </label>
            <input id="sessions_password" name="password" type="password" autocomplete="current-password" required class="{{ $inputClass }}">
            @foreach($bag->all() as $message)
                <span class="mt-1.5 block text-xs font-medium text-[#DC2626]" role="alert">{{ $message }}</span>
            @endforeach
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <button type="submit"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg bg-[#FF8A66] px-5 text-[13px] font-semibold text-[#060D26] transition hover:bg-[#E96F4F] active:scale-[0.98] sm:w-auto"
                    x-text="target === 'others' ? 'Sign out other devices' : 'Sign out this device'">Sign out</button>
                <button type="button" x-on:click="target = null"
                    class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg px-4 text-[13px] font-semibold text-[#5B6A8E] transition hover:text-[#060D26] sm:w-auto">
                    Cancel
                </button>
            </div>
        </form>
    </div>

@unless($inPanel ?? false)
</div>
@endunless
