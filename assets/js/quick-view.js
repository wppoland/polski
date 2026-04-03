document.addEventListener('DOMContentLoaded', () => {
  const config = window.spolszczonyQuickView;
  const modal = document.querySelector('[data-spolszczony-quick-view-modal]');
  const content = document.querySelector('[data-spolszczony-quick-view-content]');

  if (!config || !modal || !content) {
    return;
  }

  const openModal = () => {
    modal.hidden = false;
    document.body.classList.add('spolszczony-quick-view-open');
  };

  const closeModal = () => {
    modal.hidden = true;
    document.body.classList.remove('spolszczony-quick-view-open');
  };

  const initVariations = () => {
    if (!window.jQuery) {
      return;
    }

    const $ = window.jQuery;
    const forms = $('.spolszczony-quick-view-content form.variations_form');

    if (typeof forms.wc_variation_form === 'function') {
      forms.each(function init() {
        $(this).wc_variation_form();
      });
    }
  };

  document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-spolszczony-quick-view]');
    const close = event.target.closest('[data-spolszczony-quick-view-close]');
    const backdrop = event.target.closest('[data-spolszczony-quick-view-backdrop]');

    if (close) {
      event.preventDefault();
      closeModal();
      return;
    }

    if (backdrop && config.showBackdropClose) {
      event.preventDefault();
      closeModal();
      return;
    }

    if (!trigger) {
      return;
    }

    event.preventDefault();

    const productId = trigger.dataset.productId;

    if (!productId) {
      return;
    }

    openModal();
    content.innerHTML = `<p>${config.loadingText}</p>`;

    const url = new URL(config.ajaxUrl, window.location.origin);
    url.searchParams.set('action', 'spolszczony_quick_view');
    url.searchParams.set('nonce', config.nonce);
    url.searchParams.set('product_id', productId);

    try {
      const response = await fetch(url.toString(), { credentials: 'same-origin' });
      const payload = await response.json();

      if (!payload?.success || !payload?.data?.html) {
        throw new Error('quick-view-failed');
      }

      content.innerHTML = payload.data.html;
      initVariations();
    } catch (error) {
      content.innerHTML = `<p>${config.errorText}</p>`;
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.hidden) {
      closeModal();
    }
  });
});
