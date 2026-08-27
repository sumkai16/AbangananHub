@props(['current', 'property', 'checklist' => null])

{{--
    Property wizard step rail — mirrors the established multi-step pattern
    from landlord/verification/_stepper.blade.php (DESIGN.md §6f): sticky
    vertical rail on desktop, horizontal progress bar on mobile. Rendered
    server-side per page load (this wizard is multi-page, not an Alpine
    single-page stepper like verification), so current is read from $current
    rather than reactive Alpine state.

    Optional $checklist (PropertyWizardController::buildChecklist(), the
    same array the Review page uses) makes done/reachable data-driven rather
    than position-based: a step is checked and clickable — forward or back —
    once its own data is actually complete, not just because it sits before
    wherever $current happens to be. Without a $checklist (the two no-property
    create steps, where nothing can be complete yet) it falls back to the
    simple index comparison.
--}}

@php
    $steps = [
        ['key' => 'info',      'n' => 1, 'label' => 'Property Info'],
        ['key' => 'location',  'n' => 2, 'label' => 'Location'],
        ['key' => 'amenities', 'n' => 3, 'label' => 'Amenities'],
        ['key' => 'documents', 'n' => 4, 'label' => 'Documents'],
        ['key' => 'units',     'n' => 5, 'label' => 'Units'],
        ['key' => 'review',    'n' => 6, 'label' => 'Review'],
    ];
    $order = array_column($steps, 'key');
    $currentIndex = array_search($current, $order, true);

    // Every GET-step route name except 'info' and 'location' is the bare
    // properties.wizard.{step}; those two carry a .edit suffix when a
    // property exists (and info alone has a no-property variant).
    $stepRoute = function (string $key) use ($property) {
        return match ($key) {
            'info' => $property ? route('properties.wizard.info.edit', $property) : route('properties.create'),
            'location' => route('properties.wizard.location.edit', $property),
            default => route('properties.wizard.' . $key, $property),
        };
    };
@endphp

{{-- ── Mobile: horizontal bar ─────────────────────────── --}}
<div class="lg:hidden mb-7">
    <div class="flex items-center gap-3">
        @if($currentIndex > 0)
            <a href="{{ $stepRoute($steps[$currentIndex - 1]['key']) }}"
                class="p-1.5 -ml-1.5 rounded-lg text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors">
                <span class="sr-only">Back to the previous step</span>
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </a>
        @endif
        <div class="flex gap-1.5 flex-1">
            @foreach($steps as $s)
                <div class="h-1 flex-1 rounded-full {{ array_search($s['key'], $order, true) <= $currentIndex ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]' }}"></div>
            @endforeach
        </div>
        <span class="text-xs font-semibold text-[#64748B] whitespace-nowrap">Step {{ $currentIndex + 1 }} of {{ count($steps) }}</span>
    </div>
</div>

{{-- ── Desktop: vertical rail ─────────────────────────── --}}
<aside class="hidden lg:block lg:sticky lg:top-8">
    <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Add Property</p>
    <p class="mt-1 text-xs text-[#64748B] leading-relaxed">Progress is saved automatically — leave anytime and pick up where you left off.</p>

    <ol class="mt-5 space-y-0.5">
        @foreach($steps as $s)
            @php
                $index = array_search($s['key'], $order, true);
                $isCurrent = $s['key'] === $current;

                // Done-ness is data-driven (the same $checklist the Review
                // page already computes via buildChecklist()), not "have I
                // scrolled past this step's position" — position tells you
                // nothing once a landlord clicks back to fix an earlier
                // step, at which point every later step is still actually
                // complete and should stay checked + reachable. Review has
                // no checklist entry of its own; it's "done" (and so never
                // checked, since you don't check off the step you're
                // reviewing from) once every other step is.
                $isDone = $checklist === null
                    ? $index < $currentIndex
                    : ($s['key'] === 'review' ? false : ($checklist[$s['key']]['complete'] ?? false));

                // Clickable in both directions once its own data is
                // complete — Review additionally needs every prior step
                // done, since jumping there early would land on a checklist
                // full of red Xs.
                $isReachable = $checklist !== null && ($s['key'] === 'review'
                    ? collect($checklist)->every(fn ($item) => $item['complete'])
                    : ($checklist[$s['key']]['complete'] ?? false));
                $isLinkable = $isReachable && ! $isCurrent;
                $tag = $isLinkable ? 'a' : 'div';
            @endphp
            <li>
                <{{ $tag }}
                    @if($isLinkable) href="{{ $stepRoute($s['key']) }}" @endif
                    class="flex items-start gap-3 px-3 py-2.5 rounded-xl transition-colors duration-200
                        {{ $isCurrent ? 'bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)]' : '' }}
                        {{ $isLinkable ? 'cursor-pointer hover:bg-[#EEF8F8]' : '' }}"
                    @if($isLinkable) title="Go to {{ $s['label'] }}" @endif
                >
                    @php
                        // The step you're actively on always reads as
                        // "current" (teal), even if its own data happens to
                        // already be complete — you don't want a green
                        // checkmark on the page you're looking at right now.
                        $showCheck = $isDone && ! $isCurrent;
                    @endphp
                    <span class="w-[22px] h-[22px] shrink-0 mt-px rounded-full border-[1.5px] flex items-center justify-center text-[11px] font-bold transition-colors duration-200
                        {{ $isCurrent ? 'bg-[#2AA7A1] border-[#2AA7A1] text-white' : ($isDone ? 'bg-[#22C55E] border-[#22C55E] text-white' : 'bg-white border-[#E2E8F0] text-[#64748B]') }}">
                        @if($showCheck)
                            <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="3.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        @else
                            <span>{{ $s['n'] }}</span>
                        @endif
                    </span>
                    <span class="block text-[13px] leading-snug pt-px {{ $isCurrent || $isDone ? 'text-[#1F2937] font-semibold' : 'text-[#64748B] font-medium' }}">
                        {{ $s['label'] }}
                    </span>
                </{{ $tag }}>
            </li>
        @endforeach
    </ol>
</aside>
