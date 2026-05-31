document.addEventListener('DOMContentLoaded', () => {
  const config = window.polskiGalleryZoom;

  if (!config) {
    return;
  }

  const lightbox = document.querySelector('[data-polski-gallery-lightbox]');
  const lightboxImage = document.querySelector('[data-polski-gallery-lightbox-image]');
  const closeButton = lightbox ? lightbox.querySelector('[data-polski-gallery-lightbox-close]') : null;

  // Element focused before the lightbox opened, so we can restore it on close.
  let lastFocused = null;

  const openLightbox = (img) => {
    if (!lightbox || !lightboxImage) {
      return;
    }

    lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    lightboxImage.src = img.currentSrc || img.src;
    lightboxImage.alt = img.alt || '';
    lightbox.hidden = false;
    document.body.classList.add('polski-gallery-lightbox-open');

    if (closeButton && typeof closeButton.focus === 'function') {
      closeButton.focus();
    } else if (typeof lightbox.focus === 'function') {
      lightbox.focus();
    }
  };

  const closeLightbox = () => {
    if (!lightbox || lightbox.hidden) {
      return;
    }

    lightbox.hidden = true;
    document.body.classList.remove('polski-gallery-lightbox-open');

    if (lastFocused && typeof lastFocused.focus === 'function' && document.contains(lastFocused)) {
      lastFocused.focus();
    }
    lastFocused = null;
  };

  document.querySelectorAll('.woocommerce-product-gallery__image img').forEach((img) => {
    if (config.enableZoom) {
      img.style.setProperty('--polski-gallery-zoom-scale', String(config.zoomScale || 1.45));
      img.classList.add('polski-gallery-zoomable');
    }

    if (config.enableLightbox) {
      // Make the image operable by keyboard, since a bare <img> is neither
      // focusable nor activatable with Enter/Space.
      img.classList.add('polski-gallery-lightbox-trigger');
      if (!img.hasAttribute('tabindex')) {
        img.setAttribute('tabindex', '0');
      }
      img.setAttribute('role', 'button');
      if (config.triggerLabel && !img.getAttribute('aria-label')) {
        img.setAttribute('aria-label', config.triggerLabel);
      }

      img.addEventListener('click', () => openLightbox(img));
      img.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
          event.preventDefault();
          openLightbox(img);
        }
      });
    }
  });

  document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-polski-gallery-lightbox-close]');
    const clickedLightbox = event.target.closest('[data-polski-gallery-lightbox]');

    if (close || (clickedLightbox === event.target && config.showBackdropClose !== false)) {
      closeLightbox();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (!lightbox || lightbox.hidden) {
      return;
    }

    if (event.key === 'Escape') {
      closeLightbox();
      return;
    }

    // Single-control dialog: keep Tab on the close button so focus can't
    // escape behind the modal.
    if (event.key === 'Tab' && closeButton) {
      event.preventDefault();
      closeButton.focus();
    }
  });
});
