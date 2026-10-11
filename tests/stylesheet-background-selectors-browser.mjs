/** Actual formatter output and maintained CSS lazyload runtime; intercepted images only. */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executables.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const fixture = JSON.parse(execFileSync(php, [fileURLToPath(new URL('./stylesheet-background-selectors.php', import.meta.url)), '--export'], { encoding: 'utf8', windowsHide: true }));
const pixel = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', 'base64');
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
const targets = [['functional', 'descendant'], ['where'], ['attribute', 'attribute-child'], ['escaped']];
const html = '<div style="height:2500px"></div><div class="a"><div id="functional" class="c"><div id="descendant" class="d"></div></div></div><div id="where" class="a"><div class="child"></div></div><div id="attribute" data-value="a,b"><div id="attribute-child" class="child"></div></div><div id="escaped" class="escaped,comma"></div>';
const rules = targets.flatMap((ids, index) => ids.map(id => `#${id}{width:80px;height:80px;background-image:var(--noobrocket-bg-fixture${index})}`)).join('');
try {
  for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    for (const debug of [false, true]) {
      const context = await browser.newContext({ viewport });
      const requests = [];
      const errors = [];
      await context.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.hostname !== 'selector-images.test') { errors.push(`Unowned request: ${url}`); return route.abort(); }
        requests.push(url.pathname);
        return route.fulfill({ contentType: 'image/png', body: pixel });
      });
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      await page.setContent(`<!doctype html><html><head><style>${rules}</style></head><body><style id="noobrocket-lazyload-bg-container"></style>${html}</body></html>`);
      await page.addScriptTag({ content: `var noobrocket_pairs=${JSON.stringify(fixture.pairs)};var noobrocket_lazyload_css_data={threshold:300};` });
      await page.addScriptTag({ content: await readFile(new URL(`../noobrocket/assets/js/lazyload-css${debug ? '' : '.min'}.js`, import.meta.url), 'utf8') });
      const label = `${viewport.width}px ${debug ? 'readable' : 'minified'}`;
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      check(requests.length === 0, `${label}: backgrounds withheld before intersection`);
      for (const [index, ids] of targets.entries()) {
        await page.locator(`#${ids[0]}`).scrollIntoViewIfNeeded();
        await page.waitForFunction(({ ids, index }) => ids.every(id => document.getElementById(id).getAttribute(`data-noobrocket-lazy-bg-fixture${index}`) === 'loaded'), { ids, index });
        for (const id of ids) check(await page.locator(`#${id}`).evaluate((element, index) => getComputedStyle(element).backgroundImage.includes(`/hero${index}.png`), index), `${label}: ${id} background restored`);
        await page.waitForFunction(index => performance.getEntriesByType('resource').some(resource => resource.name.includes(`/hero${index}.png`)), index);
        check(requests.includes(`/hero${index}.png`), `${label}: selected image fetched`);
      }
      check(errors.length === 0, `${label}: no selector/runtime errors: ${errors.join('; ')}`);
      await context.close();
    }
  }
  console.log(`PASS: stylesheet-background selectors browser (${checks} assertions; both runtime modes; desktop/mobile).`);
} finally {
  await browser.close();
}
