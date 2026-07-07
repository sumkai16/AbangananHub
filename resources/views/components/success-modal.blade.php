@props([
    'name',
    'show' => false,
    'title' => 'Success!',
    'message' => 'Action completed successfully.',
    'buttonText' => 'Continue',
])

<x-modal :name="$name" :show="$show" maxWidth="sm">
    <div class="relative p-6 text-center">
        <!-- Close Button -->
        <button x-on:click="$dispatch('close')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors focus:outline-none">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Animated Checkmark Icon -->
        <div class="mx-auto flex items-center justify-center w-16 h-16 rounded-full bg-[#2AA7A1]/10 mb-5 relative">
            <div class="absolute inset-0 rounded-full animate-ping bg-[#2AA7A1]/20" style="animation-duration: 2s;"></div>
            <svg class="w-8 h-8 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <!-- Text Content -->
        <h3 class="text-xl font-black text-[#156F8C] mb-2 tracking-tight">
            {{ $title }}
        </h3>
        <p class="text-[14px] text-gray-500 font-medium mb-6 leading-relaxed px-2">
            {{ $message }}
        </p>

        <!-- Action Button -->
        <button x-on:click="$dispatch('close')" class="w-full inline-flex justify-center items-center px-5 py-3 bg-[#2AA7A1] hover:brightness-95 active:brightness-90 text-white text-[15px] font-bold rounded-xl shadow-sm hover:shadow-md transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#2AA7A1] focus:ring-offset-2">
            {{ $buttonText }}
        </button>
    </div>
</x-modal>
