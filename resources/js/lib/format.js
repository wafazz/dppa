import { dateLocale, t } from './i18n';

const dateFormatter = new Intl.DateTimeFormat(dateLocale, { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFormatter = new Intl.DateTimeFormat(dateLocale, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function formatDate(value) {
    if (!value) {
        return '—';
    }

    return dateFormatter.format(new Date(value));
}

export function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return dateTimeFormatter.format(new Date(value));
}

export function statusOptions() {
    return [
        { id: 'pending', text: t('app.enums.member_status.pending') },
        { id: 'approved', text: t('app.enums.member_status.approved') },
        { id: 'rejected', text: t('app.enums.member_status.rejected') },
    ];
}

export function statusBadgeClass(status) {
    return {
        pending: 'text-bg-warning',
        approved: 'text-bg-success',
        rejected: 'text-bg-danger',
    }[status] ?? 'text-bg-secondary';
}
