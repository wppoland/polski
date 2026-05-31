(function () {
    var popup = document.querySelector('[data-polski-popup]');

    if (!popup || !window.polskiPopup) {
        return;
    }

    var storageKey = 'polski_popup_dismissed_at';
    var dismissedAt = Number(window.localStorage.getItem(storageKey) || '0');
    var frequencyMs = Number(window.polskiPopup.frequencyDays || 7) * 24 * 60 * 60 * 1000;

    if (dismissedAt && Date.now() - dismissedAt < frequencyMs) {
        return;
    }

    var dialog = popup.querySelector('.polski-popup__dialog') || popup;
    var FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    // Element focused before the popup opened, so we can restore it on close.
    var lastFocused = null;

    function focusableInDialog() {
        return Array.prototype.slice
            .call(dialog.querySelectorAll(FOCUSABLE))
            .filter(function (el) {
                return el.offsetParent !== null;
            });
    }

    function focusFirst() {
        var items = focusableInDialog();
        if (items.length > 0) {
            items[0].focus();
        } else if (typeof dialog.focus === 'function') {
            dialog.focus();
        }
    }

    function open() {
        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        popup.hidden = false;
        focusFirst();
    }

    function close() {
        popup.hidden = true;
        window.localStorage.setItem(storageKey, String(Date.now()));

        if (lastFocused && typeof lastFocused.focus === 'function' && document.contains(lastFocused)) {
            lastFocused.focus();
        }
        lastFocused = null;
    }

    function trapFocus(event) {
        var items = focusableInDialog();
        if (items.length === 0) {
            event.preventDefault();
            if (typeof dialog.focus === 'function') {
                dialog.focus();
            }
            return;
        }

        var first = items[0];
        var last = items[items.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || active === dialog)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    popup.querySelectorAll('[data-polski-popup-close]').forEach(function (node) {
        node.addEventListener('click', close);
    });

    if (window.polskiPopup.showBackdropClose) {
        popup.querySelectorAll('[data-polski-popup-backdrop]').forEach(function (node) {
            node.addEventListener('click', close);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (popup.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'Tab') {
            trapFocus(event);
        }
    });

    window.setTimeout(open, Math.max(0, Number(window.polskiPopup.delaySeconds || 0)) * 1000);
})();
