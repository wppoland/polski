/**
 * Polski frontend JavaScript.
 *
 * Handles:
 * - Variable product: update unit price and omnibus when variation changes
 * - Checkout: legal checkbox validation
 */
(function () {
    'use strict';

    /**
     * Variable product: update Polski data when variation is selected.
     */
    function initVariationObserver() {
        var form = document.querySelector('form.variations_form');

        if (!form) {
            return;
        }

        // jQuery event from WooCommerce.
        if (typeof jQuery !== 'undefined') {
            jQuery(form).on('found_variation', function (event, variation) {
                updateDisplayElements(variation);
            });

            jQuery(form).on('reset_data', function () {
                resetDisplayElements();
            });
        }
    }

    function updateDisplayElements(variation) {
        // Update unit price if variation has Polski data.
        var unitPriceEl = document.querySelector('.polski-unit-price');
        var omnibusEl = document.querySelector('.polski-omnibus-price');

        // WooCommerce Store API provides Polski extension data.
        // For classic templates, we recalculate from variation data.
        if (variation.polski_unit_price_html) {
            if (unitPriceEl) {
                unitPriceEl.innerHTML = variation.polski_unit_price_html;
            }
        }

        if (variation.polski_omnibus_html) {
            if (omnibusEl) {
                omnibusEl.innerHTML = variation.polski_omnibus_html;
            }
        }
    }

    function resetDisplayElements() {
        // Reset to parent product display (handled by WooCommerce re-render).
    }

    /**
     * Checkout: client-side validation for legal checkboxes.
     */
    function initCheckoutValidation() {
        var checkoutForm = document.querySelector('form.checkout');

        if (!checkoutForm) {
            return;
        }

        checkoutForm.addEventListener('submit', function (e) {
            var requiredCheckboxes = checkoutForm.querySelectorAll(
                '.polski-checkbox.validate-required input[type="checkbox"]'
            );

            var hasError = false;

            requiredCheckboxes.forEach(function (checkbox) {
                var wrapper = checkbox.closest('.polski-checkbox');

                if (!checkbox.checked) {
                    hasError = true;
                    if (wrapper) {
                        wrapper.style.borderLeft = '3px solid #c00';
                        wrapper.style.paddingLeft = '8px';
                    }
                } else {
                    if (wrapper) {
                        wrapper.style.borderLeft = '';
                        wrapper.style.paddingLeft = '';
                    }
                }
            });

            // Don't prevent submission - let server-side validation handle it.
            // This is just visual feedback.
        });
    }

    // Init on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initVariationObserver();
            initCheckoutValidation();
        });
    } else {
        initVariationObserver();
        initCheckoutValidation();
    }
})();
