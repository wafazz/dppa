/*
 * Six-digit code entry: six boxes, filled one digit at a time from an iPhone-style number pad in a
 * bottom sheet. Physical keys also work. The value is written to a hidden input, so the form posts
 * exactly what a text field would, and the server validation is unchanged.
 */

const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'back'];

let sheet = null;
let activeField = null;

function buildSheet() {
    const backdrop = document.createElement('div');
    backdrop.className = 'pin-sheet-backdrop';
    backdrop.hidden = true;

    backdrop.innerHTML = `
        <div class="pin-sheet" role="dialog" aria-modal="true" aria-labelledby="pin-sheet-title">
            <div class="pin-sheet-header">
                <span id="pin-sheet-title" class="fw-semibold"></span>
                <button type="button" class="btn btn-link btn-sm text-decoration-none" data-pin-cancel></button>
            </div>
            <div class="pin-sheet-preview" data-pin-preview></div>
            <div class="pin-keypad">
                ${KEYS.map((key) => {
                    if (key === '') {
                        return '<span></span>';
                    }
                    if (key === 'back') {
                        return '<button type="button" class="pin-key pin-key-muted" data-pin-key="back" aria-label="Backspace">⌫</button>';
                    }
                    return `<button type="button" class="pin-key" data-pin-key="${key}">${key}</button>`;
                }).join('')}
            </div>
        </div>`;

    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) {
            closeSheet();
        }
    });

    backdrop.querySelector('[data-pin-cancel]').addEventListener('click', closeSheet);
    backdrop.querySelector('.pin-keypad').addEventListener('click', (event) => {
        const key = event.target.closest('[data-pin-key]');

        if (key && activeField) {
            press(key.dataset.pinKey);
        }
    });

    document.body.appendChild(backdrop);

    return backdrop;
}

function openSheet(field) {
    sheet ??= buildSheet();
    activeField = field;

    sheet.querySelector('#pin-sheet-title').textContent = field.dataset.title ?? '';
    sheet.querySelector('[data-pin-cancel]').textContent = field.dataset.cancel ?? '';
    sheet.hidden = false;
    document.body.classList.add('pin-sheet-open');
    render(field);
}

function closeSheet() {
    if (!sheet) {
        return;
    }

    sheet.hidden = true;
    document.body.classList.remove('pin-sheet-open');
    activeField?.focus();
    activeField = null;
}

// The hidden input that the form posts. It may sit inside the field or beside it.
function valueInput(field) {
    return field.querySelector('[data-pin-value]')
        ?? field.parentElement?.querySelector('[data-pin-value]')
        ?? null;
}

function valueOf(field) {
    return valueInput(field)?.value ?? '';
}

function setValue(field, value) {
    const input = valueInput(field);

    if (input) {
        input.value = value;
    }
}

function press(key) {
    if (!activeField) {
        return;
    }

    if (key === 'back') {
        backspace(activeField);
    } else if (/^\d$/.test(key)) {
        append(activeField, key);
    }
}

function append(field, digit) {
    const length = Number(field.dataset.length || 6);
    const current = valueOf(field);

    if (current.length >= length) {
        return;
    }

    setValue(field, current + digit);
    render(field);

    if (current.length + 1 === length) {
        complete(field);
    }
}

function backspace(field) {
    setValue(field, valueOf(field).slice(0, -1));
    render(field);
}

function complete(field) {
    closeSheet();

    // Give the last box a moment to show the digit before the form is sent.
    window.setTimeout(() => field.closest('form')?.requestSubmit(), 180);
}

function render(field) {
    const value = valueOf(field);
    const boxes = field.querySelectorAll('[data-pin-box]');
    const active = field === activeField ? Math.min(value.length, boxes.length - 1) : -1;

    boxes.forEach((box, index) => {
        box.textContent = value[index] ?? '';
        box.classList.toggle('pin-box-active', index === active);
    });

    const preview = sheet?.querySelector('[data-pin-preview]');

    if (preview && field === activeField) {
        preview.textContent = '•'.repeat(value.length) + '·'.repeat(Number(field.dataset.length || 6) - value.length);
    }
}

function setupField(field) {
    if (!valueInput(field)) {
        return;
    }

    render(field);

    field.addEventListener('click', () => openSheet(field));

    field.addEventListener('keydown', (event) => {
        if (/^\d$/.test(event.key)) {
            event.preventDefault();
            append(field, event.key);
        } else if (event.key === 'Backspace') {
            event.preventDefault();
            backspace(field);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            openSheet(field);
        }
    });

    // Touch screens get the pad straight away; desktops type into the boxes.
    if (field.dataset.autoOpen !== undefined && window.matchMedia('(pointer: coarse)').matches) {
        window.setTimeout(() => openSheet(field), 250);
    }
}

document.addEventListener('keydown', (event) => {
    if (!sheet || sheet.hidden || !activeField) {
        return;
    }

    if (event.key === 'Escape') {
        closeSheet();
    } else if (/^\d$/.test(event.key)) {
        event.preventDefault();
        press(event.key);
    } else if (event.key === 'Backspace') {
        event.preventDefault();
        press('back');
    }
});

export function initPinPads(root = document) {
    root.querySelectorAll('[data-pin-pad]').forEach(setupField);
}
