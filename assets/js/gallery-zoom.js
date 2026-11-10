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
    // WooCommerce puts the full-size file on the image and its link.
    const link = img.closest('a');
    lightboxImage.src = img.dataset.large_image || (link && link.href) || img.currentSrc || img.src;
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

  // WooCommerce 11.1+ swaps the gallery markup when a variation has its own
  // gallery, so new images are enhanced again after core re-inits it.
  const enhance = (root) => root.querySelectorAll('.woocommerce-product-gallery__image img').forEach((img) => {
    if (img.dataset.polskiGalleryEnhanced) {
      return;
    }
    img.dataset.polskiGalleryEnhanced = '1';

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

      img.addEventListener('click', (event) => {
        // The image sits inside a link to the full-size file.
        event.preventDefault();
        openLightbox(img);
      });
      img.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
          event.preventDefault();
          openLightbox(img);
        }
      });
    }
  });

  enhance(document);

  if (window.jQuery) {
    window.jQuery(document.body).on('wc-product-gallery-after-init', (event, el) => enhance(el || document));
  }

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
