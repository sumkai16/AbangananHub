// AI search (plans/ai-search.md). Two Alpine components:
//   aiSearchBox(q)  the search box: example sentences + recent searches (localStorage)
//   aiSearch(cfg)   the results page: read the sentence -> chips -> results, with chip removal and retry
// The server does all the thinking; this file only moves JSON and HTML around.

const RECENT_KEY = 'ai_search_recent';
const RECENT_MAX = 4;

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
            saveRecent(this.query);

            const smart = post(interpretUrl, { q: this.query }, signal);
            smart.catch(() => {}); // handled below; stops an early rejection from being reported twice

            try {
                const quick = await post(interpretUrl, { q: this.query, fast: true }, signal);
                if (id !== runId) return;
                this.apply(quick);
                this.phase = 'finding';
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
            } catch (e) {
                if (id === runId) this.fail(e);
            }
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
            this.fetchResults();
        },

        // The "Raise budget to ₱4,500" one-tap fix.
        raiseBudget(amount) {
            this.intent.price_max = amount;
            this.phase = 'finding';
            this.fetchResults();
        },

        retry() {
            this.run();
        },
    };
}
