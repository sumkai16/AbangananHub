<x-guest-layout>
    <x-slot name="rightContent">
        @include('auth.partials.brand-panel')
    </x-slot>

    <div class="flex-1 flex flex-col justify-center w-full max-w-md mx-auto px-6 sm:px-8 py-10">

        <a href="/" class="group inline-flex items-center gap-2 self-start">
            <x-brand-logo mark="w-9 h-9" text="text-[18px]" />
        </a>

        <h2 class="mt-10 font-jakarta text-[28px] sm:text-[32px] font-extrabold text-[#060D26] tracking-tight leading-tight">Two-step sign-in</h2>
        <p id="tf-help" class="mt-1.5 text-[15px] text-[#5B6A8E]">Enter the 6-digit code from your authenticator app.</p>

        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-8 space-y-5" novalidate>
            @csrf

            <div>
                <label id="tf-label" for="code" class="block font-jakarta text-[13px] font-bold text-[#060D26] mb-1.5">Authentication code</label>
                <input id="code" type="text" name="code" required autofocus autocomplete="one-time-code"
                    inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="123456"
                    class="w-full px-4 py-3 bg-[#F7F8FC] focus:bg-white border border-[#E2E4EC] rounded-xl text-[20px] sm:text-[18px] tracking-[0.25em] text-[#060D26] placeholder-[#5B6A8E]/50 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/25 focus:outline-none transition-colors"
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror />
                <x-input-error :messages="$errors->get('code')" class="mt-1.5" role="alert" id="code-error" />
            </div>

            <button type="submit" class="w-full font-jakarta bg-[#FF8A66] text-[#060D26] font-bold py-3.5 rounded-xl hover:bg-[#E96F4F] active:scale-[0.99] transition-all duration-200 text-[15px] cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2">
                Verify
            </button>
        </form>

        <button type="button" id="tf-toggle" class="mt-5 self-start text-[14px] font-semibold text-[#060D26] hover:text-[#B35A3D] hover:underline transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] rounded">
            Use a recovery code instead
        </button>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="text-[14px] text-[#5B6A8E] hover:text-[#060D26] hover:underline transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] rounded">
                Sign out
            </button>
        </form>

        <p class="mt-6 text-[12px] text-[#5B6A8E]">&copy; {{ date('Y') }} AbangananHub</p>
    </div>
</x-guest-layout>

<script>
    (function () {
        const input = document.getElementById('code');
        const toggle = document.getElementById('tf-toggle');
        const help = document.getElementById('tf-help');
        const label = document.getElementById('tf-label');
        let recovery = false;

        toggle.addEventListener('click', function () {
            recovery = !recovery;
            input.value = '';
            if (recovery) {
                input.setAttribute('inputmode', 'text');
                input.removeAttribute('pattern');
                input.setAttribute('maxlength', '9');
                input.setAttribute('placeholder', 'XXXX-XXXX');
                input.setAttribute('autocomplete', 'off');
                input.classList.add('uppercase');
                help.textContent = 'Enter one of the recovery codes you saved. Each one works once.';
                label.textContent = 'Recovery code';
                toggle.textContent = 'Use the authenticator app instead';
            } else {
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('pattern', '[0-9]*');
                input.setAttribute('maxlength', '6');
                input.setAttribute('placeholder', '123456');
                input.setAttribute('autocomplete', 'one-time-code');
                input.classList.remove('uppercase');
                help.textContent = 'Enter the 6-digit code from your authenticator app.';
                label.textContent = 'Authentication code';
                toggle.textContent = 'Use a recovery code instead';
            }
            input.focus();
        });
    })();
</script>
