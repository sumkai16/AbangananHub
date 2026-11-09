// The page panel (components/page-panel.blade.php): a link marked `data-page-panel` opens its page over the current
// one, in an iframe with ?embed=1, instead of navigating away.
//
//  - The address bar shows the page's own address (pushState), so refresh and copy-link open the full page.
//  - The browser's Back button, the Back button, the X, Escape and a tap outside (phone) all close it.
//  - If the page inside was used (it loaded more than once: a form posted, a live update reloaded it), the list
//    underneath may be out of date, so closing reloads the list.
//  - A modified click (new tab, new window) and any link to another site are left alone.

export function pagePanel() {
    return {
        open: false,
        loading: false,
        src: '',
        fullUrl: '#',
        backLabel: 'Back',
        label: 'Details',
        dirty: false,
        loads: 0,
        clearTimer: null,

        init() {
            document.addEventListener('click', (e) => this.onLinkClick(e));
            window.addEventListener('popstate', (e) => this.onPopState(e));
        },

        onLinkClick(e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

            const link = e.target.closest('a[data-page-panel]');
            if (!link || link.target) return;

            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin) return;

            e.preventDefault();
            const path = url.pathname + url.search;
            this.show(path, link.dataset.pagePanelBack || 'Back', true);
        },

        show(path, backLabel, push) {
            clearTimeout(this.clearTimer);
            this.backLabel = backLabel;
            this.label = backLabel === 'Back' ? 'Details' : backLabel + ' details';
            this.fullUrl = path;
            this.dirty = false;
            this.loads = 0;
            this.loading = true;
            this.src = this.embedUrl(path);
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');

            if (push) window.history.pushState({ pagePanel: path, backLabel }, '', path);

            this.$nextTick(() => this.$refs.back?.focus({ preventScroll: true }));
        },

        embedUrl(path) {
            const url = new URL(path, window.location.origin);
            url.searchParams.set('embed', '1');
            return url.pathname + url.search;
        },

        // Closing goes through history so the address bar and the Back button stay in step.
        close() {
            if (window.history.state?.pagePanel) {
                window.history.back();
            } else {
                this.hide();
            }
        },

        hide() {
            if (!this.open) return;

            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
            // Keep the frame while the leave animation plays, then drop it.
            this.clearTimer = setTimeout(() => { this.src = ''; }, 300);

            if (this.dirty) window.location.reload();
        },

        onPopState(e) {
            if (e.state?.pagePanel) {
                if (!this.open) this.show(e.state.pagePanel, e.state.backLabel || 'Back', false);
            } else {
                this.hide();
            }
        },

        loaded() {
            this.loading = false;
            this.loads += 1;
            if (this.loads > 1) this.dirty = true;
        },
    };
}
