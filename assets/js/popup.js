(function () {
    const popup = document.querySelector('[data-spolszczony-popup]');

    if (!popup || !window.spolszczonyPopup) {
        return;
    }

    const storageKey = 'spolszczony_popup_dismissed_at';
    const dismissedAt = Number(window.localStorage.getItem(storageKey) || '0');
    const frequencyMs = Number(window.spolszczonyPopup.frequencyDays || 7) * 24 * 60 * 60 * 1000;

    if (dismissedAt && Date.now() - dismissedAt < frequencyMs) {
        return;
    }

    const close = () => {
        popup.hidden = true;
        window.localStorage.setItem(storageKey, String(Date.now()));
    };

    popup.querySelectorAll('[data-spolszczony-popup-close]').forEach((node) => {
        node.addEventListener('click', close);
    });

    if (window.spolszczonyPopup.showBackdropClose) {
        popup.querySelectorAll('[data-spolszczony-popup-backdrop]').forEach((node) => {
            node.addEventListener('click', close);
        });
    }

    window.setTimeout(() => {
        popup.hidden = false;
    }, Math.max(0, Number(window.spolszczonyPopup.delaySeconds || 0)) * 1000);
})();
