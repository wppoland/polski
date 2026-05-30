document.addEventListener('DOMContentLoaded', function () {
    // Event delegation so wishlist buttons rendered after page load
    // (infinite scroll, quick-view modal, AJAX filters) also work.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-polski-wishlist-button]');

        if (!button) {
            return;
        }

        var config = window.polskiWishlist;

        if (!config) {
            return;
        }

        event.preventDefault();

        if (!config.allowGuests && !document.body.classList.contains('logged-in')) {
            window.location.href = config.loginUrl;
            return;
        }

        // Guard against double submissions while a request is in flight.
        if (button.getAttribute('aria-busy') === 'true') {
            return;
        }

        var productId = button.dataset.productId || '';

        if (!productId) {
            return;
        }

        var formData = new FormData();
        formData.append('action', 'polski_wishlist_toggle');
        formData.append('nonce', config.nonce);
        formData.append('product_id', productId);

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json();
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.data) {
                return;
            }

            var active = !!payload.data.in_wishlist;

            // Sync every button for this product (loop + single can co-exist).
            document.querySelectorAll(
                '[data-polski-wishlist-button][data-product-id="' + productId + '"]'
            ).forEach(function (el) {
                el.classList.toggle('is-active', active);
                el.setAttribute('aria-pressed', active ? 'true' : 'false');
                if (payload.data.button_text) {
                    el.textContent = payload.data.button_text;
                }
            });
        }).catch(function () {
            // Network/parse failure: leave the button untouched.
        }).finally(function () {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
    });
});
