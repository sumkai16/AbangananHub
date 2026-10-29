// The lease editor's engine. Loaded on demand the first time a landlord opens the editor, so TipTap
// stays out of the main bundle. Only the formats the lease sanitizer allows are switched on.
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// A merge field: "[Monthly rent]" in the editor, replaced from the lease's own data when the agreement is sent.
const Field = Node.create({
    name: 'field',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,
    addOptions: () => ({ labels: {} }),
    addAttributes: () => ({
        key: {
            default: null,
            parseHTML: (el) => el.getAttribute('data-field'),
            renderHTML: (attrs) => ({ 'data-field': attrs.key }),
        },
    }),
    parseHTML: () => [{ tag: 'span[data-field]' }],
    renderHTML({ node, HTMLAttributes }) {
        const label = this.options.labels[node.attrs.key] ?? node.attrs.key;

        return ['span', mergeAttributes({ class: 'lease-field' }, HTMLAttributes), label];
    },
});

export function createLeaseEditor({ element, html, labels, onChange }) {
    return new Editor({
        element,
        content: html,
        extensions: [
            StarterKit.configure({
                heading: { levels: [3] },
                blockquote: false,
                code: false,
                codeBlock: false,
                strike: false,
                horizontalRule: false,
                link: false,
            }),
            Field.configure({ labels }),
        ],
        editorProps: {
            attributes: { class: 'lease-prose', 'aria-label': 'Lease text', role: 'textbox', 'aria-multiline': 'true' },
        },
        onTransaction: onChange,
    });
}
