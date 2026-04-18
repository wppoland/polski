(function () {
    'use strict';

    function addFieldRow() {
        var tbody = document.querySelector('#polski-checkout-fields tbody');
        if (!tbody) {
            return;
        }
        var firstRow = tbody.querySelector('tr');
        if (!firstRow) {
            return;
        }
        var fieldIndex = tbody.querySelectorAll('tr').length;
        var newRow = firstRow.cloneNode(true);
        newRow.querySelectorAll('input, select').forEach(function (el) {
            el.name = el.name.replace(/fields\[\d+\]/, 'fields[' + fieldIndex + ']');
            if (el.type === 'checkbox') {
                el.checked = false;
            } else if (el.type === 'text' || el.type === 'number') {
                el.value = el.type === 'number' ? '100' : '';
            }
        });
        tbody.appendChild(newRow);
    }

    function init() {
        document.addEventListener('click', function (event) {
            var target = event.target.closest('[data-polski-cf-add-row]');
            if (!target) {
                return;
            }
            event.preventDefault();
            addFieldRow();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
