/**
 * Member pages live under /member and call the member API; committee pages keep /admin/api.
 */
export function apiPath(path) {
    if (window.location.pathname.startsWith('/member') && path.startsWith('/admin/api/')) {
        return `/member/api/${path.slice('/admin/api/'.length)}`;
    }

    return path;
}

import { t } from './i18n';

/**
 * Error raised for any failed API call. `errors` holds Laravel validation messages keyed by field.
 */
export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Call a JSON endpoint in the committee area using the session cookie and CSRF token.
 */
export async function api(url, { method = 'GET', body } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    let payload;

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    const response = await fetch(apiPath(url), {
        method,
        headers,
        body: payload,
        credentials: 'same-origin',
    });

    if (response.status === 401) {
        window.location.href = '/login';
        throw new ApiError(t('app.feedback.session_expired'), 401);
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ApiError(messageFor(response.status, data), response.status, data.errors ?? {});
    }

    return data;
}

/**
 * Build a query string, skipping empty values.
 */
export function buildQuery(params) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.append(key, String(value));
        }
    });

    return query.toString();
}

/**
 * Return the first validation message for a field, if any.
 */
export function firstError(errors, field) {
    return errors?.[field]?.[0] ?? null;
}

function messageFor(status, data) {
    if (status === 422) {
        const firstMessage = Object.values(data.errors ?? {})[0]?.[0];

        return firstMessage ?? t('app.feedback.check_input');
    }

    if (status === 403) return t('app.feedback.forbidden');
    if (status === 404) return t('app.feedback.not_found');
    if (status === 419) return t('app.feedback.csrf');
    if (status === 429) return t('app.feedback.too_many');

    return data.message && status < 500 ? data.message : t('app.feedback.server_error');
}
