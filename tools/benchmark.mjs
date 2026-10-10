#!/usr/bin/env node
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { resolve, dirname, extname } from 'node:path';
import { fileURLToPath } from 'node:url';

const protocol = 'noobrocket-staging-benchmark-v1';
const help = `NoobRocket staging benchmark (Node.js 22+, installed worker dependencies)
Run: node tools/benchmark.mjs run --config staging.json --product NoobRocket --version 1.2.0 --browser /path/chromium --out run.json
Compare: node tools/benchmark.mjs compare --baseline wp-rocket.json --candidate noobrocket.json --out comparison.json
Optional: --dependencies /path/to/local-css/package.json
Runs warm server-cache HTTP requests and fresh/repeat browser visits. It never purges cache, switches plugins, or changes WordPress settings.
Use the same immutable config, Chromium, machine and site snapshot for both products. Output paths must be new.
`;

function argumentsFrom(argv) {
  const result = { command: argv.shift() };
  if (result.command === '--help' || result.command === '-h') return { help: true };
  for (let index = 0; index < argv.length; index += 2) {
    const flag = argv[index];
    if (!/^--[a-z-]+$/.test(flag) || !argv[index + 1] || argv[index + 1].startsWith('--')) throw new Error(`Missing value or invalid argument: ${flag}`);
    const key = flag.slice(2);
    if (!['config', 'product', 'version', 'browser', 'out', 'baseline', 'candidate', 'dependencies'].includes(key) || result[key] !== undefined) throw new Error(`Unknown or duplicate argument: ${flag}`);
    result[key] = argv[index + 1];
  }
  if (!['run', 'compare'].includes(result.command)) throw new Error('Choose run or compare; --help lists arguments.');
  for (const name of result.command === 'run' ? ['config', 'product', 'version', 'browser', 'out'] : ['baseline', 'candidate', 'out']) if (!result[name]) throw new Error(`--${name} is required.`);
  return result;
}

export function validateConfig(input) {
  assert.ok(input.staging === true || input.observationOnly === true || input.readOnly === true, 'Declare staging: true, readOnly: true, or observationOnly: true.');
  const origin = new URL(input.baseUrl);
  assert.ok(['http:', 'https:'].includes(origin.protocol) && !origin.username && !origin.password && origin.pathname === '/' && !origin.search && !origin.hash, 'baseUrl must be an HTTP(S) origin.');
  assert.ok(input.environment && ['siteSnapshot', 'server', 'php', 'wordpress', 'theme'].every((key) => typeof input.environment[key] === 'string' && input.environment[key].trim()), 'Declare siteSnapshot, server, php, wordpress and theme in environment.');
  assert.ok(input.optimizationProfile && typeof input.optimizationProfile === 'object' && !Array.isArray(input.optimizationProfile), 'Declare the common optimizationProfile (equivalent settings and CSS-ready state).');
  assert.ok(Array.isArray(input.pages) && input.pages.length > 0 && input.pages.length <= 50, 'Provide 1–50 pages.');
  const names = new Set();
  for (const page of input.pages) {
    assert.ok(typeof page.name === 'string' && page.name.trim() && !names.has(page.name), 'Page names must be unique nonempty strings.');
    names.add(page.name);
    assert.ok(typeof page.path === 'string' && page.path.startsWith('/') && !page.path.startsWith('//'), 'Page paths must stay on baseUrl.');
    const url = new URL(page.path, origin);
    assert.ok(url.origin === origin.origin && !url.hash, 'Pages must be same-origin canonical URLs without fragments.');
    for (const step of page.interactions || []) {
      assert.notEqual(input.observationOnly, true, 'Public observations cannot perform interactions.');
      assert.equal(input.staging, true, 'Interactions require disposable staging data; public runs must stay read-only.');
      assert.ok(['click', 'hover', 'press'].includes(step.action) && typeof step.selector === 'string' && step.selector, 'Interactions support click, hover and press with a selector.');
      if (step.action === 'press') assert.ok(typeof step.key === 'string' && step.key, 'A press interaction requires key.');
    }
    for (const expectation of page.expectations || []) {
      assert.ok(typeof expectation.selector === 'string' && expectation.selector && typeof expectation.visible === 'boolean', 'Each expectation requires selector and visible.');
      if (expectation.text !== undefined) assert.equal(typeof expectation.text, 'string');
    }
  }
  const repetitions = input.repetitions ?? 5;
  const settleMs = input.settleMs ?? 2000;
  const timeoutMs = input.timeoutMs ?? 30000;
  assert.ok(Number.isSafeInteger(repetitions) && repetitions >= 3 && repetitions <= 30, 'repetitions must be 3–30.');
  assert.ok(Number.isSafeInteger(settleMs) && settleMs >= 100 && settleMs <= 10000, 'settleMs must be 100–10000.');
  assert.ok(Number.isSafeInteger(timeoutMs) && timeoutMs >= 1000 && timeoutMs <= 120000, 'timeoutMs must be 1000–120000.');
  const viewports = input.viewports ?? [{ name: 'desktop', width: 1365, height: 900, mobile: false }, { name: 'mobile', width: 390, height: 844, mobile: true }];
  const viewportNames = new Set();
  assert.ok(Array.isArray(viewports) && viewports.length >= 1 && viewports.length <= 5, 'Provide 1–5 viewports.');
  for (const viewport of viewports) {
    assert.ok(typeof viewport.name === 'string' && !viewportNames.has(viewport.name), 'Viewport names must be unique.');
    viewportNames.add(viewport.name);
    assert.ok(['width', 'height'].every((key) => Number.isSafeInteger(viewport[key]) && viewport[key] >= 200 && viewport[key] <= 3840) && typeof viewport.mobile === 'boolean', 'Viewports require bounded dimensions and mobile boolean.');
  }
  const cacheEvidence = input.cacheEvidence;
  assert.ok(input.observationOnly === true && !cacheEvidence || cacheEvidence && /^[a-z0-9-]+$/i.test(cacheEvidence.header) && typeof cacheEvidence.includes === 'string' && cacheEvidence.includes.trim(), 'cacheEvidence requires an instrumented cache-hit header and expected substring. Public observations may omit it and cannot prove cache hits.');
  return { ...input, baseUrl: origin.origin, repetitions, settleMs, timeoutMs, viewports, cacheEvidence: cacheEvidence ? { header: cacheEvidence.header.toLowerCase(), includes: cacheEvidence.includes } : null };
}

export function httpSample(url, { timeoutMs, cacheEvidence, userAgent }) {
  const started = performance.now();
  return new Promise((resolveResult, reject) => {
    const request = (url.startsWith('https:') ? httpsRequest : httpRequest)(url, { headers: { 'Accept-Encoding': 'gzip', 'User-Agent': userAgent, 'Accept': 'text/html' }, agent: false }, (response) => {
      const headersMs = performance.now() - started;
      let ttfbMs = null;
      let bytes = 0;
      response.on('data', (chunk) => { if (ttfbMs === null) ttfbMs = performance.now() - started; bytes += chunk.length; });
      response.on('error', reject);
      response.on('end', () => {
        if (response.statusCode !== 200) return reject(new Error(`Expected canonical HTTP 200; got ${response.statusCode} at ${url}.`));
        const value = cacheEvidence ? response.headers[cacheEvidence.header] ?? '' : null;
        if (cacheEvidence && !String(value).includes(cacheEvidence.includes)) return reject(new Error(`Cache hit unproven at ${url}: missing ${cacheEvidence.header}: ${cacheEvidence.includes}. Instrument both products on staging.`));
        resolveResult({ ttfbMs: ttfbMs ?? headersMs, headersMs, durationMs: performance.now() - started, transferredBodyBytes: bytes, cacheEvidence: value, cacheHitVerified: Boolean(cacheEvidence), status: response.statusCode });
      });
    });
    request.setTimeout(timeoutMs, () => request.destroy(new Error('HTTP benchmark timed out.')));
    request.on('error', reject);
    request.end();
  });
}

async function browserVisit(page, url, config, spec, phase) {
  const session = await page.context().newCDPSession(page);
  await session.send('Network.enable');
  const stylesheetRequests = new Set();
  let cssTransferredBytes = 0;
  const errors = [];
  const failure = (request) => errors.push(`${request.method()} ${request.url()}: ${request.failure()?.errorText}`);
  const consoleError = (message) => { if (message.type() === 'error') errors.push(message.text()); };
  const pageError = (error) => errors.push(`Page JavaScript: ${error.message}`);
  page.on('requestfailed', failure);
  page.on('console', consoleError);
  page.on('pageerror', pageError);
  session.on('Network.responseReceived', ({ requestId, type }) => { if (type === 'Stylesheet') stylesheetRequests.add(requestId); });
  session.on('Network.loadingFinished', ({ requestId, encodedDataLength }) => { if (stylesheetRequests.has(requestId)) cssTransferredBytes += encodedDataLength; });
  try {
    const response = await page.goto(url, { waitUntil: 'load', timeout: config.timeoutMs });
    assert.ok(response && response.status() === 200 && page.url() === url, `Canonical browser HTTP 200 required: ${url}`);
    if (config.cacheEvidence) assert.ok((await response.headerValue(config.cacheEvidence.header) || '').includes(config.cacheEvidence.includes), `Browser cache hit unproven at ${url}.`);
    await page.waitForTimeout(config.settleMs);
    const initial = await page.evaluate(() => {
      const nav = performance.getEntriesByType('navigation')[0];
      return { ttfbMs: nav.responseStart - nav.requestStart, loadMs: nav.loadEventEnd - nav.startTime, lcpMs: window.__noobrocketBenchmark.lcp, cls: window.__noobrocketBenchmark.cls, inlineCssBytes: [...document.querySelectorAll('style')].reduce((total, node) => total + new TextEncoder().encode(node.textContent).length, 0) };
    });
    for (const step of spec.interactions || []) {
      const target = page.locator(step.selector);
      if (step.action === 'press') await target.press(step.key, { timeout: config.timeoutMs });
      else await target[step.action]({ timeout: config.timeoutMs });
      await page.waitForTimeout(100);
    }
    for (const expected of spec.expectations || []) {
      await page.locator(expected.selector).waitFor({ state: expected.visible ? 'visible' : 'hidden', timeout: config.timeoutMs });
      if (expected.text !== undefined) assert.ok((await page.locator(expected.selector).textContent()).includes(expected.text), `Expected text for ${expected.selector}.`);
    }
    const interaction = await page.evaluate(() => ({ clsAfterInteractions: window.__noobrocketBenchmark.cls, scriptedInteractionLatencyMs: window.__noobrocketBenchmark.interactionLatency }));
    if (!config.observationOnly) assert.equal(errors.length, 0, `Browser errors: ${errors.slice(0, 5).join('; ')}`);
    return { phase, ...initial, ...interaction, cssTransferredBytes, stylesheetsRequested: stylesheetRequests.size, errors, cacheHitVerified: Boolean(config.cacheEvidence) };
  } finally {
    page.off('requestfailed', failure);
    page.off('console', consoleError);
    page.off('pageerror', pageError);
    await session.detach();
  }
}

export function summarize(samples) {
  const groups = new Map();
  for (const sample of samples) {
    const key = `${sample.page} / ${sample.viewport} / ${sample.phase}`;
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(sample);
  }
  return [...groups].map(([group, entries]) => {
    const metrics = {};
    for (const metric of ['ttfbMs', 'lcpMs', 'cls', 'cssTransferredBytes', 'inlineCssBytes', 'scriptedInteractionLatencyMs']) {
      const values = entries.map((entry) => entry[metric]).filter((value) => Number.isFinite(value)).sort((a, b) => a - b);
      if (!values.length) continue;
      const percentile = (p) => values[Math.max(0, Math.ceil(values.length * p) - 1)];
      const middle = Math.floor(values.length / 2);
      metrics[metric] = { count: values.length, median: values.length % 2 ? values[middle] : (values[middle - 1] + values[middle]) / 2, p75: percentile(0.75), p95: percentile(0.95), min: values[0], max: values.at(-1) };
    }
    return { group, metrics };
  });
}

export async function runBenchmark(config, options) {
  const require = createRequire(resolve(options.dependencies || fileURLToPath(new URL('../noobrocket/local-css/package.json', import.meta.url))));
  const { chromium } = require('playwright-core');
  const browser = await chromium.launch({ executablePath: options.browser, headless: true, chromiumSandbox: process.platform !== 'win32' });
  const result = { protocol, product: options.product, version: options.version, started: new Date().toISOString(), config, runtime: { platform: process.platform, arch: process.arch, node: process.versions.node }, browserVersion: browser.version(), samples: [] };
  try {
    for (const viewport of config.viewports) {
      const mobile = viewport.mobile;
      const userAgent = mobile ? 'Mozilla/5.0 (Linux; Android 13; Mobile) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36' : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
      for (const spec of config.pages) {
        const url = new URL(spec.path, config.baseUrl).href;
        // Warm only. The operator clears/switches server caches before each product run.
        // The first request is allowed to populate cache; every measured request must prove a hit.
        await fetch(url, { headers: { 'User-Agent': userAgent }, redirect: 'error', signal: AbortSignal.timeout(config.timeoutMs) }).then(async (response) => { if (response.status !== 200) throw new Error(`Warmup HTTP ${response.status}: ${url}`); await response.arrayBuffer(); });
        for (let iteration = 1; iteration <= config.repetitions; iteration++) {
          result.samples.push({ page: spec.name, viewport: viewport.name, phase: config.cacheEvidence ? 'http-server-cache-hit' : 'http-warm-unverified', iteration, ...await httpSample(url, { ...config, userAgent }) });
          const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height }, isMobile: mobile, hasTouch: mobile, userAgent, serviceWorkers: 'block' });
          try {
            await context.addInitScript(() => {
              window.__noobrocketBenchmark = { lcp: null, cls: 0, interactionLatency: null };
              let windowStart = 0;
              let lastShift = 0;
              let windowScore = 0;
              new PerformanceObserver((list) => { for (const entry of list.getEntries()) window.__noobrocketBenchmark.lcp = entry.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
              new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                  if (entry.hadRecentInput) continue;
                  if (entry.startTime - lastShift > 1000 || entry.startTime - windowStart > 5000) { windowStart = entry.startTime; windowScore = 0; }
                  lastShift = entry.startTime;
                  windowScore += entry.value;
                  window.__noobrocketBenchmark.cls = Math.max(window.__noobrocketBenchmark.cls, windowScore);
                }
              }).observe({ type: 'layout-shift', buffered: true });
              new PerformanceObserver((list) => { for (const entry of list.getEntries()) if (entry.interactionId) window.__noobrocketBenchmark.interactionLatency = Math.max(window.__noobrocketBenchmark.interactionLatency || 0, entry.duration); }).observe({ type: 'event', buffered: true, durationThreshold: 16 });
            });
            const page = await context.newPage();
            for (const phase of ['fresh-browser', 'repeat-browser']) result.samples.push({ page: spec.name, viewport: viewport.name, iteration, ...await browserVisit(page, url, config, spec, phase) });
          } finally { await context.close(); }
        }
      }
    }
  } finally { await browser.close(); }
  result.finished = new Date().toISOString();
  result.summary = summarize(result.samples);
  return result;
}

export function compareRuns(baseline, candidate) {
  assert.equal(baseline.protocol, protocol, 'Unknown baseline protocol.');
  assert.equal(candidate.protocol, protocol, 'Benchmark protocols differ.');
  assert.ok(!baseline.config.observationOnly && !candidate.config.observationOnly && baseline.config.cacheEvidence && candidate.config.cacheEvidence, 'Public observations cannot establish a controlled cache-hit comparison.');
  assert.deepEqual(candidate.config, baseline.config, 'Config differs: use identical URLs, snapshot, settings, interactions, viewports and repetitions.');
  assert.deepEqual(candidate.runtime, baseline.runtime, 'Runtime differs: benchmark on the same machine and Node runtime.');
  assert.equal(candidate.browserVersion, baseline.browserVersion, 'Chromium versions differ.');
  const before = summarize(baseline.samples);
  const after = summarize(candidate.samples);
  assert.deepEqual(after.map((entry) => entry.group), before.map((entry) => entry.group), 'Measured groups differ.');
  assert.equal(candidate.samples.length, baseline.samples.length, 'Sample counts differ.');
  const differences = before.map((entry, index) => {
    const metrics = {};
    assert.deepEqual(Object.keys(after[index].metrics), Object.keys(entry.metrics), 'Measured metrics differ.');
    for (const [name, stats] of Object.entries(entry.metrics)) {
      const measured = after[index].metrics[name];
      assert.equal(measured.count, stats.count, 'Metric sample counts differ.');
      metrics[name] = { baselineMedian: stats.median, candidateMedian: measured.median, difference: measured.median - stats.median, percent: stats.median ? 100 * (measured.median - stats.median) / stats.median : null };
    }
    return { group: entry.group, metrics };
  });
  return { protocol, baseline: { product: baseline.product, version: baseline.version, started: baseline.started }, candidate: { product: candidate.product, version: candidate.version, started: candidate.started }, differences, interpretation: 'Observed lab medians only. Negative differences are lower. No statistical significance or general product superiority is established. Scripted interaction latency is not field INP. Server CPU is measured separately on the host.' };
}

function markdown(result) {
  const rows = result.summary || result.differences;
  const display = (value) => value === null || value === undefined ? '—' : Number(value.toFixed(3));
  const lines = [`# ${result.summary ? result.product + ' ' + result.version : 'Controlled comparison'}`, '', result.config?.observationOnly ? 'Read-only public observation. Warm requests do not prove a cache hit. Fresh/repeat labels describe browser contexts; the server may forbid browser caching. This run cannot establish a controlled product comparison.' : 'Server cache is warmed before measurement; fresh/repeat labels describe browser cache only.', '', 'CSS bytes are Chromium network transfer, including response overhead. Lab interaction latency is not field INP. CPU requires separate same-host measurement.', '', '| Page / viewport / phase | TTFB ms | LCP ms | CLS | External CSS bytes |', '| --- | ---: | ---: | ---: | ---: |'];
  for (const row of rows) {
    const value = (name) => {
      const item = row.metrics[name];
      if (result.summary) return display(item?.median);
      if (!item) return '—';
      const change = name === 'cls' ? `Δ ${display(item.difference)}` : `${display(item.percent)}%`;
      return `${display(item.baselineMedian)} → ${display(item.candidateMedian)} (${change})`;
    };
    lines.push(`| ${row.group.replaceAll('|', '\\|')} | ${value('ttfbMs')} | ${value('lcpMs')} | ${value('cls')} | ${value('cssTransferredBytes')} |`);
  }
  lines.push('', result.interpretation || 'These measurements describe this site and test machine. They do not establish superiority across WordPress sites.', '');
  return lines.join('\n');
}

async function save(result, path) {
  const output = resolve(path);
  await mkdir(dirname(output), { recursive: true });
  await writeFile(output, JSON.stringify(result, null, 2) + '\n', { flag: 'wx' });
  await writeFile(output.slice(0, -extname(output).length || undefined) + '.md', markdown(result), { flag: 'wx' });
}

async function main() {
  const options = argumentsFrom(process.argv.slice(2));
  if (options.help) { process.stdout.write(help); return; }
  if (Number(process.versions.node.split('.')[0]) < 22) throw new Error('Node.js 22 or later required.');
  const result = options.command === 'run' ? await runBenchmark(validateConfig(JSON.parse(await readFile(options.config, 'utf8'))), options) : compareRuns(JSON.parse(await readFile(options.baseline, 'utf8')), JSON.parse(await readFile(options.candidate, 'utf8')));
  await save(result, options.out);
  process.stdout.write(`${options.command === 'run' ? result.samples.length + ' samples' : result.differences.length + ' compared groups'}; results: ${resolve(options.out)}\n`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) main().catch((error) => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
