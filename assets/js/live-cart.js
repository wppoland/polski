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
    var drawerEl = null;
    var panelEl = null;
    var isOpen = false;
    var lastFocused = null;

    var FOCUSABLE = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function setInert(inert) {
        if (!drawerEl) {
            return;
        }
        // When closed the drawer is only moved off-screen, so without `inert`
        // its links/buttons stay in the tab order and reachable while invisible.
        if ('inert' in drawerEl) {
            drawerEl.inert = inert;
        }
    }

    function focusableInPanel() {
        var scope = panelEl || drawerEl;
        if (!scope) {
            return [];
        }
        return Array.prototype.slice.call(scope.querySelectorAll(FOCUSABLE)).filter(function (el) {
            return el.offsetParent !== null;
        });
    }

    function open() {
        if (!drawerEl || isOpen) {
            return;
        }
        isOpen = true;
        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        setInert(false);
        drawerEl.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var items = focusableInPanel();
        if (items.length > 0) {
            items[0].focus();
        } else if (panelEl && typeof panelEl.focus === 'function') {
            panelEl.setAttribute('tabindex', '-1');
            panelEl.focus();
        }
    }

    function close() {
        if (!drawerEl || !isOpen) {
            return;
        }
        isOpen = false;
        drawerEl.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setInert(true);

        if (lastFocused && typeof lastFocused.focus === 'function' && document.contains(lastFocused)) {
            lastFocused.focus();
        }
        lastFocused = null;
    }

    function trapFocus(event) {
        if (!isOpen) {
            return;
        }

        var items = focusableInPanel();
        if (items.length === 0) {
            return;
        }

        var first = items[0];
        var last = items[items.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || active === panelEl)) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function init() {
        $drawer = $('.polski-cart-drawer');

        if (!$drawer.length) {
            return;
        }

        drawerEl = $drawer[0];
        panelEl = drawerEl.querySelector('.polski-cart-drawer__panel');

        // Start closed and non-interactive.
        setInert(true);

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

        // Escape key + focus trap.
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) {
                close();
            } else if (e.key === 'Tab') {
                trapFocus(e);
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
