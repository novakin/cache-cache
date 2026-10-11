/** Execute native generated scripts and retained module imports; localhost interception only, no WordPress. */
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { createRequire } from 'node:module';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executable paths.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const { stdout } = await promisify(execFile)(php, [fileURLToPath(new URL('./wordpress-minify-boundaries.php', import.meta.url)), '--export'], { windowsHide: true });
const fixture = JSON.parse(stdout);
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
try {
  for (const [name, html] of Object.entries(fixture.pages)) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('**/*', async (route) => {
      const url = new URL(route.request().url());
      if (url.href === 'https://site.test/fixture.html') return route.fulfill({ status: 200, contentType: 'text/html', body: html });
      url.search = '';
      let body = fixture.assets[url.href];
      if (url.href === 'https://remote.test/modules/app.js') body = 'import { value } from "./dep.js"; window.nativeModuleValue = value;';
      if (url.href === 'https://remote.test/modules/dep.js') body = 'export const value = 42;';
      if (url.href === 'https://remote.test/keep.js') body = 'window.nativeKeptRuns = (window.nativeKeptRuns || 0) + 1;';
      if (body === undefined) return route.abort();
      return route.fulfill({ status: 200, contentType: 'application/javascript', headers: { 'Access-Control-Allow-Origin': '*' }, body });
    });
    await page.goto('https://site.test/fixture.html', { waitUntil: 'load' });
    if (name.startsWith('module-')) {
      await page.waitForFunction(() => window.nativeModuleValue === 42);
      assert.equal(await page.locator('script[type="module"]').count(), 1, `${name}: original module type remains`); checks++;
      assert.equal(await page.evaluate(() => window.nativeModuleValue), 42, `${name}: relative module import executes from its original URL`); checks++;
    } else if (name.startsWith('protected-')) {
      await page.waitForFunction(() => window.nativeRemoteRuns === 1);
      assert.equal(await page.evaluate(() => window.nativeRemoteRuns), 1, `${name}: protected original executes once`); checks++;
      if (name.includes('nonce')) {
        assert.equal(await page.locator('script[nonce]').evaluate((script) => script.nonce), name.includes('nonce-padded') ? 'fixture=' : 'fixture', `${name}: CSP nonce remains on original script`); checks++;
      } else {
        assert.match(await page.locator('script[integrity]').getAttribute('integrity'), /^sha(?:256|384)-/, `${name}: original integrity metadata remains`); checks++;
      }
    } else if (name.startsWith('inline-')) {
      if (name.startsWith('inline-module-')) {
        await page.waitForFunction(() => window.nativeInlineModuleValue === 42);
        assert.equal(await page.locator('script[type="module"]').count(), 1, `${name}: original inline module type remains`); checks++;
        assert.equal(await page.evaluate(() => window.nativeInlineModuleValue), 42, `${name}: inline module import still executes`); checks++;
      } else {
        await page.waitForFunction(() => window.nativeInlineRuns === 1);
        assert.equal(await page.evaluate(() => window.nativeInlineRuns), 1, `${name}: original or optimized inline bytes execute once`); checks++;
        if (name.includes('nonce')) {
          assert.equal(await page.locator('script[nonce]').evaluate((script) => script.nonce), 'fixture', `${name}: allowed inline nonce remains under nonce-only CSP`); checks++;
        }
      }
    } else if (name.startsWith('classic-body-')) {
      await page.waitForFunction(() => window.nativeClassicRuns === 1);
      assert.equal(await page.evaluate(() => window.nativeClassicRuns), 1, `${name}: generated or retained original script executes once`); checks++;
      assert.equal(await page.locator('script[data-minify]').count(), name.includes('fallback') ? 0 : 1, `${name}: only supported insertion points produce a bundle`); checks++;
      if (name.endsWith('script-body') || name.endsWith('real-after-text')) {
        assert.equal(await page.evaluate(() => window.tagLikeBody), '</body>', `${name}: tag-like script data remains intact`); checks++;
      }
    } else if (name.startsWith('classic-self-csp-')) {
      await page.waitForFunction(() => window.nativeClassicRuns === 1);
      assert.equal(await page.evaluate(() => window.nativeClassicRuns), 1, `${name}: supported same-origin optimized script still executes under CSP`); checks++;
    } else if (name.startsWith('classic-')) {
      await page.waitForFunction(() => window.nativeClassicRuns === 1 && window.nativeRemoteRuns === 1);
      assert.deepEqual(await page.evaluate(() => [window.nativeClassicRuns, window.nativeRemoteRuns]), [1, 1], `${name}: native optimized local/remote bytes execute once`); checks++;
      assert.deepEqual(await page.evaluate(() => window.nativeOrder), ['local', 'remote'], `${name}: original execution order remains`); checks++;
    } else if (name === 'local-fallback') {
      await page.waitForFunction(() => window.nativeClassicRuns === 1);
      assert.equal(await page.evaluate(() => window.nativeClassicRuns), 1, 'empty local source keeps valid original script functional'); checks++;
    } else {
      await page.waitForFunction(() => window.nativeKeptRuns === 1);
      assert.equal(await page.evaluate(() => window.nativeKeptRuns), 1, `${name}: retained script executes once`); checks++;
    }
    assert.deepEqual(errors, [], `${name}: no JavaScript errors`); checks++;
    await page.close();
  }
  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    for (const scenario of fixture.dynamic_resources) {
      const { name, format, phase, html, requested_url, responses } = scenario;
      const label = `${name}/${phase}/${viewport.width}`;
      const page = await browser.newPage({ viewport });
      const requested = [];
      const unexpected = [];
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      await page.route('**/*', async (route) => {
        const url = route.request().url();
        if (url === 'https://site.test/fixture.html') return route.fulfill({ status: 200, contentType: 'text/html', body: html });
        requested.push(url);
        const response = responses[url];
        if (!response) { unexpected.push(url); return route.abort(); }
        if (response.status === 'transport') return route.abort('failed');
        return route.fulfill(response);
      });
      await page.goto('https://site.test/fixture.html', { waitUntil: 'load' });
      assert.deepEqual(requested, [requested_url], `${label}: browser loads exactly the actual owner URL`); checks++;
      assert.deepEqual(unexpected, [], `${label}: no unowned network request`); checks++;
      if (format === 'js') {
        assert.deepEqual(await page.evaluate(() => [window.dynamicErrorApplied ?? false, window.dynamicHealthyRuns ?? 0]), [false, phase === 'failure' ? 0 : 1], `${label}: failed bytes never execute and healthy delivery executes once`); checks++;
      } else {
        const style = await page.locator('#dynamic').evaluate((element) => ({ color: getComputedStyle(element).color, width: getComputedStyle(element).width }));
        assert.equal(style.color, phase === 'failure' ? 'rgb(0, 0, 0)' : 'rgb(0, 128, 0)', `${label}: failed CSS never applies and healthy CSS applies`); checks++;
        if (phase !== 'failure') { assert.equal(style.width, '64px', `${label}: generated CSS retains healthy dimensions`); checks++; }
      }
      assert.deepEqual(errors, [], `${label}: no JavaScript errors`); checks++;
      await page.close();
    }
  }
  console.log(`PASS: actual optimizer browser delivery (${checks} assertions; generated/retained JS and dynamic CSS/JS, intercepted HTTP).`);
} finally {
  await browser.close();
}
