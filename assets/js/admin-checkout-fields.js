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
        newRow.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.name = el.name.replace(/fields\[\d+\]/, 'fields[' + fieldIndex + ']');
            if (el.type === 'checkbox') {
                el.checked = el.hasAttribute('data-polski-cf-default-on');
            } else if (el.type === 'number') {
                el.value = '100';
            } else if (el.name.indexOf('[css_class]') !== -1) {
                el.value = 'form-row-wide';
            } else if (el.tagName !== 'SELECT') {
                // Text, textarea and hidden: a new row must not inherit the copied row's options or rules.
                el.value = '';
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
