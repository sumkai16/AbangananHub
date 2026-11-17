// A page that shows live state follows it without a reload.
//
// Mark the page with `<div hidden data-live-page data-live-user="ID" data-live-reservation="ID" data-live-conversation="ID"></div>`
// inside its content (`data-live-any` instead of the two ids on a list that covers every reservation). When something
// happens to that reservation (a payment confirmed, a status change, a system line in the chat) the page asks the server
// for itself again and swaps only <main>: the header and sidebar stay put, nothing flashes, and the scroll position is kept.
//
// It never wipes work in progress: while a form is being filled in, a dialog is open, or the tab is in the background,
// the swap waits and then goes ahead. When the websocket is not connected (Reverb stopped, or an https page that cannot
// reach a plain-ws Reverb) it looks again every 5 seconds instead, and only swaps when the server's answer changed.
// A page it cannot swap safely (the new content carries inline JavaScript) is loaded normally, as a last resort.

const POLL_MS = 5000;
const RETRY_MS = 4000;

let bound = null; // { user, channel, handlers }
let pollTimer = null;
let debounce = null;
let abort = null;
let lastSig = null;
let waiting = false;
let dirty = false;

const marker = () => document.querySelector('[data-live-page]');
const mainEl = () => document.getElementById('main');

const matches = (m, e) => {
    if (m.hasAttribute('data-live-any')) return true;
    const { liveReservation: rid, liveConversation: cid } = m.dataset;
    return (rid && String(e.reservation_id) === rid) || (cid && String(e.conversation_id) === cid);
};

const connected = () => window.Echo?.connector?.pusher?.connection?.state === 'connected';

// Anything the person is in the middle of: typing in a form that posts, or a dialog on screen.
function busy() {
    const main = mainEl();
    if (!main) return false;
    if (dirty) return true;
    const active = document.activeElement;
    if (active && main.contains(active) && active.closest('form[method="post" i], form[method="POST"]') && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return true;
    return [...main.querySelectorAll('[role="dialog"]')].some((el) => el.offsetParent !== null);
}

const signature = (root) => {
    const copy = root.cloneNode(true);
    copy.querySelectorAll('[data-live-ignore], script').forEach((el) => el.remove());
    return copy.innerHTML.replace(/\s+/g, ' ');
};

async function refresh({ polling = false } = {}) {
    const m = marker();
    if (!m || document.hidden) {
        waiting = !!m;
        return;
    }
    if (busy()) {
        clearTimeout(debounce);
        debounce = setTimeout(() => refresh({ polling }), RETRY_MS);
        return;
    }

    abort?.abort();
    const mine = (abort = new AbortController());
    const url = window.location.href;

    let doc;
    try {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin', signal: mine.signal });
        if (!res.ok || new URL(res.url).pathname !== window.location.pathname) return;
        doc = new DOMParser().parseFromString(await res.text(), 'text/html');
    } catch (error) {
        return; // Offline or aborted: leave the page as it is.
    }
    if (mine.signal.aborted) return;

    const next = doc.getElementById('main');
    const current = mainEl();
    if (!current || !next || !next.querySelector('[data-live-page]')) return;

    const sig = signature(next);
    if (polling && lastSig === null) { lastSig = sig; return; } // first look is the baseline, not a change
    if (sig === lastSig) return;
    lastSig = sig;

    // Inline JavaScript in the new content would not run after a swap: load the page normally instead.
    if ([...next.querySelectorAll('script')].some((s) => !s.src && !s.type?.includes('json'))) {
        window.location.assign(url);
        return;
    }
    for (const script of next.querySelectorAll('script[src]')) {
        if ([...document.scripts].some((s) => s.src === script.src)) script.remove();
        else { window.location.assign(url); return; }
    }

    const fresh = document.adoptNode(next);
    for (const child of [...current.children]) window.Alpine?.destroyTree(child);

    const scrollY = window.scrollY;
    current.replaceChildren(...fresh.childNodes);
    window.scrollTo(0, scrollY);
    dirty = false;
    document.dispatchEvent(new CustomEvent('live:swapped'));
    lastSig = sig; // the swap event re-binds and clears it; what is on screen is this answer
}

function trigger(e) {
    const m = marker();
    if (!m || !matches(m, e)) return;
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        refresh();
        // The server says "confirmed" a moment before its database transaction has committed, so the first look can
        // still show the old state. Look once more shortly after; it only swaps if the answer is different.
        setTimeout(() => refresh(), 1500);
    }, 300);
}

function unbind() {
    if (bound) {
        for (const [event, handler] of Object.entries(bound.handlers)) bound.channel.stopListening(event, handler);
    }
    bound = null;
    clearInterval(pollTimer);
    pollTimer = null;
    lastSig = null;
}

function bind() {
    const m = marker();
    if (!m) return unbind();

    const user = m.dataset.liveUser;
    if (window.Echo && user && bound?.user !== user) {
        unbind();
        const channel = window.Echo.private('user.' + user);
        const handlers = {
            '.PaymentStatusUpdated': trigger,
            '.ReservationStatusUpdated': trigger,
            '.PaymentClaimUpdated': trigger, // a payment report filed, cancelled or decided (no status or Payment row moves)
            // A system line is the narration of something that changed (a payment confirmed, an agreement withdrawn).
            '.MessageSent': (e) => e.is_system && trigger(e),
        };
        for (const [event, handler] of Object.entries(handlers)) channel.listen(event, handler);
        bound = { user, channel, handlers };
    }

    lastSig = null;
    if (!pollTimer) {
        pollTimer = setInterval(() => { if (!connected()) refresh({ polling: true }); }, POLL_MS);
    }
}

document.addEventListener('input', (e) => { if (e.target.closest?.('#main form[method="post" i], #main form[method="POST"]')) dirty = true; });
document.addEventListener('visibilitychange', () => { if (!document.hidden && waiting) { waiting = false; refresh(); } });
document.addEventListener('live:swapped', bind);
document.addEventListener('DOMContentLoaded', bind);
if (document.readyState !== 'loading') bind();
