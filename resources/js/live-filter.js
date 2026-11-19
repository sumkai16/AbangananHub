// Live search and filters without a page reload.
//
// A GET form marked `data-live` no longer navigates when it submits (typing in its search box, picking a filter,
// pressing Enter). It fetches the same URL in the background and swaps only the page content (<main id="main">),
// so the sidebar and header stay put, no loading bar plays, and the search box keeps focus and its caret. The URL is
// updated in place, so a refresh or a shared link shows the same result.
//
// It falls back to a normal full navigation whenever swapping is not safe: the request fails, the server redirects
// somewhere else (e.g. to login), or the new content carries an inline <script> (a swap would not run it). A
// <script src> the page already loaded (a picker library, say) is just dropped from the new content.
// A page that draws something on load (a chart) listens for the `live:swapped` event on `document` and draws again.
// Plain forms and every other link behave exactly as before.

let pending = null;

const fallback = (url) => window.location.assign(url);

async function run(form) {
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();

    pending?.abort();
    const mine = (pending = new AbortController());

    let html;
    try {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
            signal: mine.signal,
        });
        if (!res.ok || new URL(res.url).pathname !== url.pathname) return fallback(url);
        html = await res.text();
    } catch (error) {
        if (error.name !== 'AbortError') fallback(url);
        return;
    }
    if (mine.signal.aborted) return;

    const current = document.getElementById('main');
    const next = new DOMParser().parseFromString(html, 'text/html').getElementById('main');
    if (!current || !next) return fallback(url);
    for (const script of next.querySelectorAll('script')) {
        const loaded = script.src && [...document.scripts].some((s) => s.src === script.src);
        if (!loaded) return fallback(url);
        script.remove();
    }

    // Keep the very same search box (value, focus, caret) instead of a fresh copy from the server.
    const input = form.querySelector('input[name="search"]');
    const hadFocus = input && document.activeElement === input;
    const caret = hadFocus ? [input.selectionStart, input.selectionEnd] : null;
    const index = [...current.querySelectorAll('form[data-live]')].indexOf(form);
    const freshInput = next.querySelectorAll('form[data-live]')[index]?.querySelector('input[name="search"]');

    const fresh = document.adoptNode(next);
    if (input && freshInput) freshInput.replaceWith(input);

    // Let Alpine tear the old content down first (stops its bindings), so removed components do not throw as they go.
    for (const child of [...current.children]) window.Alpine?.destroyTree(child);

    const scrollY = window.scrollY;
    current.replaceChildren(...fresh.childNodes);
    window.scrollTo(0, scrollY);
    history.replaceState(null, '', url);

    if (hadFocus) {
        input.focus({ preventScroll: true });
        if (caret[0] !== null) input.setSelectionRange(caret[0], caret[1]);
    }
    document.dispatchEvent(new CustomEvent('live:swapped'));
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-live')) return;
    event.preventDefault();
    run(form);
});
