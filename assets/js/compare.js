document.addEventListener('DOMContentLoaded', () => {
  const config = window.spolszczonyCompare;

  if (!config) {
    return;
  }

  const updateButtons = (productId, active, label) => {
    document.querySelectorAll(`[data-spolszczony-compare-button][data-product-id="${productId}"]`).forEach((button) => {
      button.classList.toggle('is-active', active);
      button.textContent = label;
    });
  };

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-spolszczony-compare-button]');
    const clearButton = event.target.closest('[data-spolszczony-compare-clear]');

    if (clearButton) {
      event.preventDefault();

      const body = new URLSearchParams({
        action: 'spolszczony_compare_clear',
        nonce: config.nonce,
      });

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      });

      const payload = await response.json();

      if (payload?.success) {
        window.location.href = payload.data?.compare_url || window.location.href;
      }

      return;
    }

    if (!button) {
      return;
    }

    event.preventDefault();

    if (!config.allowGuests && !document.body.classList.contains('logged-in')) {
      window.location.href = config.loginUrl;
      return;
    }

    const productId = button.dataset.productId;

    if (!productId) {
      return;
    }

    button.disabled = true;

    try {
      const body = new URLSearchParams({
        action: 'spolszczony_compare_toggle',
        nonce: config.nonce,
        product_id: productId,
      });

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      });

      const payload = await response.json();

      if (payload?.success) {
        updateButtons(productId, payload.data.in_compare, payload.data.button_text);
      }
    } finally {
      button.disabled = false;
    }
  });

  document.querySelectorAll('[data-spolszczony-compare-differences]').forEach((checkbox) => {
    const applyVisibility = () => {
      document.querySelectorAll('.spolszczony-compare-table tbody tr').forEach((row) => {
        row.hidden = checkbox.checked && row.dataset.different !== '1';
      });
    };

    checkbox.addEventListener('change', applyVisibility);
    applyVisibility();
  });
});
