document.addEventListener('DOMContentLoaded', function () {
    const selector = '[data-polski-ajax-filters]';
    const mobileBreakpoint = window.matchMedia('(max-width: 640px)');
    let instantTimer = null;

    const getShopContainer = () => document.querySelector('.woocommerce');
    const getForm = (element) => element.closest(selector);

    const closeMobilePanel = (form) => {
        if (!form) {
            return;
        }

        form.classList.remove('is-mobile-open');

        const toggle = form.querySelector('[data-polski-ajax-filters-open]');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    };

    const openMobilePanel = (form) => {
        if (!form) {
            return;
        }

        form.classList.add('is-mobile-open');

        const toggle = form.querySelector('[data-polski-ajax-filters-open]');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }
    };

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

        const parts = ['.woocommerce-result-count', '.woocommerce-pagination'];
        // Where each new copy sits, read before any node leaves the fetched page.
        const previous = new Map();

        parts.forEach((part) => {
            doc.querySelectorAll(part).forEach((element) => {
                const siblings = [];

                for (let node = element.previousElementSibling; node; node = node.previousElementSibling) {
                    siblings.push(node);
                }

                previous.set(element, siblings);
            });
        });

        currentProducts.replaceWith(nextProducts);

        // Themes such as Storefront print the result count and the pagination
        // both above and below the list, so every copy is swapped.
        parts.forEach((part) => {
            const current = Array.from(document.querySelectorAll(part));
            const next = Array.from(doc.querySelectorAll(part));

            current.forEach((element, index) => {
                if (next[index]) {
                    element.replaceWith(next[index]);
                } else {
                    element.remove();
                }
            });

            // A copy the page lacked (a single-page result has no pagination)
            // goes after the nearest node it followed that is now on the page.
            next.slice(current.length).forEach((element) => {
                const anchor = previous.get(element).find((node) => document.contains(node));

                if (anchor) {
                    anchor.after(element);
                } else if (part === '.woocommerce-result-count') {
                    nextProducts.before(element);
                } else {
                    nextProducts.after(element);
                }
            });
        });

        return true;
    };

    const getResultCount = (doc) => {
        const resultCount = doc.querySelector('.woocommerce-result-count');

        if (resultCount) {
            const match = (resultCount.textContent || '').match(/\d[\d\s.,]*/);

            if (match) {
                const parsed = Number.parseInt(match[0].replace(/[\s.,]/g, ''), 10);

                if (!Number.isNaN(parsed)) {
                    return parsed;
                }
            }
        }

        const nextProducts = doc.querySelector('ul.products');

        if (nextProducts) {
            return nextProducts.querySelectorAll('li.product').length;
        }

        return null;
    };

    const announceResults = (doc) => {
        const region = document.querySelector('[data-polski-ajax-filters-status]');

        if (!region) {
            return;
        }

        const config = window.polskiAjaxFilters || {};
        const count = getResultCount(doc);
        let message = config.resultsUpdatedText || '';

        if (count !== null && message.indexOf('%d') !== -1) {
            message = message.replace('%d', String(count));
        } else if (message.indexOf('%d') !== -1) {
            message = config.resultsUpdatedGenericText || message.replace('%d', '').trim();
        }

        region.textContent = '';
        region.textContent = message;
    };

    const navigate = async (url, options = {}) => {
        const pushHistory = options.pushHistory !== false;
        const shopContainer = getShopContainer();

        document.querySelectorAll(selector).forEach(closeMobilePanel);

        if (!shopContainer || !document.querySelector('ul.products')) {
            window.location.href = url.toString();
            return;
        }

        shopContainer.classList.add('polski-ajax-filters--loading');
        shopContainer.setAttribute('aria-busy', 'true');

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
            announceResults(doc);
            document.dispatchEvent(new CustomEvent('polski:products-replaced'));
            if (pushHistory) {
                window.history.pushState({}, '', url.toString());
            }
        } catch (error) {
            window.location.href = url.toString();
        } finally {
            shopContainer.classList.remove('polski-ajax-filters--loading');
            shopContainer.removeAttribute('aria-busy');
        }
    };

    const submitForm = (form) => {
        if (!form) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    };

    const scheduleSubmit = (form, debounceMs) => {
        window.clearTimeout(instantTimer);
        instantTimer = window.setTimeout(function () {
            submitForm(form);
        }, debounceMs);
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
        const openButton = event.target.closest('[data-polski-ajax-filters-open]');

        if (openButton) {
            event.preventDefault();
            openMobilePanel(getForm(openButton));
            return;
        }

        const closeButton = event.target.closest('[data-polski-ajax-filters-close]');

        if (closeButton) {
            event.preventDefault();
            closeMobilePanel(getForm(closeButton));
            return;
        }

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

    document.addEventListener('change', function (event) {
        const field = event.target;

        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
            return;
        }

        const form = getForm(field);

        if (!form || form.dataset.polskiInstantFiltering !== '1') {
            return;
        }

        const debounceMs = Number.parseInt(form.dataset.polskiInstantDebounce || '350', 10) || 350;

        if (field instanceof HTMLSelectElement && field.multiple) {
            return;
        }

        if (field instanceof HTMLInputElement && (field.type === 'number' || field.type === 'text')) {
            return;
        }

        scheduleSubmit(form, debounceMs);
    });

    document.addEventListener('input', function (event) {
        const field = event.target;

        if (!(field instanceof HTMLInputElement)) {
            return;
        }

        const form = getForm(field);

        if (!form || form.dataset.polskiInstantFiltering !== '1') {
            return;
        }

        if (field.type !== 'number' && field.type !== 'text') {
            return;
        }

        const debounceMs = Number.parseInt(form.dataset.polskiInstantDebounce || '350', 10) || 350;
        scheduleSubmit(form, debounceMs);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll(selector).forEach(closeMobilePanel);
    });

    mobileBreakpoint.addEventListener('change', function (event) {
        if (!event.matches) {
            document.querySelectorAll(selector).forEach(closeMobilePanel);
        }
    });

    window.addEventListener('popstate', function () {
        if (!document.querySelector(selector)) {
            return;
        }

        navigate(new URL(window.location.href), { pushHistory: false });
    });
});
