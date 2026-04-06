/**
 * Live Cart Sidebar drawer.
 *
 * Opens a slide-in panel when a product is added to cart.
 * Relies on WooCommerce cart fragments for content updates.
 */
(function ($) {
    'use strict';

    if (typeof polskiLiveCart === 'undefined') {
        return;
    }

    var config = polskiLiveCart;
    var $drawer = null;
    var isOpen = false;

    function open() {
        if (!$drawer || isOpen) {
            return;
        }
        isOpen = true;
        $drawer.attr('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        if (!$drawer || !isOpen) {
            return;
        }
        isOpen = false;
        $drawer.attr('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function init() {
        $drawer = $('.polski-cart-drawer');

        if (!$drawer.length) {
            return;
        }

        // Close button.
        $drawer.on('click', '.polski-cart-drawer__close', function (e) {
            e.preventDefault();
            close();
        });

        // Overlay click.
        if (config.overlay) {
            $drawer.on('click', '.polski-cart-drawer__overlay', function () {
                close();
            });
        }

        // Escape key.
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) {
                close();
            }
        });

        // Auto-open on add to cart (AJAX).
        if (config.autoOpen) {
            $(document.body).on('added_to_cart', function () {
                open();
            });

            // Support for custom polski event.
            $(document.body).on('polski_added_to_cart', function () {
                open();
            });
        }
    }

    $(document).ready(init);

})(jQuery);
