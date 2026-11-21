import Swal from 'sweetalert2';
import { t } from './i18n';

/**
 * Ask the user to confirm a data-changing action with SweetAlert2.
 * Resolves true only when the user confirms.
 */
export async function confirmAction({
    title,
    text = '',
    confirmButtonText = t('app.common.yes_continue'),
    danger = false,
}) {
    const result = await Swal.fire({
        title,
        text,
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: t('app.common.cancel'),
        confirmButtonColor: danger ? '#dc3545' : '#0d6efd',
        reverseButtons: true,
        focusCancel: true,
    });

    return result.isConfirmed;
}

export function notifySuccess(title) {
    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title,
        showConfirmButton: false,
        timer: 2800,
        timerProgressBar: true,
    });
}

export function notifyError(message) {
    return Swal.fire({
        icon: 'error',
        title: t('app.common.error_title'),
        text: message,
        confirmButtonText: t('app.common.close'),
    });
}
