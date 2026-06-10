(function () {
    'use strict';

    function initToggles() {
        var config = window.polskiModules || {};

        document.querySelectorAll('.sp-toggle input[type=checkbox][data-polski-module-id]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (this.disabled) {
                    return;
                }

                var moduleId = this.getAttribute('data-polski-module-id');
                if (!moduleId) {
                    return;
                }

                var row = this.closest('.polski-modules-row');
                if (row) {
                    row.classList.toggle('polski-modules-row--active', this.checked);
                }

                var formData = new FormData();
                formData.append('action', 'polski_toggle_module');
                formData.append('nonce', config.nonce || '');
                formData.append('module_id', moduleId);
                formData.append('enabled', this.checked ? '1' : '0');

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.success) {
                            throw new Error('save');
                        }
                    })
                    .catch(function () {
                        cb.checked = !cb.checked;
                        if (row) {
                            row.classList.toggle('polski-modules-row--active', cb.checked);
                        }
                        window.alert(config.errorGeneric || '');
                    });
            });
        });
    }

    // Client-side search (by name OR description) + re-sort of the modules table.
    // No reload, no server round-trip: rows carry data-search / data-name /
    // data-enabled, set in ModulesPage::renderModuleRow().
    function initFilterSort() {
        var table = document.querySelector('.polski-modules-table');
        if (!table) {
            return;
        }
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }

        var search = document.querySelector('[data-polski-modules-search]');
        var sort = document.querySelector('[data-polski-modules-sort]');
        var empty = document.querySelector('[data-polski-modules-empty]');

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-polski-module-row]'));
        var headers = Array.prototype.slice.call(
            tbody.querySelectorAll('.polski-modules-group-header, .polski-modules-subgroup-header')
        );
        // Snapshot the original (grouped) order so "default" can be restored.
        var original = Array.prototype.slice.call(tbody.children);

        function isHeader(el) {
            return el.classList &&
                (el.classList.contains('polski-modules-group-header') ||
                    el.classList.contains('polski-modules-subgroup-header'));
        }

        // In grouped mode, hide a header that has no visible module row beneath it
        // (before the next header). In flat sort modes, all headers are hidden.
        function updateHeaders(grouped) {
            if (!grouped) {
                headers.forEach(function (h) { h.hidden = true; });
                return;
            }
            var kids = Array.prototype.slice.call(tbody.children);
            for (var i = 0; i < kids.length; i++) {
                if (!isHeader(kids[i])) {
                    continue;
                }
                var visible = false;
                for (var j = i + 1; j < kids.length; j++) {
                    if (isHeader(kids[j])) {
                        break;
                    }
                    if (kids[j].hasAttribute('data-polski-module-row') && !kids[j].hidden) {
                        visible = true;
                        break;
                    }
                }
                kids[i].hidden = !visible;
            }
        }

        function applySearch() {
            var q = (search && search.value ? search.value : '').trim().toLowerCase();
            var anyVisible = false;
            rows.forEach(function (r) {
                var hay = r.getAttribute('data-search') || '';
                var match = q === '' || hay.indexOf(q) !== -1;
                r.hidden = !match;
                if (match) {
                    anyVisible = true;
                }
            });
            updateHeaders((sort ? sort.value : 'default') === 'default');
            if (empty) {
                empty.hidden = anyVisible;
            }
        }

        function applySort() {
            var mode = sort ? sort.value : 'default';
            if (mode === 'default') {
                original.forEach(function (el) { tbody.appendChild(el); });
            } else {
                var sorted = rows.slice();
                sorted.sort(function (a, b) {
                    if (mode === 'enabled') {
                        var ea = a.getAttribute('data-enabled') === '1' ? 0 : 1;
                        var eb = b.getAttribute('data-enabled') === '1' ? 0 : 1;
                        if (ea !== eb) {
                            return ea - eb;
                        }
                    }
                    return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
                });
                sorted.forEach(function (r) { tbody.appendChild(r); });
            }
            applySearch();
        }

        if (search) {
            search.addEventListener('input', applySearch);
        }
        if (sort) {
            sort.addEventListener('change', applySort);
        }
    }

    function init() {
        initToggles();
        initFilterSort();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
