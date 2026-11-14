@extends('layouts.admin')

@section('page-title', 'System Settings')

@section('content')
<div class="max-w-[1000px] mx-auto">

    <x-page-header icon-path="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
        title="Two-factor sign-in" subtitle="A code from your phone, on top of your password." />

    @include('admin.settings._tabs')

    @if (! empty($recoveryCodes))
        <x-card class="mb-5 border-[#FF8A66]">
            <h2 class="font-jakarta text-[16px] font-bold text-[#060D26]">Save your recovery codes</h2>
            <p class="mt-1 text-[14px] text-[#5B6A8E]">If you lose your phone, each of these signs you in once. They are shown only now. Store them somewhere safe.</p>

            <ul id="recovery-codes" class="mt-4 grid grid-cols-2 gap-2 font-mono text-[15px] text-[#060D26]">
                @foreach ($recoveryCodes as $code)
                    <li class="rounded-lg border border-[#E2E4EC] bg-[#F7F8FC] px-3 py-2 text-center tracking-wider">{{ $code }}</li>
                @endforeach
            </ul>

            <button type="button" id="copy-codes" data-codes="{{ implode("\n", $recoveryCodes) }}"
                class="mt-4 inline-flex items-center justify-center h-11 px-5 rounded-xl bg-[#FF8A66] font-jakarta text-[14px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                Copy codes
            </button>
            <span id="copy-status" class="ml-3 text-[13px] text-[#5B6A8E]" role="status"></span>
        </x-card>
    @endif

    @if ($enabled)
        <x-card class="mb-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-jakarta text-[16px] font-bold text-[#060D26]">Two-factor is on</h2>
                    <p class="mt-1 text-[14px] text-[#5B6A8E]">You are asked for a code from your authenticator app each time you sign in.</p>
                </div>
                <span class="shrink-0 rounded-full bg-[#F7F8FC] border border-[#E2E4EC] px-3 py-1 text-[12px] font-bold text-[#060D26]">On</span>
            </div>
        </x-card>

        <x-card class="mb-5">
            <h2 class="font-jakarta text-[16px] font-bold text-[#060D26]">Recovery codes</h2>
            <p class="mt-1 text-[14px] text-[#5B6A8E]">
                {{ $remaining }} {{ \Illuminate\Support\Str::plural('code', $remaining) }} left.
                Making new codes cancels the old ones.
            </p>
            <form method="POST" action="{{ route('admin.security.two-factor.codes') }}" class="mt-4">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center h-11 px-5 rounded-xl border border-[#E2E4EC] bg-white font-jakarta text-[14px] font-bold text-[#060D26] hover:border-[#060D26]/40 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                    Regenerate recovery codes
                </button>
            </form>
        </x-card>

        <x-card>
            <h2 class="font-jakarta text-[16px] font-bold text-[#060D26]">Turn off two-factor</h2>
            @if ($required)
                <p class="mt-1 text-[14px] text-[#5B6A8E]">Admins must keep two-factor on, so this is not available.</p>
            @else
                <p class="mt-1 text-[14px] text-[#5B6A8E]">Your account goes back to password-only sign-in. You will be asked for your password first.</p>
                <form method="POST" action="{{ route('admin.security.two-factor.destroy') }}" class="mt-4" data-requires-password>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center justify-center h-11 px-5 rounded-xl border border-[#DC2626] bg-white font-jakarta text-[14px] font-bold text-[#DC2626] hover:bg-[#DC2626] hover:text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#DC2626] focus-visible:ring-offset-2">
                        Turn off two-factor
                    </button>
                </form>
            @endif
        </x-card>
    @else
        <x-card>
            <ol class="space-y-6">
                <li>
                    <h2 class="font-jakarta text-[16px] font-bold text-[#060D26]">1. Scan this code</h2>
                    <p class="mt-1 text-[14px] text-[#5B6A8E]">Open an authenticator app (Google Authenticator, Authy, 1Password) and scan it.</p>
                    <div class="mt-4 inline-block rounded-xl border border-[#E2E4EC] bg-white p-2 [&>svg]:block [&>svg]:w-[200px] [&>svg]:h-[200px]">{!! $qrSvg !!}</div>
                    <p class="mt-4 text-[13px] text-[#5B6A8E]">Can't scan? Enter this key by hand:</p>
                    <p class="mt-1 break-all font-mono text-[15px] font-semibold tracking-wider text-[#060D26] select-all">{{ $secret }}</p>
                </li>
                <li>
                    <h2 class="font-jakarta text-[16px] font-bold text-[#060D26]">2. Enter the 6-digit code</h2>
                    <form method="POST" action="{{ route('admin.security.two-factor.store') }}" class="mt-3 flex flex-col sm:flex-row sm:items-start gap-3" novalidate>
                        @csrf
                        <div class="flex-1 sm:max-w-[220px]">
                            <label for="code" class="sr-only">6-digit code</label>
                            <input id="code" type="text" name="code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="123456"
                                class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[18px] tracking-[0.25em] text-[#060D26] placeholder-[#5B6A8E]/50 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors"
                                @error('code') aria-invalid="true" @enderror />
                            <x-input-error :messages="$errors->get('code')" class="mt-1.5" role="alert" />
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center h-[50px] px-6 rounded-xl bg-[#FF8A66] font-jakarta text-[15px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                            Turn on two-factor
                        </button>
                    </form>
                </li>
            </ol>
        </x-card>
    @endif
</div>

@if (! empty($recoveryCodes))
<script>
    document.getElementById('copy-codes')?.addEventListener('click', async function () {
        const status = document.getElementById('copy-status');
        try {
            await navigator.clipboard.writeText(this.dataset.codes);
            status.textContent = 'Copied.';
        } catch (e) {
            status.textContent = 'Could not copy. Select the codes and copy them by hand.';
        }
    });
</script>
@endif
@endsection
