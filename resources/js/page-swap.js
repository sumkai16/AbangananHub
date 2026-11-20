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
//
// `data-page-swap="nav"` marks the landlord sidebar links and the stat cards. They swap between pages, not tabs, so a click on the page
// you are already on still reloads it (it resets the filters), and the parts of the shell that sit outside <main>
// follow the new page: the mobile title, the sidebar's active item and its badges. A page is swapped only when it is
// the same kind of page (it has the sidebar too) and has no script of its own outside <main> (a `@push('scripts')`,
// which lands in #page-scripts) and no pending flash message (its script waits for DOMContentLoaded). Otherwise the
// click is a normal navigation, so pages that need their own script keep working untouched.

let pending = null;
let shownPath = window.location.pathname; // the page the work area currently shows
let shownHref = window.location.href;
let listening = false;

const fallback = (url) => window.location.assign(url);

const isCode = (script) => {
    const type = (script.getAttribute('type') || '').toLowerCase();
    return type === '' || type === 'text/javascript' || type === 'module';
};

const alreadyLoaded = (src) => [...document.scripts].some((s) => s.src === src);

const loadScript = ({ src, type }) =>
    new Promise((resolve, reject) => {
        const el = document.createElement('script');
        if (type) el.type = type;
        el.src = src;
        el.onload = resolve;
        el.onerror = reject;
        document.head.appendChild(el);
    });

// The sidebar of the shell we are in (landlord or admin) and how its active item is styled.
const SHELLS = {
    'landlord-sidebar': { active: ['bg-white/[0.10]', 'text-white', 'font-semibold'], inactive: ['text-white/65', 'hover:bg-white/[0.06]', 'hover:text-[#FF8A66]'] },
    'admin-sidebar': { active: ['bg-white/[0.10]', 'text-white'], inactive: ['text-white/60', 'hover:bg-white/[0.06]', 'hover:text-white'] },
    // The tenant site shell: a top bar instead of a sidebar. Its primary links swap like the sidebar's.
    'site-header': { active: ['text-[#B35A3D]', 'bg-[#ECEEF6]'], inactive: ['text-[#060D26]', 'hover:bg-[#F7F8FC]', 'hover:text-[#B35A3D]'] },
};
const shellId = () => Object.keys(SHELLS).find((id) => document.getElementById(id)) ?? null;
const squash = (el) => el.textContent.replace(/\s+/g, ' ').trim();

// True when the fetched page cannot be shown by swapping <main> alone.
function needsFullLoad(doc) {
    const shell = shellId();
    if (shell && !doc.getElementById(shell)) return true;
    // Tenant shell: the search band above the page differs between pages (Home and Browse have it, most others do not),
    // and it sits outside the swapped area, so a change of band means a normal load.
    if (shell === 'site-header' && !!document.getElementById('header-search-expanded') !== !!doc.getElementById('header-search-expanded')) return true;
    // The footer sits outside the swapped area and is drawn on the landing page only: a change of page that adds or drops it is a normal load.
    if (shell === 'site-header' && !!document.getElementById('site-footer') !== !!doc.getElementById('site-footer')) return true;
    // A pushed script is fine when it can be run again: a library file (src, loaded once), data-swap-safe (a
    // self-contained function, run after the swap) or data-swap-global (declares functions the page's x-data needs,
    // run before the swap). Any other inline script means a normal load.
    if ([...doc.querySelectorAll('#page-scripts script')].some((s) => isCode(s) && !s.src && !s.hasAttribute('data-swap-safe') && !s.hasAttribute('data-swap-global'))) return true;
    return [...doc.querySelectorAll('body script')].some((s) => !s.src && isCode(s) && s.textContent.includes("'show-modal'"));
}

// The shell outside <main> follows the new page: mobile title, sidebar active item and badges.
function syncShell(doc) {
    const title = document.getElementById('mobile-title');
    const nextTitle = doc.getElementById('mobile-title');
    if (title && nextTitle) title.innerHTML = nextTitle.innerHTML;

    const shell = shellId();
    if (!shell) return;
    const { active: ACTIVE, inactive: INACTIVE } = SHELLS[shell];
    const fresh = new Map([...doc.querySelectorAll(`#${shell} nav a[href]`)].map((a) => [a.getAttribute('href'), a]));
    document.querySelectorAll(`#${shell} nav a[href]`).forEach((link) => {
        const next = fresh.get(link.getAttribute('href'));
        if (!next) return;
        const active = next.getAttribute('aria-current') === 'page';
        link.classList.remove(...(active ? INACTIVE : ACTIVE));
        link.classList.add(...(active ? ACTIVE : INACTIVE));
        if (active) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
        // Icon colour and badge counts live inside the link; only rebuild it when they actually changed.
        if (squash(link) !== squash(next) || link.querySelector('svg')?.classList.contains('text-[#FF8A66]') !== active) {
            [...link.children].forEach((child) => window.Alpine?.destroyTree(child));
            link.replaceChildren(...document.adoptNode(next).childNodes);
        }
    });
}

// Region swap: a card or tab inside the page filters the list on the same page, so only the parts marked
// `data-swap-region` change (the cards, the results) and the header and filters stay put. "results" fades in.
// It needs the same regions in the old and the new page; otherwise the whole work area is swapped as before.
const regionNames = (root) => [...root.querySelectorAll('[data-swap-region]')].map((el) => el.dataset.swapRegion);
const sameRegions = (a, b) => {
    const x = regionNames(a), y = regionNames(b);
    return x.length > 0 && x.length === y.length && x.every((n) => y.includes(n));
};

// The filter form keeps what is picked in hidden inputs (stage, status). A card changes them, so copy them over,
// or the next search would filter by the old card. Selects and the search box are the user's own and stay as typed.
function syncFilterState(main, next) {
    const here = [...main.querySelectorAll('form[method="GET" i]')];
    const there = [...next.querySelectorAll('form[method="GET" i]')];
    if (here.length !== there.length) return;
    const fields = (f) => [...f.querySelectorAll('input[type="hidden"]')].filter((el) => el.name && !el.hasAttribute('x-ref') && !el.hasAttribute(':value'));
    here.forEach((form, i) => {
        const wanted = new Map(fields(there[i]).map((el) => [el.name, el.value]));
        fields(form).forEach((el) => { if (!wanted.has(el.name)) el.remove(); });
        wanted.forEach((value, name) => {
            const el = fields(form).find((x) => x.name === name);
            if (el) el.value = value;
            else form.prepend(Object.assign(document.createElement('input'), { type: 'hidden', name, value }));
        });
    });
}

async function go(url, push, inPage = false, only = null) {
    const main = document.getElementById('main');
    if (!main) return fallback(url);

    // What dims while loading: just the results for an in-page filter, else the whole work area.
    const dim = only ? [...main.querySelectorAll(`[data-swap-region="${only}"]`)] : inPage ? [...main.querySelectorAll('[data-swap-region="results"]')] : [];
    if (!dim.length) dim.push(main);

    pending?.abort();
    const mine = (pending = new AbortController());
    const settle = () => {
        if (pending !== mine) return;
        main.removeAttribute('aria-busy');
        dim.forEach((el) => el.classList.remove('opacity-60'));
    };
    main.setAttribute('aria-busy', 'true');
    dim.forEach((el) => el.classList.add('opacity-60', 'transition-opacity', 'duration-150'));

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

        if (needsFullLoad(doc)) return fallback(url);

        const toLoad = [];
        for (const script of next.querySelectorAll('script')) {
            if (!isCode(script)) continue;
            if (!script.src) return fallback(url);
            if (!alreadyLoaded(script.src)) toLoad.push({ src: script.src });
            script.remove();
        }
        // Library files a pushed script needs (a chart bundle, a picker). A module is loaded as a module, in order.
        for (const script of doc.querySelectorAll('#page-scripts script[src]')) {
            if (isCode(script) && !alreadyLoaded(script.src)) toLoad.push({ src: script.src, type: script.getAttribute('type') });
        }
        await Promise.all(toLoad.map(loadScript));
        if (mine.signal.aborted) return;

        // Functions the new content's x-data calls on start must exist before it is inserted.
        doc.querySelectorAll('#page-scripts script[data-swap-global]').forEach((script) => {
            const el = document.createElement('script');
            el.text = script.textContent;
            document.head.appendChild(el);
            el.remove();
        });

        const regionHere = (name) => main.querySelector(`[data-swap-region="${name}"]`);
        const regionThere = (name) => next.querySelector(`[data-swap-region="${name}"]`);
        // `only` names the one region a link replaces (the Rental policies tabs and property list); otherwise every region.
        const byRegion = inPage && (only ? !!regionHere(only) && !!regionThere(only) : sameRegions(main, next));
        if (byRegion) {
            if (!only) syncFilterState(main, next);
            for (const name of only ? [only] : regionNames(next)) {
                const old = regionHere(name);
                const fresh = regionThere(name);
                // A region nested in one already swapped moved with it: nothing left to do.
                if (!old || !fresh) continue;
                for (const child of [...old.children]) window.Alpine?.destroyTree(child);
                old.replaceChildren(...document.adoptNode(fresh).childNodes);
                if (name === 'results') {
                    old.classList.remove('swap-in');
                    void old.offsetWidth; // restart the animation if it is still running
                    old.classList.add('swap-in');
                    old.addEventListener('animationend', () => old.classList.remove('swap-in'), { once: true });
                }
            }
        } else {
            // Let Alpine tear the old content down first, so removed components stop their bindings quietly.
            for (const child of [...main.children]) window.Alpine?.destroyTree(child);
            main.replaceChildren(...document.adoptNode(next).childNodes);
        }

        if (push) {
            // Tag the entry we are leaving too, so Back from here knows it is ours to swap.
            window.history.replaceState({ ...(window.history.state || {}), pageSwap: true }, '');
            // `swapped` marks entries made here, so components/back-link can tell "an earlier page of this site is
            // behind me" even though document.referrer still holds whatever the first full load had.
            window.history.pushState({ pageSwap: true, swapped: true }, '', url);
        }
        shownPath = url.pathname;
        shownHref = url.href;
        syncShell(doc);
        document.title = doc.title || document.title;
        if (!byRegion) window.scrollTo(0, 0);
        // Keep keyboard and screen reader users on the tab strip, now showing the new current tab.
        if (!only || only === 'tab') main.querySelector('a[data-page-swap][aria-current="page"]')?.focus({ preventScroll: true });
        // Self-contained page scripts run in their own function scope, so running a page twice cannot redeclare anything.
        doc.querySelectorAll('#page-scripts script[data-swap-safe]').forEach((script) => new Function(script.textContent)());
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
    // Same path normally means the page handles its own query string; a tagged entry with another URL is ours.
    const ours = event.state?.pageSwap && window.location.href !== shownHref;
    if (window.location.pathname === shownPath && !ours) return;
    go(new URL(window.location.href), false, window.location.pathname === shownPath);
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest('a[data-page-swap]');
    if (!link || link.target) return;

    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    // "nav" links (sidebar, stat cards) swap only inside the landlord or admin shell; elsewhere they navigate as usual.
    if (link.dataset.pageSwap === 'nav' && !shellId()) return;

    event.preventDefault();
    const nav = link.dataset.pageSwap === 'nav';
    // Already on this tab: nothing to load. A sidebar link still reloads, which resets the page's filters.
    // A link with data-swap-only="name" (Rental policies: the tabs and the property list) replaces just that region,
    // even on the same path or across paths. Clicking where you already are does nothing.
    const only = link.dataset.swapOnly || null;
    if (only) {
        if (url.href === window.location.href) return;
    } else if (url.pathname === shownPath && !nav) return;
    // A card or tab inside the page (not the sidebar) that stays on this path only changes the list.
    const main = document.getElementById('main');
    go(url, url.href !== window.location.href, !!only || (nav && url.pathname === shownPath && !!main?.contains(link)), only);
});
