// Intercepts submissions of forms marked with data-confirm and shows the
// global modal component instead of the native confirm() dialog.
// Usage:
//   <form data-confirm="Approve this application?"
//         data-confirm-type="confirm"
//         data-confirm-message="This will grant the Landlord role."
//         data-confirm-button="Approve">
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form.dataset || !form.dataset.confirm) return;
    e.preventDefault();

    window.dispatchEvent(new CustomEvent('show-modal', {
        detail: {
            type: form.dataset.confirmType || 'confirm',
            title: form.dataset.confirm,
            message: form.dataset.confirmMessage || 'Are you sure you want to proceed?',
            confirmText: form.dataset.confirmButton || 'Confirm',
            cancelText: form.dataset.confirmCancel || 'Cancel',
            onConfirm: () => form.submit(),
        }
    }));
});
