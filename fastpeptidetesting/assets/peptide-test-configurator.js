/**
 * Peptide Test product configurator.
 * Sets vial variant, shows per-vial peptide selects, live price, and after the
 * primary add-to-cart succeeds, AJAX-adds add-on and rush lines at vial quantity.
 */
class PeptideTestConfigurator extends HTMLElement {
  static HELPER_HANDLES = [
    'checkout-rush-next-day',
    'checkout-rush-same-day',
    'endotoxin-testing',
    'sterility-testing',
    'heavy-metals-testing',
    'karl-fischer-testing',
    'vial-vacuum-testing',
  ];

  connectedCallback() {
    this.vialSelect = this.querySelector('[data-vial-count]');
    this.peptideRows = Array.from(this.querySelectorAll('[data-vial-peptide-row]'));
    this.addonInputs = Array.from(this.querySelectorAll('[data-addon-toggle]'));
    this.turnaroundSelect = this.querySelector('[data-turnaround]');
    this.formId = this.dataset.formId;
    this.pendingHelpers = null;

    this.vialSelect?.addEventListener('change', () => this.onVialCountChange());
    this.addonInputs.forEach((input) => input.addEventListener('change', () => this.updateTotals()));
    this.turnaroundSelect?.addEventListener('change', () => this.updateTotals());

    this.prefillFromQuery();
    this.onVialCountChange();
    this.bindProductForm();
  }

  get form() {
    if (this.formId) return document.getElementById(this.formId);
    return this.closest('form') || document.querySelector('form[action*="/cart/add"]');
  }

  get variantInput() {
    const form = this.form;
    return form?.querySelector('input[name="id"]') || document.querySelector(`#${this.formId} input[name="id"], product-form input[name="id"]`);
  }

  get vialCount() {
    return parseInt(this.vialSelect?.value || '1', 10) || 1;
  }

  formatMoney(cents) {
    if (typeof Shopify !== 'undefined' && typeof Shopify.formatMoney === 'function') {
      return Shopify.formatMoney(cents, this.dataset.moneyFormat);
    }
    return `$${(cents / 100).toFixed(2)}`;
  }

  prefillFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const compound = params.get('compound');
    if (!compound) return;

    const firstSelect = this.querySelector('[data-vial-peptide][data-vial-index="1"]');
    if (!firstSelect) return;

    const match = Array.from(firstSelect.options).find(
      (opt) => opt.dataset.compoundHandle === compound || opt.value.toLowerCase() === compound.replace(/-/g, ' ')
    );
    if (match) {
      firstSelect.value = match.value;
    } else {
      const byHandle = Array.from(firstSelect.options).find((opt) =>
        (opt.dataset.compoundHandle || '').toLowerCase() === compound.toLowerCase()
      );
      if (byHandle) firstSelect.value = byHandle.value;
    }
  }

  onVialCountChange() {
    const count = this.vialCount;
    const option = this.vialSelect?.selectedOptions?.[0];
    const variantInput = this.variantInput;
    if (option && variantInput) {
      variantInput.value = option.dataset.variantId;
      variantInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const form = this.form;
    const qty = form?.querySelector('input[name="quantity"]');
    if (qty) qty.value = '1';

    this.peptideRows.forEach((row) => {
      const index = parseInt(row.dataset.vialIndex, 10);
      const select = row.querySelector('[data-vial-peptide]');
      const active = index <= count;
      row.hidden = !active;
      row.classList.toggle('hidden', !active);
      if (select) {
        select.disabled = !active;
        select.required = active;
        if (!active) select.value = '';
        if (active) {
          select.setAttribute('name', `properties[Vial ${index} peptide]`);
        } else {
          select.removeAttribute('name');
        }
      }
    });

    this.updateTotals();
  }

  selectedAddons() {
    return this.addonInputs.filter((input) => input.checked);
  }

  calcTotals() {
    const count = this.vialCount;
    const baseOption = this.vialSelect?.selectedOptions?.[0];
    const base = parseInt(baseOption?.dataset.price || String(25000 * count), 10);
    let addons = 0;
    const addonLabels = [];
    this.selectedAddons().forEach((input) => {
      const unit = parseInt(input.dataset.unitCents || '0', 10);
      addons += unit * count;
      addonLabels.push(`${input.dataset.addonLabel} (+$${unit / 100}/vial × ${count})`);
    });
    const rushOption = this.turnaroundSelect?.selectedOptions?.[0];
    const rush = parseInt(rushOption?.dataset.feeCents || '0', 10);
    return {
      base,
      addons,
      rush,
      total: base + addons + rush,
      addonLabels,
      turnaroundLabel: rushOption?.value || 'Standard (3 business days)',
    };
  }

  updateTotals() {
    const totals = this.calcTotals();
    const setText = (key, cents) => {
      const el = this.querySelector(`[data-line="${key}"]`);
      if (el) el.textContent = this.formatMoney(cents);
    };
    setText('base', totals.base);
    setText('addons', totals.addons);
    setText('rush', totals.rush);
    setText('total', totals.total);

    const addonsRow = this.querySelector('[data-line-row="addons"]');
    const rushRow = this.querySelector('[data-line-row="rush"]');
    if (addonsRow) addonsRow.hidden = totals.addons === 0;
    if (rushRow) rushRow.hidden = totals.rush === 0;

    const propBase = this.querySelector('[data-prop-base]');
    const propAddons = this.querySelector('[data-prop-addons]');
    const propFees = this.querySelector('[data-prop-fees]');
    const propFinal = this.querySelector('[data-prop-final]');
    if (propBase) propBase.value = this.formatMoney(totals.base);
    if (propAddons) propAddons.value = totals.addonLabels.length ? totals.addonLabels.join('; ') : 'None';
    if (propFees) {
      const feeParts = [];
      if (totals.addons) feeParts.push(`Screens ${this.formatMoney(totals.addons)}`);
      if (totals.rush) feeParts.push(`Turnaround ${this.formatMoney(totals.rush)}`);
      propFees.value = feeParts.length ? feeParts.join('; ') : 'None';
    }
    if (propFinal) propFinal.value = this.formatMoney(totals.total);
  }

  bindProductForm() {
    const form = this.form;
    if (!form || form.dataset.peptideConfigBound === 'true') return;
    form.dataset.peptideConfigBound = 'true';

    form.addEventListener('submit', (event) => {
      if (!this.validate()) {
        event.preventDefault();
        event.stopPropagation();
        return;
      }
      this.updateTotals();
      this.pendingHelpers = this.buildHelperItems();
    });

    document.addEventListener('cart:updated', () => this.flushHelpers());
    document.addEventListener('product-ajax:success', () => this.flushHelpers());

    // Dawn product-form fires after successful /cart/add.js
    const productForm = form.closest('product-form') || form.querySelector('product-form');
    if (productForm) {
      const original = productForm.onSubmitHandler?.bind(productForm);
      productForm.addEventListener(
        'submit',
        async (event) => {
          if (!this.validate()) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        },
        true
      );
    }

    // Hook fetch for cart/add from this form
    this.interceptCartAdd();
  }

  validate() {
    const count = this.vialCount;
    for (let i = 1; i <= count; i += 1) {
      const select = this.querySelector(`[data-vial-peptide][data-vial-index="${i}"]`);
      if (!select?.value) {
        select?.focus();
        select?.reportValidity?.();
        return false;
      }
    }
    return true;
  }

  buildHelperItems() {
    const count = this.vialCount;
    const items = [];
    this.selectedAddons().forEach((input) => {
      const id = parseInt(input.dataset.variantId, 10);
      if (id) {
        items.push({
          id,
          quantity: count,
          properties: {
            _for: 'peptide-testing',
            _addon: input.dataset.addonHandle,
          },
        });
      }
    });
    const rushOption = this.turnaroundSelect?.selectedOptions?.[0];
    const rushVariant = parseInt(rushOption?.dataset.variantId || '', 10);
    if (rushVariant) {
      items.push({
        id: rushVariant,
        quantity: 1,
        properties: {
          _for: 'peptide-testing',
          _rush: rushOption.dataset.rushHandle || '',
        },
      });
    }
    return items;
  }

  interceptCartAdd() {
    if (window.__peptideTestCartIntercept) return;
    window.__peptideTestCartIntercept = true;
    const originalFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
      const response = await originalFetch(...args);
      try {
        const url = typeof args[0] === 'string' ? args[0] : args[0]?.url || '';
        if (url.includes('/cart/add') && this.pendingHelpers?.length) {
          const clone = response.clone();
          const data = await clone.json().catch(() => null);
          if (data && !data.status && !data.message) {
            const helpers = this.pendingHelpers;
            this.pendingHelpers = null;
            await this.addHelpers(helpers, originalFetch);
          }
        }
      } catch (error) {
        console.error(error);
      }
      return response;
    };
  }

  async addHelpers(items, fetchFn = fetch) {
    if (!items?.length) return;
    const body = JSON.stringify({ items });
    const response = await fetchFn(`${window.routes?.cart_add_url || '/cart/add.js'}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body,
    });
    if (!response.ok) {
      console.error('Failed to add Peptide Test helpers', await response.text());
    }
  }

  async flushHelpers() {
    if (!this.pendingHelpers?.length) return;
    const helpers = this.pendingHelpers;
    this.pendingHelpers = null;
    await this.addHelpers(helpers);
  }
}

customElements.define('peptide-test-configurator', PeptideTestConfigurator);
