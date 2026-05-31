document.addEventListener('DOMContentLoaded', function () {
    var config = window.polskiAjaxSearch || null;

    if (!config) {
        return;
    }

    var instance = 0;

    document.querySelectorAll('[data-polski-ajax-search]').forEach(function (form) {
        var input = form.querySelector('[data-polski-ajax-search-input]');
        var results = form.querySelector('[data-polski-ajax-search-results]');

        if (!input || !results) {
            return;
        }

        var timer = null;
        var controller = null;
        var requestId = 0;
        var activeIndex = -1;
        var idBase = 'polski-ajax-search-' + (++instance);

        if (!results.id) {
            results.id = idBase + '-results';
        }

        // Combobox/listbox semantics so screen readers announce the live list.
        results.setAttribute('role', 'listbox');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-haspopup', 'listbox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', results.id);

        function options() {
            return Array.prototype.slice.call(
                results.querySelectorAll('.polski-ajax-search__item, .polski-ajax-search__all')
            );
        }

        function clearActive() {
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            options().forEach(function (el) {
                el.classList.remove('polski-ajax-search__item--active');
                el.setAttribute('aria-selected', 'false');
            });
        }

        function hideResults() {
            if (controller) {
                controller.abort();
                controller = null;
            }
            window.clearTimeout(timer);
            results.hidden = true;
            results.innerHTML = '';
            results.removeAttribute('aria-busy');
            input.setAttribute('aria-expanded', 'false');
            clearActive();
        }

        function setActive(index) {
            var items = options();
            if (items.length === 0) {
                return;
            }

            if (index < 0) {
                index = items.length - 1;
            } else if (index >= items.length) {
                index = 0;
            }

            clearActive();
            activeIndex = index;

            var el = items[index];
            el.classList.add('polski-ajax-search__item--active');
            el.setAttribute('aria-selected', 'true');
            input.setAttribute('aria-activedescendant', el.id);

            if (typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ block: 'nearest' });
            }
        }

        function buildItem(item, index) {
            var link = document.createElement('a');
            link.className = 'polski-ajax-search__item';
            link.id = idBase + '-option-' + index;
            link.setAttribute('role', 'option');
            link.setAttribute('aria-selected', 'false');
            link.setAttribute('href', item.url || '#');

            var thumb = document.createElement('span');
            thumb.className = 'polski-ajax-search__thumb';
            if (config.showImage && item.image) {
                var img = document.createElement('img');
                img.setAttribute('src', item.image);
                img.setAttribute('alt', '');
                img.setAttribute('loading', 'lazy');
                thumb.appendChild(img);
            }
            link.appendChild(thumb);

            var meta = document.createElement('span');
            meta.className = 'polski-ajax-search__meta';

            var name = document.createElement('strong');
            // textContent escapes product names/SKUs so markup characters
            // (", &, <) render literally instead of breaking the output.
            name.textContent = item.name || '';
            meta.appendChild(name);

            if (config.showSku && item.sku) {
                var sku = document.createElement('small');
                sku.className = 'polski-ajax-search__sku';
                sku.textContent = config.skuLabel + ': ' + item.sku;
                meta.appendChild(sku);
            }

        if (config.showPrice && item.price_html) {
            var price = document.createElement('span');
            price.className = 'polski-ajax-search__price';
            // price_html is trusted, server-generated WooCommerce markup.
            price.innerHTML = item.price_html;
            meta.appendChild(price);
        }

        if (config.showUnitPrice && item.unit_price_html) {
            var unitPrice = document.createElement('span');
            unitPrice.className = 'polski-ajax-search__unit-price';
            // unit_price_html is trusted, server-generated (escaped) markup.
            unitPrice.innerHTML = item.unit_price_html;
            meta.appendChild(unitPrice);
        }

        if (config.showOmnibus && item.omnibus_html) {
            var omnibus = document.createElement('span');
            omnibus.className = 'polski-ajax-search__omnibus';
            // omnibus_html is trusted, server-generated (escaped) markup.
            omnibus.innerHTML = item.omnibus_html;
            meta.appendChild(omnibus);
        }

        link.appendChild(meta);

            return link;
        }

        function renderResults(payload) {
            var items = Array.isArray(payload.results) ? payload.results : [];

            results.innerHTML = '';
            clearActive();

            if (items.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'polski-ajax-search__empty';
                empty.textContent = config.noResultsText;
                results.appendChild(empty);
                results.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                return;
            }

            items.forEach(function (item, index) {
                results.appendChild(buildItem(item, index));
            });

            if (config.showViewAllLink && payload.search_url) {
                var all = document.createElement('a');
                all.className = 'polski-ajax-search__all';
                all.id = idBase + '-option-all';
                all.setAttribute('role', 'option');
                all.setAttribute('aria-selected', 'false');
                all.setAttribute('href', payload.search_url);
                all.textContent = config.viewAllText;
                results.appendChild(all);
            }

            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function performSearch(term) {
            if (term.length < config.minChars) {
                hideResults();
                return;
            }

            if (controller) {
                controller.abort();
            }
            controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

            var currentRequest = ++requestId;
            results.setAttribute('aria-busy', 'true');

            // The endpoint already carries a query string in rest_route mode
            // (?rest_route=...), so pick the correct separator instead of
            // always appending "?q=", which produced a malformed URL and an
            // empty result set.
            var separator = config.endpoint.indexOf('?') === -1 ? '?' : '&';

            fetch(config.endpoint + separator + 'q=' + encodeURIComponent(term), {
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Bad response');
                }
                return response.json();
            }).then(function (payload) {
                // Ignore responses that arrive out of order so the newest
                // query always wins (prevents flickering/stale results).
                if (currentRequest !== requestId) {
                    return;
                }
                results.removeAttribute('aria-busy');
                renderResults(payload);
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (currentRequest === requestId) {
                    hideResults();
                }
            });
        }

        input.addEventListener('input', function () {
            var term = input.value.trim();

            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                performSearch(term);
            }, config.debounceMs);
        });

        input.addEventListener('focus', function () {
            if (results.innerHTML !== '' && results.hidden) {
                results.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }
        });

        input.addEventListener('keydown', function (event) {
            var open = !results.hidden && options().length > 0;

            switch (event.key) {
                case 'ArrowDown':
                    if (open) {
                        event.preventDefault();
                        setActive(activeIndex + 1);
                    }
                    break;
                case 'ArrowUp':
                    if (open) {
                        event.preventDefault();
                        setActive(activeIndex - 1);
                    }
                    break;
                case 'Enter':
                    if (open && activeIndex >= 0) {
                        var items = options();
                        if (items[activeIndex] && items[activeIndex].href) {
                            event.preventDefault();
                            window.location.href = items[activeIndex].href;
                        }
                    }
                    break;
                case 'Escape':
                    if (open) {
                        event.preventDefault();
                    }
                    hideResults();
                    break;
                default:
                    break;
            }
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                hideResults();
            }
        });
    });
});
