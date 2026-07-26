/**
 * Date-only picker for move-in / move-out fields.
 *
 * Sibling of public/js/datetime-picker.js — same ISO-local-midnight parsing
 * (never `new Date(iso)`, which reads as UTC and lands a day early west of
 * Greenwich) and the same "load from the owning page, not @push('scripts')"
 * rule, kept here even though only the conversations page currently needs it,
 * so the loading convention stays uniform across both pickers.
 *
 * Config: { date: 'YYYY-MM-DD'|null, min: 'YYYY-MM-DD'|null, max: 'YYYY-MM-DD'|null }
 */
function datePicker(config) {
    return {
        date: config.date || null,
        min: config.min || null,
        max: config.max || null,
        fieldDisabled: false,
        open: false,
        viewY: null,
        viewM: null,

        init() {
            this.syncView();
        },

        syncView() {
            const base = this.toDate(this.date) || this.toDate(this.min) || new Date();
            this.viewY = base.getFullYear();
            this.viewM = base.getMonth();
        },

        toggle() {
            if (this.fieldDisabled) return;
            this.open = !this.open;
            if (this.open) this.syncView();
        },

        // Parse as local midnight. new Date('2026-08-01') is read as UTC and
        // lands on the previous day for anyone west of Greenwich.
        toDate(iso) {
            if (!iso) return null;
            const [y, m, d] = iso.split('-').map(Number);
            return new Date(y, m - 1, d);
        },

        iso(y, m, d) {
            return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        },

        get minDate() {
            return this.toDate(this.min);
        },

        get maxDate() {
            return this.toDate(this.max);
        },

        get canGoBack() {
            const min = this.minDate;
            if (!min) return true;
            return this.viewY > min.getFullYear()
                || (this.viewY === min.getFullYear() && this.viewM > min.getMonth());
        },

        get canGoForward() {
            const max = this.maxDate;
            if (!max) return true;
            return this.viewY < max.getFullYear()
                || (this.viewY === max.getFullYear() && this.viewM < max.getMonth());
        },

        shiftMonth(delta) {
            const next = new Date(this.viewY, this.viewM + delta, 1);
            this.viewY = next.getFullYear();
            this.viewM = next.getMonth();
        },

        get monthLabel() {
            return new Date(this.viewY, this.viewM, 1)
                .toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },

        get grid() {
            const first = new Date(this.viewY, this.viewM, 1);
            const dayCount = new Date(this.viewY, this.viewM + 1, 0).getDate();
            const min = this.minDate;
            const max = this.maxDate;
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const cells = [];

            // Lead-in days from the previous month, greyed rather than blank —
            // an empty corner reads as a rendering fault, a dimmed 30 reads as
            // a date. Never selectable.
            const lead = first.getDay();
            const prevCount = new Date(this.viewY, this.viewM, 0).getDate();

            for (let i = lead - 1; i >= 0; i--) {
                const d = prevCount - i;
                cells.push({ key: `prev-${d}`, day: d, adjacent: true, disabled: true });
            }

            for (let d = 1; d <= dayCount; d++) {
                const iso = this.iso(this.viewY, this.viewM, d);
                const cur = new Date(this.viewY, this.viewM, d);

                cells.push({
                    key: iso,
                    iso,
                    day: d,
                    adjacent: false,
                    label: cur.toLocaleDateString(undefined, {
                        weekday: 'long', month: 'long', day: 'numeric',
                    }),
                    disabled: (min ? cur < min : false) || (max ? cur > max : false),
                    isToday: cur.getTime() === today.getTime(),
                });
            }

            // Trailing row left blank rather than filled with next-month dates
            // — the lead-in exists to explain where the first week starts, the
            // tail has nothing to explain.
            const trail = (7 - (cells.length % 7)) % 7;
            for (let i = 0; i < trail; i++) {
                cells.push({ key: `pad-${i}`, blank: true });
            }

            return cells;
        },

        select(iso) {
            this.date = iso;
            this.open = false;
            this.$dispatch('change');
        },

        clear() {
            this.date = null;
            this.open = false;
            this.$dispatch('change');
        },

        today() {
            const t = new Date();
            t.setHours(0, 0, 0, 0);
            this.viewY = t.getFullYear();
            this.viewM = t.getMonth();

            const min = this.minDate;
            const max = this.maxDate;
            if ((!min || t >= min) && (!max || t <= max)) {
                this.date = this.iso(t.getFullYear(), t.getMonth(), t.getDate());
                this.open = false;
                this.$dispatch('change');
            }
        },

        get formatted() {
            if (!this.date) return '';
            return this.toDate(this.date).toLocaleDateString(undefined, {
                month: 'short', day: 'numeric', year: 'numeric',
            });
        },
    };
}
