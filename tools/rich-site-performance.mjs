#!/usr/bin/env node
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { mkdir, readFile, realpath, writeFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { createServer as createHttpServer } from 'node:http';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import lighthouse, { Audit, Gatherer, generateReport } from 'lighthouse';

const [nodeMajor, nodeMinor] = process.versions.node.split('.').map(Number);
assert(nodeMajor > 22 || (nodeMajor === 22 && nodeMinor >= 19), 'Lighthouse requires Node 22.19 or later.');
const options = {};
for (let i = 2; i < process.argv.length; i += 2) {
  const key = process.argv[i].slice(2);
  assert(['wordpress', 'php', 'browser', 'out'].includes(key) && process.argv[i + 1] && !options[key], 'Expected --wordpress --php --browser --out.');
  options[key] = process.argv[i + 1];
}
for (const key of ['wordpress', 'php', 'browser', 'out']) assert(options[key], `--${key} required.`);
const wordpress = await realpath(options.wordpress);
assert.equal(process.env.NOOBROCKET_LINUX_PROOF, wordpress, 'Owned server rehearsal required.');
await readFile(join(wordpress, '.noobrocket-acceptance-site'));
const contractPath = join(wordpress, 'wp-content/cache-cache-rich-site/contract.json');
const initial = JSON.parse(await readFile(contractPath, 'utf8'));
assert.equal(initial.protocol, 'cache-cache-rich-site-v1');
assert.equal(initial.origin, 'http://127.0.0.1:8875');
const pages = initial.pages.filter((page) => page.performance);
assert.equal(pages.length, 3, 'Three representative public pages required.');
for (const page of pages) assert.equal(new URL(page.path, initial.origin).origin, initial.origin);
const output = resolve(options.out);
await mkdir(output, { recursive: false });
const require = createRequire(fileURLToPath(new URL('../noobrocket/local-css/package.json', import.meta.url)));
const { chromium } = require('playwright-core');
let measuredUrl;

// The browser proxy denies other origins; exact-origin requests bypass it.
// This observer leaves normal HTTP caching and request handling intact.
class OwnedNetwork extends Gatherer {
  meta = { supportedModes: ['navigation'] };
  attempts = [];
  main = null;
  async startInstrumentation({ driver }) {
    this.session = driver.defaultSession;
    this.onRequest = ({ request }) => {
      const url = new URL(request.url);
      if (['http:', 'https:', 'ws:', 'wss:'].includes(url.protocol) && url.origin !== initial.origin) {
        this.attempts.push({ origin: url.origin, path: url.pathname });
      }
    };
    this.onResponse = ({ type, response }) => {
      if (type === 'Document' && response.url === measuredUrl) {
        const headers = Object.fromEntries(Object.entries(response.headers).map(([key, value]) => [key.toLowerCase(), String(value)]));
        this.main = { url: response.url, status: response.status, cacheStatus: headers['cache-status'] || '', staticStatus: headers['x-noobrocket-static'] || '' };
      }
    };
    this.session.on('Network.requestWillBeSent', this.onRequest);
    this.session.on('Network.responseReceived', this.onResponse);
  }
  stopInstrumentation() {
    this.session.off('Network.requestWillBeSent', this.onRequest);
    this.session.off('Network.responseReceived', this.onResponse);
  }
  getArtifact() { return { attempts: this.attempts, main: this.main }; }
}
class OwnedNetworkAudit extends Audit {
  static get meta() {
    return { id: 'owned-network', title: 'Uses only the owned disposable origin and a proven page-cache hit', failureTitle: 'Disposable network/cache boundary failed', description: 'A denying browser proxy blocks other origins; the exact owned origin bypasses it. Warm server-cache evidence is recorded from the measured document.', requiredArtifacts: ['OwnedNetwork'] };
  }
  static audit(artifacts) {
    const evidence = artifacts.OwnedNetwork;
    const hit = evidence.main?.status === 200 && (/\bhit\b/i.test(evidence.main.cacheStatus) || evidence.main.staticStatus.toLowerCase() === 'hit');
    return { score: Number(evidence.attempts.length === 0 && hit), details: { type: 'debugdata', ...evidence } };
  }
}

function native(phase) {
  const script = fileURLToPath(new URL('../tests/wordpress-rich-site.php', import.meta.url));
  const result = spawnSync(options.php, [script, wordpress, phase], { encoding: 'utf8', timeout: 60000, windowsHide: true });
  assert.equal(result.status, 0, `${phase}: ${result.stderr.slice(-2000) || result.stdout.slice(-2000)}`);
  return result.stdout.trim();
}
async function sampleHttp(url) {
  const start = performance.now();
  const response = await fetch(url, { redirect: 'error', signal: AbortSignal.timeout(15000) });
  const headersReceivedMs = performance.now() - start;
  const bytes = (await response.arrayBuffer()).byteLength;
  assert.equal(response.status, 200);
  return { status: response.status, headersReceivedMs, totalMs: performance.now() - start, bytes, cacheStatus: response.headers.get('cache-status'), staticStatus: response.headers.get('x-noobrocket-static') };
}
async function unusedPort() {
  const server = createServer();
  await new Promise((done, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', done); });
  const port = server.address().port;
  await new Promise((done) => server.close(done));
  return port;
}
async function createDenyingProxy() {
  const requests = [];
  const server = createHttpServer((request, response) => {
    requests.push({ kind: 'http', target: request.url });
    response.writeHead(403, { 'Content-Length': '0', Connection: 'close' });
    response.end();
  });
  const denySocket = (kind, request, socket) => {
    requests.push({ kind, target: request.url });
    socket.end('HTTP/1.1 403 Forbidden\r\nContent-Length: 0\r\nConnection: close\r\n\r\n');
  };
  server.on('connect', (request, socket) => denySocket('connect', request, socket));
  server.on('upgrade', (request, socket) => denySocket('upgrade', request, socket));
  await new Promise((done, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', done); });
  return { server, port: server.address().port, requests };
}
async function proveNetworkBoundary(browser, proxy) {
  let forbiddenHits = 0;
  const listener = createHttpServer((request, response) => { forbiddenHits++; response.end('UNEXPECTED'); });
  await new Promise((done, reject) => { listener.once('error', reject); listener.listen(0, '127.0.0.1', done); });
  try {
    const forbidden = [
      { url: `http://127.0.0.1:${listener.address().port}/blocked`, kind: 'http' },
      { url: 'http://127.0.0.1/blocked', kind: 'http' },
      { url: 'http://cache-cache-egress.invalid/blocked', kind: 'http' },
      { url: 'https://cache-cache-egress.invalid/blocked', kind: 'connect', target: 'cache-cache-egress.invalid:443' },
    ];
    for (const probe of forbidden) {
      const page = await browser.newPage();
      const before = proxy.requests.length;
      try {
        if (probe.kind === 'connect') await assert.rejects(page.goto(probe.url, { timeout: 10000 }), /ERR_TUNNEL_CONNECTION_FAILED/);
        else {
          let response;
          try { response = await page.goto(probe.url, { waitUntil: 'domcontentloaded', timeout: 10000 }); }
          catch (error) { assert.match(error.message, /ERR_HTTP_RESPONSE_CODE_FAILURE/); }
          if (response) assert.equal(response.status(), 403);
        }
        assert(proxy.requests.slice(before).some((request) => request.kind === probe.kind && request.target === (probe.target || probe.url)), 'The denying proxy must receive each explicit/default-port probe.');
      } finally { await page.close(); }
    }
    assert.equal(forbiddenHits, 0, 'Owned alternate-origin listener must receive no request.');
    const page = await browser.newPage();
    try {
      const before = proxy.requests.length;
      const allowed = await page.goto(initial.origin, { waitUntil: 'load', timeout: 15000 });
      assert.equal(allowed.status(), 200, 'Exact owned origin must remain allowed.');
      assert(!proxy.requests.slice(before).some((request) => request.kind !== 'connect' && new URL(request.target, initial.origin).origin === initial.origin), 'Owned origin/assets must bypass the denying proxy.');
      return { boundary: 'denying HTTP proxy; exact origin bypass', blockedProbes: forbidden.length, forbiddenListenerHits: forbiddenHits, ownedOriginStatus: allowed.status() };
    } finally { await page.close(); }
  } finally {
    await new Promise((done) => listener.close(done));
  }
}
function distribution(values) {
  values = values.toSorted((a, b) => a - b);
  const middle = Math.floor(values.length / 2);
  return { count: values.length, median: values.length % 2 ? values[middle] : (values[middle - 1] + values[middle]) / 2, min: values[0], max: values.at(-1) };
}
const report = {
  protocol: 'cache-cache-rich-lighthouse-v1', started: new Date().toISOString(),
  environment: initial.environment, node: process.version, samples: [],
  limits: [
    'Within-plugin baseline/optimized profiles intentionally differ. This is not a matched WP Rocket comparison or field Core Web Vitals.',
    'Three alternating samples per profile/page/device describe this runner only; no statistical significance or universal speed claim.',
    'Lighthouse simulated desktop/mobile throttling, cold browser storage, proven warm server cache. Separate HTTP samples record observed states, including refills.',
    'Denying browser proxy with exact owned origin bypass; normal HTTP caching, no request interception, Chromium sandbox enabled. Browser background requests are denied separately from the measured-page audit. All content/assets are synthetic and local.',
    'Node fetch headersReceivedMs includes connection setup; it is not Lighthouse navigation TTFB.',
  ],
};
let proxy;
try {
  proxy = await createDenyingProxy();
  for (let iteration = 1; iteration <= 3; iteration++) {
    const profiles = iteration === 2 ? ['optimized', 'baseline'] : ['baseline', 'optimized'];
    for (const profile of profiles) {
      native(`profile-${profile}`);
      const current = JSON.parse(await readFile(contractPath, 'utf8'));
      assert.equal(current.environment.content_sha256, initial.environment.content_sha256, 'Content changed between profiles.');
      assert.equal(current.profile.name, profile);
      for (const spec of pages) {
        const url = new URL(spec.path, initial.origin).href;
        native('purge');
        const first = await sampleHttp(url);
        const second = await sampleHttp(url);
        assert(/\bhit\b/i.test(second.cacheStatus || '') || second.staticStatus?.toLowerCase() === 'hit', 'Second HTTP response must prove a cache hit.');
        for (const device of ['desktop', 'mobile']) {
          const port = await unusedPort();
          const browser = await chromium.launch({ executablePath: options.browser, headless: true, chromiumSandbox: process.platform === 'linux', args: [`--remote-debugging-port=${port}`, '--enable-automation', `--proxy-server=http://127.0.0.1:${proxy.port}`, '--proxy-bypass-list=<-loopback>;http://127.0.0.1:8875'] });
          try {
            const session = await browser.newBrowserCDPSession();
            const { arguments: args } = await session.send('Browser.getBrowserCommandLine');
            assert.equal(process.platform, 'linux');
            assert(!args.includes('--no-sandbox') && !args.includes('--disable-setuid-sandbox'), 'Chromium sandbox required.');
            if (!report.networkBoundary) report.networkBoundary = await proveNetworkBoundary(browser, proxy);
            await browser.newPage();
            report.browser = browser.version();
            const config = {
              extends: 'lighthouse:default',
              settings: { disableFullPageScreenshot: true, skipAudits: ['screenshot-thumbnails', 'final-screenshot'] },
              artifacts: [{ id: 'OwnedNetwork', gatherer: { implementation: OwnedNetwork } }],
              audits: [{ implementation: OwnedNetworkAudit }],
              categories: { owned: { title: 'Disposable ownership', auditRefs: [{ id: 'owned-network', weight: 1 }] } },
            };
            if (device === 'desktop') Object.assign(config.settings, { formFactor: 'desktop', screenEmulation: { mobile: false, width: 1365, height: 900, deviceScaleFactor: 1, disabled: false }, throttling: { rttMs: 40, throughputKbps: 10240, cpuSlowdownMultiplier: 1, requestLatencyMs: 0, downloadThroughputKbps: 0, uploadThroughputKbps: 0 } });
            measuredUrl = url;
            const result = await lighthouse(url, { port, logLevel: 'error', output: ['json', 'html'], onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo', 'owned'], enableErrorReporting: false }, config);
            assert(result?.lhr && !result.lhr.runtimeError, result?.lhr.runtimeError?.message || 'Lighthouse report missing.');
            delete result.lhr.fullPageScreenshot;
            delete result.lhr.audits['screenshot-thumbnails'];
            delete result.lhr.audits['final-screenshot'];
            const name = `${profile}-${spec.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${device}-${iteration}`;
            await writeFile(join(output, `${name}.json`), JSON.stringify(result.lhr, null, 2) + '\n', { flag: 'wx' });
            await writeFile(join(output, `${name}.html`), generateReport(result.lhr, 'html'), { flag: 'wx' });
            assert.equal(result.lhr.audits['owned-network'].score, 1, 'Measured document must pass the owned network/cache boundary.');
            const scores = Object.fromEntries(['performance', 'accessibility', 'best-practices', 'seo'].map((key) => [key, result.lhr.categories[key].score]));
            assert(Object.values(scores).every(Number.isFinite), 'All requested category scores required.');
            const metrics = Object.fromEntries(['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index'].map((key) => [key, result.lhr.audits[key].numericValue]));
            assert(Object.values(metrics).every(Number.isFinite), 'Numeric lab metrics required.');
            report.samples.push({ profile, page: spec.name, device, iteration, scores, metrics, settings: current.profile, firstHttp: first, secondHttp: second, measuredCache: result.lhr.audits['owned-network'].details.main, lighthouseVersion: result.lhr.lighthouseVersion, settingsUsed: result.lhr.configSettings, report: `${name}.html` });
            process.stdout.write(`PASS Lighthouse ${name}; performance ${Math.round(scores.performance * 100)}\n`);
          } finally { await browser.close(); }
        }
      }
    }
  }
  assert.equal(report.samples.length, 36);
  report.groups = [];
  for (const profile of ['baseline', 'optimized']) for (const page of pages) for (const device of ['desktop', 'mobile']) {
    const samples = report.samples.filter((sample) => sample.profile === profile && sample.page === page.name && sample.device === device);
    report.groups.push({ profile, page: page.name, device, scores: Object.fromEntries(Object.keys(samples[0].scores).map((key) => [key, distribution(samples.map((sample) => sample.scores[key]))])), metrics: Object.fromEntries(Object.keys(samples[0].metrics).map((key) => [key, distribution(samples.map((sample) => sample.metrics[key]))])) });
  }
  report.status = 'PASS';
} catch (error) {
  report.status = 'FAIL'; report.error = String(error); throw error;
} finally {
  if (proxy) await new Promise((done) => proxy.server.close(done));
  report.finished = new Date().toISOString();
  await writeFile(join(output, 'summary.json'), JSON.stringify(report, null, 2) + '\n', { flag: 'wx' });
  const rows = report.groups?.map((group) => `| ${group.profile} | ${group.page} | ${group.device} | ${Math.round(group.scores.performance.median * 100)} | ${Math.round(group.metrics['largest-contentful-paint'].median)} | ${group.metrics['cumulative-layout-shift'].median.toFixed(3)} |`) || [];
  await writeFile(join(output, 'summary.md'), `# Disposable WordPress Lighthouse experiment\n\nStatus: ${report.status}. ${report.samples.length}/36 measurements completed.\n\n| Profile | Page | Device | Median performance | Median LCP (ms) | Median CLS |\n| --- | --- | --- | ---: | ---: | ---: |\n${rows.join('\n')}\n\n${report.limits.join('\n\n')}\n`, { flag: 'wx' });
}
