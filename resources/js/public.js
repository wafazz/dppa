import { createEditor } from './lib/editor';
import { initPinPads } from './lib/pinPad';

/*
 * Public registration form enhancements: Select2 for the category list and Quill for every textarea.
 * The original textarea stays in the form and receives the HTML, so server-side validation is unchanged.
 */
document.querySelectorAll('select[data-app-select2]').forEach((select) => {
    window.$(select).select2({
        width: '100%',
        language: 'ms',
        theme: 'bootstrap-5',
        placeholder: select.dataset.placeholder || 'Pilih',
        dropdownParent: window.$(document.body),
    });
});

document.querySelectorAll('textarea[data-rich-text]').forEach((textarea) => {
    const holder = document.createElement('div');
    holder.className = 'app-rich-text';

    textarea.classList.add('d-none');
    textarea.after(holder);

    createEditor(
        holder,
        textarea.value,
        (html) => {
            textarea.value = html;
        },
        textarea.dataset.placeholder || '',
    );
});

initPinPads();
