document.addEventListener('DOMContentLoaded', () => {
  const config = window.spolszczonyGalleryZoom;

  if (!config) {
    return;
  }

  document.querySelectorAll('.woocommerce-product-gallery__image img').forEach((img) => {
    if (config.enableZoom) {
      img.style.setProperty('--spolszczony-gallery-zoom-scale', String(config.zoomScale || 1.45));
      img.classList.add('spolszczony-gallery-zoomable');
    }

    if (config.enableLightbox) {
      img.addEventListener('click', () => {
        const lightbox = document.querySelector('[data-spolszczony-gallery-lightbox]');
        const lightboxImage = document.querySelector('[data-spolszczony-gallery-lightbox-image]');

        if (!lightbox || !lightboxImage) {
          return;
        }

        lightboxImage.src = img.currentSrc || img.src;
        lightboxImage.alt = img.alt || '';
        lightbox.hidden = false;
        document.body.classList.add('spolszczony-gallery-lightbox-open');
      });
    }
  });

  document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-spolszczony-gallery-lightbox-close]');
    const lightbox = event.target.closest('[data-spolszczony-gallery-lightbox]');

    if (close || (lightbox === event.target && config.showBackdropClose !== false)) {
      const shell = document.querySelector('[data-spolszczony-gallery-lightbox]');

      if (shell) {
        shell.hidden = true;
        document.body.classList.remove('spolszczony-gallery-lightbox-open');
      }
    }
  });
});
