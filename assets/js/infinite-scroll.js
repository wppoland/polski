(function () {
    const root = document.querySelector('.spolszczony-infinite-scroll');

    if (!root) {
        return;
    }

    const list = document.querySelector('ul.products');
    const button = root.querySelector('.spolszczony-infinite-scroll__button');
    const status = root.querySelector('.spolszczony-infinite-scroll__status');
    const pagination = document.querySelector('.woocommerce-pagination');

    if (!list || !button) {
        return;
    }

    if (pagination) {
        pagination.hidden = true;
    }

    if ((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.mode) === 'auto'
        && !((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.showButtonInAutoMode))) {
        button.hidden = true;
    }

    let nextPageUrl = root.dataset.nextPage || '';
    let loading = false;
    let autoLoaded = 0;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message || '';
        }
    };

    const updateDoneState = () => {
        if (!nextPageUrl) {
            button.hidden = true;
            setStatus((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.endText) || '');
        }
    };

    const loadNextPage = async () => {
        if (!nextPageUrl || loading) {
            return;
        }

        loading = true;
        button.disabled = true;
        setStatus((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.loadingText) || '');

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
            setStatus('');
            updateDoneState();
        } catch (error) {
            setStatus((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.errorText) || '');
        } finally {
            loading = false;
            button.disabled = false;
        }
    };

    button.addEventListener('click', loadNextPage);

    if ((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.mode) !== 'auto') {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            const limit = Number((window.spolszczonyInfiniteScroll && window.spolszczonyInfiniteScroll.autoAfterPages) || 0);

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
