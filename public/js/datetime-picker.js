/**
 * Date + time picker for the key-handover scheduler.
 *
 * Lives in a file rather than an @push('scripts') block inside the Blade
 * component: ConversationController returns the bare chat-panel partial on
 * AJAX, with no layout, so @stack('scripts') never renders. Open Messages
 * with no thread selected, click one, and the pushed script would never have
 * run — the component would reference an undefined function.
 *
 * Config: { date: 'YYYY-MM-DD'|null, time: 'HH:mm'|null, min: 'YYYY-MM-DD', deadline: 'YYYY-MM-DD'|null }
 */
function datetimePicker(config) {
    return {
        date: config.date || null,
        time: config.time || null,
        minIso: config.min,
        deadlineIso: config.deadline || null,
        viewY: null,
        viewM: null,

        presets: [
            { value: '08:00', label: '8 AM' },
            { value: '09:00', label: '9 AM' },
            { value: '10:00', label: '10 AM' },
            { value: '11:00', label: '11 AM' },
            { value: '13:00', label: '1 PM' },
            { value: '14:00', label: '2 PM' },
            { value: '15:00', label: '3 PM' },
            { value: '16:00', label: '4 PM' },
        ],

        init() {
            // Open on the chosen slot's month when rescheduling, otherwise on
            // the earliest month that holds a selectable day.
            const base = this.toDate(this.date) || this.toDate(this.minIso) || new Date();
            this.viewY = base.getFullYear();
            this.viewM = base.getMonth();
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
            return this.toDate(this.minIso);
        },

        get canGoBack() {
            const min = this.minDate;
            if (!min) return true;
            return this.viewY > min.getFullYear()
                || (this.viewY === min.getFullYear() && this.viewM > min.getMonth());
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
            const deadline = this.toDate(this.deadlineIso);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const cells = [];

            // Lead-in days from the previous month, greyed rather than blank —
            // an empty corner reads as a rendering fault, a dimmed 30 reads as
            // a date. They are never selectable.
            const lead = first.getDay();
            const prevCount = new Date(this.viewY, this.viewM, 0).getDate();

            for (let i = lead - 1; i >= 0; i--) {
                const d = prevCount - i;
                cells.push({
                    key: `prev-${d}`,
                    day: d,
                    adjacent: true,
                    disabled: true,
                });
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
                    disabled: min ? cur < min : false,
                    isToday: cur.getTime() === today.getTime(),
                    isDeadline: deadline ? cur.getTime() === deadline.getTime() : false,
                    beyondDeadline: deadline ? cur > deadline : false,
                });
            }

            // Pad the final row so the grid keeps its shape. Left blank rather
            // than filled with next-month dates: the lead-in exists to explain
            // where the first week starts, and the tail has nothing to explain.
            const trail = (7 - (cells.length % 7)) % 7;

            for (let i = 0; i < trail; i++) {
                cells.push({ key: `pad-${i}`, blank: true });
            }

            return cells;
        },

        get value() {
            return this.date && this.time ? `${this.date}T${this.time}` : '';
        },

        get label() {
            return this.describe({ weekday: 'short', month: 'short', day: 'numeric' });
        },

        // Spelled out for the confirmation readback — this is the moment the
        // user checks they picked what they meant, so no abbreviations.
        get longLabel() {
            return this.describe({ month: 'long', day: 'numeric', year: 'numeric' });
        },

        describe(dateOpts) {
            if (!this.value) return '';

            const [h, m] = this.time.split(':').map(Number);
            const d = this.toDate(this.date);
            d.setHours(h, m);

            return d.toLocaleDateString(undefined, dateOpts)
                + ' at '
                + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        },
    };
}
