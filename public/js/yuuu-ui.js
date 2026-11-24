/*
 * yuuu-ui (README §5): dialogs as a progressive enhancement, and the
 * current link or tab centred in its row.
 */
(function () {
'use strict';

// The row scrolls, never the page.
document.querySelectorAll(':is(.side-nav, .tabs) [aria-current=page], .tabs [aria-selected=true]').forEach(function (item) {
    const row = item.closest('.side-nav, .tabs');
    const a = row.getBoundingClientRect();
    const b = item.getBoundingClientRect();
    row.scrollLeft += (b.left + b.right - a.left - a.right) / 2;
});

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
        // The original button's name=value is sent.
        state.form.requestSubmit(state.trigger);
        return;
    }

    el = target.closest('[data-confirm]');
    if (el) {
        dialog = byId(el.getAttribute('data-confirm'));
        if (dialog && el.form && el.form.requestSubmit) {
            open(event, dialog, el, el.form);
        }
        return;
    }

    el = target.closest('[data-modal-open]');
    if (el) {
        dialog = byId(el.getAttribute('data-modal-open'));
        // A modified click (new tab) is left alone.
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
