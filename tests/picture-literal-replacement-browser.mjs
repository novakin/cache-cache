/** Actual nonnative picture output and bundled runtime; parsed DOM and intercepted images. */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executables.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const fixture = JSON.parse(execFileSync(php, [fileURLToPath(new URL('./picture-literal-replacement.php', import.meta.url)), '--export'], { encoding: 'utf8', windowsHide: true }));
const pixel = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', 'base64');
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
const document = content => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>${content}</body></html>`;
try {
  for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    for (const debug of [false, true]) {
      const context = await browser.newContext({ viewport });
      const errors = [];
      await context.route('**/*', route => {
        if (new URL(route.request().url()).hostname !== 'picture-images.test') { errors.push(`Unowned request: ${route.request().url()}`); return route.abort(); }
        return route.fulfill({ contentType: 'image/png', body: pixel });
      });
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      const label = `${viewport.width}px ${debug ? 'readable' : 'minified'}`;
      for (const picture of fixture.pictures) {
        await page.setContent(document(picture.html));
        const img = page.locator('picture > img');
        check(await img.getAttribute('alt') === picture.alt, `${label}: parsed visible alt preserves ${picture.alt}`);
        check(await img.getAttribute('title') === picture.title, `${label}: parsed title/backslashes preserved`);
        check(await page.locator('picture > source').getAttribute('data-lazy-srcset') === 'https://picture-images.test/photo.png 1x, https://picture-images.test/photo-2x.png 2x', `${label}: responsive source preserved`);
        await page.addScriptTag({ content: fixture.configuration });
        await page.addScriptTag({ content: await readFile(new URL(`../noobrocket/assets/js/lazyload/17.8.3/lazyload${debug ? '' : '.min'}.js`, import.meta.url), 'utf8') });
        await page.waitForFunction(() => document.querySelector('picture > img').classList.contains('lazyloaded'));
        check(await img.evaluate(element => element.complete && element.naturalWidth === 1 && element.currentSrc.includes('picture-images.test/photo.png')), `${label}: actual responsive picture loads`);
        check(await img.getAttribute('alt') === picture.alt && await img.getAttribute('title') === picture.title, `${label}: runtime preserves literal attributes`);
      }
      await page.setContent(document(fixture.excluded));
      check(await page.locator('img').getAttribute('alt') === 'Price $0.45' && await page.locator('img').getAttribute('data-lazy-src') === null, `${label}: excluded picture remains eager/literal`);
      check(errors.length === 0, `${label}: no browser errors: ${errors.join('; ')}`);
      await context.close();
    }
    const context = await browser.newContext({ viewport, javaScriptEnabled: false });
    await context.route('**/*', route => new URL(route.request().url()).hostname === 'picture-images.test' ? route.fulfill({ contentType: 'image/png', body: pixel }) : route.abort());
    const page = await context.newPage();
    for (const picture of fixture.pictures) {
      await page.setContent(document(picture.html));
      const fallback = page.locator('noscript img');
      check(await fallback.getAttribute('alt') === picture.alt && await fallback.getAttribute('title') === picture.title, `${viewport.width}px no-JS: literal fallback attributes preserved`);
      await fallback.evaluate(element => element.decode());
      check(await fallback.evaluate(element => element.naturalWidth === 1 && element.currentSrc === 'https://picture-images.test/photo.png'), `${viewport.width}px no-JS: original fallback loads`);
    }
    await context.close();
  }
  console.log(`PASS: literal picture replacement browser (${checks} assertions; both runtime modes; desktop/mobile and no-JS).`);
} finally {
  await browser.close();
}
