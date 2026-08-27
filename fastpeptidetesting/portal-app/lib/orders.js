import {
  NS,
  STAGES,
  DEFAULT_STAGE,
  adminGql,
  metafieldValue,
  parseJson,
  stageIndex,
} from '../lib/shopify.js';

const ORDER_FIELDS = `
  id
  name
  createdAt
  displayFinancialStatus
  totalPriceSet { shopMoney { amount currencyCode } }
  lineItems(first: 50) {
    nodes {
      title
      quantity
      variantTitle
      customAttributes { key value }
    }
  }
  shippingAddress {
    name
    company
    address1
    address2
    city
    province
    zip
    country
    phone
  }
  customer {
    id
    displayName
    email
    phone
  }
  metafields(first: 20, namespace: "${NS}") {
    nodes { namespace key value type }
  }
`;

export async function findCustomerByLoggedInId(loggedInCustomerId) {
  if (!loggedInCustomerId) return null;
  const gid = String(loggedInCustomerId).startsWith('gid://')
    ? loggedInCustomerId
    : `gid://shopify/Customer/${loggedInCustomerId}`;
  const data = await adminGql(
    `query Customer($id: ID!) {
      customer(id: $id) {
        id
        displayName
        email
        metafields(first: 10, namespace: "${NS}") {
          nodes { namespace key value type }
        }
      }
    }`,
    { id: gid }
  );
  return data?.customer || null;
}

export async function listCustomerOrders(customerGid) {
  const data = await adminGql(
    `query Orders($query: String!) {
      orders(first: 50, query: $query, sortKey: CREATED_AT, reverse: true) {
        nodes { ${ORDER_FIELDS} }
      }
    }`,
    { query: `customer_id:${String(customerGid).replace('gid://shopify/Customer/', '')}` }
  );
  return (data?.orders?.nodes || []).map(normalizeOrder);
}

export async function getOrder(orderId) {
  const gid = String(orderId).startsWith('gid://')
    ? orderId
    : `gid://shopify/Order/${orderId}`;
  const data = await adminGql(
    `query Order($id: ID!) {
      order(id: $id) { ${ORDER_FIELDS} }
    }`,
    { id: gid }
  );
  return data?.order ? normalizeOrder(data.order) : null;
}

function normalizeOrder(order) {
  const stage = metafieldValue(order.metafields, 'lab_stage') || DEFAULT_STAGE;
  const packingNotes = metafieldValue(order.metafields, 'packing_notes') || '';
  const certIds = parseJson(metafieldValue(order.metafields, 'certificate_ids'), []) || [];
  const vialsRequired = Number(metafieldValue(order.metafields, 'vials_required') || 0);
  return {
    ...order,
    labStage: stage,
    labStageIndex: stageIndex(stage),
    stages: STAGES,
    packingNotes,
    certificateIds: Array.isArray(certIds) ? certIds : [],
    vialsRequired,
    sampleProperties: extractSampleProperties(order.lineItems?.nodes || []),
  };
}

function extractSampleProperties(lineItems) {
  const props = {};
  for (const item of lineItems) {
    for (const attr of item.customAttributes || []) {
      if (!attr.key || attr.key.startsWith('_')) continue;
      props[attr.key] = attr.value;
    }
  }
  return props;
}

export function getCoaProfiles(customer) {
  const raw = metafieldValue(customer?.metafields, 'coa_profiles');
  const parsed = parseJson(raw, []);
  return Array.isArray(parsed) ? parsed : [];
}
