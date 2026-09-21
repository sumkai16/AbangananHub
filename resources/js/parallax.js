// Scroll-linked depth for a few decorative layers on the landing page ("How it works" and the owner section).
//
// Markup contract:
//   [data-parallax-scope]   a section; it never moves, so it is a stable yardstick for scroll progress
//   [data-parallax="24"]    a layer inside it; signed pixels of travel across the section's whole pass through the viewport.
//                           Positive lags behind the page (reads as further away), negative runs ahead (reads as nearer).
//
// Only `translate` is written (never `transform`), so it composes with the transforms Tailwind and the float
// animations already put on these elements. Work happens in one rAF per scroll burst, and only while a section is
// near the viewport. Off entirely for visitors who prefer reduced motion; halved on phones.

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

function init() {
    if (reduced.matches || !('IntersectionObserver' in window)) return;

    const sections = [...document.querySelectorAll('[data-parallax-scope]')].map((scope) => ({
        scope,
        visible: false,
        layers: [...scope.querySelectorAll('[data-parallax]')].map((el) => ({ el, strength: parseFloat(el.dataset.parallax) || 0 })),
    }));
    if (sections.length === 0) return;

    let queued = false;
    const schedule = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(update);
    };

    function update() {
        queued = false;
        const vh = window.innerHeight;
        const damp = window.innerWidth < 768 ? 0.5 : 1;

        sections.forEach((section) => {
            if (!section.visible) return;
            const rect = section.scope.getBoundingClientRect();
            // How far the section's centre is above the viewport centre, scaled to -1 (just entering from below)
            // … +1 (just leaving at the top).
            const travel = vh / 2 + rect.height / 2;
            const progress = Math.max(-1, Math.min(1, (vh / 2 - (rect.top + rect.height / 2)) / travel));
            section.layers.forEach(({ el, strength }) => {
                el.style.translate = `0 ${(progress * strength * damp).toFixed(1)}px`;
            });
        });
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const section = sections.find((s) => s.scope === entry.target);
            if (!section) return;
            section.visible = entry.isIntersecting;
            // Promote the layers to their own compositor layer only while they are moving.
            section.layers.forEach(({ el }) => { el.style.willChange = entry.isIntersecting ? 'translate' : ''; });
        });
        schedule();
    }, { rootMargin: '25% 0px' });
    sections.forEach((section) => observer.observe(section.scope));

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    schedule();
}

init();
