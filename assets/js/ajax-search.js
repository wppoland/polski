document.addEventListener('DOMContentLoaded', function () {
    var config = window.polskiAjaxSearch || null;

    if (!config) {
        return;
    }

    document.querySelectorAll('[data-polski-ajax-search]').forEach(function (form) {
        var input = form.querySelector('[data-polski-ajax-search-input]');
        var results = form.querySelector('[data-polski-ajax-search-results]');
        var timer = null;

        if (!input || !results) {
            return;
        }

        function hideResults() {
            results.hidden = true;
            results.innerHTML = '';
        }

        function renderResults(payload, term) {
            var items = Array.isArray(payload.results) ? payload.results : [];

            if (items.length === 0) {
                results.innerHTML = '<div class="polski-ajax-search__empty">' + config.noResultsText + '</div>';
                results.hidden = false;
                return;
            }

            var html = items.map(function (item) {
                var image = config.showImage && item.image ? '<img src="' + item.image + '" alt="">' : '';
                var sku = config.showSku && item.sku ? '<small class="polski-ajax-search__sku">' + config.skuLabel + ': ' + item.sku + '</small>' : '';
                var price = config.showPrice && item.price_html ? '<span class="polski-ajax-search__price">' + item.price_html + '</span>' : '';

                return (
                    '<a class="polski-ajax-search__item" href="' + item.url + '">' +
                        '<span class="polski-ajax-search__thumb">' + image + '</span>' +
                        '<span class="polski-ajax-search__meta">' +
                            '<strong>' + item.name + '</strong>' +
                            sku +
                            price +
                        '</span>' +
                    '</a>'
                );
            }).join('');

            if (config.showViewAllLink && payload.search_url) {
                html += '<a class="polski-ajax-search__all" href="' + payload.search_url + '">' + config.viewAllText + '</a>';
            }

            results.innerHTML = html;
            results.hidden = false;
        }

        async function performSearch(term) {
            if (term.length < config.minChars) {
                hideResults();
                return;
            }

            try {
                var response = await fetch(config.endpoint + '?q=' + encodeURIComponent(term), {
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    hideResults();
                    return;
                }

                var payload = await response.json();
                renderResults(payload, term);
            } catch (error) {
                hideResults();
            }
        }

        input.addEventListener('input', function () {
            var term = input.value.trim();

            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                performSearch(term);
            }, config.debounceMs);
        });

        document.addEventListener('click', function (event) {
            if (!form.contains(event.target)) {
                hideResults();
            }
        });
    });
});
