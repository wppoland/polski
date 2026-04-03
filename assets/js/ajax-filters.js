document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-spolszczony-ajax-filters]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            var url = new URL(form.action || window.location.href, window.location.origin);
            var data = new FormData(form);

            url.search = '';

            data.forEach(function (value, key) {
                if (String(value).trim() !== '') {
                    url.searchParams.set(key, String(value));
                }
            });

            var shopContainer = document.querySelector('.woocommerce');
            var products = document.querySelector('ul.products');

            if (!shopContainer || !products) {
                window.location.href = url.toString();
                return;
            }

            shopContainer.classList.add('spolszczony-ajax-filters--loading');

            try {
                var response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    window.location.href = url.toString();
                    return;
                }

                var html = await response.text();
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var nextProducts = doc.querySelector('ul.products');
                var nextResultCount = doc.querySelector('.woocommerce-result-count');
                var nextPagination = doc.querySelector('.woocommerce-pagination');
                var currentResultCount = document.querySelector('.woocommerce-result-count');
                var currentPagination = document.querySelector('.woocommerce-pagination');

                if (!nextProducts) {
                    window.location.href = url.toString();
                    return;
                }

                products.replaceWith(nextProducts);

                if (currentResultCount && nextResultCount) {
                    currentResultCount.replaceWith(nextResultCount);
                }

                if (currentPagination && nextPagination) {
                    currentPagination.replaceWith(nextPagination);
                } else if (currentPagination && !nextPagination) {
                    currentPagination.remove();
                } else if (!currentPagination && nextPagination) {
                    nextProducts.after(nextPagination);
                }

                window.history.pushState({}, '', url.toString());
            } catch (error) {
                window.location.href = url.toString();
            } finally {
                shopContainer.classList.remove('spolszczony-ajax-filters--loading');
            }
        });
    });
});
