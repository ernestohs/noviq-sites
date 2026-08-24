/**
 * Unit tests for agentic path classification used by the edge worker.
 * Run: node --test fastpeptidetesting/edge/worker.test.mjs
 */
import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const DIR = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(join(DIR, 'worker.js'), 'utf8');

describe('fpt agentic edge worker source', () => {
  it('declares OpenAPI JSON paths', () => {
    assert.match(source, /\/openapi\.json/);
    assert.match(source, /application\/json/);
  });

  it('sets Vary Accept on rewritten responses', () => {
    assert.match(source, /Vary.*Accept/);
    assert.match(source, /Accept-Encoding/);
  });

  it('handles markdown paths', () => {
    assert.match(source, /text\/markdown/);
    assert.match(source, /llms\.txt/);
  });

  it('synthesizes JSON error bodies for empty API 404s', () => {
    assert.match(source, /Not Found/);
    assert.match(source, /recovery/);
  });

  it('uses same-host origin fetch for OpenAPI', () => {
    assert.match(source, /\/pages\/openapi/);
    assert.match(source, /openApiJson/);
  });
});
