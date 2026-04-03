(function () {
    const popup = document.querySelector('[data-polski-popup]');

    if (!popup || !window.polskiPopup) {
        return;
    }

    const storageKey = 'polski_popup_dismissed_at';
    const dismissedAt = Number(window.localStorage.getItem(storageKey) || '0');
    const frequencyMs = Number(window.polskiPopup.frequencyDays || 7) * 24 * 60 * 60 * 1000;

    if (dismissedAt && Date.now() - dismissedAt < frequencyMs) {
        return;
    }

    const close = () => {
        popup.hidden = true;
        window.localStorage.setItem(storageKey, String(Date.now()));
    };

    popup.querySelectorAll('[data-polski-popup-close]').forEach((node) => {
        node.addEventListener('click', close);
    });

    if (window.polskiPopup.showBackdropClose) {
        popup.querySelectorAll('[data-polski-popup-backdrop]').forEach((node) => {
            node.addEventListener('click', close);
        });
    }

    window.setTimeout(() => {
        popup.hidden = false;
    }, Math.max(0, Number(window.polskiPopup.delaySeconds || 0)) * 1000);
})();
