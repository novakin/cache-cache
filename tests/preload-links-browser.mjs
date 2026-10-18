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
  const native = ['native', 'document-candidates'].includes(scenario);
  const config = { ...defaults(), ...JSON.parse(url.searchParams.get('config') || '{}') };
  const capabilities = JSON.parse(url.searchParams.get('capabilities') || '{}');
  const run = url.searchParams.get('run') || variant;
  const documentRules = config.mode === 'document' ? `<script id="noobrocket-document-speculation-rules" type="${capabilities.legacyRules ? 'speculationrules' : 'application/json'}" nonce="${nonce}" data-fixture-hook="preserved">${JSON.stringify({ prerender: [{ source: 'document', where: { and: [{ selector_matches: 'a' }, { not: { selector_matches: 'a' } }] }, eagerness: config.eagerness || 'conservative' }] })}</script>` : '';
  const configure = `window.NoobRocketPreloadLinksConfig = ${JSON.stringify(config)};
    window.bootstrapNativeRuleCount = document.querySelectorAll('script[type="speculationrules"]').length;
    window.bootstrapDocumentRule = document.querySelector('#noobrocket-document-speculation-rules');
    ${capabilities.unsupported ? "HTMLScriptElement.supports = () => false;" : ''}
    ${capabilities.noUrlPattern ? "window.URLPattern = undefined;" : ''}
    ${capabilities.noMutationObserver ? "window.MutationObserver = undefined;" : ''}
    ${capabilities.noEligibleLinks ? "document.querySelectorAll('a').forEach(anchor => anchor.href = '/wordpress/private/account');" : ''}
    ${capabilities.noPrefetch ? "NoobRocketBrowserCompatibilityChecker.prototype.supportsLinkPrefetch = () => false;" : ''}
    ${capabilities.countDocumentPatterns ? `const NativeURLPattern = window.URLPattern;
      window.runtimeLiteralPatterns = [];
      window.URLPattern = class extends NativeURLPattern {
        constructor(...args) {
          super(...args);
          if (args[0] && typeof args[0] === 'object' && Object.hasOwn(args[0], 'protocol')) window.runtimeLiteralPatterns.push({ ...args[0] });
        }
      };` : ''}
    ${capabilities.saveData || capabilities.slow ? `Object.defineProperty(navigator, 'connection', { value: { saveData: ${!!capabilities.saveData}, effectiveType: '${capabilities.slow ? '2g' : '4g'}' }, configurable: true });` : ''}`;
  const automation = scenario !== 'native' ? '' : `${config.mode === 'document' ? 'document.querySelector("#second").classList.remove("no-prerender");document.querySelector("#second").href="/wordpress/private/account";' : ''}
    fetch('/qa/report?run=${run}&stage=rules&data='+encodeURIComponent(document.querySelector('#noobrocket-document-speculation-rules')?.textContent || 'intent'));
    ${config.mode === 'document' ? '' : "setTimeout(() => document.querySelector('#first').dispatchEvent(new MouseEvent('mouseover', { bubbles: true })), 200);"}
    const ready = setInterval(async () => {
      const state = await fetch('/qa/state?run=${run}').then(response => response.json());
      if (!state.loaded) return;
      clearInterval(ready);
      await fetch('/qa/report?run=${run}&stage=prepared&data='+encodeURIComponent(JSON.stringify([...document.querySelectorAll('script[type="speculationrules"]')].map(node => ({ id: node.id, rules: JSON.parse(node.textContent) })))));
      document.querySelector('#first').click();
    }, 150);`;
  return `<!doctype html><html><head><meta charset="utf-8"><title>Link intent fixture</title></head><body>
    <a id="first" href="/wordpress/article-one"><span>First article</span></a>
    <a id="second" ${native && config.mode === 'document' ? 'class="no-prerender"' : ''} href="/wordpress/article-two">Second article</a><button id="other">Other control</button>
    ${capabilities.countDocumentPatterns ? `<a id="duplicate-relative" href="./article-one">Relative duplicate</a><a id="duplicate-absolute" href="${origin}/wordpress/article-one">Absolute duplicate</a><a href="./article-two">Second duplicate</a><a href="/wordpress/private/account">Excluded</a><a href="${origin}/wordpress/private/account">Excluded duplicate</a>` : ''}
    ${native ? '<a href="/wordpress/private/account">Private route</a><a href="/wordpress/never-cache/account">Cache exclusion</a><a href="/outside/article">Outside site</a><a href="/wordpress/download.png">Image</a><a href="/wordpress/article-download" download>Download</a><a href="/wordpress/article-tab" target="_blank">New tab</a><div class="no-prerender"><a href="/wordpress/article-parent">Parent exclusion</a></div>' : ''}
    ${documentRules}${script(checker)}${script(configure)}${script(scripts[variant])}${script(automation)}</body></html>`;
};
const server = createServer((request, response) => {
  const url = new URL(request.url, origin);
  const active = nativeResults.get(activeNativeRun);
  if (active && !url.pathname.startsWith('/qa/') && url.pathname !== '/wordpress/fixture' && request.headers['sec-fetch-dest'] === 'document') {
    active.requests.push({ pathname: url.pathname, purpose: request.headers['sec-purpose'] || '' });
  }
  if (url.pathname === '/qa/report') {
    const result = nativeResults.get(url.searchParams.get('run'));
    if (result) {
      if (url.searchParams.get('stage') === 'loaded') {
        result.loaded = true;
        result.wasPrerendered = url.searchParams.get('prerender') === 'true';
      } else if (url.searchParams.get('stage') === 'activated') {
        result.activationStart = Number(url.searchParams.get('start'));
        result.resolve();
      } else if (url.searchParams.get('stage') === 'rules') {
        result.rules = url.searchParams.get('data');
      } else if (url.searchParams.get('stage') === 'prepared') {
        result.preparedRules = JSON.parse(url.searchParams.get('data'));
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
    if (['native', 'document-candidates'].includes(url.searchParams.get('scenario'))) activeNativeRun = url.searchParams.get('run') || url.searchParams.get('variant');
    if (url.searchParams.get('csp') === '1') response.setHeader('Content-Security-Policy', `default-src 'self'; script-src 'nonce-${nonce}'; connect-src 'self'`);
    response.end(render(url));
    return;
  }
  if (url.pathname.startsWith('/wordpress/article-')) {
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
      // Count genuine native constructions made by the runtime. These assertions
      // inspect serialized rules only and never construct their own URLPatterns.
      const dedupPage = await newPage({ mode: 'document', eagerness: 'eager' }, { countDocumentPatterns: true }, { csp: '1' });
      const dedupState = () => dedupPage.evaluate(() => {
        const node = document.querySelector('#noobrocket-document-speculation-rules');
        window.firstActiveDocumentRule ||= node;
        const json = node.textContent;
        const rule = JSON.parse(json).prerender[0];
        return { json, sameNode: node === window.firstActiveDocumentRule, paths: rule.where.and[0].href_matches.map(pattern => pattern.pathname), constructed: window.runtimeLiteralPatterns.map(pattern => pattern.pathname) };
      });
      let dedup = await dedupState();
      const originalDocumentRules = dedup.json;
      check(dedup.paths.join() === '/wordpress/article-one,/wordpress/article-two', `${variant}: duplicate canonical hrefs retain unique ordered eligible candidates`);
      check(dedup.constructed.join() === dedup.paths.join(), `${variant}: native document patterns are constructed once per unique eligible URL (${dedup.constructed.length} constructions for ${dedup.paths.length} destinations)`);
      await dedupPage.evaluate(async () => {
        window.runtimeLiteralPatterns.length = 0;
        document.querySelector('#duplicate-absolute').remove();
        await new Promise(resolve => requestAnimationFrame(resolve));
      });
      dedup = await dedupState();
      check(dedup.sameNode && dedup.json === originalDocumentRules && dedup.constructed.join() === dedup.paths.join(), `${variant}: removing a duplicate preserves the native rule node and deduplicates the fresh scan`);
      await dedupPage.evaluate(async () => {
        window.runtimeLiteralPatterns.length = 0;
        document.querySelector('#duplicate-relative').href = '/wordpress/article-three';
        await new Promise(resolve => requestAnimationFrame(resolve));
      });
      dedup = await dedupState();
      check(dedup.paths.join() === '/wordpress/article-one,/wordpress/article-two,/wordpress/article-three' && dedup.constructed.join() === dedup.paths.join(), `${variant}: href mutation rebuilds unique candidates in document order`);
      await dedupPage.evaluate(async () => {
        window.runtimeLiteralPatterns.length = 0;
        document.querySelector('#first').remove();
        document.querySelector('#duplicate-relative').href = '/wordpress/private/account';
        await new Promise(resolve => requestAnimationFrame(resolve));
      });
      dedup = await dedupState();
      check(dedup.paths.join() === '/wordpress/article-two' && dedup.constructed.join() === dedup.paths.join(), `${variant}: last-link removal and excluded href mutation remove stale candidates`);
      await dedupPage.close();

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

      for (const eagerness of ['conservative', 'moderate', 'eager']) {
        const documentPage = await newPage({ mode: 'document', eagerness }, {}, { csp: '1' });
        let documentState = await prepared(documentPage);
        const rule = documentState.rules[0]?.rules.prerender[0];
        check(await documentPage.evaluate(() => window.bootstrapNativeRuleCount === 0 && !window.bootstrapDocumentRule.isConnected && document.querySelectorAll('#noobrocket-document-speculation-rules').length === 1), `${variant}: inert bootstrap activates one replacement without a native placeholder`);
        check(rule?.source === 'document' && rule.eagerness === eagerness && documentState.prefetch.length === 0, `${variant}: native document mode exposes ${eagerness} without duplicate prefetch`);
        check(documentState.rules[0].nonce === nonce && await documentPage.locator('#noobrocket-document-speculation-rules').getAttribute('data-fixture-hook') === 'preserved', `${variant}: document-rule replacement preserves nonce and inline tag hooks`);
        const allowed = await documentPage.evaluate(() => {
          const patterns = JSON.parse(document.querySelector('#noobrocket-document-speculation-rules').textContent).prerender[0].where.and[0].href_matches;
          return patterns.every(pattern => new URLPattern(pattern).test(location.origin + pattern.pathname));
        });
        check(allowed, `${variant}: document destinations bind to exact scheme/host/port/path/query/hash components`);
        await dispatch(documentPage, '#first', 'mousedown');
        check((await prepared(documentPage)).rules.length === 1 && (await prepared(documentPage)).prefetch.length === 0, `${variant}: document mode has one owner after intent`);
        await documentPage.evaluate(() => {
          window.anchorScans = 0;
          const original = document.querySelectorAll.bind(document);
          document.querySelectorAll = selector => { if (selector === 'a[href]') window.anchorScans++; return original(selector); };
          document.querySelector('#other').setAttribute('data-state', 'updated');
          document.querySelector('#other').append('Unrelated text');
        });
        await documentPage.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
        check(await documentPage.evaluate(() => window.anchorScans === 0), `${variant}: unrelated mutations do not rescan anchors`);
        await documentPage.evaluate(() => {
          document.querySelector('#first').href = '/wordpress/private/account';
          document.body.insertAdjacentHTML('beforeend', '<a id="dynamic-document" href="/wordpress/article-new">Dynamic article</a><a id="literal-document" href="/wordpress/article(test)+:*{value}">Literal URL syntax</a>');
        });
        await documentPage.waitForFunction(() => {
          const patterns = JSON.parse(document.querySelector('#noobrocket-document-speculation-rules').textContent).prerender[0].where.and[0].href_matches;
          return patterns.some(pattern => new URLPattern(pattern).test(location.origin + '/wordpress/article-new'));
        });
        const literalProof = await documentPage.evaluate(() => {
          const patterns = JSON.parse(document.querySelector('#noobrocket-document-speculation-rules').textContent).prerender[0].where.and[0].href_matches.map(pattern => new URLPattern(pattern));
          return {
            private: patterns.some(pattern => pattern.test(location.origin + '/wordpress/private/account')),
            literal: patterns.some(pattern => pattern.test(document.querySelector('#literal-document').href)),
            other: patterns.some(pattern => pattern.test(location.origin + '/wordpress/article-other')),
            query: patterns.some(pattern => pattern.test(location.origin + '/wordpress/article-new?action=logout')),
            outside: patterns.some(pattern => pattern.test('https://example.invalid/wordpress/article-new'))
          };
        });
        check(!literalProof.private && literalProof.literal && !literalProof.other && !literalProof.query && !literalProof.outside, `${variant}: dynamic candidates preserve exclusions and literal URLPattern syntax`);
        await documentPage.close();
      }
      for (const capabilities of [{ saveData: true }, { slow: true }]) {
        const inert = await newPage({ mode: 'document', eagerness: 'eager' }, capabilities);
        const state = await prepared(inert);
        check(state.rules.length === 0 && state.prefetch.length === 0 && await inert.evaluate(() => window.bootstrapDocumentRule.isConnected && window.bootstrapDocumentRule.type === 'application/json'), `${variant}: constrained connection never registers native document rules`);
        await inert.close();
      }
      const documentFallback = await newPage({ mode: 'document', eagerness: 'eager' }, { unsupported: true });
      await dispatch(documentFallback, '#first', 'mousedown');
      const fallbackState = await prepared(documentFallback);
      check(fallbackState.prefetch.length === 1 && fallbackState.rules.length === 0, `${variant}: unsupported document API retains legacy intent preloading without native bootstrap rules`);
      await documentFallback.close();
      for (const capabilities of [{ noUrlPattern: true }, { noMutationObserver: true }]) {
        const fallback = await newPage({ mode: 'document', eagerness: 'eager' }, capabilities);
        await dispatch(fallback, '#first', 'mousedown');
        const fallbackState = await prepared(fallback);
        check(fallbackState.rules.length === 1 && fallbackState.rules[0].id === 'noobrocket-speculation-rules' && fallbackState.rules[0].rules.prerender[0].source === 'list', `${variant}: unavailable document prerequisites preserve guarded intent fallback`);
        await fallback.close();
      }
      const legacy = await newPage({ mode: 'document', eagerness: 'eager' }, { legacyRules: true }, { csp: '1' });
      const legacyState = await prepared(legacy);
      check(legacyState.rules.length === 1 && legacyState.rules[0].rules.prerender[0].where.and[0].href_matches.length === 2 && legacyState.rules[0].nonce === nonce, `${variant}: cached legacy native bootstrap still activates eligible document rules with CSP nonce`);
      await legacy.close();
      const empty = await newPage({ mode: 'document', eagerness: 'eager' }, { noEligibleLinks: true }, { csp: '1' });
      const emptyState = await prepared(empty);
      check(emptyState.rules.length === 1 && !emptyState.rules[0].rules.prerender[0].where.and[0].href_matches && await empty.evaluate(() => !window.bootstrapDocumentRule.isConnected), `${variant}: equal no-match JSON still activates the initially inert rule`);
      await empty.evaluate(() => document.body.insertAdjacentHTML('beforeend', '<a href="/wordpress/article-later">Later article</a>'));
      await empty.waitForFunction(() => JSON.parse(document.querySelector('#noobrocket-document-speculation-rules').textContent).prerender[0].where.and[0].href_matches?.length === 1);
      check((await prepared(empty)).rules.length === 1, `${variant}: initially empty document rules admit a later eligible link with one native owner`);
      await empty.close();
      check(errors.length === 0, `${variant}: no uncaught browser errors (${errors.join('; ')})`);
    } finally { await context.close(); }
  }

  // Native document eagerness requires trusted browser interest. Synthetic DOM
  // events do not establish that interest. DevTools input does, but Chrome then
  // deliberately blocks prerender activation; assert that exact boundary.
  for (const variant of Object.keys(scripts)) {
    const context = await browser.newContext();
    const page = await context.newPage();
    const client = await context.newCDPSession(page);
    const ruleSets = [];
    const removedRuleSets = [];
    const statuses = [];
    let candidates = [];
    const waitForNative = async (predicate, message) => {
      for (let attempt = 0; attempt < 100; attempt++) {
        if (predicate()) return;
        await new Promise(resolveWait => setTimeout(resolveWait, 30));
      }
      throw new Error(`${variant}: ${message}; candidates=${JSON.stringify(candidates)} statuses=${JSON.stringify(statuses)}`);
    };
    client.on('Preload.ruleSetUpdated', event => ruleSets.push(event.ruleSet));
    client.on('Preload.ruleSetRemoved', event => removedRuleSets.push(event.id));
    client.on('Preload.preloadingAttemptSourcesUpdated', event => {
      candidates = event.preloadingAttemptSources.filter(item => item.key.action === 'Prerender').map(item => item.key.url).sort();
    });
    client.on('Preload.prerenderStatusUpdated', event => statuses.push(event));
    await client.send('Preload.enable');
    const run = `${variant}-document-candidates`;
    const result = { requests: [] };
    nativeResults.set(run, result);
    const firstUrl = origin + '/wordpress/article-one';
    try {
      await page.goto(urlFor(variant, { mode: 'document', eagerness: 'eager' }, {}, { scenario: 'document-candidates', csp: '1', run }));
      await waitForNative(() => candidates.join() === firstUrl, 'native document candidates exclude private/resource/new-tab/download/ancestor destinations');
      check(ruleSets.length === 1 && removedRuleSets.length === 0 && ruleSets.every(ruleSet => !ruleSet.errorMessage && !ruleSet.errorType), `${variant}: native browser registers one validated rule set under CSP without removing a bootstrap rule set`);
      check(candidates.join() === firstUrl, `${variant}: native candidates enforce URL and CSS exclusions`);
      await page.evaluate(() => {
        document.querySelector('#second').classList.remove('no-prerender');
        document.querySelector('#second').href = '/wordpress/private/account';
        document.body.insertAdjacentHTML('beforeend', '<a id="native-dynamic" href="/wordpress/article(test)+:*{value}">Dynamic literal</a>');
      });
      const dynamicUrl = await page.locator('#native-dynamic').evaluate(anchor => anchor.href);
      await waitForNative(() => candidates.join() === [firstUrl, dynamicUrl].sort().join(), 'native document rules admit a literal dynamic URL without admitting private links');
      check(ruleSets.length === 2 && removedRuleSets.length === 1, `${variant}: a changed destination set still replaces the existing native rule set once`);
      check(candidates.join() === [firstUrl, dynamicUrl].sort().join(), `${variant}: native URLPattern predicates preserve literal syntax for dynamic links`);
      const beforeTargetChange = (await prepared(page)).rules[0].rules;
      await page.locator('#native-dynamic').evaluate(anchor => anchor.target = '_blank');
      await waitForNative(() => candidates.join() === firstUrl, 'native CSS predicates react to target changes without a runtime rescan');
      check(JSON.stringify((await prepared(page)).rules[0].rules) === JSON.stringify(beforeTargetChange), `${variant}: native CSS removes a new-tab candidate without rewriting its URL predicates`);
      await page.locator('#first').hover();
      await waitForNative(() => statuses.some(status => status.key.url === firstUrl && status.status === 'Failure' && status.prerenderStatus === 'PrerenderingDisabledByDevTools'), 'trusted interest reaches the documented DevTools activation boundary');
      await waitForNative(() => result.requests.some(request => request.pathname === '/wordpress/article-one' && request.purpose.includes('prerender')), 'trusted interest issues a native prerender document request');
      check(statuses.some(status => status.key.url === firstUrl && status.prerenderStatus === 'PrerenderingDisabledByDevTools'), `${variant}: trusted native interest reaches prerender scheduling, with explicit DevTools activation limit`);
      check(result.requests.length === 1 && result.requests[0].pathname === '/wordpress/article-one' && result.requests[0].purpose.includes('prerender'), `${variant}: native document interest requests only the eligible destination`);
    } finally {
      activeNativeRun = undefined;
      await context.close();
    }
  }
  await browser.close();
  browser = undefined;

  // Legacy page-target sessions disable prerender activation. Intent can navigate
  // itself detached; document Eager needs trusted hover through a supported tab target.
  for (const variant of Object.keys(scripts)) {
    for (const mode of ['intent', 'document']) {
      const run = `${variant}-${mode}`;
      const profile = await mkdtemp(join(tmpdir(), 'noobrocket-link-prerender-'));
      let chrome;
      let connection;
      try {
        const result = { requests: [] };
        const activated = new Promise(resolveActivation => { result.resolve = resolveActivation; });
        nativeResults.set(run, result);
        const fixtureUrl = urlFor(variant, { mode, eagerness: 'eager' }, {}, { scenario: 'native', csp: '1', run });
        chrome = spawn(executablePath, [
          '--headless', '--no-first-run', '--no-default-browser-check', '--user-data-dir=' + profile,
          ...(mode === 'document' ? ['--remote-debugging-port=0'] : []),
          fixtureUrl
        ], { windowsHide: true, stdio: 'ignore' });
        if (mode === 'document') {
          let debuggerPath;
          for (let attempt = 0; attempt < 100; attempt++) {
            try { debuggerPath = (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).trim().split('\n'); break; }
            catch { await new Promise(resolveWait => setTimeout(resolveWait, 30)); }
          }
          check(!!debuggerPath, `${variant}: owned Chrome exposes its loopback debugger`);
          connection = new WebSocket(`ws://127.0.0.1:${debuggerPath[0]}${debuggerPath[1]}`);
          await new Promise((resolveOpen, reject) => {
            const timer = setTimeout(() => reject(new Error(`${run}: debugger connection timed out`)), 3000);
            connection.addEventListener('open', () => { clearTimeout(timer); resolveOpen(); }, { once: true });
            connection.addEventListener('error', () => { clearTimeout(timer); reject(new Error(`${run}: debugger connection failed`)); }, { once: true });
          });
          let sequence = 0;
          const pending = new Map();
          let pageSession;
          const exceptions = [];
          connection.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id) {
              const request = pending.get(message.id);
              if (!request) return;
              pending.delete(message.id);
              clearTimeout(request.timer);
              if (message.error) request.reject(new Error(JSON.stringify(message.error)));
              else request.resolve(message.result);
            } else if (message.method === 'Target.attachedToTarget' && message.params.targetInfo.type === 'page' && message.params.targetInfo.url === fixtureUrl && !message.params.targetInfo.subtype) {
              pageSession = message.params.sessionId;
            } else if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
          });
          const send = (method, params = {}, sessionId) => new Promise((resolveCommand, reject) => {
            const id = ++sequence;
            const timer = setTimeout(() => { pending.delete(id); reject(new Error(`${run}: ${method} timed out`)); }, 3000);
            pending.set(id, { resolve: resolveCommand, reject, timer });
            connection.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
          });
          const { targetInfos } = await send('Target.getTargets', { filter: [{ type: 'tab', exclude: false }, { exclude: true }] });
          const tabs = targetInfos.filter(target => target.url === fixtureUrl);
          check(tabs.length === 1, `${variant}: document proof owns one native fixture tab target`);
          const { sessionId: tabSession } = await send('Target.attachToTarget', { targetId: tabs[0].targetId, flatten: true });
          await send('Target.setAutoAttach', { autoAttach: true, waitForDebuggerOnStart: false, flatten: true, filter: [{ type: 'page', exclude: false }, { exclude: true }] }, tabSession);
          check(!!pageSession, `${variant}: page session belongs to its supported tab target`);
          await send('Runtime.enable', {}, pageSession);
          for (let attempt = 0; !result.rules && attempt < 100; attempt++) await new Promise(resolveWait => setTimeout(resolveWait, 30));
          check(!!result.rules, `${variant}: owned runtime publishes document rules before trusted input`);
          const { result: bounds, exceptionDetails } = await send('Runtime.evaluate', {
            expression: `new Promise(resolve => {
              const ready = () => resolve(JSON.stringify({ url: location.href, rectangle: document.querySelector('#first')?.getBoundingClientRect() }));
              if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, { once: true });
              else ready();
            })`, awaitPromise: true, returnByValue: true
          }, pageSession);
          check(!exceptionDetails && typeof bounds.value === 'string', `${variant}: owned document is ready for trusted input (${JSON.stringify(exceptionDetails || bounds)})`);
          const { url: documentUrl, rectangle } = JSON.parse(bounds.value);
          check(documentUrl === fixtureUrl && !!rectangle, `${variant}: trusted input targets the owned fixture document (${documentUrl})`);
          await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: rectangle.x + rectangle.width / 2, y: rectangle.y + rectangle.height / 2 }, pageSession);
          result.exceptions = exceptions;
        }
        let timer;
        await Promise.race([
          activated,
          new Promise((_, reject) => { timer = setTimeout(() => reject(new Error(`${run}: native prerender activation timed out ${JSON.stringify({ loaded: result.loaded, wasPrerendered: result.wasPrerendered, requests: result.requests, rules: result.rules })}`)), 8000); }),
          new Promise((_, reject) => chrome.once('error', reject))
        ]).finally(() => clearTimeout(timer));
        check(result.wasPrerendered, `${variant}: real destination loads with document.prerendering=true under CSP`);
        check(result.activationStart > 0, `${variant}: clicking activates the prepared document`);
        check(result.requests.length === 1 && result.requests[0].pathname === '/wordpress/article-one', `${variant}: activation reuses one document request`);
        check(result.requests[0].purpose.includes('prerender'), `${variant}: the document request carries Sec-Purpose: prefetch;prerender`);
        if (mode === 'document') {
          const documentRule = result.preparedRules[0];
          check(result.preparedRules.length === 1 && documentRule.id === 'noobrocket-document-speculation-rules' && documentRule.rules.prerender[0].source === 'document' && documentRule.rules.prerender[0].eagerness === 'eager', `${variant}: native activation uses document Eager rules without intent fallback`);
          check(result.exceptions.length === 0, `${variant}: document initiator has no runtime exceptions`);
        }
      } finally {
        activeNativeRun = undefined;
        if (connection) connection.close();
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
  }
  console.log(`PASS: ${checks} source/minified link preparation browser assertions. Detached intent and tab-target-aware document Eager activation verified; eligible navigation reuses one prerender request and excluded destinations are not loaded.`);
} finally {
  if (browser) await browser.close();
  await new Promise(resolveClose => server.close(resolveClose));
}
