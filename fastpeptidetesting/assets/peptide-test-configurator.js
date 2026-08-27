/**
 * Peptide Test multi-step order wizard.
 * Sets vial variant, shows per-vial peptide selects, live price, step navigation,
 * and after the primary add-to-cart succeeds, AJAX-adds add-on and rush lines.
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
    this.currentStep = 1;
    this.maxStep = 5;
    this.isWizard = this.dataset.wizard === 'true';

    this.vialSelect?.addEventListener('change', () => this.onVialCountChange());
    this.addonInputs.forEach((input) => input.addEventListener('change', () => this.updateTotals()));
    this.turnaroundSelect?.addEventListener('change', () => this.updateTotals());

    this.prefillFromQuery();
    this.onVialCountChange();
    this.bindProductForm();
    this.initCoaProfiles();

    if (this.isWizard) {
      this.bindWizard();
      this.goToStep(1);
    }
  }

  get form() {
    if (this.formId) return document.getElementById(this.formId);
    return this.closest('form') || document.querySelector('form[action*="/cart/add"]');
  }

  get variantInput() {
    const form = this.form;
    return (
      form?.querySelector('input[name="id"]') ||
      document.querySelector(`#${this.formId} input[name="id"], product-form input[name="id"]`)
    );
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
      (opt) =>
        opt.dataset.compoundHandle === compound ||
        opt.value.toLowerCase() === compound.replace(/-/g, ' ')
    );
    if (match) {
      firstSelect.value = match.value;
    } else {
      const byHandle = Array.from(firstSelect.options).find(
        (opt) => (opt.dataset.compoundHandle || '').toLowerCase() === compound.toLowerCase()
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
    const base = parseInt(baseOption?.dataset.price || String(19900 * count), 10);
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

    this.updateReviewSummary(totals);
  }

  /**
   * Sample/COA inputs live inside this element but use the HTML form= attribute
   * to attach to the product form, so form.querySelector cannot see them.
   */
  fieldByName(name) {
    return (
      this.querySelector(`[name="${name}"]`) ||
      this.form?.querySelector(`[name="${name}"]`) ||
      document.querySelector(`[name="${name}"][form="${this.formId}"]`) ||
      null
    );
  }

  updateReviewSummary(totals) {
    const list = this.querySelector('[data-review-summary]');
    if (!list) return;
    const peptides = [];
    const count = this.vialCount;
    for (let i = 1; i <= count; i += 1) {
      const select = this.querySelector(`[data-vial-peptide][data-vial-index="${i}"]`);
      if (select?.value) peptides.push(`Vial ${i}: ${select.value}`);
    }
    const batch = this.fieldByName('properties[Batch or lot number]')?.value || '';
    const company = this.querySelector('[data-coa-company]')?.value || '';
    const rows = [
      ['Vials', String(count)],
      ['Peptides', peptides.join('; ') || '—'],
      ['Add-ons', totals.addonLabels.length ? totals.addonLabels.join('; ') : 'None'],
      ['Turnaround', totals.turnaroundLabel],
      ['Batch / lot', batch || '—'],
      ['COA company', company || '—'],
      ['Total', this.formatMoney(totals.total)],
    ];
    list.innerHTML = rows
      .map(([label, value]) => `<li><span>${label}</span><span>${this.escapeHtml(value)}</span></li>`)
      .join('');
  }

  escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  initCoaProfiles() {
    const select = this.querySelector('[data-coa-profile-select]');
    const company = this.querySelector('[data-coa-company]');
    const profileId = this.querySelector('[data-coa-profile-id]');
    if (!select) return;

    let profiles = [];
    try {
      profiles = JSON.parse(this.dataset.coaProfiles || '[]');
    } catch (e) {
      profiles = [];
    }
    if (!Array.isArray(profiles)) profiles = [];

    profiles.forEach((profile) => {
      if (!profile?.id || !profile?.company) return;
      const option = document.createElement('option');
      option.value = profile.id;
      option.textContent = profile.company;
      option.dataset.company = profile.company;
      select.appendChild(option);
    });

    select.addEventListener('change', () => {
      const option = select.selectedOptions?.[0];
      if (option?.value && company) {
        company.value = option.dataset.company || option.textContent;
        if (profileId) profileId.value = option.value;
      } else if (profileId) {
        profileId.value = '';
      }
    });
  }

  bindWizard() {
    this.nextBtn = this.querySelector('[data-wizard-next]');
    this.backBtn = this.querySelector('[data-wizard-back]');
    this.nextBtn?.addEventListener('click', () => this.onNext());
    this.backBtn?.addEventListener('click', () => this.goToStep(this.currentStep - 1));
    this.querySelectorAll('[data-goto-step]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const step = parseInt(btn.dataset.gotoStep, 10);
        if (step < this.currentStep || this.validateThrough(step - 1)) {
          this.goToStep(step);
        }
      });
    });
  }

  goToStep(step) {
    if (step < 1 || step > this.maxStep) return;
    this.currentStep = step;
    this.querySelectorAll('[data-wizard-step]').forEach((panel) => {
      const panelStep = parseInt(panel.dataset.wizardStep, 10);
      panel.hidden = panelStep !== step;
    });
    this.querySelectorAll('[data-goto-step]').forEach((btn) => {
      const btnStep = parseInt(btn.dataset.gotoStep, 10);
      btn.setAttribute('aria-current', btnStep === step ? 'step' : 'false');
      btn.classList.toggle('is-complete', btnStep < step);
    });
    if (this.backBtn) this.backBtn.hidden = step === 1;
    if (this.nextBtn) {
      this.nextBtn.hidden = step === this.maxStep;
      this.nextBtn.textContent = 'Continue';
    }
    this.toggleBuyButtons(step === this.maxStep);
    this.updateTotals();
    this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  toggleBuyButtons(show) {
    const section = this.closest('.product__info-container') || document;
    section.querySelectorAll('[data-wizard-buy-buttons]').forEach((el) => {
      el.hidden = !show;
    });
  }

  onNext() {
    if (!this.validateStep(this.currentStep)) return;
    this.goToStep(this.currentStep + 1);
  }

  validateThrough(upToStep) {
    for (let step = 1; step <= upToStep; step += 1) {
      if (!this.validateStep(step, false)) return false;
    }
    return true;
  }

  validateStep(step, focus = true) {
    if (step === 1) {
      const count = this.vialCount;
      for (let i = 1; i <= count; i += 1) {
        const select = this.querySelector(`[data-vial-peptide][data-vial-index="${i}"]`);
        if (!select?.value) {
          if (focus) {
            this.goToStep(1);
            select?.focus();
            select?.reportValidity?.();
          }
          return false;
        }
      }
      return true;
    }
    if (step === 3) {
      const requiredNames = [
        'properties[Batch or lot number]',
        'properties[Quantity supplied]',
        'properties[Customer return address]',
      ];
      for (const name of requiredNames) {
        const field = this.fieldByName(name);
        if (!field?.value?.trim()) {
          if (focus) {
            this.goToStep(3);
            field?.focus();
            field?.reportValidity?.();
          }
          return false;
        }
      }
      return true;
    }
    if (step === 4) {
      const company = this.querySelector('[data-coa-company]');
      if (!company?.value?.trim()) {
        if (focus) {
          this.goToStep(4);
          company?.focus();
          company?.reportValidity?.();
        }
        return false;
      }
      return true;
    }
    return true;
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

    const productForm = form.closest('product-form') || form.querySelector('product-form');
    if (productForm) {
      productForm.addEventListener(
        'submit',
        (event) => {
          if (!this.validate()) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        },
        true
      );
    }

    this.interceptCartAdd();
  }

  validate() {
    if (this.isWizard) {
      for (let step = 1; step <= this.maxStep; step += 1) {
        if (!this.validateStep(step)) return false;
      }
      return true;
    }
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
