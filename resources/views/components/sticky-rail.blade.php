{{--
    A side column that stays pinned just under the site header while the main column scrolls past (a "sticky
    sidebar", CSS position: sticky). Used on the agreement page and the tenant and landlord rent pages so they all
    behave the same.

    It pins only while the whole rail fits in the window. A rail taller than the window would be cut off at the
    bottom with its buttons out of reach, so then it scrolls with the page instead. Re-checked on resize and when the
    rail's own height changes (a panel opens, a form grows). Desktop only (lg+); never printed. The parent grid needs
    lg:items-start so the rail is as tall as its content, not stretched.
--}}
<aside {{ $attributes->class(['print:hidden']) }}
    x-data="{ pin: false, check() { this.pin = window.matchMedia('(min-width: 1024px)').matches && this.$el.offsetHeight <= window.innerHeight - 112 } }"
    x-init="check(); new ResizeObserver(() => check()).observe($el)"
    @resize.window="check()"
    :class="pin ? 'lg:sticky lg:top-24' : ''">
    {{ $slot }}
</aside>
