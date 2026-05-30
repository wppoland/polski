document.addEventListener('DOMContentLoaded', () => {
  const config = window.polskiQuickView;
  const modal = document.querySelector('[data-polski-quick-view-modal]');
  const content = document.querySelector('[data-polski-quick-view-content]');

  if (!config || !modal || !content) {
    return;
  }

  const dialog = modal.querySelector('.polski-quick-view-dialog') || modal;
  const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  // Element that had focus before the modal opened, so we can restore it.
  let lastFocused = null;

  const focusableInDialog = () =>
    Array.prototype.slice
      .call(dialog.querySelectorAll(FOCUSABLE))
      .filter((el) => el.offsetParent !== null || el === document.activeElement);

  const focusFirst = () => {
    const items = focusableInDialog();
    if (items.length > 0) {
      items[0].focus();
    } else if (typeof dialog.focus === 'function') {
      dialog.focus();
    }
  };

  const openModal = (trigger) => {
    lastFocused = trigger || (document.activeElement instanceof HTMLElement ? document.activeElement : null);
    modal.hidden = false;
    document.body.classList.add('polski-quick-view-open');
    focusFirst();
  };

  const closeModal = () => {
    modal.hidden = true;
    document.body.classList.remove('polski-quick-view-open');

    // Return focus to the control that opened the modal (accessibility).
    if (lastFocused && typeof lastFocused.focus === 'function' && document.contains(lastFocused)) {
      lastFocused.focus();
    }
    lastFocused = null;
  };

  const trapFocus = (event) => {
    if (event.key !== 'Tab' || modal.hidden) {
      return;
    }

    const items = focusableInDialog();
    if (items.length === 0) {
      event.preventDefault();
      if (typeof dialog.focus === 'function') {
        dialog.focus();
      }
      return;
    }

    const first = items[0];
    const last = items[items.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || active === dialog)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  };

  const initVariations = () => {
    if (!window.jQuery) {
      return;
    }

    const $ = window.jQuery;
    const forms = $('.polski-quick-view-content form.variations_form');

    if (typeof forms.wc_variation_form === 'function') {
      forms.each(function init() {
        $(this).wc_variation_form();
      });
    }
  };

  document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-polski-quick-view]');
    const close = event.target.closest('[data-polski-quick-view-close]');
    const backdrop = event.target.closest('[data-polski-quick-view-backdrop]');

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

    openModal(trigger);
    content.setAttribute('aria-busy', 'true');
    content.innerHTML = `<p>${config.loadingText}</p>`;

    const url = new URL(config.ajaxUrl, window.location.origin);
    url.searchParams.set('action', 'polski_quick_view');
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
      // Move focus into the freshly loaded content if the modal is still open.
      if (!modal.hidden) {
        focusFirst();
      }
    } catch (error) {
      content.innerHTML = `<p>${config.errorText}</p>`;
    } finally {
      content.removeAttribute('aria-busy');
    }
  });

  document.addEventListener('keydown', (event) => {
    if (modal.hidden) {
      return;
    }

    if (event.key === 'Escape') {
      closeModal();
      return;
    }

    trapFocus(event);
  });
});
