/** Actual PHP inline-background output and bundled lazyload runtime; intercepted images only. */
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executable paths.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const { stdout } = await promisify(execFile)(php, [fileURLToPath(new URL('./inline-background-lazyload.php', import.meta.url)), '--export'], { windowsHide: true });
const fixture = JSON.parse(stdout);
const pixel = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=', 'base64');
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
const document = (content) => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>.box{width:80px;height:80px}</style></head><body>${content}</body></html>`;

try {
  for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    for (const debug of [false, true]) {
      const context = await browser.newContext({ viewport });
      const requests = [];
      const errors = [];
      await context.route('**/*', (route) => {
        const url = new URL(route.request().url());
        if (url.hostname !== 'inline-images.test') return route.abort();
        requests.push(url.pathname);
        return route.fulfill({ contentType: 'image/png', body: pixel });
      });
      const baseline = await context.newPage();
      await baseline.setContent(document(fixture.original));
      const original = await baseline.locator('.box').evaluateAll((elements) => Object.fromEntries(elements.map((element) => {
        const style = getComputedStyle(element);
        return [element.id, { background: style.backgroundImage, color: style.color, position: style.backgroundPosition }];
      })));
      await baseline.close();
      requests.length = 0;
      const page = await context.newPage();
      page.on('pageerror', (error) => errors.push(error.message));
      await page.setContent(document(fixture.converted));
      await page.addScriptTag({ content: fixture.configuration });
      const runtime = await readFile(new URL(`../noobrocket/assets/js/lazyload/17.8.3/lazyload${debug ? '' : '.min'}.js`, import.meta.url), 'utf8');
      await page.addScriptTag({ content: runtime });
      const label = `${viewport.width}px ${debug ? 'readable' : 'minified'}`;
      for (const id of ['url-layers', 'gradient-layer', 'gradient-first', 'excluded']) {
        const actual = await page.locator(`#${id}`).evaluate((element) => ({ background: getComputedStyle(element).backgroundImage, lazy: element.classList.contains('noobrocket-lazyload'), data: element.hasAttribute('data-bg') }));
        check(actual.background === original[id].background, `${label}: ${id} retains every computed background layer`);
        check(!actual.lazy && !actual.data, `${label}: ${id} remains eager`);
      }
      for (const path of ['/front.png', '/back.png', '/overlay.png', '/under.png', '/eager.png']) {
        check(requests.includes(path), `${label}: unchanged background requests ${path}`);
      }
      check(!requests.includes('/single(name).png'), `${label}: supported offscreen single URL waits for intersection`);
      check(await page.locator('#single').evaluate((element) => getComputedStyle(element).backgroundImage === 'none'), `${label}: deferred single declaration removed completely`);
      await page.locator('#single').scrollIntoViewIfNeeded();
      await page.waitForFunction(() => document.querySelector('#single').classList.contains('lazyloaded'));
      check(await page.locator('#single').evaluate((element, expected) => getComputedStyle(element).backgroundImage === expected, original.single.background), `${label}: bundled runtime restores quoted parentheses URL`);
      check(requests.includes('/single(name).png'), `${label}: supported single URL fetched after intersection`);
      check(await page.locator('#single').evaluate((element, expected) => {
        const style = getComputedStyle(element);
        return style.color === expected.color && style.backgroundPosition === expected.position;
      }, original.single), `${label}: unrelated declarations survive runtime loading`);
      check(errors.length === 0, `${label}: runtime has no page errors: ${errors.join('; ')}`);
      await context.close();
    }
    const nojs = await browser.newContext({ viewport, javaScriptEnabled: false });
    await nojs.route('**/*', (route) => new URL(route.request().url()).hostname === 'inline-images.test' ? route.fulfill({ contentType: 'image/png', body: pixel }) : route.abort());
    const page = await nojs.newPage();
    await page.setContent(document(fixture.converted));
    for (const id of ['url-layers', 'gradient-layer', 'gradient-first', 'excluded']) {
      const style = await page.locator(`#${id}`).getAttribute('style');
      check(style.includes('background-image:'), `${viewport.width}px: eager layered/excluded declaration remains available without JavaScript`);
    }
    await nojs.close();
  }
  console.log(`PASS: ${checks} inline-background browser checks`);
} finally {
  await browser.close();
}
