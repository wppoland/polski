document.addEventListener('DOMContentLoaded', () => {
  const config = window.spolszczonyWaitlist;

  if (!config) {
    return;
  }

  document.querySelectorAll('.spolszczony-waitlist-form').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const message = form.querySelector('[data-spolszczony-waitlist-message]');
      const body = new URLSearchParams(new FormData(form));
      body.set('action', 'spolszczony_waitlist_subscribe');
      body.set('nonce', config.nonce);

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      });

      const payload = await response.json();

      if (message) {
        message.hidden = false;
        message.textContent = payload?.data?.message || payload?.data?.error || '';
      }

      if (payload?.success) {
        form.reset();
      }
    });
  });
});
