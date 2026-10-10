import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { createServer } from 'node:net';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executable paths.');
const require = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url));
const { chromium } = require('playwright-core');
const directory = await mkdtemp(join(tmpdir(), 'noobrocket-delay-csp-'));
const resources = fileURLToPath(new URL('./wordpress-optimizer-resources.php', import.meta.url)).replaceAll('\\', '/');
const router = `<?php
ob_start(); require '${resources}'; ob_end_clean();
if ('/app.js' === parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) { header('Content-Type: application/javascript'); echo 'window.nativeAppRuns=(window.nativeAppRuns||0)+1;'; exit; }
if ('/module.js' === parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) { header('Content-Type: application/javascript'); echo 'export {}; window.nativeAppRuns=(window.nativeAppRuns||0)+1;'; exit; }
class DiskRuntime extends ResourceFilesystem {
  public function exists($path) { return !$this->missing_runtime && is_file($path); }
  public function get_contents($path) { return $this->missing_runtime || !is_file($path) ? false : file_get_contents($path); }
}
$policy = "script-src 'self' 'nonce-fixture'";
$mode = $_GET['mode'] ?? 'ordinary';
if ('header' === $mode) { header('Content-Security-Policy: '.$policy); }
if ('report' === $mode) { header('Content-Security-Policy-Report-Only: '.$policy); }
$meta = 'meta' === $mode ? '<meta http-equiv="Content-Security-Policy" content="'.$policy.'">' : '';
$tag = '<script nonce="fixture" src="/app.js"></script>';
if ('unquoted-src' === $mode) { $tag = '<script src=/app.js></script>'; }
if ('unquoted-query' === $mode) { $tag = '<script src=/app.js?a=1&amp;b=2></script>'; }
if ('unquoted-module' === $mode) { $tag = '<script type=module src=/module.js></script>'; }
if ('single-module' === $mode) { $tag = "<script type='module' src='/module.js'></script>"; }
if ('entity-module' === $mode) { $tag = '<script type=mod&#117;le src=/module.js></script>'; }
if ('attribute-names' === $mode) { $tag = '<script data-type="application/ld+json" data-src="/wrong.js" src=/app.js></script>'; }
if ('quoted-noise' === $mode) { $tag = '<script data-note="type=application/ld+json src=/wrong.js >" src="/app.js?a=1&amp;b=2"></script>'; }
if ('bare-type' === $mode) { $tag = '<script type src=/app.js></script>'; }
if ('jsonld' === $mode) { $tag = '<script type=application/ld+json>{"kind":"fixture"}</script>'.$tag; }
$page = '<!doctype html><html><head>'.$meta.$tag.'</head><body><button id="trigger">Trigger</button></body></html>';
if ('uppercase-head' === $mode) { $page = str_replace(['<head>', '</head>'], ['<HEAD data-note="quoted > attribute">', '</HEAD>'], $page); }
if (in_array($mode, ['headless', 'header-element', 'comment-head', 'script-head', 'attribute-head'], true)) {
  $misleading = ['headless' => '', 'header-element' => '<header>Navigation</header>', 'comment-head' => '<!-- <head> -->', 'script-head' => '<script>window.tagLikeHead="<head>";</script>', 'attribute-head' => '<div data-note="<head>"></div>'][$mode];
  $page = '<!doctype html><html><body>'.$misleading.$tag.'<button id="trigger">Trigger</button></body></html>';
}
if (in_array($mode, ['unfinished-double-attribute', 'unfinished-single-attribute'], true)) {
  $quote = 'unfinished-double-attribute' === $mode ? '"' : "'";
  $page = '<!doctype html><html><body>'.$tag.'<button id="trigger">Trigger</button><div data-note='.$quote.'unterminated <head> tail';
}
$disk = new DiskRuntime(); $disk->missing_runtime = 'missing' === $mode;
$subscriber = new NoobRocket\\Engine\\Optimization\\DelayJS\\Subscriber($html, $disk);
echo $subscriber->add_delay_js_script($subscriber->delay_js($page));
`;
let server;
let browser;
let checks = 0;
try {
  await writeFile(join(directory, 'router.php'), router);
  const reservation = createServer();
  await new Promise((resolve) => reservation.listen(0, '127.0.0.1', resolve));
  const port = reservation.address().port;
  await new Promise((resolve) => reservation.close(resolve));
  const origin = `http://127.0.0.1:${port}`;
  server = spawn(php, ['-S', `127.0.0.1:${port}`, join(directory, 'router.php')], { windowsHide: true, stdio: 'ignore' });
  for (let attempt = 0; ; attempt++) {
    try { const response = await fetch(origin); if (response.ok) break; } catch {}
    if (attempt >= 50) throw new Error('Isolated PHP fixture did not start.');
    await new Promise((resolve) => setTimeout(resolve, 100));
  }
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
  for (const mode of ['header', 'meta', 'report', 'ordinary', 'missing', 'unquoted-src', 'unquoted-query', 'unquoted-module', 'single-module', 'entity-module', 'attribute-names', 'quoted-noise', 'bare-type', 'jsonld', 'uppercase-head', 'headless', 'header-element', 'comment-head', 'script-head', 'attribute-head', 'unfinished-double-attribute', 'unfinished-single-attribute']) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${origin}/?mode=${mode}`, { waitUntil: 'load' });
    const conservative = ['header', 'meta', 'missing', 'headless', 'header-element', 'comment-head', 'script-head', 'attribute-head', 'unfinished-double-attribute', 'unfinished-single-attribute'].includes(mode);
    assert.equal(await page.locator('script[data-noobrocket-src]').count(), conservative ? 0 : 1, `${mode}: correct script delivery path`); checks++;
    if (mode === 'jsonld') { assert.equal(await page.locator('script[type="application/ld+json"]').textContent(), '{"kind":"fixture"}', 'JSON-LD stays available before interaction'); checks++; }
    if (conservative) { assert.equal(await page.evaluate(() => window.nativeAppRuns), 1, `${mode}: original script runs before interaction`); checks++; }
    await page.locator('#trigger').click();
    await page.waitForFunction(() => window.nativeAppRuns === 1);
    assert.equal(await page.evaluate(() => window.nativeAppRuns), 1, `${mode}: functional script after interaction`); checks++;
    assert.deepEqual(errors, [], `${mode}: no JavaScript errors`); checks++;
    if (mode === 'jsonld') { assert.equal(await page.locator('script[type="application/ld+json"]').textContent(), '{"kind":"fixture"}', 'JSON-LD stays untouched after interaction'); checks++; }
    await page.close();
  }
  console.log(`PASS: native DelayJS CSP browser fallback (${checks} assertions).`);
} finally {
  if (browser) await browser.close();
  if (server && server.exitCode === null) { server.kill(); await new Promise((resolve) => server.once('exit', resolve)); }
  await rm(directory, { recursive: true, force: true });
}
