@php
    $avg = \App\Models\PlatformRating::avg('rating');
    $count = \App\Models\PlatformRating::count();
    $mine = auth()->check() ? \App\Models\PlatformRating::where('user_id', auth()->id())->first() : null;
    $starPath = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';
    $labels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great', 5 => 'Excellent'];
    $starWords = [5 => 'Five', 4 => 'Four', 3 => 'Three', 2 => 'Two', 1 => 'One'];

    // Public feed: written reviews of 4 stars and up, newest first. Names show as "Juan D.".
    $reviews = \App\Models\PlatformRating::with('user')
        ->whereNotNull('comment')->where('comment', '!=', '')->where('rating', '>=', 4)
        ->latest()->limit(3)->get();
    $spread = \App\Models\PlatformRating::selectRaw('rating, count(*) as n')->groupBy('rating')->pluck('n', 'rating');
@endphp

{{-- Plain white band so it reads as one calm block: summary on top, recent feedback left, review form right. --}}
<section id="rate-us" class="scroll-mt-24 bg-white" aria-labelledby="rate-us-title">
    <div class="mx-auto max-w-[1100px] px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
        <h2 id="rate-us-title" class="font-jakarta text-[26px] font-extrabold tracking-tight text-[#060D26] sm:text-[32px]">Platform ratings</h2>
        <p class="mt-2 max-w-[52ch] text-[14.5px] leading-relaxed text-[#5B6A8E]">See how people rate AbangananHub, and tell us how it is working for you.</p>

        {{-- Summary: breakdown bars + average --}}
        <div class="mt-8 grid gap-6 md:grid-cols-[1fr_340px] md:items-stretch md:gap-10">
            <ul class="space-y-3" aria-label="Rating breakdown">
                @foreach($starWords as $star => $word)
                    @php $n = (int) ($spread[$star] ?? 0); $pct = $count > 0 ? round($n / $count * 100) : 0; @endphp
                    <li class="flex items-center gap-3 text-[12.5px]">
                        <span class="w-12 font-semibold uppercase tracking-wide text-[#060D26]">{{ $word }}</span>
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="#FBBF24" aria-hidden="true"><path d="{{ $starPath }}" /></svg>
                        <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-[#FEF3C7]"><span class="block h-full rounded-full bg-[#FBBF24]" style="width: {{ $pct }}%"></span></span>
                        <span class="w-10 text-right font-semibold tabular-nums text-[#060D26]">{{ number_format($n) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-col items-center justify-center rounded-2xl bg-[#FFFBEB] px-6 py-8 text-center">
                @if($count > 0)
                    <span class="font-jakarta text-[44px] font-extrabold leading-none tabular-nums text-[#060D26]">{{ number_format($avg, 1) }}</span>
                    <x-star-rating :rating="$avg" size="md" :show-value="false" class="mt-3 [&_svg]:h-6 [&_svg]:w-6" />
                    <span class="mt-3 text-[14px] font-medium text-[#060D26]">{{ number_format($count) }} {{ \Illuminate\Support\Str::plural('Rating', $count) }}</span>
                @else
                    <span class="font-jakarta text-[18px] font-extrabold text-[#060D26]">No ratings yet</span>
                    <span class="mt-1 text-[13.5px] text-[#5B6A8E]">Be the first to rate.</span>
                @endif
            </div>
        </div>

        <hr class="my-10 border-[#E2E4EC]">

        <div class="grid gap-10 md:grid-cols-2 md:gap-12">
            {{-- Recent feedback --}}
            <div>
                <h3 class="font-jakarta text-[20px] font-extrabold text-[#060D26]">Recent feedback</h3>
                @if($reviews->isNotEmpty())
                    <ul class="mt-5 space-y-4">
                        @foreach($reviews as $review)
                            @php $name = $review->user?->shortName() ?: 'AbangananHub user'; @endphp
                            <li class="flex gap-4 rounded-2xl border border-[#E2E4EC] p-4">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-[#FFE4D9] font-jakarta text-[18px] font-extrabold text-[#B35A3D]" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate font-jakarta text-[14px] font-bold text-[#060D26]">{{ $name }}</p>
                                        <x-star-rating :rating="$review->rating" :show-value="false" class="shrink-0" />
                                    </div>
                                    <p class="mt-1.5 text-[13.5px] leading-relaxed text-[#5B6A8E]">{{ $review->comment }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-5 rounded-2xl border border-dashed border-[#E2E4EC] p-6 text-[14px] text-[#5B6A8E]">Written reviews will show up here. Yours could be the first.</p>
                @endif
            </div>

            {{-- Add a review --}}
            <div id="rate-us-form">
                <h3 class="font-jakarta text-[20px] font-extrabold text-[#060D26]">{{ $mine ? 'Update your review' : 'Add a review' }}</h3>
                @auth
                    <form method="POST" action="{{ route('platform-rating.store') }}" class="mt-5"
                        x-data="{ rating: {{ $mine?->rating ?? 0 }}, hover: 0, text: @js(old('comment', $mine?->comment ?? '')), labels: @js($labels) }">
                        @csrf
                        <input type="hidden" name="rating" :value="rating">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[13px] font-semibold text-[#060D26]">Your rating</p>
                            <span class="rounded-full bg-[#FFFBEB] px-3 py-1 text-[12px] font-semibold text-[#92400E]" aria-live="polite"
                                x-text="labels[hover || rating] || 'Tap a star'"></span>
                        </div>
                        <div class="mt-2 flex gap-1" role="radiogroup" aria-label="Rating">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" role="radio" :aria-checked="rating === {{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                                    @click="rating = {{ $i }}" @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
                                    class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl transition-transform duration-200 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] motion-reduce:hover:translate-y-0">
                                    <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" :fill="(hover || rating) >= {{ $i }} ? '#FBBF24' : '#E2E4EC'"><path d="{{ $starPath }}" /></svg>
                                </button>
                            @endfor
                        </div>
                        @error('rating') <p class="mt-2 text-[12.5px] text-[#DC2626]">{{ $message }}</p> @enderror
                        <label for="rate-us-comment" class="mt-4 block text-[13px] font-semibold text-[#060D26]">Your review <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                        <textarea id="rate-us-comment" name="comment" rows="5" maxlength="1000" x-model="text" placeholder="Write here..."
                            class="mt-1.5 w-full rounded-xl border border-[#E2E4EC] px-3.5 py-2.5 text-[14px] text-[#060D26] placeholder:text-[#5B6A8E] focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30"></textarea>
                        <p class="mt-1 flex justify-between gap-3 text-[11.5px] text-[#5B6A8E]">
                            <span>4 and 5 star comments may be shown here as "Juan D.".</span>
                            <span class="tabular-nums"><span x-text="text.length"></span>/1000</span>
                        </p>
                        <button type="submit" :disabled="! rating"
                            class="mt-4 h-12 w-full cursor-pointer rounded-xl bg-[#FF8A66] font-jakarta text-[15px] font-extrabold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ $mine ? 'Update review' : 'Submit' }}
                        </button>
                    </form>
                @else
                    <div class="mt-5 rounded-2xl border border-[#E2E4EC] p-6">
                        <div class="flex gap-1" aria-hidden="true">
                            @for($i = 1; $i <= 5; $i++)
                                <svg width="30" height="30" viewBox="0 0 24 24" fill="#E2E4EC"><path d="{{ $starPath }}" /></svg>
                            @endfor
                        </div>
                        <p class="mt-4 text-[14px] leading-relaxed text-[#5B6A8E]">Log in to leave a rating. It takes a few seconds.</p>
                        <a href="{{ route('login') }}" class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-xl bg-[#FF8A66] font-jakarta text-[15px] font-extrabold text-[#060D26] no-underline transition-colors duration-200 hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26] focus-visible:ring-offset-2">Log in to rate</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</section>
