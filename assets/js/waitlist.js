// Variable products: the form is rendered hidden and follows the selected
// variation. WooCommerce triggers these events through jQuery, so they are
// bound here, before its variation form initialises on DOM ready.
if (window.jQuery) {
  const waitlistFor = (variationsForm) => {
    const productId = variationsForm.getAttribute('data-product_id');
    const input = document.querySelector(`.polski-waitlist input[name="product_id"][data-parent-id="${productId}"]`);

    return input ? { box: input.closest('.polski-waitlist'), input } : null;
  };

  window.jQuery(document)
    .on('found_variation', '.variations_form', (event, variation) => {
      const target = waitlistFor(event.currentTarget);

      if (!target) {
        return;
      }

      const message = target.box.querySelector('[data-polski-waitlist-message]');
      if (message) {
        message.hidden = true;
      }

      target.input.value = String(variation.variation_id);
      target.box.hidden = !variation.polski_waitlist;
    })
    .on('reset_data', '.variations_form', (event) => {
      const target = waitlistFor(event.currentTarget);

      if (target) {
        target.box.hidden = true;
      }
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const config = window.polskiWaitlist;

  if (!config) {
    return;
  }

  document.querySelectorAll('.polski-waitlist-form').forEach((form) => {
    const message = form.querySelector('[data-polski-waitlist-message]');

    // Announce the result to assistive tech as soon as it is shown.
    if (message) {
      message.setAttribute('role', 'status');
      message.setAttribute('aria-live', 'polite');
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const submitButton = form.querySelector('[type="submit"]');

      // Guard against double submissions while the request is in flight.
      if (form.getAttribute('aria-busy') === 'true') {
        return;
      }

      const showMessage = (text) => {
        if (message && text) {
          message.hidden = false;
          message.textContent = text;
        }
      };

      const body = new URLSearchParams(new FormData(form));
      body.set('action', config.action || 'polski_waitlist_subscribe');
      body.set('nonce', config.nonce);

      form.setAttribute('aria-busy', 'true');
      if (submitButton) {
        submitButton.disabled = true;
      }

      try {
        const response = await fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString(),
        });

        const payload = await response.json();

        showMessage(payload?.data?.message || payload?.data?.error || '');

        if (payload?.success) {
          form.reset();
        }
      } catch (error) {
        showMessage(config.errorText || '');
      } finally {
        form.removeAttribute('aria-busy');
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });
  });
});
