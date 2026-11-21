/*
 * Translations for the committee area. The messages come from lang/en/app.php and lang/ms/app.php,
 * injected by layouts/admin.blade.php. Changing language reloads the page, so the lookup is static.
 */
const messages = window.APP_MESSAGES ?? {};

export const locale = window.APP_LOCALE ?? 'en';

export const dateLocale = locale === 'ms' ? 'ms-MY' : 'en-MY';

/**
 * Look up a translation key and replace :placeholders. Missing keys show the key itself.
 */
export function t(key, params = {}) {
    let text = messages[key] ?? key;

    for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`:${name}`, String(value));
    }

    return text;
}
