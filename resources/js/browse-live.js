// In-place filtering for the Browse Rentals page.
//
// Filter links (type/amenity chips, "Filtering by" chips, pagination, Clear all) and GET forms that
// target /properties (the Filters modal, the search pill) used to reload the whole page — which threw
// away the open map, the scroll position and the modal. Here they fetch the new results and swap only
// the regions that depend on the filters; the Alpine root (mapVisible, mobileView) and the Leaflet map
// are never touched, so the map stays open and just moves its pins.
//
// It is an enhancement, not a replacement: every one of those links and forms still works as plain
// HTML, and any failure falls back to a normal page load.

const LIVE_PATH = '/properties';

// Regions whose content depends on the active filters. Each has the same id in the fetched page.
const REGIONS = ['#browse-summary', '#browse-toolbar', '#browse-list', '#browse-category-strip'];
// Swapped too, but not while the visitor is typing in it.
const REGIONS_IF_IDLE = ['#browse-search-pill'];

let controller = null;

const isLive = () => document.querySelector('[data-browse-live]') !== null;

function isLiveUrl(url) {
    return url.origin === window.location.origin && url.pathname === LIVE_PATH;
}

document.addEventListener('click', (e) => {
    if (!isLive() || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    const link = e.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;

    const url = new URL(link.href, window.location.href);
    if (!isLiveUrl(url)) return;

    e.preventDefault();
    navigate(url.href, { scrollToTop: url.searchParams.has('page') });
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!isLive() || e.defaultPrevented || !(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') return;

    const url = new URL(form.action, window.location.href);
    if (!isLiveUrl(url)) return;

    e.preventDefault();
    // Same shape a native GET submit would produce, minus empty values (the "Any" radios).
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
        if (typeof value === 'string' && value !== '') params.append(key, value);
    });
    url.search = params.toString();
    navigate(url.href);
});

window.addEventListener('popstate', () => {
    if (isLive()) navigate(window.location.href, { push: false });
});

// The entry the visitor landed on must be restorable by Back/Forward too.
history.replaceState({ browseLive: true }, '', window.location.href);

async function navigate(href, { push = true, scrollToTop = false } = {}) {
    controller?.abort();
    controller = new AbortController();

    // Close the filters modal first: its <template> is about to be replaced, and the open flag lives on
    // the (persistent) Alpine root, so a still-true flag would open the fresh copy straight away.
    window.dispatchEvent(new CustomEvent('browse-close-filters'));
    setBusy(true);

    try {
        const response = await fetch(href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        // A redirect to some other page (login, landing…) must not be swapped into the browse layout.
        if (!doc.querySelector('[data-browse-live]')) throw new Error('not a browse page');

        REGIONS.forEach((selector) => swap(selector, doc));
        REGIONS_IF_IDLE.forEach((selector) => {
            const current = document.querySelector(selector);
            if (current && !current.contains(document.activeElement)) swap(selector, doc);
        });
        updateMap(doc);

        if (doc.title) document.title = doc.title;
        if (push) history.pushState({ browseLive: true }, '', href);
        if (scrollToTop) document.getElementById('browse-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
        if (error.name === 'AbortError') return;
        // Anything unexpected: do it the old way rather than leave a half-updated page.
        window.location.href = href;
    } finally {
        setBusy(false);
    }
}

function swap(selector, doc) {
    const current = document.querySelector(selector);
    const next = doc.querySelector(selector);
    if (current && next) current.replaceWith(document.importNode(next, true));
}

function updateMap(doc) {
    const fresh = doc.getElementById('browse-map-data');
    const current = document.getElementById('browse-map-data');
    if (!fresh || !current) return;

    // Keep the page's copy current: the map is only built the first time it's opened, and reads this.
    current.textContent = fresh.textContent;

    if (typeof window.browseMapUpdate === 'function') {
        try {
            window.browseMapUpdate(JSON.parse(fresh.textContent));
        } catch (error) {
            console.error('Browse map: could not update pins', error);
        }
    }
}

// The Show/Hide map button lives in the header strip, which is replaced on every swap. A fresh copy has to start
// with the right label, so it reads the map state (kept on the page's Alpine root, which never changes) when it is
// created rather than waiting for an event it might have missed while Alpine was still setting it up.
window.browseMapVisible = () => {
    const root = document.querySelector('[data-browse-live]');
    try {
        return !!(root && window.Alpine && window.Alpine.$data(root).mapVisible);
    } catch (error) {
        return false; // root not initialised yet (first paint) — the root's own event will follow
    }
};
function setBusy(busy) {
    const list = document.getElementById('browse-list');
    if (!list) return;
    list.setAttribute('aria-busy', busy ? 'true' : 'false');
    list.classList.toggle('opacity-60', busy);
    list.classList.toggle('pointer-events-none', busy);
}
