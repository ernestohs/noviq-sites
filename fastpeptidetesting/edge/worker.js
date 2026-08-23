/**
 * Fast Peptide Testing / March Analytics — agentic edge worker.
 *
 * Reliable on www.fastpeptidetesting.com (Workers Route in front of Shopify).
 * Apex may not invoke customer Workers under Shopify primary-domain routing.
 *
 * OpenAPI: same-host origin fetch of /pages/openapi, rewrite Content-Type to JSON.
 * Do not fetch myshopify and follow redirects to the primary domain (loops).
 */

const PUBLIC_ORIGIN = 'https://fastpeptidetesting.com';

const ORIGIN_MAP = {
  '/openapi.json': '/pages/openapi',
  '/api/openapi.json': '/pages/openapi',
  '/api/openapi.yaml': '/pages/openapi',
};

function withAgentHeaders(response, contentType) {
  const headers = new Headers(response.headers);
  if (contentType) headers.set('Content-Type', contentType);
  headers.set('X-FPT-Worker', 'fpt-agentic-headers');
  const vary = headers.get('Vary');
  if (!vary) headers.set('Vary', 'Accept, Accept-Encoding');
  else if (!/\bAccept\b/i.test(vary)) headers.set('Vary', `${vary}, Accept`);
  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

async function openApiJson(request) {
  const originUrl = new URL('/pages/openapi', request.url);
  const res = await fetch(
    new Request(originUrl.toString(), {
      method: 'GET',
      headers: {
        Accept: 'text/html,application/xhtml+xml',
        'User-Agent': request.headers.get('User-Agent') || 'fpt-agentic-headers',
      },
      redirect: 'follow',
    })
  );
  if (!res.ok) return withAgentHeaders(res);
  const body = await res.text();
  const start = body.indexOf('{');
  const json = start >= 0 ? body.slice(start) : body;
  return withAgentHeaders(
    new Response(json, { status: 200, headers: { 'Cache-Control': 'public, max-age=300' } }),
    'application/json; charset=utf-8'
  );
}

export default {
  async fetch(request) {
    const url = new URL(request.url);
    const path = url.pathname;

    if (path === '/__fpt-worker-ping') {
      return new Response('ok', {
        status: 200,
        headers: {
          'Content-Type': 'text/plain; charset=utf-8',
          'X-FPT-Worker': 'fpt-agentic-headers',
        },
      });
    }

    if (ORIGIN_MAP[path] || path === '/pages/openapi') {
      return openApiJson(request);
    }

    if (path === '/llms.txt' || path === '/agents.md' || path === '/llms-full.txt') {
      const res = await fetch(request);
      return withAgentHeaders(res, 'text/markdown; charset=utf-8');
    }

    const upstream = await fetch(request);
    const out = new Headers(upstream.headers);
    out.set('X-FPT-Worker', 'fpt-agentic-headers');
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
            'The requested resource does not exist. See /openapi.json, /llms.txt, and /agents.md.',
          recovery: {
            openapi: `${PUBLIC_ORIGIN}/openapi.json`,
            llms: `${PUBLIC_ORIGIN}/llms.txt`,
            agents: `${PUBLIC_ORIGIN}/agents.md`,
            sitemap: `${PUBLIC_ORIGIN}/sitemap.xml`,
            catalog: `${PUBLIC_ORIGIN}/collections/order-testing`,
          },
        });
        return withAgentHeaders(
          new Response(payload, { status: 404, headers: out }),
          'application/json; charset=utf-8'
        );
      }
    }
    return new Response(upstream.body, {
      status: upstream.status,
      statusText: upstream.statusText,
      headers: out,
    });
  },
};
