/** Appearance and owned notices before first paint; native fallbacks. Synthetic browser only; no WordPress writes. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const { chromium } = createRequire(new URL('../noobrocket/local-css/generator.mjs', import.meta.url))('playwright-core');
const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const [appearance, admin, css, enqueue] = await Promise.all([
  readFile(new URL('../noobrocket/assets/js/noobrocket-appearance.js', import.meta.url), 'utf8'),
  readFile(new URL('../noobrocket/assets/js/noobrocket-admin.js', import.meta.url), 'utf8'),
  readFile(new URL('../noobrocket/assets/css/noobrocket-admin.css', import.meta.url), 'utf8'),
  readFile(new URL('../noobrocket/inc/admin/ui/enqueue.php', import.meta.url), 'utf8')
]);
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
check(/wp_enqueue_script\( 'noobrocket-appearance',[^\n]+, false \)/.test(enqueue), 'appearance owner is enqueued in the head');
check(!admin.includes('noobrocket-theme') && !admin.includes('prefers-color-scheme'), 'footer has no competing preference owner');

let heldFooter;
let renderCount = 0;
const posts = [];
const render = url => {
  const legacy = url.searchParams.has('footer-only');
  const nativeNotices = `<div id="fixture-native-outcome" class="notice notice-success" data-noobrocket-notice="cache_purged"><p>Cache cleared.</p></div>
  <div id="setting-error-settings_updated" class="notice notice-success"><p>Settings saved.</p></div>
  <div id="fixture-thirdparty" class="notice notice-warning"><p>Unrelated plugin needs attention.</p></div>
  <div id="fixture-inline" class="notice notice-error inline" data-noobrocket-notice="validation"><p>Correct the setting beside this message.</p></div>
  <div class="inline"><div id="fixture-inline-descendant" class="notice notice-error" data-noobrocket-notice="validation"><p>Nested contextual validation.</p></div></div>`;
  const app = `<div class="noobrocket-app"><div class="noobrocket-toasts" aria-live="polite" data-dismiss="Dismiss this notice" data-success-label="Success" data-error-label="Error" data-warning-label="Warning" data-info-label="Information"></div>
  <div id="fixture-diagnostics" class="notice notice-success" data-noobrocket-toast><p>Preloading resumed.</p></div>
  <div data-noobrocket-inline-feedback><div id="fixture-contextual" class="notice notice-error" data-noobrocket-toast><p>Invalid provider setting.</p></div></div>
  <label for="noobrocket-appearance">Appearance</label><select id="noobrocket-appearance"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select>
  <main id="noobrocket-main"><form id="noobrocket_options" action="/options.php" method="post"><section class="noobrocket-Page" id="preload"><h1>Preload</h1><label for="preload-option">Activate preloading</label><input id="preload-option" name="noobrocket_settings[preload]" value="0"></section></form>
  <div class="noobrocket-settings-footer"><p data-noobrocket-save-state data-clean="No unsaved changes" data-dirty="Unsaved settings" data-saving="Saving…" data-pending="Working…"></p><div class="noobrocket-save-summary"><ul data-noobrocket-changed-sections></ul></div><input id="noobrocket-options-submit" type="submit" form="noobrocket_options" value="Save settings"></div></main></div>`;
  return `<!doctype html><html><head><meta charset="utf-8"><style>.notice { background: #fff; color: #1d2327; padding: 12px; } ${css}</style>${legacy ? '' : '<script src="/appearance.js"></script>'}</head><body>
  ${nativeNotices}${url.searchParams.has('without-app') ? '' : app}
  <script src="/footer.js?document=${++renderCount}${legacy ? '&footer-only=1' : ''}"></script></body></html>`;
};
const server = createServer((request, response) => {
  const url = new URL(request.url, 'http://fixture');
  response.setHeader('Cache-Control', 'no-store');
  if (request.method === 'POST' && url.pathname === '/options.php') {
    let body = '';
    request.on('data', chunk => { body += chunk; });
    request.on('end', () => {
      posts.push(new URLSearchParams(body));
      response.writeHead(303, { Location: '/panel?saved=1' }).end();
    });
    return;
  }
  if (request.method !== 'GET') { response.writeHead(405).end(); return; }
  if (url.pathname === '/appearance.js') {
    response.setHeader('Content-Type', 'text/javascript');
    response.end(appearance);
  } else if (url.pathname === '/footer.js') {
    response.setHeader('Content-Type', 'text/javascript');
    heldFooter = { response, legacy: url.searchParams.has('footer-only') };
  } else {
    response.setHeader('Content-Type', 'text/html');
    response.end(url.pathname === '/storage' ? '<!doctype html><title>Preference setup</title>' : render(url));
  }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
const colors = { light: 'rgb(248, 250, 252)', dark: 'rgb(15, 23, 42)' };
const errors = [];
try {
  const cases = [
    { saved: 'dark', system: 'light', theme: 'dark' },
    { saved: 'light', system: 'dark', theme: 'light' },
    { saved: 'system', system: 'dark', theme: 'dark' },
    { saved: 'system', system: 'light', theme: 'light' },
    { saved: 'invalid', system: 'dark', theme: 'dark' },
    { saved: null, system: 'light', theme: 'light' },
    { saved: null, system: 'dark', theme: 'dark', blocked: true }
  ];
  for (const config of cases) {
    const context = await browser.newContext({ colorScheme: config.system });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${origin}/storage`);
    if (config.saved !== null) await page.evaluate(value => localStorage.setItem('noobrocket-theme', value), config.saved);
    if (config.blocked) await context.addInitScript(() => {
      Storage.prototype.getItem = () => { throw new DOMException('Storage unavailable', 'SecurityError'); };
      Storage.prototype.setItem = () => { throw new DOMException('Storage unavailable', 'SecurityError'); };
    });
    const label = `${config.saved ?? 'missing'}/${config.system}${config.blocked ? '/blocked' : ''}`;
    const beforeFooter = async expected => {
      await page.locator('.noobrocket-app').waitFor({ state: 'visible' });
      const early = await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => {
        const app = document.querySelector('.noobrocket-app');
        resolve({ theme: document.documentElement.dataset.noobrocketTheme, appTheme: app.dataset.theme, background: getComputedStyle(app).backgroundColor, ready: document.readyState });
      })));
      check(early.ready === 'loading' && early.appTheme === undefined, `shell is rendered while footer and DOMContentLoaded are held: ${label}`);
      check(early.theme === expected && early.background === colors[expected], `first rendered shell uses ${expected} before footer: ${label}`);
      check(await page.evaluate(() => document.documentElement.classList.contains('noobrocket-notices-pending')), `owned notice preparation starts in the head: ${label}`);
      for (const id of ['fixture-native-outcome', 'setting-error-settings_updated', 'fixture-diagnostics']) {
        check(!await page.locator(`#${id}`).isVisible(), `no native top/light outcome before footer: ${id}, ${label}`);
      }
      for (const id of ['fixture-thirdparty', 'fixture-inline', 'fixture-inline-descendant', 'fixture-contextual']) {
        check(await page.locator(`#${id}`).isVisible(), `unrelated or contextual feedback stays visible before footer: ${id}, ${label}`);
      }
    };
    const release = async expected => {
      assert.ok(heldFooter, 'footer request is held');
      const { response, legacy } = heldFooter;
      heldFooter = null;
      response.end(`${legacy ? appearance : ''}\n${admin}`);
      await page.waitForLoadState('load');
      check(errors.length === 0, `footer initializes without script errors: ${errors.join('; ')}`);
      check(await page.locator('.noobrocket-app').getAttribute('data-theme') === expected, `app mirrors its initialized theme: ${label}`);
      check(!await page.evaluate(() => document.documentElement.classList.contains('noobrocket-notices-pending')), `DOMContentLoaded clears notice preparation: ${label}`);
      for (const id of ['fixture-native-outcome', 'setting-error-settings_updated', 'fixture-diagnostics']) {
        await page.locator(`#${id}`).waitFor({ state: 'visible' });
        check(await page.locator(`#${id}`).evaluate(node => !!node.closest('.noobrocket-toasts') && node.getClientRects().length > 0), `owned outcome appears in the tray after footer: ${id}, ${label}`);
      }
    };
    if (config.saved === 'dark') {
      const request = page.waitForRequest(request => new URL(request.url()).pathname === '/footer.js');
      await page.goto(`${origin}/panel?footer-only=1`, { waitUntil: 'commit' });
      await request;
      check(await page.locator('.noobrocket-app').evaluate(app => getComputedStyle(app).backgroundColor) === colors.light, 'footer-only initialization reproduces the original light flash');
      await release('dark');
    }
    let request = page.waitForRequest(request => new URL(request.url()).pathname === '/footer.js');
    await page.goto(`${origin}/panel`, { waitUntil: 'commit' });
    await request;
    await beforeFooter(config.theme);
    await release(config.theme);
    const preference = ['light', 'dark', 'system'].includes(config.saved) ? config.saved : 'system';
    check(await page.locator('#noobrocket-appearance').inputValue() === preference, `control reflects validated preference: ${label}`);
    await page.locator('#preload-option').fill('1');
    request = page.waitForRequest(request => new URL(request.url()).pathname === '/footer.js');
    await Promise.all([
      page.waitForURL(`${origin}/panel?saved=1`, { waitUntil: 'commit' }),
      page.locator('#noobrocket-options-submit').click({ noWaitAfter: true })
    ]);
    await request;
    await beforeFooter(config.theme);
    await release(config.theme);
    check(posts.at(-1).get('noobrocket_settings[preload]') === '1' && !posts.at(-1).has('noobrocket-theme'), `native Save submits settings without display preferences: ${label}`);
    const changedSystem = config.system === 'dark' ? 'light' : 'dark';
    await page.emulateMedia({ colorScheme: changedSystem });
    const changedTheme = preference === 'system' ? changedSystem : config.theme;
    await page.waitForFunction(theme => document.documentElement.dataset.noobrocketTheme === theme && document.querySelector('.noobrocket-app').dataset.theme === theme, changedTheme);
    check(await page.locator('.noobrocket-app').evaluate(app => getComputedStyle(app).backgroundColor) === colors[changedTheme], `System updates live while explicit preferences remain stable: ${label}`);
    await page.locator('#noobrocket-appearance').selectOption('dark');
    check(await page.locator('.noobrocket-app').getAttribute('data-theme') === 'dark', `appearance choice applies immediately: ${label}`);
    if (!config.blocked) check(await page.evaluate(() => localStorage.getItem('noobrocket-theme')) === 'dark', `appearance choice keeps existing storage key: ${label}`);
    request = page.waitForRequest(request => new URL(request.url()).pathname === '/footer.js');
    await page.reload({ waitUntil: 'commit' });
    await request;
    const reloadTheme = config.blocked ? changedSystem : 'dark';
    await beforeFooter(reloadTheme);
    await release(reloadTheme);
    await context.close();
  }
  const noJs = await browser.newContext({ javaScriptEnabled: false, colorScheme: 'dark' });
  const page = await noJs.newPage();
  await page.goto(`${origin}/panel`);
  check(await page.locator('.noobrocket-app').evaluate(app => getComputedStyle(app).backgroundColor) === colors.light, 'without JavaScript the existing visible light fallback remains');
  for (const id of ['fixture-native-outcome', 'setting-error-settings_updated', 'fixture-diagnostics', 'fixture-thirdparty', 'fixture-contextual']) {
    check(await page.locator(`#${id}`).isVisible(), `without JavaScript native feedback remains readable: ${id}`);
  }
  await noJs.close();
  for (const withoutApp of [false, true]) {
    const context = await browser.newContext({ colorScheme: 'dark' });
    const fallback = await context.newPage();
    fallback.on('pageerror', error => errors.push(error.message));
    const request = fallback.waitForRequest(request => new URL(request.url()).pathname === '/footer.js');
    await fallback.goto(`${origin}/panel?footer-failed=1${withoutApp ? '&without-app=1' : ''}`, { waitUntil: 'commit' });
    await request;
    check(!await fallback.locator('#fixture-native-outcome').isVisible(), `head preparation hides owned native feedback while footer is held, app absent=${withoutApp}`);
    const { response } = heldFooter;
    heldFooter = null;
    response.end('/* Footer unavailable. */');
    await fallback.waitForLoadState('load');
    check(!await fallback.evaluate(() => document.documentElement.classList.contains('noobrocket-notices-pending')), `DOMContentLoaded clears preparation with unavailable footer, app absent=${withoutApp}`);
    for (const id of ['fixture-native-outcome', 'setting-error-settings_updated', 'fixture-thirdparty', 'fixture-inline', 'fixture-inline-descendant', ...(withoutApp ? [] : ['fixture-diagnostics', 'fixture-contextual'])]) {
      check(await fallback.locator(`#${id}`).isVisible(), `unavailable footer preserves readable native fallback: ${id}, app absent=${withoutApp}`);
    }
    check(await fallback.locator('#fixture-native-outcome').evaluate(node => node.classList.contains('notice') && !node.closest('.noobrocket-toasts')), `unavailable footer leaves source notice intact, app absent=${withoutApp}`);
    await context.close();
  }
  check(errors.length === 0, `no browser script errors: ${errors.join('; ')}`);
  check(posts.length === cases.length, 'all Save probes use one native synthetic POST and redirect');
  console.log(`PASS ${checks} appearance checks; ${posts.length} synthetic native Save redirects; no WordPress writes.`);
} finally {
  heldFooter?.response.end('');
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
