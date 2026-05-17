/**
 * Withdrawal form progressive enhancements.
 *
 * All forms work without JS. With JS we add:
 *
 *  1. Submit loading state: data-loading + aria-busy on the first click so
 *     screen readers announce "busy" and the user cannot double-submit.
 *  2. Notice autofocus: when a notice (success or error) is rendered, move
 *     focus to it so screen readers and keyboard users see it immediately.
 *  3. Items live counter: in the two-step form, an aria-live="polite" region
 *     reports the total quantity selected as the user changes the spinners.
 *
 * No build step; loaded with strategy=defer.
 */
(function () {
    'use strict';

    var FORM_SELECTOR = '.polski-withdrawal-lookup form, .polski-withdrawal-guest-form form, .polski-withdrawal-form form';
    var PENDING_LABEL = 'Wysyłanie…';

    // 1. Submit loading state.
    document.addEventListener('submit', function (evt) {
        var form = evt.target;
        if (!(form instanceof HTMLFormElement) || !form.matches(FORM_SELECTOR)) {
            return;
        }

        var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!submitBtn || submitBtn.dataset.loading === '1') {
            return;
        }

        submitBtn.dataset.loading = '1';
        submitBtn.setAttribute('aria-busy', 'true');

        var originalLabel = submitBtn.textContent;
        submitBtn.dataset.polskiOriginalLabel = originalLabel || '';
        submitBtn.textContent = PENDING_LABEL;

        // Restore the button after 8 seconds if the browser hasn't navigated.
        setTimeout(function () {
            if (submitBtn.dataset.loading !== '1') {
                return;
            }
            submitBtn.dataset.loading = '';
            submitBtn.removeAttribute('aria-busy');
            submitBtn.textContent = submitBtn.dataset.polskiOriginalLabel || originalLabel || '';
        }, 8000);
    }, true);

    // 2. Notice autofocus (single shot per page load).
    var notice = document.querySelector('.polski-withdrawal-notice');
    if (notice && typeof notice.focus === 'function') {
        // Defer to next paint so the browser doesn't compete with native autofocus.
        window.requestAnimationFrame(function () {
            notice.focus();
            notice.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }

    // 3. Items live counter + bulk select handlers.
    var itemsTable = document.querySelector('.polski-withdrawal-items');
    if (itemsTable) {
        var counter = document.createElement('p');
        counter.className = 'polski-withdrawal-items__counter';
        counter.setAttribute('role', 'status');
        counter.setAttribute('aria-live', 'polite');
        counter.style.color = '#475569';
        itemsTable.insertAdjacentElement('afterend', counter);

        var inputs = itemsTable.querySelectorAll('input[type="number"][name^="polski_items["]');
        var update = function () {
            var total = 0;
            inputs.forEach(function (input) {
                var val = parseFloat(input.value || '0');
                if (!isNaN(val) && val > 0) {
                    total += val;
                }
            });
            counter.textContent = total === 0
                ? 'Nie wybrano żadnej pozycji.'
                : 'Wybrano łącznie ' + total + ' sztuk do odstąpienia.';
        };
        inputs.forEach(function (input) {
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        update();

        // Bulk-select quick actions.
        document.querySelectorAll('[data-polski-select]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-polski-select');
                inputs.forEach(function (input) {
                    if (mode === 'all') {
                        input.value = input.getAttribute('max') || input.value;
                    } else if (mode === 'none') {
                        input.value = '0';
                    }
                });
                update();
            });
        });
    }
})();
