document.addEventListener('DOMContentLoaded', function () {
    const selector = '[data-polski-ajax-filters]';

    const getShopContainer = () => document.querySelector('.woocommerce');

    const replaceFilterForms = (doc) => {
        const currentForms = Array.from(document.querySelectorAll(selector));
        const nextForms = Array.from(doc.querySelectorAll(selector));

        if (currentForms.length === 0 || nextForms.length === 0) {
            return;
        }

        currentForms.forEach((form, index) => {
            const nextForm = nextForms[index] || nextForms[0];

            if (nextForm) {
                form.replaceWith(nextForm);
            }
        });
    };

    const replaceProducts = (doc) => {
        const currentProducts = document.querySelector('ul.products');
        const nextProducts = doc.querySelector('ul.products');

        if (!currentProducts || !nextProducts) {
            return false;
        }

        const nextResultCount = doc.querySelector('.woocommerce-result-count');
        const nextPagination = doc.querySelector('.woocommerce-pagination');
        const currentResultCount = document.querySelector('.woocommerce-result-count');
        const currentPagination = document.querySelector('.woocommerce-pagination');

        currentProducts.replaceWith(nextProducts);

        if (currentResultCount && nextResultCount) {
            currentResultCount.replaceWith(nextResultCount);
        } else if (currentResultCount && !nextResultCount) {
            currentResultCount.remove();
        } else if (!currentResultCount && nextResultCount) {
            nextProducts.before(nextResultCount);
        }

        if (currentPagination && nextPagination) {
            currentPagination.replaceWith(nextPagination);
        } else if (currentPagination && !nextPagination) {
            currentPagination.remove();
        } else if (!currentPagination && nextPagination) {
            nextProducts.after(nextPagination);
        }

        return true;
    };

    const navigate = async (url) => {
        const shopContainer = getShopContainer();

        if (!shopContainer || !document.querySelector('ul.products')) {
            window.location.href = url.toString();
            return;
        }

        shopContainer.classList.add('polski-ajax-filters--loading');

        try {
            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                window.location.href = url.toString();
                return;
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            if (!replaceProducts(doc)) {
                window.location.href = url.toString();
                return;
            }

            replaceFilterForms(doc);
            window.history.pushState({}, '', url.toString());
        } catch (error) {
            window.location.href = url.toString();
        } finally {
            shopContainer.classList.remove('polski-ajax-filters--loading');
        }
    };

    document.addEventListener('submit', function (event) {
        const form = event.target.closest(selector);

        if (!form) {
            return;
        }

        event.preventDefault();

        const url = new URL(form.action || window.location.href, window.location.origin);
        const data = new FormData(form);
        const params = new URLSearchParams();

        url.search = '';

        data.forEach(function (value, key) {
            if (String(value).trim() !== '') {
                params.append(key, String(value));
            }
        });

        url.search = params.toString();

        navigate(url);
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-polski-ajax-filters-reset], [data-polski-ajax-filters-chip]');

        if (!link) {
            return;
        }

        event.preventDefault();

        const href = link.getAttribute('href');

        if (!href) {
            return;
        }

        navigate(new URL(href, window.location.origin));
    });
});
