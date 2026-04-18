(function () {
    'use strict';

    function init() {
        var config = window.polskiModules || {};

        document.querySelectorAll('.sp-card .sp-toggle input[type=checkbox][data-polski-module-id]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (this.disabled) {
                    return;
                }

                var moduleId = this.getAttribute('data-polski-module-id');
                if (!moduleId) {
                    return;
                }

                var card = this.closest('.sp-card');
                if (card) {
                    card.classList.toggle('sp-card--active', this.checked);
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
                        if (card) {
                            card.classList.toggle('sp-card--active', cb.checked);
                        }
                        window.alert(config.errorGeneric || '');
                    });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
