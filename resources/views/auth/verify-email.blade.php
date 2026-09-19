<x-guest-layout>
    <div class="mb-4 text-sm text-[#5B6A8E]">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

<<<<<<< HEAD
            <button type="submit" class="underline text-sm text-[#5B6A8E] hover:text-[#060D26] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#DA8E77]">
=======
            <button type="submit" class="underline text-sm text-[#5B6A8E] hover:text-[#060D26] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#FF8A66]">
>>>>>>> 092fb1454a20ae889717d4d8b1bee67f9c0c8eaa
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
