document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('.polski-fbt-form');

  forms.forEach((form) => {
    const total = form.querySelector('[data-polski-fbt-total]');

    if (!total) {
      return;
    }

    const currency = total.dataset.polskiFbtCurrency || 'PLN';

    const updateTotal = () => {
      const value = Array.from(form.querySelectorAll('[data-polski-fbt-checkbox]'))
        .filter((checkbox) => checkbox.checked)
        .reduce((sum, checkbox) => {
          const price = checkbox.closest('label')?.querySelector('[data-polski-fbt-price]');
          return sum + Number(price?.dataset.polskiFbtPrice || 0);
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
