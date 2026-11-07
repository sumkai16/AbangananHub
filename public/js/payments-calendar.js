// Landlord Payments > Calendar: a calendar that zooms like a map.
//
//   All years  ->  one year (12 month tiles)  ->  one month (the day grid, with a day popup)
//
// Markup lives in resources/views/landlord/payments/_calendar.blade.php; the data is one JSON payload for every
// year, built by Landlord\PaymentController::buildCalendar, so no click waits on the server:
//   months: { 'YYYY-MM': { expected, collected, due, overdue, partial, paid, upcoming } }
//   days:   { 'YYYY-MM-DD': [ [reservationId, status, expected, paid], ... ] }   (most urgent first)
//   people: { reservationId: { name, unit, property, url } }
//
// The level, year and month live in the address (?view=calendar&month=2026-11 | &zoom=year&year=2026 |
// &zoom=years[&year=2029, the first year of the window]) so a view can be shared, reloaded and walked back with the
// Back button. Default is the current month.
//
// The payload only holds years that have ledger data. Every other year or month still opens: the browser draws it
// empty (zero due, no dots) from plain date arithmetic, so nothing is invented and the arrows have no edge to hit
// (apart from the server's own span, data.minYear to data.maxYear).
// Class names the script hands back (status dots, chips) come in through the config, because Tailwind only scans views.
document.addEventListener('alpine:init', () => {
    Alpine.data('paymentsCalendar', (cfg = {}) => {
        // Kept in the closure, not on the component: Alpine would wrap every entry in a reactive proxy.
        let data = { today: '', level: 'month', year: new Date().getFullYear(), month: '', years: [], months: {}, days: {}, people: {} };
        try {
            data = Object.assign(data, JSON.parse(document.getElementById(cfg.source).textContent));
        } catch (e) { /* an empty calendar is better than a broken page */ }

        const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const ORDER = { years: 0, year: 1, month: 2 };
        const ZERO = { expected: 0, collected: 0, due: 0, overdue: 0, partial: 0, paid: 0, upcoming: 0 };
        const pad = (n) => String(n).padStart(2, '0');
        const [todayYear, todayMon] = (data.today || '').split('-').map(Number).concat([0, 0]);
        const nowY = todayYear || new Date().getFullYear();
        const nowM = todayMon || new Date().getMonth() + 1;
        const minYear = data.minYear || 1990; // the span the server will open an address in
        const maxYear = data.maxYear || 2100;
        const dataStart = (data.years || [])[0] || nowY; // first year with data (never later than this year)
        const lgUp = window.matchMedia('(min-width: 1024px)');
        const xlUp = window.matchMedia('(min-width: 1280px)');
        // Years shown at once on the all-years level: two rows of 4 (xl), two rows of 3 (lg), a list of 4 on a phone.
        const windowSize = () => (xlUp.matches ? 8 : lgUp.matches ? 6 : 4);
        const BOTTOM_GAP = 44; // the page's own bottom padding, plus a few pixels so rounding never adds a scrollbar
        const inSpan = (y) => y >= minYear && y <= maxYear;
        const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const only = (level) => ({ years: level === 'years', year: level === 'year', month: level === 'month' });
        const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

        const initialMon = Number((data.month || '').slice(5, 7)) || nowM;

        return {
            level: data.level,
            year: data.year,
            mon: initialMon,
            start: data.start || dataStart, // first year of the all-years window
            size: windowSize(),
            chipLimit: 2, // named chips per day before "See more"; 3 when the day cells are tall
            vis: only(data.level),
            selected: null,
            announce: '',
            todayYear: nowY,
            styles: cfg.styles || {},
            chips: cfg.chips || {},
            _opener: null,
            _run: null,
            _token: 0,

            init() {
                this.syncForm();

                // On large screens the card fills the window: tell it how much page sits above it.
                const refit = () => requestAnimationFrame(() => { this.fit(); this.measure(); });
                window.addEventListener('resize', refit);
                window.addEventListener('load', refit);
                [lgUp, xlUp].forEach((mq) => mq.addEventListener('change', () => { this.size = windowSize(); refit(); }));
                refit();
            },

            // min-height of the card = the viewport minus what sits above it. Only from lg up; on phones the card keeps its own height.
            fit() {
                const card = this.$root.firstElementChild;
                if (!card) return;
                if (!lgUp.matches) { this.$root.style.removeProperty('--cal-top'); this.$root.style.removeProperty('--row-min'); return; }
                const top = card.getBoundingClientRect().top + window.scrollY;
                this.$root.style.setProperty('--cal-top', `${Math.round(top + BOTTOM_GAP)}px`);
                // A shorter window gets shorter minimum week rows, so a month still fits without a page scrollbar.
                this.$root.style.setProperty('--row-min', window.innerHeight >= 1000 ? '88px' : '72px');
            },

            // How many named chips a day cell has room for: the row height minus the date line and padding, 24px a chip.
            measure() {
                this.$nextTick(() => {
                    const cell = this.$refs.pMonth && this.$refs.pMonth.querySelector('[data-day-cell]');
                    if (!cell || !lgUp.matches || !cell.offsetParent) { this.chipLimit = 2; return; }
                    this.chipLimit = Math.max(1, Math.min(3, Math.floor((cell.getBoundingClientRect().height - 36) / 24)));
                });
            },

            // ── numbers ────────────────────────────────────────────────────────────────
            get ym() { return `${this.year}-${pad(this.mon)}`; },
            get isCurrentView() { return this.level === 'month' && this.year === nowY && this.mon === nowM; },

            peso(n, d = 0) {
                return '₱' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
            },
            // ₱950, ₱14.1k, ₱1.2M: short enough for a small month tile.
            compact(n) {
                n = Number(n || 0);
                if (n >= 1e6) return '₱' + (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
                if (n >= 1e3) return '₱' + (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'k';
                return '₱' + Math.round(n);
            },
            get weeks() {
                const lead = new Date(Date.UTC(this.year, this.mon - 1, 1)).getUTCDay();
                return Math.ceil((lead + new Date(Date.UTC(this.year, this.mon, 0)).getUTCDate()) / 7);
            },
            mAgg(y, m) { return data.months[`${y}-${pad(m)}`] || ZERO; },
            yearAgg(y) {
                const t = { ...ZERO };
                for (let m = 1; m <= 12; m++) {
                    const a = this.mAgg(y, m);
                    for (const k in t) t[k] += a[k];
                }
                return t;
            },
            // The years on screen at the all-years level, and their totals.
            get windowYears() { return Array.from({ length: this.size }, (_, i) => this.start + i); },
            get windowRange() { return `${this.start} – ${this.start + this.size - 1}`; },
            windowAgg() {
                const t = { ...ZERO };
                this.windowYears.forEach((y) => { const a = this.yearAgg(y); for (const k in t) t[k] += a[k]; });
                return t;
            },
            // The window that holds a year, lined up with the first window so Previous and Next keep the same rhythm.
            alignedStart(y) { return dataStart + this.size * Math.floor((y - dataStart) / this.size); },
            pct(a) { return a.expected > 0 ? Math.min(100, Math.round(a.collected / a.expected * 100)) : 0; },
            sumFor(level) {
                const a = level === 'years' ? this.windowAgg() : level === 'year' ? this.yearAgg(this.year) : this.mAgg(this.year, this.mon);
                return { ...a, pct: this.pct(a) };
            },
            brief(a) { return a.expected > 0 ? `${this.peso(a.collected)} of ${this.peso(a.expected)}` : 'No rent due'; },

            // ── labels ─────────────────────────────────────────────────────────────────
            monthName(m) { return MONTHS[m - 1]; },
            abbr(m) { return MONTHS[m - 1].slice(0, 3); },
            isNow(y, m) { return y === nowY && m === nowM; },
            countText(a) {
                if (!a.due) return 'no rent due';
                let t = `${a.due} ${a.due === 1 ? 'payment' : 'payments'} due`;
                if (a.overdue) t += `, ${a.overdue} overdue`;
                return t;
            },
            tileLabel(y, m) { return `${MONTHS[m - 1]} ${y}, ${this.countText(this.mAgg(y, m))}`; },
            yearLabel(y) { return `${y}, ${this.countText(this.yearAgg(y))}. Zoom in`; },
            describe() {
                return this.level === 'years' ? `All years, ${this.windowRange}` : this.level === 'year' ? `${this.year}` : `${MONTHS[this.mon - 1]} ${this.year}`;
            },

            // The worst thing that month, as one dot: overdue, then partial, then upcoming, then paid.
            dotClass(y, m) {
                const a = this.mAgg(y, m);
                const tone = a.overdue ? 'overdue' : a.partial ? 'partial' : a.upcoming ? 'upcoming' : a.paid ? 'paid' : null;
                return tone && this.styles[tone] ? this.styles[tone].dot : (cfg.noDot || '');
            },

            // ── the day grid ───────────────────────────────────────────────────────────
            get cells() {
                const y = this.year, m = this.mon;
                const lead = new Date(Date.UTC(y, m - 1, 1)).getUTCDay();
                const daysIn = new Date(Date.UTC(y, m, 0)).getUTCDate();
                const total = Math.ceil((lead + daysIn) / 7) * 7;
                const out = [];

                for (let i = 0; i < total; i++) {
                    const d = new Date(Date.UTC(y, m - 1, 1 - lead + i));
                    const key = `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`;
                    const inMonth = d.getUTCMonth() === m - 1 && d.getUTCFullYear() === y;
                    out.push({
                        key,
                        day: d.getUTCDate(),
                        out: !inMonth,
                        today: key === data.today,
                        entries: inMonth ? (data.days[key] || []).map(this.entry) : [],
                    });
                }

                return out;
            },
            entry(e) { return { pid: e[0], status: e[1], expected: e[2], paid: e[3] }; },
            person(e) { return data.people[e.pid] || { name: 'Unknown', unit: '—', property: '', url: '#' }; },
            firstName(e) { return (this.person(e).name || '').split(' ')[0]; },
            dayLabel(c) {
                const n = c.entries.length;
                return `${MONTHS[this.mon - 1]} ${c.day}, ${n ? `${n} ${n === 1 ? 'payment' : 'payments'} due` : 'no rent due'}`;
            },
            get dayEntries() { return this.selected ? (data.days[this.selected] || []).map(this.entry) : []; },
            get dayTitle() {
                if (!this.selected) return '';
                const [y, m, d] = this.selected.split('-').map(Number);
                return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', timeZone: 'UTC' });
            },
            openDay(key, el) {
                this._opener = el;
                this.selected = key;
                this.$nextTick(() => this.$refs.closeBtn && this.$refs.closeBtn.focus());
            },
            closeDay() {
                if (!this.selected) return;
                this.selected = null;
                const back = this._opener;
                this._opener = null;
                if (back && document.contains(back)) back.focus({ preventScroll: true });
            },

            // ── moving between levels ──────────────────────────────────────────────────
            // Clicked a tile: zoom in, from that tile.
            zoomIn(level, to, el) {
                if (to.year != null) this.year = to.year;
                if (to.mon != null) this.mon = to.mon;
                this.go(level, el);
            },
            // Clicked a crumb: zoom back out to a level.
            zoomTo(level) {
                if (level === this.level) { this.focusHeading(level); return; }
                this.go(level);
            },
            goToday() {
                const sameLevel = this.level === 'month';
                this.year = nowY;
                this.mon = nowM;
                if (sameLevel) { this.syncUrl(true); this.announce = this.describe(); this.slide(0); this.closeDay(); } else { this.go('month'); }
            },
            // Arrows: a window of years on the all-years level, a year on the month tiles, a month on the day grid.
            // No edge of the payload: a year with no data is simply empty. Only the server's own span stops them.
            step(dir) {
                if (this.level === 'month') {
                    let y = this.year, m = this.mon + dir;
                    if (m < 1) { m = 12; y--; }
                    if (m > 12) { m = 1; y++; }
                    if (!inSpan(y)) return;
                    this.year = y;
                    this.mon = m;
                } else if (this.level === 'year') {
                    if (!inSpan(this.year + dir)) return;
                    this.year += dir;
                } else {
                    const next = this.start + dir * this.size;
                    if (next + this.size - 1 < minYear || next > maxYear) return;
                    this.start = next;
                }
                this.closeDay();
                this.syncUrl(true);
                this.announce = this.describe();
                this.slide(dir);
                this.measure();
            },

            // Cross-fade the two panels, scaling around the tile the user came from (or is returning to).
            async go(next, originEl) {
                const from = this.level;
                if (next === from) return;

                this.finish();
                this.closeDay();
                if (next === 'years' && !(this.year >= this.start && this.year < this.start + this.size)) this.start = this.alignedStart(this.year);
                this.level = next;
                this.syncUrl(true);
                this.announce = this.describe();

                if (reduced()) {
                    this.vis = only(next);
                    this.focusHeading(next);
                    this.measure();
                    return;
                }

                const token = ++this._token;
                this.vis = { ...this.vis, [next]: true };
                await this.$nextTick();
                if (token !== this._token) return;

                const stage = this.$refs.stage;
                const fromEl = this.$refs['p' + cap(from)];
                const toEl = this.$refs['p' + cap(next)];
                if (!stage || !fromEl || !toEl) { this.vis = only(next); this.focusHeading(next); return; }

                const dir = ORDER[next] > ORDER[from] ? 'in' : 'out';
                const anchor = originEl || this.findOrigin(from, next, dir);
                const box = stage.getBoundingClientRect();
                let ox = box.width / 2, oy = Math.min(box.height / 2, 320);
                if (anchor) {
                    const r = anchor.getBoundingClientRect();
                    ox = r.left + r.width / 2 - box.left;
                    oy = r.top + r.height / 2 - box.top;
                }

                fromEl.style.transformOrigin = toEl.style.transformOrigin = `${ox}px ${oy}px`;
                fromEl.style.pointerEvents = 'none';
                const ease = 'cubic-bezier(.22,.8,.24,1)';
                const big = 1.7, small = 0.6;
                const anims = [
                    fromEl.animate(
                        [{ transform: 'scale(1)', opacity: 1 }, { transform: `scale(${dir === 'in' ? big : small})`, opacity: 0 }],
                        { duration: 200, easing: ease, fill: 'forwards' }
                    ),
                    toEl.animate(
                        [{ transform: `scale(${dir === 'in' ? small : big})`, opacity: 0 }, { transform: 'scale(1)', opacity: 1 }],
                        { duration: 280, easing: ease, fill: 'backwards' }
                    ),
                ];
                this._run = { anims, fromEl, toEl };

                await Promise.allSettled(anims.map((a) => a.finished));
                if (token !== this._token) return;

                this.finish();
                this.focusHeading(next);
                this.measure();
            },

            // End any zoom in progress right now (also used when the user clicks again mid-zoom).
            finish() {
                this._token++;
                if (this._run) {
                    this._run.anims.forEach((a) => a.cancel());
                    [this._run.fromEl, this._run.toEl].forEach((el) => { el.style.transformOrigin = ''; el.style.pointerEvents = ''; });
                    this._run = null;
                }
                this.vis = only(this.level);
            },

            // A small slide for stepping to the next or previous month or year.
            slide(dir) {
                if (reduced()) return;
                const el = this.$refs['p' + cap(this.level)];
                if (!el) return;
                el.animate(
                    [{ opacity: 0.3, transform: `translateX(${dir * 14}px)` }, { opacity: 1, transform: 'none' }],
                    { duration: 180, easing: 'ease-out' }
                );
            },

            // The tile a zoom started from, or lands on, when the user did not click one directly.
            findOrigin(from, to, dir) {
                const scope = dir === 'in' ? this.$refs['p' + cap(from)] : this.$refs['p' + cap(to)];
                const level = dir === 'in' ? from : to;
                if (!scope) return null;
                if (level === 'years') return scope.querySelector(`[data-mini="${this.year}-${this.mon}"]`) || scope.querySelector(`[data-tile-year="${this.year}"]`);
                if (level === 'year') return scope.querySelector(`[data-tile-month="${this.mon}"]`);
                return null;
            },

            focusHeading(level) {
                this.$nextTick(() => {
                    const h = this.$refs['h' + cap(level)];
                    if (h) h.focus({ preventScroll: true });
                });
            },

            // ── the address ────────────────────────────────────────────────────────────
            syncUrl(push) {
                const u = new URL(window.location.href);
                ['zoom', 'year', 'month'].forEach((k) => u.searchParams.delete(k));
                u.searchParams.set('view', 'calendar');
                if (this.level === 'years') {
                    u.searchParams.set('zoom', 'years');
                    if (this.start !== dataStart) u.searchParams.set('year', this.start);
                } else if (this.level === 'year') {
                    u.searchParams.set('zoom', 'year');
                    u.searchParams.set('year', this.year);
                } else if (!(this.year === nowY && this.mon === nowM)) {
                    u.searchParams.set('month', this.ym);
                }
                if (u.href !== window.location.href) {
                    window.history[push ? 'pushState' : 'replaceState']({ cal: true }, '', u);
                }
                this.syncForm();
            },

            // The filter form above the calendar is a plain GET form: keep its hidden fields in step, so a search or
            // filter reloads on the level the user is looking at.
            syncForm() {
                const set = (name, value) => document.querySelectorAll(`[data-cal-field="${name}"]`).forEach((input) => {
                    input.value = value == null ? '' : value;
                    input.disabled = value == null;
                });
                set('zoom', this.level === 'month' ? null : this.level);
                set('year', this.level === 'year' ? this.year : this.level === 'years' && this.start !== dataStart ? this.start : null);
                set('month', this.level === 'month' && !(this.year === nowY && this.mon === nowM) ? this.ym : null);
            },

            // Back and Forward: read the level out of the address, no animation.
            onPopState() {
                const q = new URLSearchParams(window.location.search);
                if (q.get('view') !== 'calendar') return;
                const zoom = q.get('zoom');
                const level = zoom === 'years' ? 'years' : zoom === 'year' ? 'year' : 'month';
                const m = /^(\d{4})-(0[1-9]|1[0-2])$/.exec(q.get('month') || '');
                const y = /^\d{4}$/.test(q.get('year') || '') ? Number(q.get('year')) : null;

                this.closeDay();
                this._token++;
                if (this._run) this.finish();
                this.year = m ? Number(m[1]) : (level === 'year' && y && inSpan(y) ? y : nowY);
                this.mon = m ? Number(m[2]) : nowM;
                this.start = level === 'years' && y && inSpan(y) ? y : (level === 'years' ? dataStart : this.alignedStart(this.year));
                this.level = level;
                this.vis = only(level);
                this.announce = this.describe();
                this.syncForm();
            },
        };
    });
});
