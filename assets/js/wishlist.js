document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-spolszczony-wishlist-button]').forEach(function (button) {
        button.addEventListener('click', async function () {
            if (!window.spolszczonyWishlist) {
                return;
            }

            if (!window.spolszczonyWishlist.allowGuests && !document.body.classList.contains('logged-in')) {
                window.location.href = window.spolszczonyWishlist.loginUrl;
                return;
            }

            var formData = new FormData();
            formData.append('action', 'spolszczony_wishlist_toggle');
            formData.append('nonce', window.spolszczonyWishlist.nonce);
            formData.append('product_id', button.dataset.productId || '');

            button.disabled = true;

            try {
                var response = await fetch(window.spolszczonyWishlist.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });

                var payload = await response.json();

                if (!payload.success || !payload.data) {
                    button.disabled = false;
                    return;
                }

                button.classList.toggle('is-active', !!payload.data.in_wishlist);
                button.textContent = payload.data.button_text || button.textContent;
            } catch (error) {
            } finally {
                button.disabled = false;
            }
        });
    });
});
