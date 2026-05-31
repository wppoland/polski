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
      body.set('action', 'polski_waitlist_subscribe');
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
