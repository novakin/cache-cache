/** Link intent, exclusions and real prerender activation. Disposable localhost only; no WordPress. */
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createServer } from 'node:http';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join, resolve, sep } from 'node:path';

const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const [checker, source, minified] = await Promise.all([
  readFile(new URL('../noobrocket/assets/js/browser-checker.js', import.meta.url), 'utf8'),
  readFile(new URL('../noobrocket/assets/js/preload-links.js', import.meta.url), 'utf8'),
  readFile(new URL('../noobrocket/assets/js/preload-links.min.js', import.meta.url), 'utf8')
]);
const scripts = { source, minified };
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
const nativeResults = new Map();
let activeNativeRun;
let origin;
const nonce = 'noobrocket-local-fixture';
const script = code => `<script nonce="${nonce}">${code}</script>`;
const defaults = () => ({
  prefetch: false, prerender: true, excludeUris: '/never-cache(?:/|$)',
  prerenderExcludeUris: '/private(?:/|$)', siteUrl: origin + '/wordpress/',
  onHoverDelay: 100, rateThrottle: 3, usesTrailingSlash: true,
  imageExt: 'jpg|jpeg|gif|png|tiff|bmp|webp|avif|pdf|doc|docx|xls|xlsx|php',
  fileExt: 'jpg|jpeg|gif|png|tiff|bmp|webp|avif|pdf|doc|docx|xls|xlsx|php|html|htm'
});
const render = url => {
  const variant = url.searchParams.get('variant') || 'source';
  const scenario = url.searchParams.get('scenario') || 'dom';
  const config = { ...defaults(), ...JSON.parse(url.searchParams.get('config') || '{}') };
  const capabilities = JSON.parse(url.searchParams.get('capabilities') || '{}');
  const configure = `window.NoobRocketPreloadLinksConfig = ${JSON.stringify(config)};
    ${capabilities.unsupported ? "HTMLScriptElement.supports = () => false;" : ''}
    ${capabilities.noUrlPattern ? "window.URLPattern = undefined;" : ''}
    ${capabilities.noPrefetch ? "NoobRocketBrowserCompatibilityChecker.prototype.supportsLinkPrefetch = () => false;" : ''}
    ${capabilities.saveData || capabilities.slow ? `Object.defineProperty(navigator, 'connection', { value: { saveData: ${!!capabilities.saveData}, effectiveType: '${capabilities.slow ? '2g' : '4g'}' }, configurable: true });` : ''}`;
  const automation = scenario !== 'native' ? '' : `setTimeout(() => document.querySelector('#first').dispatchEvent(new MouseEvent('mouseover', { bubbles: true })), 200);
    const ready = setInterval(async () => {
      const state = await fetch('/qa/state?run=${variant}').then(response => response.json());
      if (!state.loaded) return;
      clearInterval(ready);
      document.querySelector('#first').click();
    }, 150);`;
  return `<!doctype html><html><head><meta charset="utf-8"><title>Link intent fixture</title></head><body>
    <a id="first" href="/wordpress/article-one"><span>First article</span></a>
    <a id="second" href="/wordpress/article-two">Second article</a><button id="other">Other control</button>
    ${script(checker)}${script(configure)}${script(scripts[variant])}${script(automation)}</body></html>`;
};
const server = createServer((request, response) => {
  const url = new URL(request.url, origin);
  if (url.pathname === '/qa/report') {
    const result = nativeResults.get(url.searchParams.get('run'));
    if (result) {
      if (url.searchParams.get('stage') === 'loaded') {
        result.loaded = true;
        result.wasPrerendered = url.searchParams.get('prerender') === 'true';
      } else if (url.searchParams.get('stage') === 'activated') {
        result.activationStart = Number(url.searchParams.get('start'));
        result.resolve();
      }
    }
    response.end('ok');
    return;
  }
  if (url.pathname === '/qa/state') {
    response.setHeader('Content-Type', 'application/json');
    response.setHeader('Cache-Control', 'no-store');
    response.end(JSON.stringify({ loaded: !!nativeResults.get(url.searchParams.get('run'))?.loaded }));
    return;
  }
  response.setHeader('Content-Type', 'text/html');
  response.setHeader('Cache-Control', 'max-age=60');
  if (url.pathname === '/wordpress/fixture') {
    if (url.searchParams.get('scenario') === 'native') activeNativeRun = url.searchParams.get('variant');
    if (url.searchParams.get('csp') === '1') response.setHeader('Content-Security-Policy', `default-src 'self'; script-src 'nonce-${nonce}'; connect-src 'self'`);
    response.end(render(url));
    return;
  }
  if (url.pathname.startsWith('/wordpress/article-')) {
    const native = nativeResults.get(activeNativeRun);
    if (native) native.requests.push({ pathname: url.pathname, purpose: request.headers['sec-purpose'] || '' });
    const run = JSON.stringify(activeNativeRun);
    response.end(`<!doctype html><title>Prepared article</title><p>Article content</p><script>
      fetch('/qa/report?run=' + ${run} + '&stage=loaded&prerender=' + document.prerendering);
      document.addEventListener('prerenderingchange', () => fetch('/qa/report?run=' + ${run} + '&stage=activated&start=' + performance.getEntriesByType('navigation')[0].activationStart));
    </script>`);
    return;
  }
  response.end('<!doctype html><title>Fixture resource</title>');
});
await new Promise(resolveListen => server.listen(0, '127.0.0.1', resolveListen));
origin = `http://127.0.0.1:${server.address().port}`;

const urlFor = (variant, config = {}, capabilities = {}, extra = {}) => {
  const url = new URL('/wordpress/fixture', origin);
  url.search = new URLSearchParams({ variant, config: JSON.stringify(config), capabilities: JSON.stringify(capabilities), ...extra });
  return url.href;
};
const dispatch = (page, selector, type) => page.locator(selector).evaluate((element, eventType) => {
  element.dispatchEvent(new Event(eventType, { bubbles: true }));
}, type);
const prepared = page => page.evaluate(() => ({
  prefetch: [...document.querySelectorAll('link[rel="prefetch"]')].map(link => link.href),
  rules: [...document.querySelectorAll('script[type="speculationrules"]')].map(node => ({ id: node.id, nonce: node.nonce, rules: JSON.parse(node.textContent) }))
}));
let browser;
try {
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
  for (const variant of Object.keys(scripts)) {
    const context = await browser.newContext();
    await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
    const errors = [];
    const newPage = async (config = {}, capabilities = {}, extra = {}) => {
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(urlFor(variant, config, capabilities, extra));
      return page;
    };
    try {
      const page = await newPage();
      await dispatch(page, '#first span', 'mouseover');
      check((await prepared(page)).rules.length === 0, `${variant}: hover waits for intent delay`);
      await page.waitForTimeout(140);
      let state = await prepared(page);
      check(state.rules.length === 1 && state.prefetch.length === 0, `${variant}: prerender does not also insert legacy prefetch`);
      check(state.rules[0].id === 'noobrocket-speculation-rules', `${variant}: rules use the owned script identifier`);
      check(state.rules[0].nonce === nonce, `${variant}: CSP nonce is preserved`);
      check(state.rules[0].rules.prerender[0].source === 'list' && state.rules[0].rules.prerender[0].eagerness === 'immediate', `${variant}: validated intent uses an immediate explicit list`);
      check(state.rules[0].rules.prerender[0].urls.join() === origin + '/wordpress/article-one', `${variant}: only the intended destination is prepared`);
      await dispatch(page, '#first', 'mousedown');
      check((await prepared(page)).rules.length === 1, `${variant}: repeated intent does not duplicate the active rule`);
      await dispatch(page, '#second', 'mousedown');
      state = await prepared(page);
      check(state.rules.length === 1 && state.rules[0].rules.prerender[0].urls.join() === origin + '/wordpress/article-two', `${variant}: newest candidate replaces the previous candidate`);
      await page.close();

      for (const type of ['mouseover', 'focusin']) {
        const cancelled = await newPage();
        await dispatch(cancelled, '#first', type);
        await dispatch(cancelled, '#first', type === 'mouseover' ? 'mouseout' : 'focusout');
        await cancelled.waitForTimeout(140);
        check((await prepared(cancelled)).rules.length === 0, `${variant}: ${type} cancellation clears intent`);
        await cancelled.close();
      }
      const keyboard = await newPage();
      await keyboard.locator('#first').focus();
      await keyboard.waitForTimeout(140);
      check((await prepared(keyboard)).rules.length === 1, `${variant}: keyboard focus prepares a destination`);
      await keyboard.close();
      const touch = await newPage();
      await dispatch(touch, '#first', 'touchstart');
      check((await prepared(touch)).rules.length === 1, `${variant}: touch intent prepares a destination`);
      await touch.close();
      const changed = await newPage();
      await dispatch(changed, '#first', 'mouseover');
      await changed.locator('#first').evaluate(element => element.href = '/wordpress/private/account');
      await changed.waitForTimeout(140);
      check((await prepared(changed)).rules.length === 0, `${variant}: a link changed during the timer is revalidated`);
      await changed.close();

      const modes = [
        { config: { prefetch: true, prerender: false }, capabilities: {}, prefetch: 1, rules: 0, name: 'legacy prefetch' },
        { config: { prefetch: true, prerender: true }, capabilities: {}, prefetch: 0, rules: 1, name: 'combined options' },
        { config: {}, capabilities: { unsupported: true }, prefetch: 1, rules: 0, name: 'unsupported API fallback' },
        { config: { prefetch: false, prerender: false }, capabilities: {}, prefetch: 0, rules: 0, name: 'both options disabled' },
        { config: {}, capabilities: { saveData: true }, prefetch: 0, rules: 0, name: 'data saver' },
        { config: {}, capabilities: { slow: true }, prefetch: 0, rules: 0, name: 'slow connection' },
        { config: {}, capabilities: { noPrefetch: true }, prefetch: 0, rules: 1, name: 'prerender without legacy prefetch support' },
        { config: {}, capabilities: { unsupported: true, noPrefetch: true }, prefetch: 0, rules: 0, name: 'neither API supported' },
        { config: { excludeUris: '[' }, capabilities: {}, prefetch: 0, rules: 0, name: 'invalid cache exclusion' },
        { config: { prerenderExcludeUris: '[' }, capabilities: {}, prefetch: 0, rules: 0, name: 'invalid prerender exclusion' },
        { config: { prefetch: true, prerender: false, prerenderExcludeUris: '[' }, capabilities: {}, prefetch: 1, rules: 0, name: 'inactive invalid prerender exclusion preserves prefetch' },
        { config: { nativeExcludePaths: ['/wordpress/provider-private/*'] }, capabilities: { noUrlPattern: true }, prefetch: 0, rules: 0, name: 'active native paths require URLPattern' },
        { config: { prefetch: true, prerender: false, nativeExcludePaths: ['/wordpress/provider-private/*'] }, capabilities: { noUrlPattern: true }, prefetch: 1, rules: 0, name: 'inactive native paths do not require URLPattern' },
        { config: { nativeExcludeSelectors: ['['] }, capabilities: {}, prefetch: 0, rules: 0, name: 'invalid active native selector fails closed' },
        { config: { prefetch: true, prerender: false, nativeExcludeSelectors: ['['] }, capabilities: {}, prefetch: 1, rules: 0, name: 'inactive native selector preserves prefetch' }
      ];
      for (const mode of modes) {
        const candidate = await newPage(mode.config, mode.capabilities);
        await dispatch(candidate, '#first', 'mousedown');
        const outcome = await prepared(candidate);
        check(outcome.prefetch.length === mode.prefetch && outcome.rules.length === mode.rules, `${variant}: ${mode.name}`);
        await candidate.close();
      }

      const unsafe = [
        { name: 'query', href: '/wordpress/article?preview=true' },
        { name: 'empty query marker', href: '/wordpress/article?' },
        { name: 'nonce action', href: '/wordpress/article?_wpnonce=one' },
        { name: 'fragment', href: '/wordpress/article#section' },
        { name: 'empty fragment marker', href: '/wordpress/article#' },
        { name: 'self', href: 'SELF' },
        { name: 'external', href: 'https://example.invalid/wordpress/article' },
        { name: 'prefix collision', href: origin.replace('127.0.0.1', '127.0.0.1.example.invalid') + '/wordpress/article' },
        { name: 'outside site base', href: '/another/article' },
        { name: 'site base collision', href: '/wordpress-other/article' },
        { name: 'credentials', href: origin.replace('http://', 'http://user:password@') + '/wordpress/article' },
        { name: 'mail link', href: 'mailto:user@example.invalid' },
        { name: 'cache exclusion', href: '/wordpress/never-cache/account' },
        { name: 'encoded cache exclusion', href: '/wordpress/%6Eever-cache/account' },
        { name: 'prerender exclusion', href: '/wordpress/private/account' },
        { name: 'encoded prerender exclusion', href: '/wordpress/%70rivate/account' },
        { name: 'malformed URL escape', href: '/wordpress/article%ZZ' },
        { name: 'new tab', target: '_blank' },
        { name: 'other frame', target: '_top' },
        { name: 'base target', baseTarget: '_blank' },
        { name: 'download', download: true },
        { name: 'nofollow', rel: 'nofollow' },
        { name: 'case-insensitive nofollow', rel: 'external NOFOLLOW' },
        { name: 'direct no-prefetch', className: 'no-prefetch' },
        { name: 'direct no-prerender', className: 'no-prerender' },
        { name: 'parent no-prefetch', parentClass: 'no-prefetch' },
        { name: 'parent no-prerender', parentClass: 'no-prerender' },
        { name: 'parent explicit opt-out', parentAttribute: true },
        { name: 'encoded resource extension', href: '/wordpress/resource.%70ng' },
        ...['xml', 'json', 'csv', 'zip', 'gz', 'mp3', 'mp4', 'webm', 'svg', 'ico', 'css', 'js', 'png', 'avif', 'pdf', 'php'].map(extension => ({ name: `${extension} resource`, href: '/wordpress/resource.' + extension }))
      ];
      const exclusion = await newPage();
      for (const item of unsafe) {
        await exclusion.evaluate(item => {
          document.querySelector('base')?.remove();
          document.querySelector('#fixture-wrapper')?.remove();
          document.querySelector('#first')?.remove();
          const anchor = document.createElement('a'); anchor.id = 'first'; anchor.textContent = 'Candidate';
          anchor.href = item.href === 'SELF' ? window.location.href : item.href || '/wordpress/article-one';
          if (item.target) anchor.target = item.target;
          if (item.rel) anchor.rel = item.rel;
          if (item.download) anchor.download = '';
          if (item.className) anchor.className = item.className;
          if (item.baseTarget) { const base = document.createElement('base'); base.target = item.baseTarget; document.head.append(base); }
          const wrapper = document.createElement('div'); wrapper.id = 'fixture-wrapper';
          if (item.parentClass) wrapper.className = item.parentClass;
          if (item.parentAttribute) wrapper.setAttribute('data-noobrocket-no-speculation', '');
          wrapper.append(anchor); document.body.append(wrapper);
        }, item);
        await dispatch(exclusion, '#first', 'mousedown');
        const outcome = await prepared(exclusion);
        check(outcome.rules.length === 0 && outcome.prefetch.length === 0, `${variant}: rejects ${item.name}`);
      }
      await exclusion.close();
      const nativePaths = await newPage({ nativeExcludePaths: ['/wordpress/provider-private/*'] });
      await nativePaths.locator('#first').evaluate(element => element.href = '/wordpress/provider-private/profile');
      await dispatch(nativePaths, '#first', 'mousedown');
      check((await prepared(nativePaths)).rules.length === 0, `${variant}: third-party native URLPattern exclusion is honored`);
      await dispatch(nativePaths, '#second', 'mousedown');
      check((await prepared(nativePaths)).rules.length === 1, `${variant}: a relative native URLPattern does not disable safe links`);
      await nativePaths.close();
      const nativeSelector = await newPage({ nativeExcludeSelectors: ['.provider-private, .provider-private a'] });
      await nativeSelector.locator('#first').evaluate(element => {
        const wrapper = document.createElement('div'); wrapper.className = 'provider-private';
        element.replaceWith(wrapper); wrapper.append(element);
      });
      await dispatch(nativeSelector, '#first', 'mousedown');
      check((await prepared(nativeSelector)).rules.length === 0, `${variant}: third-party native CSS selector exclusion is honored`);
      await dispatch(nativeSelector, '#second', 'mousedown');
      check((await prepared(nativeSelector)).rules.length === 1, `${variant}: native selector does not disable safe links`);
      await nativeSelector.close();
      const unicode = await newPage({ excludeUris: '/jamais-caché(?:/|$)' });
      await unicode.locator('#first').evaluate(element => element.href = '/wordpress/jamais-cach%C3%A9/account');
      await dispatch(unicode, '#first', 'mousedown');
      check((await prepared(unicode)).rules.length === 0, `${variant}: Unicode cache exclusions match encoded destinations`);
      await unicode.close();
      for (const suffix of ['.html', '.htm']) {
        const article = await newPage();
        await article.locator('#first').evaluate((element, suffix) => element.href = '/wordpress/article' + suffix, suffix);
        await dispatch(article, '#first', 'mousedown');
        check((await prepared(article)).rules.length === 1, `${variant}: ${suffix} article permalinks remain eligible`);
        await article.close();
      }
      const sameWindow = await newPage();
      await sameWindow.locator('#first').evaluate(element => {
        const base = document.createElement('base'); base.target = '_blank'; document.head.append(base);
        element.target = '_self';
      });
      await dispatch(sameWindow, '#first', 'mousedown');
      check((await prepared(sameWindow)).rules.length === 1, `${variant}: explicit _self overrides a new-tab base target`);
      await sameWindow.close();
      const throttled = await newPage();
      await throttled.evaluate(() => {
        for (let number = 1; number <= 4; number++) {
          const anchor = document.createElement('a'); anchor.id = 'rate-' + number;
          anchor.href = '/wordpress/rate-article-' + number; document.body.append(anchor);
          anchor.dispatchEvent(new Event('mouseover', { bubbles: true }));
        }
      });
      await throttled.waitForTimeout(140);
      check((await prepared(throttled)).rules[0]?.rules.prerender[0].urls[0].endsWith('/rate-article-3'), `${variant}: hover rate limit keeps the fourth candidate out`);
      await throttled.waitForTimeout(1000);
      await dispatch(throttled, '#rate-4', 'mouseover');
      await throttled.waitForTimeout(140);
      check((await prepared(throttled)).rules[0]?.rules.prerender[0].urls[0].endsWith('/rate-article-4'), `${variant}: hover preparation resumes after the rate window`);
      await throttled.close();
      const csp = await newPage({}, {}, { csp: '1' });
      await dispatch(csp, '#first', 'mousedown');
      check((await prepared(csp)).rules[0]?.nonce === nonce, `${variant}: nonce survives an enforced CSP`);
      await csp.close();
      check(errors.length === 0, `${variant}: no uncaught browser errors (${errors.join('; ')})`);
    } finally { await context.close(); }
  }
  await browser.close();
  browser = undefined;

  // A DevTools-attached page disables real prerender activation. Let this isolated Chrome navigate itself instead.
  for (const variant of Object.keys(scripts)) {
    const profile = await mkdtemp(join(tmpdir(), 'noobrocket-link-prerender-'));
    let chrome;
    try {
      const result = { requests: [] };
      const activated = new Promise(resolveActivation => { result.resolve = resolveActivation; });
      nativeResults.set(variant, result);
      chrome = spawn(executablePath, [
        '--headless', '--no-first-run', '--no-default-browser-check', '--user-data-dir=' + profile,
        urlFor(variant, {}, {}, { scenario: 'native', csp: '1' })
      ], { windowsHide: true, stdio: 'ignore' });
      let timer;
      await Promise.race([
        activated,
        new Promise((_, reject) => { timer = setTimeout(() => reject(new Error(`${variant}: native prerender activation timed out`)), 8000); }),
        new Promise((_, reject) => chrome.once('error', reject))
      ]).finally(() => clearTimeout(timer));
      check(result.wasPrerendered, `${variant}: real destination loads with document.prerendering=true under CSP`);
      check(result.activationStart > 0, `${variant}: clicking activates the prepared document`);
      check(result.requests.length === 1 && result.requests[0].pathname === '/wordpress/article-one', `${variant}: activation reuses one document request`);
      check(result.requests[0].purpose.includes('prerender'), `${variant}: the document request carries Sec-Purpose: prefetch;prerender`);
    } finally {
      activeNativeRun = undefined;
      if (chrome && chrome.exitCode === null) {
        const stopped = new Promise(resolveStopped => chrome.once('close', resolveStopped));
        chrome.kill();
        await stopped;
      }
      const path = resolve(profile);
      check(path.startsWith(resolve(tmpdir()) + sep), `${variant}: disposable profile remains inside the temporary directory`);
      await rm(path, { recursive: true, force: true, maxRetries: 8, retryDelay: 200 });
    }
  }
  console.log(`PASS: ${checks} source/minified link preparation browser assertions, including actual localhost prerender activation.`);
} finally {
  if (browser) await browser.close();
  await new Promise(resolveClose => server.close(resolveClose));
}
