import Quill from 'quill';

const TOOLBAR = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link', 'clean'],
];

/**
 * Mount a Quill rich-text editor on an element.
 * onChange receives sanitized-on-server HTML, or an empty string when the editor has no visible text.
 */
export function createEditor(element, initialHtml, onChange, placeholder = '') {
    const quill = new Quill(element, {
        theme: 'snow',
        placeholder,
        modules: { toolbar: TOOLBAR },
    });

    const setHtml = (html) => {
        quill.setContents(quill.clipboard.convert({ html: html || '' }), 'silent');
    };

    setHtml(initialHtml);

    quill.on('text-change', (_delta, _oldDelta, source) => {
        if (source !== 'user') {
            return;
        }

        onChange(quill.getText().trim() === '' ? '' : quill.root.innerHTML);
    });

    return {
        setHtml,
        currentHtml: () => (quill.getText().trim() === '' ? '' : quill.root.innerHTML),
        destroy: () => {
            element.innerHTML = '';
        },
    };
}
