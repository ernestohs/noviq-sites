/**
 * Blocks cart checkout until RUO attestation is checked.
 * Persists the cart attribute via /cart/update.js when toggled.
 */
(function () {
  function init(root) {
    const checkbox = root.querySelector('[data-ruo-checkbox]');
    const error = root.querySelector('[data-ruo-error]');
    const formId = root.dataset.ruoForm || 'cart';
    if (!checkbox) return;

    const checkoutButtons = () =>
      Array.from(
        document.querySelectorAll(
          '#checkout, #CartDrawer-Checkout, button[name="checkout"], [name="checkout"]'
        )
      ).filter((btn) => {
        const formAttr = btn.getAttribute('form');
        if (formAttr) return formAttr === formId;
        return btn.closest('form')?.id === formId || formId === 'cart';
      });

    const syncButtons = () => {
      const ok = checkbox.checked;
      checkoutButtons().forEach((btn) => {
        btn.disabled = !ok;
      });
      if (error) error.hidden = ok;
    };

    const persist = async () => {
      try {
        await fetch(`${window.routes?.cart_update_url || '/cart/update.js'}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            attributes: {
              ruo_attestation: checkbox.checked ? 'accepted' : '',
            },
          }),
        });
      } catch (e) {
        console.error('Failed to persist RUO attestation', e);
      }
    };

    checkbox.addEventListener('change', () => {
      syncButtons();
      persist();
    });

    document.addEventListener(
      'click',
      (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const btn = target.closest(
          '#checkout, #CartDrawer-Checkout, button[name="checkout"], [name="checkout"]'
        );
        if (!btn) return;
        if (!checkbox.checked) {
          event.preventDefault();
          event.stopPropagation();
          if (error) error.hidden = false;
          checkbox.focus();
        }
      },
      true
    );

    syncButtons();
  }

  document.querySelectorAll('[data-cart-ruo-attestation]').forEach(init);
})();
