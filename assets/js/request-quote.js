document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-polski-quote-root]').forEach(function (root) {
        var modal = root.querySelector('[data-polski-quote-modal]');
        var openButton = root.querySelector('[data-polski-quote-open]');
        var closeButtons = root.querySelectorAll('[data-polski-quote-close]');
        var variationTarget = root.querySelector('[data-polski-quote-variation]');
        var variationSource = document.querySelector('form.variations_form input[name="variation_id"]');

        if (!modal || !openButton) {
            return;
        }

        function syncVariation() {
            if (variationTarget && variationSource) {
                variationTarget.value = variationSource.value || '';
            }
        }

        function openModal() {
            syncVariation();
            modal.hidden = false;
            document.documentElement.classList.add('polski-quote-open');
        }

        function closeModal() {
            modal.hidden = true;
            document.documentElement.classList.remove('polski-quote-open');
        }

        openButton.addEventListener('click', openModal);

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        if (variationSource) {
            variationSource.addEventListener('change', syncVariation);
            syncVariation();
        }

        if (root.dataset.autoOpen === '1') {
            openModal();
        }
    });
});
