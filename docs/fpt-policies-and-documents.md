# March Analytics: policies and documents

Brand: March Analytics  
Site: `fastpeptidetesting/` → fastpeptidetesting.com  
Platform: Shopify (Dawn)

This note describes every legal policy and storefront document needed before go-live. It does **not** invent final legal wording.

**Status (Mar 2026):** Client-approved terms, privacy, refund, and shipping policies are uploaded to the FPT Shopify store (**Settings → Policies** and matching Online Store pages). Repo preview stubs in `fastpeptidetesting/seed/catalog.json` are obsolete for legal copy. Remaining work is verification and operational pages still blocked on intake C7–C13.

Launch requires terms, privacy, refund, and shipping to match in **both** places:

1. **Settings → Policies** (checkout footer and theme policy links)
2. **Online Store → Pages** for the matching handles (footer menu and public URLs)

Footer already has `show_policy: true` in `fastpeptidetesting/sections/footer-group.json`.

---

## Document map

| Document | Settings → Policies | Online Store page | Shown in |
| --- | --- | --- | --- |
| Terms of service | Yes | `/pages/terms` | Checkout + footer |
| Privacy policy | Yes | `/pages/privacy` | Checkout + footer |
| Refund policy | Yes | `/pages/refund-policy` | Checkout + footer |
| Shipping policy | Yes (customer ships to lab) | Optional dedicated page | Checkout + policy links |
| Contact information | Yes (if using Shopify slot) | `/pages/contact-us` | Policies / contact page |
| Attestation | No | `/pages/attestation` | Footer menu |
| How it works | No | `/pages/how-it-works` | Nav / footer |
| Methods | No | `/pages/methods` | Nav / footer |
| Turnaround | No | `/pages/turnaround` | Nav / footer |
| About | No | `/pages/about` | Nav / footer |

Keep Settings → Policies text identical to the matching Online Store page bodies so checkout and storefront never disagree.

---

## A. Shopify legal policies (Settings → Policies)

### 1. Terms of service

**Handle / page:** `/pages/terms`  
**Purpose:** Contract between the customer and March Analytics for buying laboratory analysis.

**What this store sells:** Analytical testing of customer-supplied samples. The store does not ship physical goods to the buyer. The customer pays for a test, then ships a sample to the lab, then receives a report (certificate of analysis).

**What the document must cover:**

- Nature of the service (lab analysis, not product fulfilment to the customer)
- Who ships the sample (customer → lab after payment)
- Scope of results: COA applies only to the sample received
- No warranty that results fit any use beyond the stated analytical outcome
- Research / analytical use framing, aligned with the attestation page
- Limitation of liability and indemnity
- Governing law / venue (counsel decides)
- Right to refuse or cancel orders (unlabeled, contaminated, illegal, or incomplete submissions)
- Results are delivered only to the customer; certificates are never published as a public COA
- Relationship to turnaround, refund, and sample handling rules (cross-reference; do not contradict)

**Who writes it:** Client counsel (intake D3; upload complete Mar 2026).  
**Verify:** Entity name, address, and support contact appear in live copy (transcribe into intake D1–D2 when auditing).

**Repo seed:** Placeholder only; do not re-import for launch.

---

### 2. Privacy policy

**Handle / page:** `/pages/privacy`  
**Purpose:** How personal data and sample-related data are collected, used, stored, shared, and retained.

**What the document must cover:**

- Identity and contact of the controller (legal entity, address, support email for this domain only)
- Data collected at checkout (name, email, billing, payment handled by Shopify / processor)
- Sample intake fields attached to the order, for example:
  - Compound name
  - Batch or lot number
  - Quantity supplied
  - Customer return address
- Use of contact details to deliver the report (email and/or private portal; confirm C13)
- Explicit statement that results are delivered only to the customer and are never published as a public COA
- Retention of order records and lab records
- Processors and subprocessors (Shopify, payment, email delivery)
- Customer rights and how to request access, correction, or deletion
- Cross-border transfer language if applicable (counsel)

**Who writes it:** Client counsel (intake D3; upload complete Mar 2026).  
**Verify:** Controller identity and support email in live copy; result-delivery language aligns with intake C13 when confirmed.

**Repo seed:** Placeholder only; do not re-import for launch.

**Separation rule:** Support email and legal entity must be unique to this domain. Do not reuse bacwatermarket or noviqpeptides contacts.

---

### 3. Refund policy

**Handle / page:** `/pages/refund-policy`  
**Purpose:** When test fees are refundable. This must read as a **service** refund policy, not a goods / restocking policy.

**Why it differs from a product store:** There is no “return the item unused.” Value is consumed when analysis starts (or when the sample is logged), not when a parcel leaves a warehouse.

**What the document must cover:**

- Refund or cancellation before the sample is received or logged
- Non-refundable (or limited) status after analysis starts
- Failed, delayed, or inconclusive tests caused by sample quality vs lab error
- Rush / turnaround add-ons and cancellations
- How to request a refund (channel and email from D2)
- Chargeback / dispute posture at a high level (counsel)

**Who writes it:** Client counsel (intake D3; upload complete Mar 2026).  
**Verify:** Refund windows align with turnaround clock rules (intake C10) when confirmed.

**Repo seed:** Placeholder only; do not re-import for launch.

---

### 4. Shipping policy

**Shopify field:** Settings → Policies → Shipping policy (required for checkout / policy links even when catalog items are non-physical)  
**Optional page:** Only if you want a dedicated storefront URL; not in the nine-page spec list as a separate handle.

**Purpose:** Explain shipping for this business model. Shipping is **inverted**: the customer ships the sample to the lab. The store does not ship product to the customer.

**What the document must cover:**

- Explicit statement that checkout does not include outbound shipping of goods to the buyer
- That service products are non-physical and no shipping method is charged on the order
- That after payment the customer receives lab receiving instructions (confirmation email or equivalent)
- Lab receiving address once confirmed (intake C11), or a clear statement that the address is issued after checkout
- Packaging and labeling requirements once confirmed (intake C12), for example:
  - Order number on the outside of the package
  - Do not require a signature on delivery
  - Allowed packaging / what to ship
- What happens if the sample never arrives, arrives damaged, or is mislabeled
- Whether unused sample material is returned, discarded, or retained (client decision)

**Do not write:**

- Carrier rate tables for shipping product to the customer
- “We ship within X days” fulfilment language copied from a goods store
- Any implication that March Analytics ships peptides or other restricted product

**Blocked on:** C11 (receiving address), C12 (packaging), C13 (how results / instructions are delivered).

---

### 5. Contact information (Shopify policies slot)

**Related page:** `/pages/contact-us`

**Purpose:** Legal and support contact shown with policies and on the contact page.

**What it must include:**

- Legal entity name (D1)
- Business address (D1)
- Support email for fastpeptidetesting.com / March Analytics only (D2)
- Optional: phone, hours (only if real)

**Do not:**

- Put a fake lab street address on the preview
- Reuse another brand’s support email
- Link to noviqpeptides.com or bacwatermarket.com

**Page purpose beyond the policy slot:** Dawn `contact-form` for questions about orders, samples, or reports.

---

## B. Storefront content pages (not Shopify policy fields)

These are Online Store pages from `specs/02-fastpeptidetesting.md`. Attestation is compliance-facing. The others are operational or brand content.

### 6. Attestation (`/pages/attestation`)

**Purpose:** Research-use and acceptable-submission notice. Customers confirm this by placing an order. This is a core compliance surface for a peptide-related testing lab.

**What it must state (current seed structure):**

- Sample is submitted for research and analytical purposes only
- Not for human or animal use
- Certificate of analysis is valid only for the tested sample and may not be reused for other material
- Certificates may not be used to market, sell, or validate untested products
- Customer will comply with applicable law
- Customer indemnifies March Analytics and laboratory partners for misuse, misrepresentation, or unlawful submission

**Still needs:**

- Legal entity name in the indemnity clause (D1)
- Final RUO disclaimer text if the client supplies a verbatim string (D4)

**Tone:** Clinical and factual. No health claims, dosage guidance, or therapeutic language.

---

### 7. How it works (`/pages/how-it-works`)

**Purpose:** Plain-language process so customers understand they are buying a service and shipping a sample in.

**Process the page should describe:**

1. Open Peptide Test; choose vial count, peptide per vial, optional screens, and turnaround; complete sample intake fields (lot, quantity, return address)
2. Complete checkout (non-physical products; no shipping method charged)
3. Ship the sample to the address in the confirmation materials; mark the order number on the package
4. Receive results as a certificate of analysis privately (delivery method per C13; never published publicly)

**Must stay accurate on:**

- Checkout does not ship anything to the buyer
- Intake fields are required and appear on the order in Admin
- Turnaround language matching C10 once confirmed

**Blocked on:** C11, C12, C13.

---

### 8. Methods (`/pages/methods`)

**Purpose:** Factual methodology and instrumentation positioning. Trust page, not a legal policy.

**What it should cover:**

- HPLC as the primary purity / potency method (as offered)
- Validation framing (e.g. USP &lt;1225&gt; principles) only if still accurate for the lab
- Optional add-ons only if the lab actually runs them (heavy metals, sterility, endotoxin, moisture, vacuum integrity, etc.)
- That reports are certificates of analysis derived from instrument output

**Hard rule:** Do not advertise a test the lab cannot perform (intake C7). Identity confirmation by mass spectrometry stays out of SEO and firm claims until confirmed.

---

### 9. Turnaround (`/pages/turnaround`)

**Purpose:** Timing expectations and what starts the clock. Misaligned copy here drives chargebacks.

**What it must settle:**

- Standard turnaround in business days (C9)
- Whether the clock starts at payment or at sample receipt (C10)
- Rush options and prices only after C8 is real (demo rush fees in seed are not launch copy)

**Blocked on:** C8, C9, C10.

---

### 10. About (`/pages/about`)

**Purpose:** Independent laboratory positioning. Commercial identity page.

**What it must reinforce:**

- March Analytics analyses customer-supplied samples and returns a COA
- Independence from any seller of research compounds
- No marketing, validation, or endorsement of products that were not in the vial tested
- No co-branding, shared logo, or “our other brands” language with noviqpeptides or bacwatermarket

---

### 11. Contact (`/pages/contact-us`)

Covered under section 5. Page body plus Dawn contact form. Live support email required before launch (D2).

---

## C. Intake blockers (do not invent)

| Intake | Blocks |
| --- | --- |
| D1 | Transcribe legal entity name and business address from uploaded policies into `specs/10-intake.md` |
| D2 | Transcribe support email per domain from uploaded policies into intake |
| D4 | Final RUO disclaimer text if supplied separately from attestation (Noviq) |
| C7 | Which tests appear on Methods and product pages |
| C8–C9 | Prices and turnaround numbers on Turnaround and products |
| C10 | Clock start language on Turnaround, How it works, Refund (verify against uploaded refund policy) |
| C11–C12 | Receiving address and packaging on Shipping policy and How it works |
| C13 | Result delivery language on Privacy, How it works, and order emails (verify against uploaded privacy policy) |

Legal policies (intake D3): uploaded Mar 2026.

---

## D. Verification checklist (policies uploaded Mar 2026)

1. Confirm **Settings → Policies** for Terms, Privacy, Refund, and Shipping match the Online Store pages with handles `terms`, `privacy`, and `refund-policy`.
2. Confirm checkout footer shows all four Shopify policies.
3. Confirm footer menu links: attestation, terms, privacy, refund-policy, contact-us.
4. Confirm no page links to noviqpeptides.com or bacwatermarket.com.
5. Do **not** re-run `fastpeptidetesting/seed/import.mjs` for legal page bodies; live admin copy is source of truth.
6. Update `attestation`, `how-it-works`, `methods`, `turnaround`, `about`, and `contact-us` with non-placeholder operational copy once C intake lands.

---

## E. Related specs

- `specs/02-fastpeptidetesting.md` — page handles, service model, SEO checklist
- `specs/10-intake.md` — D1–D4, C7–C14
- `docs/client-preview.md` — FPT demo URL and preview teardown
- `specs/00-overview.md` — no cross-branding constraint
- `fastpeptidetesting/seed/README.md` — preview stubs vs launch
