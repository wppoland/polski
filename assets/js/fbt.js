document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('.spolszczony-fbt-form');

  forms.forEach((form) => {
    const total = form.querySelector('[data-spolszczony-fbt-total]');

    if (!total) {
      return;
    }

    const currency = total.dataset.spolszczonyFbtCurrency || 'PLN';

    const updateTotal = () => {
      const value = Array.from(form.querySelectorAll('[data-spolszczony-fbt-checkbox]'))
        .filter((checkbox) => checkbox.checked)
        .reduce((sum, checkbox) => {
          const price = checkbox.closest('label')?.querySelector('[data-spolszczony-fbt-price]');
          return sum + Number(price?.dataset.spolszczonyFbtPrice || 0);
        }, 0);

      total.textContent = new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency,
      }).format(value);
    };

    form.addEventListener('change', updateTotal);
    updateTotal();
  });
});
