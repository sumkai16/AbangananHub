// Alpine wrapper for <x-lease-editor>: the "Edit lease" button, the full-screen editor and its preview.
// The TipTap instance lives in a closure, not on the Alpine object, because Alpine's reactive proxy breaks ProseMirror.
export function leaseEditor(cfg) {
    let editor = null;

    return {
        open: false,
        tab: 'edit',
        value: cfg.value ?? '',
        tick: 0,
        confirmReset: false,
        previewHtml: '',
        previewBusy: false,
        previewError: null,
        fields: cfg.fields,

        get edited() {
            return this.value !== '';
        },

        // One line for the policy page's settings row, from whatever the wording is right now.
        get summary() {
            const html = this.value || cfg.initial;
            const clauses = (html.match(/<h3\b/g) || []).length;
            if (!this.value) return clauses ? clauses + ' clauses \u00b7 standard wording' : 'Standard wording';
            return clauses ? clauses + ' clauses' : 'Custom wording';
        },

        async openEditor() {
            this.open = true;
            this.tab = 'edit';
            this.confirmReset = false;
            document.body.style.overflow = 'hidden';
            await this.$nextTick();

            const html = this.value || cfg.initial;
            if (editor) {
                editor.commands.setContent(html);
            } else {
                const { createLeaseEditor } = await import('./lease-editor');
                editor = createLeaseEditor({
                    element: this.$refs.surface,
                    html,
                    labels: cfg.fields,
                    onChange: () => { this.tick++; },
                });
            }
            editor.commands.focus('start');
        },

        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        use() {
            this.value = editor.getHTML();
            this.close();
        },

        useTemplate() {
            this.value = '';
            this.close();
        },

        reset() {
            editor.commands.setContent(cfg.starter);
            this.confirmReset = false;
        },

        run(command, ...args) {
            editor.chain().focus()[command](...args).run();
        },

        active(name, attrs) {
            this.tick; // re-evaluated on every editor change
            return !!editor && editor.isActive(name, attrs);
        },

        insertField(key) {
            if (!key) return;
            editor.chain().focus().insertContent([{ type: 'field', attrs: { key } }, { type: 'text', text: ' ' }]).run();
        },

        async showPreview() {
            this.tab = 'preview';
            if (!cfg.previewUrl) return;
            this.previewBusy = true;
            this.previewError = null;
            try {
                const res = await fetch(cfg.previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ lease_body: editor.getHTML() }),
                });
                if (!res.ok) throw new Error();
                this.previewHtml = (await res.json()).html;
            } catch (e) {
                this.previewError = 'The preview could not load. Your edits are still here.';
            }
            this.previewBusy = false;
        },
    };
}
