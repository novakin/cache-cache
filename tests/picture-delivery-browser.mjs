import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { basename, extname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const [wordpress, php, executablePath, dependencies] = process.argv.slice(2);
if (!wordpress || !php || !executablePath) throw new Error('Provide disposable WordPress, PHP executable, Chromium executable; optional worker package.json.');
const workerPackage = dependencies ? resolve(dependencies) : fileURLToPath(new URL('../noobrocket/local-css/package.json', import.meta.url));
const { chromium } = createRequire(workerPackage)('playwright-core');
const temporary = await mkdtemp(join(tmpdir(), 'noobrocket-picture-browser-'));
let browser;
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
try {
  // Every tested picture comes from the production PHP converter and real lazyload/CDN implementations.
  const result = execFileSync(php, [fileURLToPath(new URL('./wordpress-picture-delivery.php', import.meta.url)), resolve(wordpress), temporary], { encoding: 'utf8', maxBuffer: 128 * 1024 });
  console.log(result.trim());
  const fixtures = JSON.parse(await readFile(join(temporary, 'delivery.json'), 'utf8'));
  const lazyLibrary = await readFile(join(resolve(wordpress), 'wp-content/plugins/noobrocket/assets/js/lazyload/17.8.3/lazyload.min.js'), 'utf8');
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
  const route = async (routing) => {
    const url = new URL(routing.request().url());
    if (url.hostname === 'picture-page.test') {
      const name = url.pathname.slice(1);
      const html = fixtures[name];
      if (typeof html !== 'string') { await routing.abort(); return; }
      await routing.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0}img{max-width:100%;height:auto}</style><title>Native delivery fixture</title></head><body>' + html + '</body></html>' });
      return;
    }
    if (!['picture-images.test', 'picture-cdn.test', '127.0.0.1'].includes(url.hostname)) { await routing.abort(); return; }
    const extension = extname(url.pathname);
    const mime = { '.jpg': 'image/jpeg', '.avif': 'image/avif', '.webp': 'image/webp' }[extension];
    if (!mime) { await routing.abort(); return; }
    try { await routing.fulfill({ contentType: mime, body: await readFile(join(temporary, basename(url.pathname))) }); }
    catch { await routing.abort(); }
  };
  for (const viewport of [{ width: 390, height: 844 }, { width: 1365, height: 900 }]) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
    await context.route('**/*', route);
    try {
      for (const fixture of [
        { name: 'responsive', id: 'responsive', expected: viewport.width < 480 ? 'small.avif' : 'large.avif' },
        { name: 'webp', id: 'webp', expected: viewport.width < 480 ? 'partial-small.webp' : 'partial-large.webp' },
        { name: 'fallback', id: 'fallback', expected: 'original.jpg' },
        { name: 'cdn', id: null, expected: viewport.width < 480 ? 'small.avif' : 'large.avif', host: 'picture-cdn.test' },
      ]) {
        const page = await context.newPage();
        try {
          await page.goto('http://picture-page.test/' + fixture.name);
          const selector = fixture.id ? '#' + fixture.id : 'img';
          await page.waitForFunction((selector) => { const image = document.querySelector(selector); return image.complete && image.naturalWidth > 0; }, selector);
          const image = await page.locator(selector).evaluate((element) => ({ current: element.currentSrc, original: element.getAttribute('src'), sources: element.parentElement.tagName === 'PICTURE' ? [...element.parentElement.querySelectorAll('source')].map((source) => source.type) : [] }));
          check(new URL(image.current).pathname.endsWith('/' + fixture.expected), `${viewport.width}: ${fixture.name} uses the native responsive source`);
          check(image.original.endsWith('.jpg'), `${viewport.width}: ${fixture.name} keeps its JPEG fallback URL`);
          if (fixture.host) check(new URL(image.current).hostname === fixture.host, `${viewport.width}: generated AVIF survives the actual CDN image zone`);
          if (fixture.name === 'responsive') check(image.sources.join(',') === 'image/avif,image/webp', `${viewport.width}: browser sees AVIF then WebP`);
          if (fixture.name === 'webp') check(image.sources.join(',') === 'image/webp', `${viewport.width}: missing AVIF candidate leaves a complete WebP source`);
        } finally { await page.close(); }
      }
      const lazy = await context.newPage();
      try {
        await lazy.goto('http://picture-page.test/lazy');
        check(await lazy.locator('source').evaluateAll((sources) => sources.length === 2 && sources.every((source) => !source.hasAttribute('srcset') && source.hasAttribute('data-lazy-srcset'))), `${viewport.width}: picture sources stay deferred before lazyload`);
        await lazy.addScriptTag({ content: fixtures.lazy_script });
        await lazy.addScriptTag({ content: lazyLibrary });
        await lazy.waitForFunction(() => { const image = document.querySelector('#lazy'); return image.complete && image.naturalWidth > 0 && image.classList.contains('lazyloaded'); });
        const current = await lazy.locator('#lazy').evaluate((image) => image.currentSrc);
        check(new URL(current).pathname.endsWith(viewport.width < 480 ? '/small.avif' : '/large.avif'), `${viewport.width}: production lazyload activates the native responsive AVIF source`);
      } finally { await lazy.close(); }
    } finally { await context.close(); }
  }
  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  await noScript.route('**/*', route);
  try {
    const page = await noScript.newPage();
    await page.goto('http://picture-page.test/lazy');
    const fallback = page.locator('noscript img');
    await fallback.waitFor({ state: 'visible' });
    check((await fallback.getAttribute('src')).endsWith('/hero.jpg'), 'JavaScript-disabled lazyload exposes the original noscript JPEG');
    check(await fallback.evaluate((image) => image.complete && image.naturalWidth > 0), 'JavaScript-disabled original fallback decodes');
  } finally { await noScript.close(); }
  console.log(`PASS: ${checks} native picture browser assertions across desktop/mobile and JavaScript-disabled fallback.`);
} finally {
  if (browser) await browser.close();
  await rm(temporary, { recursive: true, force: true });
}
