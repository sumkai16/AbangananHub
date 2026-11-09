@php
    $avg = \App\Models\PlatformRating::avg('rating');
    $count = \App\Models\PlatformRating::count();
    $mine = auth()->check() ? \App\Models\PlatformRating::where('user_id', auth()->id())->first() : null;
    $starPath = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
    $labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great', 5 => 'Excellent'];
@endphp

{{-- Warm cream band with a soft coral glow: deliberately not the cool lavender + texture of the owner CTA above it,
     and it sits right above the navy footer, so the page steps lavender -> cream -> navy. --}}
<section id="rate-us" class="relative isolate scroll-mt-24 overflow-hidden bg-[#FFF6F1]" aria-labelledby="rate-us-title">
    <div class="pointer-events-none absolute -left-24 -top-24 -z-10 h-[420px] w-[420px] rounded-full bg-[#FF8A66]/20 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-32 -right-24 -z-10 h-[380px] w-[380px] rounded-full bg-[#FFD3C2]/50 blur-3xl" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-[1400px] gap-10 px-4 py-14 sm:px-6 sm:py-20 md:grid-cols-2 md:items-center lg:gap-20 lg:px-8">

        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-[#FF8A66]/40 bg-white/70 px-3 py-1 font-jakarta text-[10.5px] font-bold uppercase tracking-[0.14em] text-[#B35A3D]">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="#FF8A66" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                Your feedback
            </span>
            <h2 id="rate-us-title" class="mt-4 font-jakarta text-[28px] font-extrabold leading-[1.12] tracking-tight text-[#060D26] text-balance sm:text-[38px]">How is AbangananHub working for you?</h2>
            <p class="mt-4 max-w-[46ch] text-[15px] leading-relaxed text-[#5B6A8E]">Tell us what is working and what is not. Every rating goes straight to the team that builds the platform.</p>

            <div class="mt-8 flex items-center gap-5">
                @if($count > 0)
                    <span class="font-jakarta text-[56px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ number_format($avg, 1) }}</span>
                    <span class="border-l border-[#060D26]/10 pl-5">
                        <x-star-rating :rating="$avg" size="md" :show-value="false" />
                        <span class="mt-1.5 block text-[13px] text-[#5B6A8E]">from {{ number_format($count) }} {{ \Illuminate\Support\Str::plural('rating', $count) }}</span>
                    </span>
                @else
                    <span class="rounded-2xl border border-dashed border-[#FF8A66]/50 bg-white/60 px-4 py-3 text-[13.5px] font-medium text-[#060D26]">No ratings yet. Be the first.</span>
                @endif
            </div>
        </div>

        <div class="rounded-3xl border border-[#F3D9CD] bg-white p-6 shadow-[0_24px_48px_-24px_rgba(179,90,61,0.35)] sm:p-8">
            @auth
                <form method="POST" action="{{ route('platform-rating.store') }}"
                    x-data="{ rating: {{ $mine?->rating ?? 0 }}, hover: 0, text: @js(old('comment', $mine?->comment ?? '')), labels: @js($labels) }">
                    @csrf
                    <input type="hidden" name="rating" :value="rating">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-jakarta text-[17px] font-bold text-[#060D26]">{{ $mine ? 'Update your rating' : 'Rate your experience' }}</p>
                        <span class="rounded-full bg-[#FFF6F1] px-3 py-1 text-[12px] font-semibold text-[#B35A3D]" aria-live="polite"
                            x-text="labels[hover || rating] || 'Tap a star'"></span>
                    </div>
                    <div class="mt-4 flex gap-1.5" role="radiogroup" aria-label="Rating">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" role="radio" :aria-checked="rating === {{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                                @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
                                class="flex h-12 w-12 cursor-pointer items-center justify-center rounded-xl transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#FFF6F1] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] motion-reduce:hover:translate-y-0">
                                <svg width="34" height="34" viewBox="0 0 24 24" aria-hidden="true" :fill="(hover || rating) >= {{ $i }} ? '#FBBF24' : '#E2E4EC'"><path d="{{ $starPath }}" /></svg>
                            </button>
                        @endfor
                    </div>
                    @error('rating') <p class="mt-2 text-[12.5px] text-[#DC2626]">{{ $message }}</p> @enderror
                    <label for="rate-us-comment" class="mt-5 block text-[12.5px] font-semibold text-[#060D26]">Comment <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                    <textarea id="rate-us-comment" name="comment" rows="3" maxlength="1000" x-model="text" placeholder="Anything we should know?"
                        class="mt-1.5 w-full rounded-xl border border-[#E2E4EC] px-3.5 py-2.5 text-[14px] text-[#060D26] placeholder:text-[#5B6A8E] focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30"></textarea>
                    <p class="mt-1 text-right text-[11.5px] tabular-nums text-[#5B6A8E]"><span x-text="text.length"></span>/1000</p>
                    <button type="submit" :disabled="! rating"
                        class="mt-2 h-12 w-full cursor-pointer rounded-xl bg-[#FF8A66] font-jakarta text-[15px] font-extrabold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $mine ? 'Update rating' : 'Submit rating' }}
                    </button>
                </form>
            @else
                <p class="font-jakarta text-[17px] font-bold text-[#060D26]">Rate your experience</p>
                <div class="mt-4 flex gap-1.5" aria-hidden="true">
                    @for($i = 1; $i <= 5; $i++)
                        <span class="flex h-12 w-12 items-center justify-center"><svg width="34" height="34" viewBox="0 0 24 24" fill="#E2E4EC"><path d="{{ $starPath }}" /></svg></span>
                    @endfor
                </div>
                <p class="mt-4 text-[14px] leading-relaxed text-[#5B6A8E]">Log in to leave a rating. It takes a few seconds.</p>
                <a href="{{ route('login') }}" class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#FF8A66] font-jakarta text-[15px] font-extrabold text-[#060D26] no-underline transition-colors duration-200 hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26] focus-visible:ring-offset-2">Log in to rate</a>
            @endauth
        </div>
    </div>
</section>
