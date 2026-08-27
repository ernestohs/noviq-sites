export function layout({ title, body, brand = 'March Analytics' }) {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapeHtml(title)} · ${escapeHtml(brand)}</title>
  <link rel="stylesheet" href="/proxy/assets/portal.css">
</head>
<body>
  <header class="portal-header">
    <div class="portal-wrap portal-header__inner">
      <a class="portal-brand" href="/apps/portal">${escapeHtml(brand)}</a>
      <nav class="portal-nav">
        <a href="/apps/portal">Orders</a>
        <a href="/apps/portal/profiles">COA profiles</a>
        <a href="/products/peptide-testing">New order</a>
      </nav>
    </div>
  </header>
  <main class="portal-wrap portal-main">
    ${body}
  </main>
  <footer class="portal-footer">
    <div class="portal-wrap">
      <p>Client portal for laboratory order status. Research use only.</p>
    </div>
  </footer>
</body>
</html>`;
}

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export function renderLoginRequired() {
  return layout({
    title: 'Sign in',
    body: `
      <section class="portal-card">
        <h1>Sign in required</h1>
        <p class="muted">Sign in to your March Analytics account to view orders, packing slips, and COA profiles.</p>
        <p><a class="btn" href="/account/login">Sign in</a></p>
      </section>
    `,
  });
}

export function renderOrderList(orders) {
  const rows = orders.length
    ? orders
        .map(
          (o) => `
      <tr>
        <td><a href="/apps/portal/orders/${encodeURIComponent(o.id.replace('gid://shopify/Order/', ''))}">${escapeHtml(o.name)}</a></td>
        <td>${escapeHtml(new Date(o.createdAt).toLocaleDateString())}</td>
        <td><span class="badge">${escapeHtml(o.labStage)}</span></td>
        <td>${escapeHtml(o.totalPriceSet?.shopMoney?.amount || '')} ${escapeHtml(o.totalPriceSet?.shopMoney?.currencyCode || '')}</td>
      </tr>`
        )
        .join('')
    : `<tr><td colspan="4">No orders yet. <a href="/products/peptide-testing">Submit a sample</a>.</td></tr>`;

  return layout({
    title: 'Orders',
    body: `
      <h1>Your orders</h1>
      <p class="muted">Track lab progress from submission through completed certificates.</p>
      <div class="portal-table-wrap">
        <table class="portal-table">
          <thead>
            <tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th></tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    `,
  });
}

export function renderOrderDetail(order) {
  const stages = order.stages
    .map((label, idx) => {
      const state =
        idx < order.labStageIndex ? 'is-done' : idx === order.labStageIndex ? 'is-current' : '';
      return `<li class="pipeline__item ${state}"><span class="pipeline__dot"></span><span>${escapeHtml(label)}</span></li>`;
    })
    .join('');

  const props = Object.entries(order.sampleProperties || {})
    .map(([k, v]) => `<li><strong>${escapeHtml(k)}:</strong> ${escapeHtml(v)}</li>`)
    .join('');

  const certs = (order.certificateIds || [])
    .map((id) => {
      const orderNumericId = order.id.replace('gid://shopify/Order/', '');
      return `<li class="cert-row">
        <a href="/pages/certificates/${encodeURIComponent(id)}">${escapeHtml(id)}</a>
        <img src="/apps/portal/orders/${orderNumericId}/qr/${encodeURIComponent(id)}.png" alt="QR for ${escapeHtml(id)}" width="96" height="96">
      </li>`;
    })
    .join('');

  const orderNumericId = order.id.replace('gid://shopify/Order/', '');

  return layout({
    title: order.name,
    body: `
      <p class="muted"><a href="/apps/portal">← All orders</a></p>
      <h1>${escapeHtml(order.name)}</h1>
      <p class="muted">Placed ${escapeHtml(new Date(order.createdAt).toLocaleString())}</p>

      <section class="portal-card">
        <h2>Status</h2>
        <ol class="pipeline">${stages}</ol>
      </section>

      <section class="portal-card">
        <h2>Sample details</h2>
        <ul class="plain-list">${props || '<li>No sample properties on this order.</li>'}</ul>
      </section>

      <section class="portal-actions">
        <a class="btn" href="/apps/portal/orders/${orderNumericId}/packing-slip">Packing slip</a>
        <a class="btn btn--secondary" href="/apps/portal/orders/${orderNumericId}/additional-coa">Order additional COA</a>
      </section>

      <section class="portal-card">
        <h2>Certificates</h2>
        <ul class="plain-list">${certs || '<li>Certificates appear here when reporting is complete.</li>'}</ul>
      </section>
    `,
  });
}

export function renderPackingSlip(order, brand = 'March Analytics') {
  const props = Object.entries(order.sampleProperties || {})
    .map(([k, v]) => `<tr><th>${escapeHtml(k)}</th><td>${escapeHtml(v)}</td></tr>`)
    .join('');
  const address = order.shippingAddress;
  const addrHtml = address
    ? [
        address.name,
        address.company,
        address.address1,
        address.address2,
        [address.city, address.province, address.zip].filter(Boolean).join(', '),
        address.country,
        address.phone,
      ]
        .filter(Boolean)
        .map((line) => escapeHtml(line))
        .join('<br>')
    : '—';

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Packing slip ${escapeHtml(order.name)}</title>
  <link rel="stylesheet" href="/proxy/assets/portal.css">
</head>
<body class="print-slip">
  <div class="slip">
    <header class="slip__header">
      <div>
        <strong>${escapeHtml(brand)}</strong>
        <div class="muted">Sample packing slip</div>
      </div>
      <div class="slip__meta">
        <div>${escapeHtml(order.name)}</div>
        <div>${escapeHtml(new Date(order.createdAt).toLocaleDateString())}</div>
      </div>
    </header>
    <section>
      <h2>Ship from</h2>
      <p>${addrHtml}</p>
    </section>
    <section>
      <h2>Include in package</h2>
      <ul>
        <li>This packing slip</li>
        <li>Labeled vials matching the lot codes below</li>
        <li>Cold packs if your compound requires refrigeration</li>
      </ul>
    </section>
    <section>
      <h2>Order contents</h2>
      <table class="slip-table">
        <tbody>${props}</tbody>
      </table>
      ${order.packingNotes ? `<p><strong>Notes:</strong> ${escapeHtml(order.packingNotes)}</p>` : ''}
    </section>
    <p class="no-print"><button onclick="window.print()">Print</button> · <a href="/apps/portal/orders/${order.id.replace('gid://shopify/Order/', '')}">Back to order</a></p>
  </div>
</body>
</html>`;
}

export function renderProfiles(profiles) {
  const cards = profiles.length
    ? profiles
        .map(
          (p) => `
      <article class="portal-card">
        <h3>${escapeHtml(p.company)}</h3>
        <p class="muted">${escapeHtml(p.website || '')}</p>
        <p>${escapeHtml(p.email || '')} · ${escapeHtml(p.phone || '')}</p>
        <form method="post" action="/apps/portal/profiles/${encodeURIComponent(p.id)}/delete">
          <button type="submit" class="btn btn--secondary">Delete</button>
        </form>
      </article>`
        )
        .join('')
    : `<p class="muted">No saved profiles yet.</p>`;

  return layout({
    title: 'COA profiles',
    body: `
      <h1>COA branding profiles</h1>
      <p class="muted">Saved profiles appear in the order wizard. Certificates display the selected company name.</p>
      <div class="profile-grid">${cards}</div>
      <section class="portal-card">
        <h2>Add profile</h2>
        <form method="post" action="/apps/portal/profiles" class="portal-form">
          <label>Company name <input name="company" required></label>
          <label>Website <input name="website" type="url" placeholder="https://"></label>
          <label>Email <input name="email" type="email"></label>
          <label>Phone <input name="phone"></label>
          <label>Address <textarea name="address" rows="3"></textarea></label>
          <button type="submit" class="btn">Save profile</button>
        </form>
      </section>
    `,
  });
}

export function renderAdditionalCoa(order) {
  const company = order.sampleProperties?.['COA company'] || '';
  const href = `/products/peptide-testing?additional_coa=1&order=${encodeURIComponent(order.name)}&company=${encodeURIComponent(company)}`;
  return layout({
    title: 'Additional COA',
    body: `
      <h1>Order an additional COA</h1>
      <p class="muted">Additional branded certificates reuse the sample already on file for ${escapeHtml(order.name)}.</p>
      <p><a class="btn" href="${href}">Continue to order form</a></p>
      <p><a href="/apps/portal/orders/${order.id.replace('gid://shopify/Order/', '')}">Cancel</a></p>
    `,
  });
}
