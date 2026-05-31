(function () {
    const root = document.querySelector('.polski-infinite-scroll');

    if (!root) {
        return;
    }

    const list = document.querySelector('ul.products');
    const button = root.querySelector('.polski-infinite-scroll__button');
    const status = root.querySelector('.polski-infinite-scroll__status');
    const pagination = document.querySelector('.woocommerce-pagination');

    if (!list || !button) {
        return;
    }

    if (pagination) {
        pagination.hidden = true;
    }

    if ((window.polskiInfiniteScroll && window.polskiInfiniteScroll.mode) === 'auto'
        && !((window.polskiInfiniteScroll && window.polskiInfiniteScroll.showButtonInAutoMode))) {
        button.hidden = true;
    }

    let nextPageUrl = root.dataset.nextPage || '';
    let loading = false;
    let autoLoaded = 0;
    let statusClearTimer = null;

    const setStatus = (message) => {
        if (statusClearTimer) {
            window.clearTimeout(statusClearTimer);
            statusClearTimer = null;
        }
        if (status) {
            status.textContent = message || '';
        }
    };

    // Announce a transient message via the aria-live status region, then clear
    // it so the live region is not left cluttered for sighted users.
    const announce = (message) => {
        if (!message) {
            return;
        }
        setStatus(message);
        statusClearTimer = window.setTimeout(() => setStatus(''), 3000);
    };

    const updateDoneState = () => {
        if (!nextPageUrl) {
            button.hidden = true;
            setStatus((window.polskiInfiniteScroll && window.polskiInfiniteScroll.endText) || '');
        }
    };

    const loadNextPage = async () => {
        if (!nextPageUrl || loading) {
            return;
        }

        loading = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setStatus((window.polskiInfiniteScroll && window.polskiInfiniteScroll.loadingText) || '');

        try {
            const response = await fetch(nextPageUrl, { credentials: 'same-origin' });
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newList = doc.querySelector('ul.products');
            const newPaginationNext = doc.querySelector('.woocommerce-pagination .next');

            if (newList) {
                Array.from(newList.children).forEach((item) => {
                    list.appendChild(item);
                });
            }

            nextPageUrl = newPaginationNext ? newPaginationNext.href : '';

            if (nextPageUrl) {
                announce((window.polskiInfiniteScroll && window.polskiInfiniteScroll.loadedText) || '');
            }

            updateDoneState();
        } catch (error) {
            setStatus((window.polskiInfiniteScroll && window.polskiInfiniteScroll.errorText) || '');
        } finally {
            loading = false;
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    };

    button.addEventListener('click', loadNextPage);

    if ((window.polskiInfiniteScroll && window.polskiInfiniteScroll.mode) !== 'auto') {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            const limit = Number((window.polskiInfiniteScroll && window.polskiInfiniteScroll.autoAfterPages) || 0);

            if (limit > 0 && autoLoaded >= limit) {
                observer.disconnect();
                return;
            }

            autoLoaded += 1;
            loadNextPage();
        });
    }, { rootMargin: '300px 0px' });

    observer.observe(root);
})();
