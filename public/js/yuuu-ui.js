/*
 * yuuu-ui: dialogs as a progressive enhancement (README, "Dialogs").
 * Without this file, or without <dialog>, links navigate, forms submit.
 */
(function () {
    'use strict';

    const Dialog = window.HTMLDialogElement;
    if (!Dialog || typeof Dialog.prototype.showModal !== 'function') {
        return;
    }

    // dialog -> { trigger, form }
    const openedBy = new WeakMap();

    function byId(id) {
        const dialog = id ? document.getElementById(id) : null;
        return dialog instanceof Dialog ? dialog : null;
    }

    function open(event, dialog, trigger, form) {
        event.preventDefault();
        if (!dialog.open) {
            openedBy.set(dialog, { trigger: trigger, form: form });
            dialog.showModal();
        }
    }

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (event.defaultPrevented || !(target instanceof Element)) {
            return;
        }

        let el = target.closest('[data-modal-close]');
        let dialog = el && el.closest('dialog');
        if (dialog) {
            event.preventDefault();
            dialog.close();
            return;
        }

        el = target.closest('[data-modal-confirm]');
        dialog = el && el.closest('dialog');
        const state = dialog && openedBy.get(dialog);
        if (state && state.form) {
            event.preventDefault();
            dialog.close();
            // The original button: its name=value is sent too.
            state.form.requestSubmit(state.trigger);
            return;
        }

        el = target.closest('[data-confirm]');
        if (el) {
            dialog = byId(el.getAttribute('data-confirm'));
            if (dialog && el.form && typeof el.form.requestSubmit === 'function') {
                open(event, dialog, el, el.form);
            }
            return;
        }

        el = target.closest('[data-modal-open]');
        if (el) {
            dialog = byId(el.getAttribute('data-modal-open'));
            // A modified click keeps its meaning (new tab).
            if (dialog && event.button === 0 && !(event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) {
                open(event, dialog, el, null);
            }
            return;
        }

        // Backdrop: a click on the dialog, outside its box.
        if (target instanceof Dialog && target.open && target.classList.contains('modal')) {
            const box = target.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
                target.close();
            }
        }
    });

    // `close` (Escape, close()) does not bubble: capture it.
    document.addEventListener('close', function (event) {
        const state = openedBy.get(event.target);
        if (state) {
            openedBy.delete(event.target);
            if (state.trigger.isConnected) {
                state.trigger.focus();
            }
        }
    }, true);
}());
