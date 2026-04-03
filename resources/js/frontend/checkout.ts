/**
 * Frontend checkout enhancements.
 *
 * Handles:
 * - Legal checkbox client-side validation feedback
 * - Smooth transitions when AJAX fragments replace checkbox container
 * - Accessibility: focus management after fragment refresh
 */

declare const jQuery: any;

(function () {
    const CONTAINER_SELECTOR = '.polski-legal-checkboxes';
    const CHECKBOX_SELECTOR = '.polski-checkbox input[type="checkbox"]';
    const ERROR_CLASS = 'polski-checkbox--error';

    /**
     * Add inline validation feedback on required checkboxes.
     */
    function initValidation(): void {
        document.querySelectorAll<HTMLInputElement>(CHECKBOX_SELECTOR).forEach((input) => {
            if (!input.required) return;

            input.addEventListener('change', () => {
                const row = input.closest('.polski-checkbox');
                if (!row) return;

                if (input.checked) {
                    row.classList.remove(ERROR_CLASS);
                } else {
                    row.classList.add(ERROR_CLASS);
                }
            });
        });
    }

    /**
     * Preserve checked state across AJAX fragment refreshes.
     *
     * WooCommerce replaces the .polski-legal-checkboxes container
     * via update_order_review fragments. We save checked states before
     * the update and restore them after.
     */
    function initFragmentPersistence(): void {
        let savedStates: Record<string, boolean> = {};

        // Before fragments update: save states.
        jQuery(document.body).on('update_checkout', () => {
            savedStates = {};
            document.querySelectorAll<HTMLInputElement>(CHECKBOX_SELECTOR).forEach((input) => {
                savedStates[input.name] = input.checked;
            });
        });

        // After fragments update: restore states and re-init validation.
        jQuery(document.body).on('updated_checkout', () => {
            document.querySelectorAll<HTMLInputElement>(CHECKBOX_SELECTOR).forEach((input) => {
                if (input.name in savedStates) {
                    input.checked = savedStates[input.name];
                }
            });

            initValidation();
        });
    }

    /**
     * Initialize when DOM is ready and WooCommerce checkout is present.
     */
    function init(): void {
        const container = document.querySelector(CONTAINER_SELECTOR);
        if (!container) return;

        initValidation();

        // Only init fragment persistence if jQuery and WC checkout are available.
        if (typeof jQuery !== 'undefined') {
            initFragmentPersistence();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
