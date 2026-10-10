/** Real subscriber output/runtime, intercepted HTTP only; no WordPress installation or mutations. */
import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executable paths.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const pixel = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=', 'base64');
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };

try {
  for (const debug of [false, true]) {
    const { stdout } = await promisify(execFile)(php, [fileURLToPath(new URL('./lazyload-bootstrap.php', import.meta.url)), '--export', ...(debug ? ['--debug'] : [])], { windowsHide: true });
    const fixture = JSON.parse(stdout);
    const runtime = await readFile(new URL(`../noobrocket/assets/js/lazyload/17.8.3/lazyload${debug ? '' : '.min'}.js`, import.meta.url));
    const label = debug ? 'debug' : 'minified';
    const pages = [];
    const failures = [];
    const open = async (name, hold = false, disableObservers = false) => {
      const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
      pages.push(page);
      const requests = [];
      const errors = [];
      const consoleErrors = [];
      failures.push({ name, errors });
      let release;
      const gate = hold ? new Promise((resolve) => { release = resolve; }) : Promise.resolve();
      page.on('pageerror', (error) => errors.push(error.message));
      page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
      await page.addInitScript((disableObservers) => {
        const probe = window.lazyProbe = { observers: [], instances: [] };
        if (disableObservers) {
          window.MutationObserver = undefined;
        } else {
          const NativeObserver = window.MutationObserver;
          window.MutationObserver = class extends NativeObserver {
            constructor(callback) {
              super(callback);
              this.record = { active: false, disconnects: 0, body: false, options: null };
              probe.observers.push(this.record);
            }
            observe(target, options) {
              this.record.active = true;
              this.record.body = target === document.body;
              this.record.options = options;
              return super.observe(target, options);
            }
            disconnect() {
              this.record.active = false;
              this.record.disconnects++;
              return super.disconnect();
            }
          };
        }
        window.addEventListener('LazyLoad::Initialized', (event) => probe.instances.push(event.detail.instance));
      }, disableObservers);
      await page.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (url.hostname === 'lazy-page.test') return route.fulfill({ contentType: 'text/html', body: fixture.pages[name], headers: fixture.headers[name] || {} });
        if (url.hostname === 'lazy-runtime.test') {
          requests.push(url.href);
          await gate;
          return route.fulfill({ contentType: 'application/javascript', body: runtime, headers: { 'Access-Control-Allow-Origin': '*' } });
        }
        if (['lazy-images.test', 'i.ytimg.com'].includes(url.hostname)) return route.fulfill({ contentType: 'image/png', body: pixel });
        if (['lazy-frames.test', 'www.youtube.com'].includes(url.hostname)) return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Owned iframe response</title>' });
        return route.abort();
      });
      await page.goto('https://lazy-page.test/' + name, { waitUntil: 'domcontentloaded' });
      return { page, requests, release, consoleErrors };
    };
    const settle = (page) => page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    // Playwright's own document observer runs in this realm; count production body observers only.
    const state = (page) => page.evaluate(() => ({ library: typeof window.LazyLoad, instances: window.lazyProbe.instances.length, observers: window.lazyProbe.observers.filter((observer) => observer.body) }));
    const noRuntime = async (result, name) => {
      await settle(result.page);
      const current = await state(result.page);
      check(result.requests.length === 0 && current.library === 'undefined' && current.instances === 0, `${label}/${name}: no runtime download or library instances`);
      check(current.observers.length === 1 && current.observers[0].active && current.observers[0].body && current.observers[0].options.childList && current.observers[0].options.subtree, `${label}/${name}: only one pending child-list observer`);
    };
    try {
      for (const name of ['default-empty', 'default-initial']) {
        const original = await open(name);
        await original.page.waitForFunction(() => typeof window.LazyLoad === 'function');
        check(original.requests.length === 1 && await original.page.locator('template').count() === 0 && (await state(original.page)).instances === 2, `${label}/${name}: default original runtime works under CSP added after PHP`);
        if (name === 'default-initial') {
          await original.page.waitForFunction(() => document.getElementById('initial').classList.contains('lazyloaded'));
          check(await original.page.locator('#initial').getAttribute('src') === 'https://lazy-images.test/initial.png', `${label}/${name}: original target loads under downstream hash/source policy`);
        } else {
          await original.page.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<img id="default-dynamic" data-lazy-src="https://lazy-images.test/default-dynamic.png">'));
          await original.page.waitForFunction(() => document.getElementById('default-dynamic').classList.contains('lazyloaded'));
          check(original.requests.length === 1, `${label}/${name}: later AJAX target uses the original initialized runtime`);
        }
        check(original.consoleErrors.length === 0, `${label}/${name}: downstream policy produces no blocked script errors: ${JSON.stringify(original.consoleErrors)}`);
      }
      await noRuntime(await open('empty'), 'empty');
      const eager = await open('eager');
      await noRuntime(eager, 'eager');
      await eager.page.evaluate(() => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = '<img loading="eager" src="https://lazy-images.test/added-eager.png"><iframe src="https://lazy-frames.test/added-eager"></iframe>';
        document.body.append(wrapper);
      });
      await noRuntime(eager, 'added eager image/iframe');

      for (const trigger of ['image', 'background', 'iframe']) {
        const dynamic = await open('empty', true);
        const markup = {
          image: '<img id="trigger" data-lazy-src="https://lazy-images.test/trigger.png">',
          background: '<div id="trigger" class="noobrocket-lazyload" data-bg="https://lazy-images.test/trigger.png"></div>',
          iframe: '<iframe id="trigger" data-lazy-src="https://lazy-frames.test/trigger"></iframe>',
        }[trigger];
        const request = dynamic.page.waitForRequest((request) => new URL(request.url()).hostname === 'lazy-runtime.test');
        await dynamic.page.evaluate((markup) => document.body.insertAdjacentHTML('beforeend', markup), markup);
        await request;
        check((await state(dynamic.page)).observers.every((observer) => !observer.active), `${label}/${trigger}: pending observer disconnects before runtime fetch completes`);
        await dynamic.page.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<img id="during-fetch" data-lazy-src="https://lazy-images.test/during.png"><div id="background-during-fetch" class="noobrocket-lazyload" data-bg="https://lazy-images.test/background.png"></div>'));
        dynamic.release();
        await dynamic.page.waitForFunction(() => window.lazyProbe.instances.length === 2 && ['trigger', 'during-fetch', 'background-during-fetch'].every((id) => document.getElementById(id).classList.contains('lazyloaded')));
        check(dynamic.requests.length === 1, `${label}/${trigger}: dynamic target loads runtime once, including targets added during fetch`);
        const current = await state(dynamic.page);
        check(current.observers.length === 3 && !current.observers[0].active && current.observers[0].disconnects === 1 && current.observers.slice(1).every((observer) => observer.active), `${label}/${trigger}: existing instance observers take ownership; pending observer stays disconnected: ${JSON.stringify(current)}`);
        await dynamic.page.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<img id="after-load" data-lazy-src="https://lazy-images.test/after.png">'));
        await dynamic.page.waitForFunction(() => document.getElementById('after-load').classList.contains('lazyloaded'));
        check(dynamic.requests.length === 1, `${label}/${trigger}: later AJAX image uses existing library`);
      }

      const initial = await open('initial');
      await initial.page.waitForFunction(() => document.getElementById('initial').classList.contains('lazyloaded'));
      check(initial.requests.length === 1 && (await state(initial.page)).observers.length === 2, `${label}/initial: rewritten target activates immediately without a pending observer`);
      check(initial.requests[0].endsWith(`/lazyload${debug ? '' : '.min'}.js`), `${label}: selected runtime respects SCRIPT_DEBUG`);

      const youtube = await open('youtube');
      await youtube.page.waitForFunction(() => document.querySelector('.rll-youtube-player img')?.classList.contains('lazyloaded'));
      check(youtube.requests.length === 1 && await youtube.page.locator('.rll-youtube-player img').getAttribute('alt') === 'Video thumbnail', `${label}/youtube: real DOMContentLoaded thumbnail activates deferred runtime`);
      await youtube.page.locator('.rll-youtube-player .play').click();
      check((await youtube.page.locator('iframe').getAttribute('src')).includes('autoplay=1'), `${label}/youtube: thumbnail play action retains iframe behavior`);

      const nativeImages = await open('native-images');
      await noRuntime(nativeImages, 'native image mode');
      check(await nativeImages.page.locator('#native').getAttribute('loading') === 'lazy', `${label}/native image mode: native image behavior retained`);
      await nativeImages.page.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<div id="native-background" class="noobrocket-lazyload" data-bg="https://lazy-images.test/native-background.png"></div>'));
      await nativeImages.page.waitForFunction(() => document.getElementById('native-background').classList.contains('lazyloaded'));
      check(nativeImages.requests.length === 1, `${label}/native image mode: backgrounds still activate runtime`);

      const nativeRuntime = await open('native-runtime');
      await nativeRuntime.page.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<img id="native-runtime-image" loading="lazy" data-lazy-src="https://lazy-images.test/native-runtime.png">'));
      await nativeRuntime.page.waitForFunction(() => document.getElementById('native-runtime-image').getAttribute('src') === 'https://lazy-images.test/native-runtime.png');
      check(await nativeRuntime.page.locator('#native-runtime-image').getAttribute('loading') === 'lazy', `${label}/native runtime filter: library preserves native lazy loading`);

      const filtered = await open('filtered');
      await filtered.page.evaluate(() => document.getElementById('outside').innerHTML = '<img class="filtered-target" data-lazy-src="https://lazy-images.test/outside.png">');
      await noRuntime(filtered, 'filtered target outside container');
      await filtered.page.evaluate(() => document.getElementById('custom-container').innerHTML = '<img id="filtered-image" class="filtered-target" data-lazy-src="https://lazy-images.test/filtered.png">');
      await filtered.page.waitForFunction(() => document.getElementById('filtered-image').dataset.filteredCallback === 'loaded' && window.filteredRuntimeLoads === 1);
      const configured = await filtered.page.evaluate(() => {
        const script = document.getElementById('filtered-runtime');
        const settings = window.lazyProbe.instances[0]._settings;
        return { source: script.src, nonce: script.nonce, integrity: script.integrity, data: script.dataset.fixture, priority: script.fetchPriority, cors: script.crossOrigin, threshold: settings.threshold, thresholds: settings.thresholds, selector: settings.elements_selector, container: settings.container.id };
      });
      check(configured.source === 'https://lazy-runtime.test/filtered-runtime.js' && configured.nonce === 'fixture-nonce' && configured.integrity.startsWith('sha384-') && configured.data === 'preserved' && configured.priority === 'low' && configured.cors === 'anonymous', `${label}/filtered: original URL and security/custom script attributes retained`);
      check(configured.threshold === 17 && configured.thresholds === '21px' && configured.selector === 'img.filtered-target' && configured.container === 'custom-container' && (await state(filtered.page)).instances === 1, `${label}/filtered: evaluated selector/container/options own activation and library configuration`);

      for (const policy of ['meta', 'header', 'trusted-types']) {
        const protectedPage = await open('csp-' + policy);
        await protectedPage.page.waitForFunction(() => typeof window.LazyLoad === 'function');
        check(protectedPage.requests.length === 1 && await protectedPage.page.locator('template').count() === 0 && (await state(protectedPage.page)).instances === 2, `${label}/${policy}: actual enforcing CSP preserves functional original scripts`);
      }
      await noRuntime(await open('csp-report-only'), 'report-only CSP still permits gating');

      const delayed = await open('delayed-filtered');
      const visibleDelayedRuntime = await delayed.page.evaluate(() => {
        const script = document.querySelector('script[type="text/noobrocketlazyloadscript"]');
        return script?.getAttribute('data-noobrocket-src') === 'https://lazy-runtime.test/filtered-runtime.js' && document.querySelectorAll('template').length === 0;
      });
      check(visibleDelayedRuntime && delayed.requests.length === 0, `${label}/DelayJS: rewritten custom runtime is visible to the shipped delay runtime before its script snapshot`);
      await delayed.page.keyboard.press('A');
      await delayed.page.waitForFunction(() => window.delayDone === true);
      check(delayed.requests.length === 1 && (await state(delayed.page)).instances === 1, `${label}/DelayJS: existing user interaction activates the filtered runtime`);
      await delayed.page.evaluate(() => {
        const image = document.createElement('img');
        image.className = 'filtered-target';
        image.id = 'delayed-image';
        image.setAttribute('data-lazy-src', 'https://lazy-images.test/delayed.png');
        document.getElementById('custom-container').append(image);
      });
      await delayed.page.waitForFunction(() => document.getElementById('delayed-image').dataset.filteredCallback === 'loaded');
      check(delayed.requests.length === 1, `${label}/DelayJS: first late target remains functional after the delay runtime has completed`);

      const combined = await open('combined-empty-template');
      await combined.page.waitForFunction(() => typeof window.LazyLoad === 'function');
      check(combined.requests.length === 1 && await combined.page.locator('template').evaluate((template) => template.content.children.length === 0), `${label}/Combine removal output: empty template is safe and relocated original runtime executes`);

      for (const name of ['nonasync', 'explicit-type', 'defer', 'complex', 'missing-bootstrap']) {
        const fallback = await open(name);
        await fallback.page.waitForFunction(() => typeof window.LazyLoad === 'function');
        check(fallback.requests.length === 1, `${label}/${name}: conservative fallback preserves original runtime delivery`);
        if (name === 'complex') check(await fallback.page.evaluate(() => window.extraFilteredScript === true), `${label}/complex: additional filtered script executes`);
      }
      const inline = await open('inline');
      check(await inline.page.evaluate(() => window.customInlineLazyload === true) && inline.requests.length === 0, `${label}/inline: custom inline runtime retains execution`);
      const malformed = await open('malformed');
      await settle(malformed.page);
      check(malformed.requests.length === 0 && (await state(malformed.page)).library === 'undefined' && await malformed.page.locator('script-foo').count() === 1, `${label}/malformed: filtered inert element stays inert`);
      const noObservers = await open('empty', false, true);
      await noObservers.page.waitForLoadState('load');
      check(noObservers.requests.length === 1 && (await state(noObservers.page)).library === 'function', `${label}/unsupported observer API: original runtime still loads`);
      check(failures.every(({ errors }) => errors.length === 0), `${label}: no JavaScript errors: ${JSON.stringify(failures.filter(({ errors }) => errors.length))}`);
    } finally {
      await Promise.all(pages.map((page) => page.close()));
    }
  }
  console.log(`PASS: lazyload bootstrap browser (${checks} assertions; default downstream-CSP compatibility and opt-in minified/debug gating; dynamic, filtered, native, YouTube, CSP/Trusted Types and shipped DelayJS; modeled Combine relocation; intercepted HTTP only).`);
} finally {
  await browser.close();
}
