// AI search (plans/ai-search.md). Two Alpine components:
//   aiSearchBox(q)  the search box: example sentences + recent searches (localStorage)
//   aiSearch(cfg)   the results page: read the sentence -> chips -> results, with chip removal and retry
// The server does all the thinking; this file only moves JSON and HTML around.

const RECENT_KEY = 'ai_search_recent';
const RECENT_MAX = 3;

const readRecent = () => {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        return Array.isArray(list) ? list.filter((s) => typeof s === 'string').slice(0, RECENT_MAX) : [];
    } catch {
        return [];
    }
};

const saveRecent = (q) => {
    try {
        const next = [q, ...readRecent().filter((s) => s !== q)].slice(0, RECENT_MAX);
        localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch { /* private window: recents just don't persist */ }
};

export function aiSearchBox(initial = '') {
    return {
        q: initial,
        recent: [],
        init() {
            this.recent = readRecent();
        },
        use(text) {
            this.q = text;
            this.$nextTick(() => {
                const form = this.$root.matches('form') ? this.$root : this.$root.querySelector('form');
                form?.requestSubmit();
            });
        },
        clearRecent() {
            try { localStorage.removeItem(RECENT_KEY); } catch { /* ignore */ }
            this.recent = [];
        },
    };
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const post = async (url, body, signal) => {
    const res = await fetch(url, {
        method: 'POST',
        signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    if (!res.ok) throw new Error('HTTP ' + res.status);

    return res.json();
};

const NOTICES = {
    not_configured: ['Showing keyword results', 'Smart search is not switched on yet, so only the words you typed were matched.'],
    unavailable: ['Smart search is busy right now', 'Below are plain keyword matches. Your full request was not read. Try again in a few minutes.'],
    busy: ['Smart search is busy right now', 'Many people are searching today. Below are plain keyword matches. Try again later.'],
    crowded: ['Lots of people are searching right now', 'Showing plain keyword results so the site stays fast. Retry in a moment for the full smart search.'],
    rate_limited: ['You have searched a lot this hour', 'Showing plain keyword results for now. Smart search is back within the hour.'],
    network: ['Something went wrong', 'We could not reach the server. Check your connection and retry.'],
};

export function aiSearch({ query, interpretUrl, resultsUrl }) {
    let controller = null;
    let runId = 0;

    return {
        query,
        phase: 'reading', // reading | finding | done | error
        upgrading: false, // keyword results are showing while the AI reads the full sentence
        chips: [],
        intent: null,
        usedAi: false,
        reason: null,
        html: '',
        filtersOpen: false, // the Filters panel (header button -> browse-open-filters)
        draft: { price_min: '', price_max: '', property_type: '', amenities: [], rules: [], living: '', for: '', furnishing: '' }, // the panel's working copy of the intent's filters; applied on "Show results"
        mapVisible: false, // desktop: the header's Show map button (browse-toggle-map)
        mobileView: 'list', // below lg: List | Map tabs
        pins: [], // the map pins of the current results (also drawn as the mobile card carousel)
        selectedId: null, // property_id of the selected pin / carousel card
        _lockUntil: 0, // while the carousel is being scrolled by code, ignore its scroll events
        _scrollTimer: null,

        init() {
            this.run();
        },

        get notice() {
            return this.reason && this.reason !== 'fast' ? (NOTICES[this.reason] ?? NOTICES.unavailable) : null;
        },

        get loading() {
            return this.phase === 'reading' || this.phase === 'finding';
        },

        // Two readings run side by side: the keyword one answers at once, the AI one can take
        // several seconds. Results from the keyword reading show first and are replaced when the AI is done.
        async run() {
            this.abort();
            const id = ++runId;
            controller = new AbortController();
            const { signal } = controller;
            Object.assign(this, { phase: 'reading', upgrading: false, html: '', chips: [], reason: null, usedAi: false });

            const smart = post(interpretUrl, { q: this.query }, signal);
            smart.catch(() => {}); // handled below; stops an early rejection from being reported twice

            try {
                const quick = await post(interpretUrl, { q: this.query, fast: true }, signal);
                if (id !== runId) return;
                this.apply(quick);
                this.phase = 'finding';
                this.html = '';
                await this.fetchResults(id);
                if (id !== runId) return;
                this.upgrading = true;

                const read = await smart;
                if (id !== runId) return;
                this.upgrading = false;
                this.reason = read.fallback_reason;
                if (read.used_ai && JSON.stringify(read.intent) !== JSON.stringify(this.intent)) {
                    this.apply(read);
                    this.phase = 'finding';
                    this.html = '';
                    await this.fetchResults(id);
                } else if (read.used_ai) {
                    this.usedAi = true;
                }
            } catch (e) {
                this.upgrading = false;
                if (id === runId) this.fail(e);
            }
        },

        apply(read) {
            this.intent = read.intent;
            this.chips = read.chips;
            this.usedAi = read.used_ai;
            this.reason = read.fallback_reason;
        },

        async fetchResults(id = runId) {
            controller = new AbortController();
            try {
                const out = await post(resultsUrl, {
                    q: this.query,
                    intent: this.intent,
                    used_ai: this.usedAi,
                    fallback_reason: this.reason === 'fast' ? null : this.reason,
                }, controller.signal);
                if (id !== runId) return;
                this.html = out.html;
                this.chips = out.chips;
                this.intent = out.intent;
                this.phase = 'done';
                if (out.exact_count > 0) saveRecent(this.query); // typos and empty searches stay out of Recent
                this.showOnMap(out.map || [], out.map_origin ?? null);
            } catch (e) {
                if (id === runId) this.fail(e);
            }
        },

        // The results map (browse-map.js) is built the first time it is opened and reads #browse-map-data;
        // once it exists, browseMapUpdate redraws the pins (and the search origin + radius). Waits a tick so
        // the new cards are in the page (the pins and cards highlight each other).
        showOnMap(pins, origin = null) {
            this.pins = pins;
            if (!pins.some((p) => String(p.property_id) === String(this.selectedId))) this.selectedId = null;
            const data = document.getElementById('browse-map-data');
            if (data) data.textContent = JSON.stringify({ pins, origin });
            this.$nextTick(() => window.browseMapUpdate?.(pins, { origin }));
        },

        // --- mobile card carousel (below lg) ---------------------------------------------------------
        pinPrice(pin) {
            return '₱' + Number(pin.rental_fee).toLocaleString();
        },

        pinMeta(pin) {
            return [pin.area, pin.rating ? '★ ' + Number(pin.rating).toFixed(1) : null].filter(Boolean).join(' · ');
        },

        pinTag(pin) {
            if (pin.fit === 'exact') return 'Fits everything';
            return pin.tag || (pin.fit === 'other' ? 'Other option' : 'Closest match');
        },

        // A pin was clicked (or cleared) on the map: mirror it in the carousel.
        onMapSelected(detail) {
            this.selectedId = detail?.id ?? null;
            if (detail?.source !== 'map' || this.selectedId === null || this.mobileView !== 'map') return;
            this.$nextTick(() => {
                const card = this.$refs.carousel?.querySelector(`[data-pin-card="${CSS.escape(String(this.selectedId))}"]`);
                if (!card) return;
                this._lockUntil = Date.now() + 700;
                card.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
            });
        },

        selectPin(pin) {
            this.selectedId = pin.property_id;
            window.browseMapSelect?.(pin.property_id, { pan: true });
        },

        // When the swipe settles, the card nearest the middle becomes the selected pin.
        onCarouselScroll() {
            clearTimeout(this._scrollTimer);
            this._scrollTimer = setTimeout(() => {
                if (Date.now() < this._lockUntil) return;
                const box = this.$refs.carousel;
                if (!box) return;
                const mid = box.getBoundingClientRect().left + box.clientWidth / 2;
                let best = null;
                let bestGap = Infinity;
                box.querySelectorAll('[data-pin-card]').forEach((card) => {
                    const r = card.getBoundingClientRect();
                    const gap = Math.abs(r.left + r.width / 2 - mid);
                    if (gap < bestGap) { bestGap = gap; best = card; }
                });
                const id = best?.dataset.pinCard;
                if (id && id !== String(this.selectedId)) {
                    const pin = this.pins.find((p) => String(p.property_id) === id);
                    if (pin) this.selectPin(pin);
                }
            }, 120);
        },

        get activeDot() {
            const i = this.pins.findIndex((p) => String(p.property_id) === String(this.selectedId));
            return Math.max(0, i);
        },

        toggleMap() {
            this.mapVisible = !this.mapVisible;
            if (this.mapVisible) this.$nextTick(() => window.browseMapRefit?.());
        },

        showList() {
            this.mobileView = 'list';
        },

        showMapTab() {
            this.mobileView = 'map';
            this.$nextTick(() => window.browseMapRefit?.());
        },

        fail(e) {
            if (e?.name === 'AbortError') return;
            this.reason = 'network';
            this.phase = 'error';
            this.upgrading = false;
        },

        abort() {
            controller?.abort();
        },

        cancel() {
            runId++;
            this.abort();
            this.upgrading = false;
            this.phase = 'error';
            this.reason = null;
        },

        // Drop one filter and search again without re-reading the sentence.
        removeChip(chip) {
            const { field, value } = chip.remove;
            if (field === 'price') {
                this.intent.price_min = this.intent.price_max = null;
            } else if (field === 'amenities' || field === 'rules') {
                this.intent[field] = this.intent[field].filter((v) => v !== value);
            } else {
                this.intent[field] = null;
            }
            this.chips = this.chips.filter((c) => c !== chip);
            this.phase = 'finding';
            this.html = '';
            this.fetchResults();
        },

        // The "Raise budget to ₱4,500" one-tap fix.
        raiseBudget(amount) {
            this.intent.price_max = amount;
            this.phase = 'finding';
            this.html = '';
            this.fetchResults();
        },

        retry() {
            this.run();
        },

        // ── Filters panel ──
        // Edits a copy (`draft`) of what the search understood; "Show results" writes it back to the intent and searches
        // again in place. No new AI call and nothing counts against the daily cap: only fetchResults() runs.
        openFilters() {
            const i = this.intent || {};
            this.draft = {
                price_min: i.price_min ?? '',
                price_max: i.price_max ?? '',
                property_type: i.property_type ?? '',
                amenities: [...(i.amenities || [])],
                rules: [...(i.rules || [])],
                living: i.living ?? '',
                for: i.for ?? '',
                furnishing: i.furnishing ?? '',
            };
            this.filtersOpen = true;
            this.$nextTick(() => this.$refs.filtersDialog?.focus());
        },

        closeFilters() {
            this.filtersOpen = false;
        },

        toggleDraft(field, value) {
            const list = this.draft[field];
            this.draft[field] = list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
        },

        // A radio-style chip: choosing the one already chosen clears it.
        pickDraft(field, value) {
            this.draft[field] = this.draft[field] === value ? '' : value;
        },

        setBudget(min, max) {
            this.draft.price_min = min ?? '';
            this.draft.price_max = max ?? '';
        },

        get draftCount() {
            const d = this.draft;
            return (d.price_min !== '' || d.price_max !== '' ? 1 : 0) + (d.property_type ? 1 : 0) + d.amenities.length + d.rules.length
                + (d.living ? 1 : 0) + (d.for ? 1 : 0) + (d.furnishing ? 1 : 0);
        },

        clearDraft() {
            this.draft = { price_min: '', price_max: '', property_type: '', amenities: [], rules: [], living: '', for: '', furnishing: '' };
        },

        applyFilters() {
            const d = this.draft;
            const money = (v) => {
                const n = parseInt(String(v).replace(/[^0-9]/g, ''), 10);
                return Number.isFinite(n) && n > 0 ? n : null;
            };
            // The place stays as understood (remove its chip to change it); everything else comes from the panel.
            this.intent = {
                ...(this.intent || {}),
                price_min: money(d.price_min),
                price_max: money(d.price_max),
                property_type: d.property_type || null,
                amenities: d.amenities,
                rules: d.rules,
                living: d.living || null,
                for: d.for || null,
                furnishing: d.furnishing || null,
            };
            // A still-running AI read would overwrite these edits when it lands: stop it, the edits win.
            runId++;
            this.abort();
            this.upgrading = false;
            this.filtersOpen = false;
            this.phase = 'finding';
            this.html = '';
            this.fetchResults();
        },

        // Keeps Tab inside the open panel.
        trapTab(e) {
            const nodes = [...this.$refs.filtersDialog.querySelectorAll('button, input, [href], [tabindex]:not([tabindex="-1"])')].filter((n) => !n.disabled && n.offsetParent !== null);
            if (!nodes.length) return;
            const first = nodes[0];
            const last = nodes[nodes.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.filtersDialog)) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },
    };
}
