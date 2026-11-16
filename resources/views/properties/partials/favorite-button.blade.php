{{-- Heart on the listing photo. Used by the phone strip and the desktop bento, so the two stay in sync:
     a tap dispatches `favorite-changed` and every heart for the same property follows it.
     Optimistic: it flips on tap and rolls back if the save fails.
     Props: $property, $isFavorited, $class (position classes). 44px touch target. --}}
@if(auth()->check() && ! $isOwner && auth()->user()->hasRole('Tenant'))
    <div class="{{ $class }}" x-data="{
            fav: @js($isFavorited ?? false),
            busy: false,
            async toggleFav() {
                if (this.busy) return;
                const before = this.fav;
                this.busy = true;
                this.fav = !before;
                window.dispatchEvent(new CustomEvent('favorite-changed', { detail: { id: {{ $property->property_id }}, fav: this.fav } }));
                try {
                    const res = await fetch('{{ route('favorites.toggle', $property->property_id) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('toggle failed');
                    const d = await res.json();
                    this.fav = d.favorited;
                } catch (e) {
                    this.fav = before;
                } finally {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('favorite-changed', { detail: { id: {{ $property->property_id }}, fav: this.fav } }));
                }
            }
        }"
        @favorite-changed.window="if ($event.detail.id === {{ $property->property_id }}) fav = $event.detail.fav"
        @click.stop>
        <button type="button" @click="toggleFav()"
            :aria-pressed="fav"
            class="w-11 h-11 rounded-full bg-white/90 hover:brightness-95 active:scale-95 flex items-center justify-center shadow-sm transition-all cursor-pointer touch-manipulation">
            <span class="sr-only" x-text="fav ? 'Remove from favorites' : 'Add to favorites'"></span>
            <svg class="w-5 h-5 transition-colors" :class="fav ? 'text-[#EF4444]' : 'text-[#5B6A8E]'"
                :fill="fav ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"
                stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
            </svg>
        </button>
    </div>
@endif
