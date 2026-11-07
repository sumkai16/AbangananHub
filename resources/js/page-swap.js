// Switching between the tabs of one page without a page load (Payments: Rent | Payouts).
//
// A link marked `data-page-swap` no longer navigates. It fetches the target in the background and swaps only the work
// area (<main id="main">: the page header, the tabs and the content), so the sidebar stays put and nothing flashes.
// The address bar and the page title follow, and the browser's Back and Forward buttons walk between the tabs.
//
// It falls back to a normal full navigation whenever swapping is not safe: the request fails, the server redirects
// somewhere else (login), or the new content carries inline JavaScript (a swap would not run it). A `<script src>`
// the page has not loaded yet is loaded first, so a tab can bring its own library; one it already has is dropped.
// Data blocks (`<script type="application/json">`) are content, not code, and are kept.
// A page that draws something on load listens for `live:swapped` on `document` (the same event live-filter.js fires).
// A modified click (new tab) and any link to another site are left alone, and the href stays a real address.

let pending = null;
let shownPath = window.location.pathname; // the page the work area currently shows
let listening = false;

const fallback = (url) => window.location.assign(url);

const isCode = (script) => {
    const type = (script.getAttribute('type') || '').toLowerCase();
    return type === '' || type === 'text/javascript' || type === 'module';
};

const alreadyLoaded = (src) => [...document.scripts].some((s) => s.src === src);

const loadScript = (src) =>
    new Promise((resolve, reject) => {
        const el = document.createElement('script');
        el.src = src;
        el.onload = resolve;
        el.onerror = reject;
        document.head.appendChild(el);
    });

async function go(url, push) {
    const main = document.getElementById('main');
    if (!main) return fallback(url);

    pending?.abort();
    const mine = (pending = new AbortController());
    const settle = () => {
        if (pending !== mine) return;
        main.removeAttribute('aria-busy');
        main.classList.remove('opacity-60');
    };
    main.setAttribute('aria-busy', 'true');
    main.classList.add('opacity-60', 'transition-opacity', 'duration-150');

    try {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
            signal: mine.signal,
        });
        if (!res.ok || new URL(res.url).pathname !== url.pathname) return fallback(url);

        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const next = doc.getElementById('main');
        if (!next) return fallback(url);

        const toLoad = [];
        for (const script of next.querySelectorAll('script')) {
            if (!isCode(script)) continue;
            if (!script.src) return fallback(url);
            if (!alreadyLoaded(script.src)) toLoad.push(script.src);
            script.remove();
        }
        await Promise.all(toLoad.map(loadScript));
        if (mine.signal.aborted) return;

        // Let Alpine tear the old content down first, so removed components stop their bindings quietly.
        for (const child of [...main.children]) window.Alpine?.destroyTree(child);
        main.replaceChildren(...document.adoptNode(next).childNodes);

        if (push) window.history.pushState({ pageSwap: true }, '', url);
        shownPath = url.pathname;
        document.title = doc.title || document.title;
        window.scrollTo(0, 0);
        // Keep keyboard and screen reader users on the tab strip, now showing the new current tab.
        main.querySelector('a[data-page-swap][aria-current="page"]')?.focus({ preventScroll: true });
        document.dispatchEvent(new CustomEvent('live:swapped'));

        if (!listening) {
            listening = true;
            window.addEventListener('popstate', onPopState);
        }
    } catch (error) {
        if (error.name !== 'AbortError') fallback(url);
    } finally {
        settle();
    }
}

// Back and Forward. Only a change of page counts: a page that moves its own query string (the payments calendar)
// stays on the same path and handles that itself. An entry opened by the page panel is the panel's to handle.
function onPopState(event) {
    if (event.state?.pagePanel) return;
    if (window.location.pathname === shownPath) return;
    go(new URL(window.location.href), false);
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest('a[data-page-swap]');
    if (!link || link.target) return;

    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;

    event.preventDefault();
    // Already on this tab: nothing to load.
    if (url.pathname === shownPath) return;
    go(url, true);
});
