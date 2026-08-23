/**
 * Fast Peptide Testing / March Analytics — agentic content negotiation worker.
 *
 * Deployed on www.fastpeptidetesting.com (Workers Route). Apex often bypasses
 * customer Workers when Shopify + Cloudflare share the hostname; use www or
 * apex→www redirects for OpenAPI Content-Type fixes.
 *
 * Origin fetches go to the myshopify hostname to avoid same-zone Worker recursion.
 */

const PUBLIC_ORIGIN = 'https://fastpeptidetesting.com';
const SHOPIFY_ORIGIN = 'https://srgkrj-ij.myshopify.com';

const JSON_PATHS = new Set([
  '/openapi.json',
  '/api/openapi.json',
  '/api/openapi.yaml',
  '/pages/openapi',
]);

const MARKDOWN_PATHS = new Set([
  '/llms.txt',
  '/llms-full.txt',
  '/agents.md',
  '/pages/llms-txt',
  '/pages/agents-md',
]);

const ORIGIN_MAP = {
  '/openapi.json': '/pages/openapi',
  '/api/openapi.json': '/pages/openapi',
  '/api/openapi.yaml': '/pages/openapi',
};

function prefersMarkdown(accept) {
  if (!accept) return false;
  const lower = accept.toLowerCase();
  if (lower.includes('text/markdown')) {
    const md = qValue(lower, 'text/markdown');
    const html = qValue(lower, 'text/html');
    return md >= html;
  }
  return false;
}

function prefersJson(accept) {
  if (!accept) return false;
  const lower = accept.toLowerCase();
  if (lower.includes('application/json') || lower.includes('application/vnd.oai.openapi+json')) {
    const json = Math.max(
      qValue(lower, 'application/json'),
      qValue(lower, 'application/vnd.oai.openapi+json')
    );
    const html = qValue(lower, 'text/html');
    return json >= html;
  }
  return false;
}

function qValue(accept, type) {
  const parts = accept.split(',').map((p) => p.trim());
  for (const part of parts) {
    const [media, ...params] = part.split(';').map((s) => s.trim());
    if (media === type || media === '*/*') {
      const qParam = params.find((p) => p.startsWith('q='));
      return qParam ? parseFloat(qParam.slice(2)) : 1;
    }
  }
  return 0;
}

function withContentHeaders(response, contentType) {
  const headers = new Headers(response.headers);
  headers.set('Content-Type', contentType);
  headers.set('X-FPT-Worker', 'fpt-agentic-headers');
  const vary = headers.get('Vary');
  if (!vary) {
    headers.set('Vary', 'Accept, Accept-Encoding');
  } else if (!/\bAccept\b/i.test(vary)) {
    headers.set('Vary', `${vary}, Accept`);
  }
  headers.set('Cache-Control', 'public, max-age=300');
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

async function fetchShopify(path, request) {
  const url = new URL(path, SHOPIFY_ORIGIN);
  const headers = new Headers({
    Accept: 'text/html,application/xhtml+xml,*/*',
    'User-Agent': request.headers.get('User-Agent') || 'fpt-agentic-headers',
  });
  return fetch(url.toString(), { method: 'GET', headers, redirect: 'follow' });
}

export default {
  async fetch(request) {
    const url = new URL(request.url);
    const path = url.pathname;
    const accept = request.headers.get('Accept') || '';

    if (path === '/__fpt-worker-ping') {
      return new Response('ok', {
        status: 200,
        headers: {
          'Content-Type': 'text/plain; charset=utf-8',
          'X-FPT-Worker': 'fpt-agentic-headers',
        },
      });
    }

    if (
      prefersMarkdown(accept) &&
      !MARKDOWN_PATHS.has(path) &&
      !JSON_PATHS.has(path) &&
      !path.startsWith('/cdn/') &&
      !path.startsWith('/cart') &&
      !path.endsWith('.js') &&
      !path.endsWith('.css') &&
      !path.endsWith('.json')
    ) {
      const md = await fetchShopify('/llms.txt', request);
      return withContentHeaders(md, 'text/markdown; charset=utf-8');
    }

    if (ORIGIN_MAP[path] || path === '/pages/openapi') {
      const originPath = ORIGIN_MAP[path] || path;
      const res = await fetchShopify(originPath, request);
      if (!res.ok) return res;
      const body = await res.text();
      const jsonStart = body.indexOf('{');
      const jsonBody = jsonStart >= 0 ? body.slice(jsonStart) : body;
      return withContentHeaders(
        new Response(jsonBody, { status: 200, headers: res.headers }),
        'application/json; charset=utf-8'
      );
    }

    if (path === '/pages/llms-txt' || path === '/pages/agents-md') {
      const res = await fetchShopify(path, request);
      if (!res.ok) return res;
      return withContentHeaders(res, 'text/markdown; charset=utf-8');
    }

    if (path === '/llms.txt' || path === '/llms-full.txt' || path === '/agents.md') {
      const res = await fetchShopify(path, request);
      if (!res.ok) return res;
      return withContentHeaders(res, 'text/markdown; charset=utf-8');
    }

    if (prefersJson(accept) && (path === '/' || path === '/openapi')) {
      const res = await fetchShopify('/pages/openapi', request);
      if (res.ok) {
        const body = await res.text();
        const jsonStart = body.indexOf('{');
        const jsonBody = jsonStart >= 0 ? body.slice(jsonStart) : body;
        return withContentHeaders(
          new Response(jsonBody, { status: 200, headers: res.headers }),
          'application/json; charset=utf-8'
        );
      }
    }

    const upstream = await fetch(request);
    const outHeaders = new Headers(upstream.headers);
    outHeaders.set('X-FPT-Worker', 'fpt-agentic-headers');
    const ct = (upstream.headers.get('Content-Type') || '').toLowerCase();
    if (
      upstream.status === 404 &&
      ct.includes('application/json') &&
      (path.endsWith('.json') || path.includes('/cart') || path.includes('/search/suggest'))
    ) {
      const text = await upstream.clone().text();
      if (!text || !text.trim()) {
        const payload = JSON.stringify({
          status: 404,
          message: 'Not Found',
          description:
            'The requested resource does not exist. See /openapi.json for available endpoints, /sitemap.xml for pages, and /llms.txt for agent guidance.',
          recovery: {
            openapi: `${PUBLIC_ORIGIN}/openapi.json`,
            llms: `${PUBLIC_ORIGIN}/llms.txt`,
            agents: `${PUBLIC_ORIGIN}/agents.md`,
            sitemap: `${PUBLIC_ORIGIN}/sitemap.xml`,
            catalog: `${PUBLIC_ORIGIN}/collections/order-testing`,
          },
        });
        return withContentHeaders(
          new Response(payload, { status: 404, headers: outHeaders }),
          'application/json; charset=utf-8'
        );
      }
    }
    return new Response(upstream.body, {
      status: upstream.status,
      statusText: upstream.statusText,
      headers: outHeaders,
    });
  },
};
