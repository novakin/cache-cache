/** Actual PHP-owner output and served CSS/runtime bytes; owned loopback HTTP, no WordPress bootstrap. */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const [executablePath, php] = process.argv.slice(2);
if (!executablePath || !php) throw new Error('Provide Chromium and PHP executables.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const runtime = await readFile(new URL('../noobrocket/assets/js/lazyload-css.min.js', import.meta.url), 'utf8');
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', 'base64');
const requests = new Map();
let fixture;
const server = createServer((request, response) => {
  const url = new URL(request.url, 'http://127.0.0.1');
  requests.set(request.url, (requests.get(request.url) ?? 0) + 1);
  if (url.pathname === '/hero.png') {
    response.writeHead(200, { 'Content-Type': 'image/png', 'Cache-Control': 'public,max-age=3600' });
    return response.end(image);
  }
  if (url.pathname === '/runtime.js') {
    response.writeHead(200, { 'Content-Type': 'text/javascript', 'Cache-Control': 'public,max-age=3600' });
    return response.end(`const noobrocket_lazyload_css_data={threshold:300};${runtime}`);
  }
  const asset = fixture.assets[url.pathname];
  if (asset || url.pathname === '/css/main.css') {
    response.writeHead(200, { 'Content-Type': 'text/css', 'Cache-Control': 'public,max-age=3600' });
    return response.end(asset ?? fixture.original);
  }
  const original = fixture.http_originals[url.pathname];
  if (original) {
    response.writeHead(original.status, { 'Content-Type': 'text/css', 'Cache-Control': 'no-store' });
    return response.end(original.body);
  }
  const html = fixture.pages[decodeURIComponent(url.pathname.slice(1))];
  if (html) {
    response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' });
    return response.end(`${html}<script src="/runtime.js"></script>`);
  }
  response.writeHead(404);
  response.end();
});
server.on('connect', (_request, socket) => socket.end('HTTP/1.1 403 Forbidden\r\n\r\n'));
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
let browser;
let checks = 0;
let unownedRequests = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
function observeRequest(request) {
  if (new URL(request.url()).origin !== origin) unownedRequests++;
}

try {
  fixture = JSON.parse(execFileSync(php, [fileURLToPath(new URL('stylesheet-background-publication.php', import.meta.url)), origin], { encoding: 'utf8' }));
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32', args: [`--proxy-server=${origin}`, '--proxy-bypass-list=127.0.0.1'] });
  for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport });
    context.on('request', observeRequest);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const href = new URL(fixture.pages.first.match(/href="([^"]+)"/)[1]);
    const assetRequest = href.pathname + href.search;
    const before = requests.get(assetRequest) ?? 0;
    const variantRequests = new Map();
    for (const name of ['variant-0', 'variant-1']) {
      const url = new URL(fixture.pages[name].match(/href="([^"]+)"/)[1]);
      const key = url.pathname + url.search;
      variantRequests.set(key, requests.get(key) ?? 0);
    }
    for (const name of ['first', 'second', 'variant-0', 'variant-1', 'variant-2', 'variant-3', 'variant-4', 'variant-5', 'uppercase', 'comment', 'raw text', 'attribute', 'template', 'inline', 'outer', 'nested']) {
      await page.goto(`${origin}/${encodeURIComponent(name)}`, { waitUntil: 'load' });
      await page.waitForFunction(() => getComputedStyle(document.querySelector('.hero')).backgroundImage.includes('/hero.png'));
      check(await page.locator('.hero').evaluate(element => getComputedStyle(element).width === '64px'), `${viewport.width}: ${name} retains non-background CSS`);
      check(await page.locator('#noobrocket-lazyload-bg-container').count() === 1, `${viewport.width}: ${name} delivers one active mapping owner`);
      if (name === 'second') {
        check((requests.get(assetRequest) ?? 0) - before === 1, `${viewport.width}: next page reuses generated CSS from browser cache`);
        check(await page.evaluate(() => performance.getEntriesByType('resource').some(entry => entry.name.includes('.css?') && entry.transferSize === 0)), `${viewport.width}: reused stylesheet has no transferred body`);
      }
      if (name.startsWith('variant-')) {
        const color = Number(name.slice(-1)) % 2 ? 'rgb(0, 0, 0)' : 'rgb(255, 0, 0)';
        check(await page.locator('.hero').evaluate(element => getComputedStyle(element).color) === color, `${viewport.width}: alternating query variant preserves its own styling`);
      }
    }
    check(variantRequests.size === 2, `${viewport.width}: query variants have independent generated resources`);
    for (const [key, previous] of variantRequests) {
      check((requests.get(key) ?? 0) - previous === 1, `${viewport.width}: six alternating query variants fetch each generated resource once`);
    }
    for (const name of ['headless', 'failed publication']) {
      await page.goto(`${origin}/${encodeURIComponent(name)}`, { waitUntil: 'load' });
      check(await page.locator('.hero').evaluate(element => getComputedStyle(element).backgroundImage.includes('/hero.png')), `${viewport.width}: ${name} retains original visible background`);
      check(await page.locator('link').getAttribute('href') === `${origin}/css/main.css?ver=7&theme=light`, `${viewport.width}: ${name} keeps original stylesheet`);
    }
    for (const name of ['404', '500', 'error']) {
      await page.goto(`${origin}/http-${name}`, { waitUntil: 'load' });
      check(await page.locator('link').getAttribute('href') === `${origin}/http/${name}.css?theme=dark&size=2`, `${viewport.width}: ${name} response retains original delivery`);
      check(await page.locator('.hero').evaluate(element => getComputedStyle(element).backgroundImage === 'none' && getComputedStyle(element).color === 'rgb(0, 0, 0)'), `${viewport.width}: ${name} response body remains unapplied`);
      await page.goto(`${origin}/http-${name}-recovered`, { waitUntil: 'load' });
      await page.waitForFunction(() => getComputedStyle(document.querySelector('.hero')).backgroundImage.includes('/hero.png'));
      check(await page.locator('.hero').evaluate(element => getComputedStyle(element).color === 'rgb(255, 0, 0)' && getComputedStyle(element).width === '64px'), `${viewport.width}: ${name} recovery applies healthy response bytes`);
    }
    check(errors.length === 0, `${viewport.width}: generated and fallback pages have no browser exceptions`);
    await context.close();
    const noScriptContext = await browser.newContext({ viewport, javaScriptEnabled: false });
    noScriptContext.on('request', observeRequest);
    const noScript = await noScriptContext.newPage();
    await noScript.goto(`${origin}/first`, { waitUntil: 'load' });
    check(await noScript.locator('.hero').evaluate(element => getComputedStyle(element).backgroundImage.includes('/hero.png')), `${viewport.width}: noscript restores generated backgrounds`);
    for (const name of ['404', '500', 'error']) {
      await noScript.goto(`${origin}/http-${name}`, { waitUntil: 'load' });
      check(await noScript.locator('.hero').evaluate(element => getComputedStyle(element).backgroundImage === 'none' && getComputedStyle(element).color === 'rgb(0, 0, 0)'), `${viewport.width}: no-JS ${name} body remains unapplied`);
      await noScript.goto(`${origin}/http-${name}-recovered`, { waitUntil: 'load' });
      check(await noScript.locator('.hero').evaluate(element => getComputedStyle(element).backgroundImage.includes('/hero.png') && getComputedStyle(element).color === 'rgb(255, 0, 0)'), `${viewport.width}: no-JS ${name} recovery restores healthy backgrounds`);
    }
    await noScriptContext.close();
  }
  check((requests.get('/hero.png') ?? 0) > 0, 'actual image bytes are fetched from the owned server');
  check(unownedRequests === 0, 'no unowned browser requests attempted');
  console.log(`PASS: stylesheet-background publication browser (${checks} assertions; 1365x900 and 390x844; actual PHP output/CSS/runtime).`);
} finally {
  if (browser) await browser.close();
  await new Promise(resolve => server.close(resolve));
}
