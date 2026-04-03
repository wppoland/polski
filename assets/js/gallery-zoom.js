document.addEventListener('DOMContentLoaded', () => {
  const config = window.polskiGalleryZoom;

  if (!config) {
    return;
  }

  document.querySelectorAll('.woocommerce-product-gallery__image img').forEach((img) => {
    if (config.enableZoom) {
      img.style.setProperty('--polski-gallery-zoom-scale', String(config.zoomScale || 1.45));
      img.classList.add('polski-gallery-zoomable');
    }

    if (config.enableLightbox) {
      img.addEventListener('click', () => {
        const lightbox = document.querySelector('[data-polski-gallery-lightbox]');
        const lightboxImage = document.querySelector('[data-polski-gallery-lightbox-image]');

        if (!lightbox || !lightboxImage) {
          return;
        }

        lightboxImage.src = img.currentSrc || img.src;
        lightboxImage.alt = img.alt || '';
        lightbox.hidden = false;
        document.body.classList.add('polski-gallery-lightbox-open');
      });
    }
  });

  document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-polski-gallery-lightbox-close]');
    const lightbox = event.target.closest('[data-polski-gallery-lightbox]');

    if (close || (lightbox === event.target && config.showBackdropClose !== false)) {
      const shell = document.querySelector('[data-polski-gallery-lightbox]');

      if (shell) {
        shell.hidden = true;
        document.body.classList.remove('polski-gallery-lightbox-open');
      }
    }
  });
});
