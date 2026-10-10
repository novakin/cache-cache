/** Actual beacon assets and browser fetch/abort behavior; every request intercepted, no WordPress. */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const source = await readFile(new URL('../noobrocket/assets/js/noobrocket-beacon.js', import.meta.url), 'utf8');
const minified = await readFile(new URL('../noobrocket/assets/js/noobrocket-beacon.min.js', import.meta.url), 'utf8');
const origin = 'http://beacon.fixture.test';
const check = 'noobrocket_check_beacon';
const save = 'noobrocket_beacon';
const refresh = 'noobrocket_beacon_nonce';
const json = data => ({ body: JSON.stringify({ success: true, data }) });
const missing = json({ lcp: false, lrc: false, preload_fonts: false, preconnect_external_domain: false });
const stored = json({ lcp: { status: 'completed' }, lrc: { status: 'completed' }, preload_fonts: { status: 'completed' }, preconnect_external_domains: { status: 'completed' } });
const fresh = json({ nonce: 'current-nonce' });
const expired = { status: 403, body: '-1' };
const defaults = { [check]: [missing], [save]: [stored], [refresh]: [fresh] };
const cases = [
  { name: 'current', actions: [check, save], outcome: 'saved' },
  { name: 'uncached-current', cached: false, actions: [save], outcome: 'saved' },
  { name: 'already-generated', replies: { [check]: [json({ lcp: true })] }, actions: [check], outcome: 'skipped' },
  { name: 'expired-check', replies: { [check]: [expired, missing] }, actions: [check, refresh, check, save], outcome: 'saved' },
  { name: 'expired-save', replies: { [save]: [expired, stored] }, actions: [check, save, refresh, save], outcome: 'saved' },
  { name: 'shared-budget', replies: { [check]: [expired, missing], [save]: [expired] }, actions: [check, refresh, check, save], outcome: 'failed' },
  { name: 'persistent-check-403', replies: { [check]: [expired, expired] }, actions: [check, refresh, check], outcome: 'failed' },
  { name: 'persistent-save-403', replies: { [save]: [expired, expired] }, actions: [check, save, refresh, save], outcome: 'failed' },
  ...[
    ['refresh-403', expired], ['refresh-network', { network: true }], ['refresh-invalid-json', { body: '{' }],
    ['refresh-empty-nonce', json({ nonce: '' })], ['refresh-invalid-nonce', json({ nonce: 123 })],
    ['refresh-missing-nonce', json({})], ['refresh-rejected', { body: '{"success":false,"data":{"nonce":"other"}}' }]
  ].map(([name, reply]) => ({ name, replies: { [check]: [expired], [refresh]: [reply] }, actions: [check, refresh], outcome: 'failed' })),
  ...[
    ['check-other-403', { status: 403, body: 'forbidden' }], ['check-invalid-json', { body: '{' }],
    ['check-200-nonce-error', { body: '-1' }], ['check-server-error', { status: 500, body: '-1' }],
    ['check-network', { network: true }], ['check-invalid-fields', json({ lcp: 'false' })],
    ['check-array-data', json([])], ['check-null', { body: 'null' }],
    ['check-rejected', { body: '{"success":false,"data":{"lcp":false}}' }]
  ].map(([name, reply]) => ({ name, replies: { [check]: [reply] }, actions: [check], outcome: 'failed' })),
  ...[
    ['save-other-403', { status: 403, body: 'forbidden' }], ['save-invalid-json', { body: '{' }],
    ['save-network', { network: true }], ['save-missing-fields', json({})],
    ['save-not-allowed', json({ lcp: 'not allowed' })], ['save-write-failed', json({ lcp: 'error when adding the entry to the database' })],
    ['save-failed-row', json({ lcp: { status: 'failed' } })], ['save-rejected', { body: '{"success":false,"data":{"lcp":{"status":"completed"}}}' }]
  ].map(([name, reply]) => ({ name, replies: { [save]: [reply] }, actions: [check, save], outcome: 'failed' })),
  { name: 'save-existing-row', replies: { [save]: [json({ lcp: 'item already in the database' })] }, actions: [check, save], outcome: 'skipped' },
  { name: 'preconnect-response-key', preconnect: true, actions: [check, save], outcome: 'saved' },
  { name: 'lrc-enabled', lrc: true, actions: [check, save], outcome: 'saved' },
  { name: 'mixed-existing-and-new', preconnect: true, replies: { [save]: [json({ lcp: 'item already in the database', preconnect_external_domains: { status: 'completed' } })] }, actions: [check, save], outcome: 'saved' },
  { name: 'check-timeout', replies: { [check]: [{ hang: true }] }, timeout: check, actions: [check], outcome: 'failed' },
  { name: 'refresh-timeout', replies: { [check]: [expired], [refresh]: [{ hang: true }] }, timeout: refresh, actions: [check, refresh], outcome: 'failed' },
  { name: 'save-timeout', replies: { [save]: [{ hang: true }] }, timeout: save, actions: [check, save], outcome: 'failed' },
  { name: 'collector-timeout', fonts: true, timeout: 'collector', replies: { [save]: [json({ lcp: { status: 'failed' }, preload_fonts: { status: 'failed' } })] }, actions: [check, save], outcome: 'failed' },
  { name: 'partial-save-timeout', fonts: true, timeout: 'collector-save', replies: { [save]: [{ hang: true }] }, actions: [check, save], outcome: 'failed' }
];
const fields = body => Object.fromEntries([...body.matchAll(/name="([^"]+)"\r\n\r\n([\s\S]*?)(?=\r\n--)/g)].map(match => [match[1], match[2]]));
const waitUntil = async (page, predicate) => {
  const deadline = Date.now() + 3000;
  while (!predicate()) {
    assert.ok(Date.now() < deadline, 'Expected beacon request within the browser fixture deadline');
    await page.waitForTimeout(5);
  }
};
const instrumentation = `
  window.__timers = [];
  const originalSetTimeout = window.setTimeout;
  const originalClearTimeout = window.clearTimeout;
  window.setTimeout = (callback, milliseconds, ...args) => {
    if (milliseconds !== 10000) return originalSetTimeout(callback, milliseconds, ...args);
    const timer = { callback, active: true };
    __timers.push(timer);
    return timer;
  };
  window.clearTimeout = timer => {
    if (timer && __timers.includes(timer)) timer.active = false;
    else originalClearTimeout(timer);
  };
  window.__expireTimer = () => {
    const timer = __timers.find(timer => timer.active);
    if (!timer) throw new Error('No active beacon deadline');
    timer.active = false;
    timer.callback();
  };
`;
let assertions = 0;
let browser;
try {
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
  for (const [variant, script] of Object.entries({ source, minified })) {
    for (const scenario of cases) {
      const context = await browser.newContext({ viewport: { width: 1365, height: 900 } });
      const requests = [];
      const errors = [];
      const unexpected = [];
      const counters = {};
      const config = { nonce: 'cached-nonce', url: origin + '/article', ajax_url: origin + '/admin-ajax.php', is_mobile: false,
        delay: 0, debug: false, elements: 'img', width_threshold: 1, height_threshold: 1,
        preconnect_external_domain_elements: ['img'], preconnect_external_domain_exclusions: [],
        preload_fonts_exclusions: [], external_font_exclusions: [],
        status: { atf: true, lrc: !!scenario.lrc, preload_fonts: !!scenario.fonts, preconnect_external_domain: !!scenario.preconnect } };
      const fontDelay = scenario.fonts ? `window.__fontsWaiting = false; const fontReady = new Promise(resolve => window.__releaseFonts = resolve); Object.defineProperty(document.fonts, 'ready', { get() { window.__fontsWaiting = true; return fontReady; } });` : '';
      const html = `<!doctype html><html><head><meta charset="utf-8"><title>Beacon fixture</title></head><body>
        <img width="100" height="100" alt="Candidate" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'/%3E">
        <script>${instrumentation}${fontDelay}window.noobrocket_beacon_data=${JSON.stringify(config)};</script>
        <script data-name="noobrocket-noobrocket-beacon">${script}</script></body></html>${scenario.cached === false ? '' : '<!--Debug: cached-->'}`;
      await context.route('**/*', async route => {
        const request = route.request();
        if (request.url() === origin + '/article') return route.fulfill({ contentType: 'text/html', body: html });
        if (request.url() !== config.ajax_url) {
          unexpected.push(request.url());
          return route.abort();
        }
        const input = fields(request.postData());
        requests.push({ ...input, headers: request.headers() });
        const index = counters[input.action] || 0;
        counters[input.action] = index + 1;
        const reply = (scenario.replies?.[input.action] || defaults[input.action])?.[index];
        if (!reply) {
          unexpected.push('Unexpected/repeated action: ' + input.action);
          return route.abort();
        }
        if (reply.hang) return;
        if (reply.network) return route.abort('failed');
        return route.fulfill({ status: reply.status || 200, contentType: 'application/json', body: reply.body });
      });
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(origin + '/article');
      // Repeated DOM readiness must never start another manager run.
      await page.evaluate(() => {
        document.dispatchEvent(new Event('DOMContentLoaded'));
        document.dispatchEvent(new Event('DOMContentLoaded'));
      });
      if (scenario.timeout) {
        if (scenario.timeout.startsWith('collector')) {
          await page.waitForFunction(() => window.__fontsWaiting);
        } else {
          await waitUntil(page, () => requests.some(request => request.action === scenario.timeout));
        }
        await page.evaluate(() => window.__expireTimer());
        if (scenario.timeout === 'collector-save') {
          await waitUntil(page, () => requests.some(request => request.action === save));
          await page.evaluate(() => window.__expireTimer());
        }
      }
      await page.waitForFunction(() => document.querySelector('[data-name="noobrocket-noobrocket-beacon"]').getAttribute('beacon-completed') === 'true');
      const outcome = await page.locator('[data-name="noobrocket-noobrocket-beacon"]').getAttribute('beacon-status');
      assert.equal(outcome, scenario.outcome, `${variant}/${scenario.name}: truthful outcome`);
      assert.deepEqual(requests.map(request => request.action), scenario.actions, `${variant}/${scenario.name}: bounded requests`);
      for (const request of requests) {
        if (request.action === refresh) {
          assert.equal(request.noobrocket_beacon_nonce, undefined, 'Refresh does not reuse expired nonce');
        } else {
          const refreshIndex = requests.findIndex(request => request.action === refresh);
          const requestIndex = requests.indexOf(request);
          assert.equal(request.noobrocket_beacon_nonce, refreshIndex >= 0 && requestIndex > refreshIndex ? 'current-nonce' : 'cached-nonce');
          assert.equal(request.url, config.url);
          assert.equal(request.is_mobile, 'false');
        }
        if (request.action === save) {
          assert.equal(request.headers['noobrocket-saas-no-intercept'], 'true');
          assert.equal(request.status, scenario.timeout?.startsWith('collector') ? 'timeout' : 'success');
          assert.ok(Array.isArray(JSON.parse(request.results).lcp));
        }
      }
      const count = requests.length;
      await page.evaluate(() => {
        if (window.__releaseFonts) window.__releaseFonts();
        window.__timers.forEach(timer => timer.callback());
      });
      await page.waitForTimeout(30);
      assert.equal(requests.length, count, `${variant}/${scenario.name}: no timer/late collector follow-up`);
      assert.equal(await page.evaluate(() => window.__timers.filter(timer => timer.active).length), 0, 'Finalization clears the deadline');
      assert.deepEqual(errors, [], `${variant}/${scenario.name}: no unhandled browser error`);
      assert.deepEqual(unexpected, [], `${variant}/${scenario.name}: no unowned request`);
      assertions += 6;
      await context.close();
    }
  }
  console.log(`PASS: beacon source/minified; ${cases.length * 2} browser scenarios, ${assertions}+ assertions; intercepted requests only.`);
} finally {
  if (browser) await browser.close();
}
