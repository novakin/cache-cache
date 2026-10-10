import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn } from 'node:child_process';
import { validateConfig, httpSample, runBenchmark, compareRuns, summarize } from '../tools/benchmark.mjs';

const browser = process.argv[2];
const dependencies = process.argv[3] ? resolve(process.argv[3]) : undefined;
if (!browser) throw new Error('Provide Chromium executable; optional next argument selects worker package.json.');
let checks = 0;
const check = (value, label) => { assert.ok(value, label); checks++; };
const server = createServer((request, response) => {
  if (request.url === '/redirect') { response.writeHead(302, { Location: '/' }); response.end(); return; }
  if (request.url === '/unproven') { response.writeHead(200); response.end('No cache evidence'); return; }
  response.setHeader('Cache-Status', 'Fixture; hit');
  if (request.url === '/style.css') {
    response.setHeader('Content-Type', 'text/css');
    response.setHeader('Cache-Control', 'public,max-age=300');
    response.end('body {font-family:sans-serif} .menu {display:none} .menu.open {display:block}');
    return;
  }
  response.setHeader('Content-Type', 'text/html');
  response.end('<!doctype html><html><head><link rel="stylesheet" href="/style.css"><title>Benchmark fixture</title><style>h1 {color:navy}</style></head><body><h1>Staging benchmark</h1><button id="open" onclick="document.querySelector(\'.menu\').classList.add(\'open\')">Menu</button><nav class="menu">Navigation</nav></body></html>');
});
await new Promise((resolveResult) => server.listen(0, '127.0.0.1', resolveResult));
const origin = `http://127.0.0.1:${server.address().port}`;
const fixture = { staging: true, baseUrl: origin, environment: { siteSnapshot: 'synthetic-fixture', server: 'node-loopback', php: 'none', wordpress: 'none', theme: 'fixture' }, optimizationProfile: { fixture: true }, cacheEvidence: { header: 'Cache-Status', includes: 'hit' }, repetitions: 3, settleMs: 500, timeoutMs: 5000, viewports: [{ name: 'desktop', width: 1365, height: 900, mobile: false }, { name: 'mobile', width: 390, height: 844, mobile: true }], pages: [{ name: 'interactive fixture', path: '/', interactions: [{ action: 'click', selector: '#open' }], expectations: [{ selector: '.menu', visible: true, text: 'Navigation' }] }] };
const temporary = await mkdtemp(join(tmpdir(), 'noobrocket-benchmark-'));
try {
  const config = validateConfig(fixture);
  check(config.cacheEvidence.header === 'cache-status', 'header normalization is deterministic');
  const observational = validateConfig({ ...fixture, staging: false, observationOnly: true, cacheEvidence: undefined, pages: [{ name: 'public', path: '/' }] });
  check(observational.cacheEvidence === null, 'public observation explicitly records unproven cache hits');
  assert.throws(() => validateConfig({ ...fixture, observationOnly: true })); checks++;
  for (const change of [{ staging: false }, { baseUrl: origin + '/path' }, { repetitions: 1 }, { cacheEvidence: null }, { pages: [{ name: 'foreign', path: '//outside.test/' }] }, { viewports: [{ name: 'invalid', width: 0, height: 900, mobile: false }] }]) {
    assert.throws(() => validateConfig({ ...fixture, ...change })); checks++;
  }
  const http = await httpSample(origin + '/', { ...config, userAgent: 'proof' });
  check(http.ttfbMs >= 0 && http.transferredBodyBytes > 0 && http.cacheEvidence.includes('hit'), 'HTTP TTFB measures a proven cache hit');
  await assert.rejects(httpSample(origin + '/unproven', { ...config, userAgent: 'proof' }), /Cache hit unproven/); checks++;
  await assert.rejects(httpSample(origin + '/redirect', { ...config, userAgent: 'proof' }), /canonical HTTP 200/); checks++;
  const run = await runBenchmark(config, { browser, dependencies, product: 'Synthetic fixture', version: '1' });
  check(run.samples.length === 18, 'both viewports include three HTTP/fresh/repeat triplets');
  check(run.samples.every((sample) => sample.ttfbMs >= 0), 'all navigation and HTTP timings recorded');
  check(run.samples.filter((sample) => sample.phase === 'fresh-browser').every((sample) => sample.cssTransferredBytes > 0 && sample.inlineCssBytes > 0), 'external and inline CSS measured separately');
  check(run.samples.filter((sample) => sample.phase === 'repeat-browser').every((sample) => sample.cssTransferredBytes === 0), 'repeat visits use browser CSS cache');
  const badPaint = run.samples.filter((sample) => sample.phase !== 'http-server-cache-hit' && !(sample.lcpMs > 0 && sample.cls >= 0));
  check(badPaint.length === 0, 'real buffered paint and session-window layout metrics recorded: ' + JSON.stringify(badPaint.map(({ phase, viewport, iteration, lcpMs, cls }) => ({ phase, viewport, iteration, lcpMs, cls }))));
  const candidate = structuredClone(run);
  candidate.product = 'Synthetic candidate';
  check(compareRuns(run, candidate).differences.every((group) => Object.values(group.metrics).every((metric) => metric.difference === 0)), 'identical measurements compare without invented improvement');
  assert.throws(() => compareRuns({ ...run, config: observational }, { ...candidate, config: observational }), /Public observations/); checks++;
  for (const mutation of [(value) => value.protocol = 'other', (value) => value.config.environment.siteSnapshot = 'changed', (value) => value.config.optimizationProfile.fixture = false, (value) => value.runtime.platform = 'different', (value) => value.browserVersion = 'different', (value) => value.samples.pop()]) {
    const changed = structuredClone(candidate); mutation(changed); assert.throws(() => compareRuns(run, changed)); checks++;
  }
  const stats = summarize([{ page: 'p', viewport: 'v', phase: 'f', ttfbMs: 1 }, { page: 'p', viewport: 'v', phase: 'f', ttfbMs: 3 }]);
  check(stats[0].metrics.ttfbMs.median === 2 && stats[0].metrics.ttfbMs.p95 === 3, 'even median and nearest-rank percentiles correct');
  const output = join(temporary, 'cli.json');
  const { writeFile } = await import('node:fs/promises');
  const configFile = join(temporary, 'config.json');
  await writeFile(configFile, JSON.stringify({ ...fixture, viewports: [fixture.viewports[0]] }));
  const args = ['tools/benchmark.mjs', 'run', '--config', configFile, '--browser', browser, '--product', 'Synthetic fixture', '--version', '1', '--out', output];
  if (dependencies) args.push('--dependencies', dependencies);
  const code = await new Promise((resolveResult, reject) => { const child = spawn(process.execPath, args, { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] }); let errors = ''; child.stderr.on('data', (chunk) => errors += chunk); child.on('error', reject); child.on('close', (value) => value ? reject(new Error(errors)) : resolveResult(value)); });
  check(code === 0 && JSON.parse(await readFile(output, 'utf8')).samples.length === 9, 'CLI writes raw samples and browser metadata');
  check((await readFile(join(temporary, 'cli.md'), 'utf8')).includes('not field INP'), 'human-readable report states metric limits');
  const baselineFile = join(temporary, 'baseline.json');
  const candidateFile = join(temporary, 'candidate.json');
  const comparisonFile = join(temporary, 'comparison.json');
  const reportBaseline = structuredClone(run);
  const reportCandidate = structuredClone(candidate);
  for (const record of [reportBaseline, reportCandidate]) {
    for (const sample of record.samples) {
      if (sample.phase !== 'http-server-cache-hit') sample.cls = 0;
    }
  }
  for (const sample of reportBaseline.samples) {
    if (sample.viewport === 'desktop' && sample.phase === 'fresh-browser') sample.cls = 0.000001;
  }
  for (const sample of reportCandidate.samples) {
    if (sample.viewport === 'desktop' && sample.phase === 'fresh-browser') sample.cls = 0.03;
  }
  await writeFile(baselineFile, JSON.stringify(reportBaseline));
  await writeFile(candidateFile, JSON.stringify(reportCandidate));
  await new Promise((resolveResult, reject) => {
    const child = spawn(process.execPath, ['tools/benchmark.mjs', 'compare', '--baseline', baselineFile, '--candidate', candidateFile, '--out', comparisonFile], { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    let errors = '';
    child.stderr.on('data', (chunk) => errors += chunk);
    child.on('error', reject);
    child.on('close', (value) => value ? reject(new Error(errors)) : resolveResult());
  });
  const comparisonReport = await readFile(join(temporary, 'comparison.md'), 'utf8');
  check(comparisonReport.includes('0 → 0.03 (Δ 0.03)'), 'near-zero baseline CLS reports absolute change rather than a misleading percentage');
  check(comparisonReport.includes('0 → 0 (Δ 0)'), 'zero baseline CLS reports a defined absolute change');
  console.log(`${checks} benchmark assertions passed; synthetic fixture only, no product performance comparison.`);
} finally {
  await new Promise((resolveResult) => server.close(resolveResult));
  await rm(temporary, { recursive: true, force: true });
}
