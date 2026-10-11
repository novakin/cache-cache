/** Native disposable WordPress flows. Functional routing disables browser cache; this is not a benchmark. */
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const flags = {};
for (let index = 2; index < process.argv.length; index += 2) {
  const name = process.argv[index];
  if (!['--browser', '--wordpress', '--php', '--out'].includes(name) || !process.argv[index + 1] || flags[name]) throw new Error('Use --browser, --wordpress, --php and --out once each.');
  flags[name] = process.argv[index + 1];
}
const executablePath = flags['--browser'];
const siteArgument = flags['--wordpress'];
const php = flags['--php'];
if (!executablePath || !siteArgument || !php || !flags['--out']) throw new Error('Provide --browser, --wordpress, --php and --out.');
const output = resolve(flags['--out']);
const site = resolve(siteArgument);
await readFile(join(site, '.noobrocket-acceptance-site'));
const fixtureDirectory = join(site, 'wp-content/cache-cache-rich-site');
const contract = JSON.parse(await readFile(join(fixtureDirectory, 'contract.json'), 'utf8'));
const auth = JSON.parse(await readFile(join(dirname(site), 'rich-site-auth.private.json'), 'utf8'));
assert.equal(contract.protocol, 'cache-cache-rich-site-v1', 'Native seed contract matches the browser fixture.');
const origin = new URL(contract.origin).origin;
assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(origin).hostname), 'Only an owned loopback origin is allowed.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const nativeFixture = fileURLToPath(new URL('./wordpress-rich-site.php', import.meta.url));
const report = {
  status: 'RUNNING', protocol: contract.protocol, checks: 0, cases: [], adminStates: [], blocked: [],
  limits: [
    'Native seeded WordPress, copied plugin, real settings, authentication, nonces, responses and maintained browser bytes.',
    'Functional context routing guards egress and disables browser HTTP cache. These timings cannot be used as performance evidence.',
    'All content and assets are synthetic and owned. No external providers or production requests.',
    'Rich page automation checks speculation source/runtime candidates. Native activation has separate preload-links-browser coverage; this fixture does not override browser prerender policy.',
    'Admin screenshots and authentication values are never included in this receipt.'
  ]
};
let checks = 0;
const check = (condition, label) => { assert.ok(condition, label); checks++; };
const textError = error => String(error.message).replace(/https?:\/\/[^\s)]+/g, value => {
  try { const url = new URL(value); return `${url.origin}${url.pathname}`; } catch { return '[URL]'; }
}).slice(0, 500);
async function scenario(name, run) {
  const result = { name, status: 'RUNNING', checks: 0 };
  const before = checks;
  try { Object.assign(result, await run()); result.status = 'PASS'; }
  catch (error) { result.status = 'FAIL'; result.error = textError(error); }
  result.checks = checks - before;
  report.cases.push(result);
}
async function nativePhase(phase) {
  await new Promise((resolvePhase, reject) => {
    const child = spawn(php, [nativeFixture, site, phase], { stdio: ['ignore', 'pipe', 'pipe'] });
    let bytes = 0;
    for (const stream of [child.stdout, child.stderr]) stream.on('data', chunk => { bytes += chunk.length; });
    child.once('error', reject);
    child.once('close', code => code === 0 ? resolvePhase() : reject(new Error(`Native phase ${phase} failed: exit ${code}, ${bytes} private output bytes.`)));
  });
}
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
async function context(options = {}) {
  const result = await browser.newContext({ viewport: { width: 1365, height: 900 }, serviceWorkers: 'block', ...options });
  result.setDefaultTimeout(15000);
  await result.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.origin === origin) return route.continue();
    report.blocked.push({ method: route.request().method(), origin: url.origin, path: url.pathname });
    return route.abort();
  });
  return result;
}
function pageErrors(page) {
  const errors = [];
  page.on('pageerror', error => errors.push(textError(error)));
  return errors;
}
function pageByName(name) {
  const page = Array.isArray(contract.pages) ? contract.pages.find(item => item.name === name) : contract.pages[name];
  assert.ok(page, `Seed declares ${name}.`);
  const url = new URL(page.url || page.path, origin);
  assert.equal(url.origin, origin, `Seed page ${name} is owned.`);
  return { ...page, url: url.href };
}
async function login(target) {
  const page = await target.newPage();
  await page.goto(`${origin}/wp-login.php`, { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(auth.username);
  await page.locator('#user_pass').fill(auth.password);
  await Promise.all([page.waitForURL(url => url.origin === origin && url.pathname.startsWith('/wp-admin/')), page.locator('#wp-submit').click()]);
  check(new URL(page.url()).origin === origin, 'Real native login remains on owned origin.');
  await page.close();
}
async function noOverflow(page, label) {
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${label}: no horizontal overflow.`);
}
async function adminRoute(page, hash) {
  const menu = page.locator('.noobrocket-menu-toggle');
  const navigation = page.locator('#noobrocket-navigation');
  if (await menu.isVisible() && await menu.getAttribute('aria-expanded') !== 'true') await menu.click();
  const link = navigation.locator(`a[href="#${hash}"]`).first();
  await link.focus();
  await page.keyboard.press('Enter');
  await page.waitForFunction(expected => location.hash === `#${expected}` && document.querySelector(`#${CSS.escape(expected)}`)?.getClientRects().length > 0, hash);
}
async function adminMatrix() {
  for (const locale of ['en_US', 'fr_FR']) for (const theme of ['light', 'dark']) for (const width of [1365, 390]) {
    const target = await context({ viewport: { width, height: width === 390 ? 844 : 900 }, colorScheme: theme });
    try {
      await target.addCookies([{ name: 'rich_fixture_locale', value: locale, url: origin }]);
      await login(target);
      const page = await target.newPage();
      const errors = pageErrors(page);
      await page.goto(`${origin}/wp-admin/options-general.php?page=noobrocket#dashboard`, { waitUntil: 'networkidle' });
      const app = page.locator('.noobrocket-app');
      await app.waitFor();
      const menu = page.locator('.noobrocket-menu-toggle');
      if (await menu.isVisible()) await menu.click();
      await page.locator('#noobrocket-appearance').selectOption(theme);
      check(await app.getAttribute('data-theme') === theme, `${locale}/${theme}/${width}: real appearance preference applies.`);
      if (await menu.isVisible()) {
        await page.keyboard.press('Escape');
        check(await menu.getAttribute('aria-expanded') === 'false' && await menu.evaluate(node => document.activeElement === node), `${locale}/${theme}/${width}: compact menu Escape restores trigger focus.`);
      }
      const routes = await page.locator('#noobrocket-navigation .noobrocket-menuItem').evaluateAll(nodes => nodes.map(node => node.hash.slice(1)));
      check(routes.length === 14 && new Set(routes).size === 14, 'Native admin exposes all 14 distinct maintained routes.');
      for (const hash of routes) {
        await adminRoute(page, hash);
        const section = page.locator(`#${hash}`);
        check(await section.isVisible() && await section.locator('h1').first().isVisible(), `${locale}/${theme}/${width}/${hash}: native page title visible after keyboard navigation.`);
        check(await page.locator(`#noobrocket-navigation a[href="#${hash}"]`).first().getAttribute('aria-current') === 'page', `${hash}: current navigation state is exposed.`);
        await noOverflow(page, `${locale}/${theme}/${width}/${hash}`);
        const disclosure = section.locator('details:visible').first();
        if (await disclosure.count()) {
          await disclosure.locator('summary').focus();
          if (!await disclosure.evaluate(node => node.open)) await page.keyboard.press('Enter');
          check(await disclosure.evaluate(node => node.open), `${hash}: native disclosure opens by keyboard.`);
          await noOverflow(page, `${locale}/${theme}/${width}/${hash}/open`);
        }
      }
      check(errors.length === 0, `${locale}/${theme}/${width}: native admin has no JavaScript exceptions.`);
      report.adminStates.push({ locale, theme, width, routes, consoleErrors: errors.length });
    } finally { await target.close(); }
  }
}
async function adminSaveAndCancel() {
  const target = await context();
  try {
    await login(target);
    const page = await target.newPage();
    const errors = pageErrors(page);
    await page.goto(`${origin}/wp-admin/options-general.php?page=noobrocket#file_optimization`, { waitUntil: 'networkidle' });
    const checkbox = page.locator('input[type="checkbox"][name="noobrocket_settings[minify_css]"]');
    const original = await checkbox.isChecked();
    const cancel = page.locator('#noobrocket-options-cancel');
    const submit = page.locator('#noobrocket-options-submit');
    const dirty = page.locator('[data-noobrocket-save-state]');
    await checkbox.setChecked(!original);
    check(await cancel.isVisible() && await cancel.isEnabled(), 'Native edit exposes enabled Cancel.');
    page.once('dialog', dialog => dialog.accept());
    await cancel.click();
    check(await checkbox.isChecked() === original && await cancel.isDisabled(), 'Native Cancel restores saved control state.');
    await checkbox.setChecked(!original);
    check(await dirty.innerText() === await dirty.getAttribute('data-dirty'), 'Native change announces unsaved state.');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), submit.click()]);
    await adminRoute(page, 'file_optimization');
    check(await checkbox.isChecked() === !original, 'Real nonce-authorized settings POST persists changed minify option.');
    check(await page.locator('[data-noobrocket-save-state]').getAttribute('data-saved-evidence') === 'true', 'Native saved state requires redirect evidence.');
    await checkbox.setChecked(original);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), submit.click()]);
    await adminRoute(page, 'file_optimization');
    check(await checkbox.isChecked() === original, 'Real settings POST restores original option.');
    await checkbox.setChecked(!original);
    const nonce = page.locator('#noobrocket_options input[name="_wpnonce"]');
    check(await nonce.count() === 1, 'Native settings mutation includes its WordPress nonce.');
    await nonce.evaluate(node => { node.value = 'invalid-disposable-fixture-nonce'; });
    const rejected = await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), submit.click()]);
    check(rejected[0]?.status() === 403, 'Invalid native settings nonce refuses mutation.');
    await page.goto(`${origin}/wp-admin/options-general.php?page=noobrocket#file_optimization`, { waitUntil: 'networkidle' });
    check(await checkbox.isChecked() === original, 'Refused native settings write leaves persisted value unchanged.');
    report.adminMutationErrors = errors;
    check(errors.length === 0, `Native save/cancel/rejection flow has no JavaScript exceptions: ${errors.join('; ')}`);
  } finally { await target.close(); }
}
async function readyScripts(page) {
  await page.waitForFunction(() => window.richFixtureCore?.dependencyReady === true && window.richFixtureModule?.message === 'RICH_MODULE_READY');
  check(await page.locator('#rich-classic-state').innerText() === 'RICH_CLASSIC_READY', 'Classic dependent script starts after its native dependency.');
  check(await page.locator('#rich-module-state').innerText() === 'RICH_MODULE_READY', 'Retained module resolves relative module-helper import.');
}
async function jsonData(page) {
  const data = page.locator('#rich-jsonld');
  check(await data.getAttribute('type') === 'application/ld+json', 'JSON-LD retains its inert script type.');
  const payload = JSON.parse(await data.textContent());
  check(payload['@type'] === 'WebPage' && payload.name === 'Local synthetic acceptance', 'JSON-LD bytes still parse as original data.');
}
async function frontendMatrix(profile) {
  await nativePhase(`profile-${profile}`);
  const samples = [];
  for (const width of [1365, 390]) {
    const target = await context({ viewport: { width, height: width === 390 ? 844 : 900 } });
    try {
      for (const name of ['Home', 'Article', 'Catalog', 'Media', 'Interactions', 'Modules']) {
        const declared = pageByName(name);
        const page = await target.newPage();
        try {
          const errors = pageErrors(page);
          const response = await page.goto(declared.url, { waitUntil: 'networkidle' });
          check(response?.status() === 200, `${profile}/${width}/${name}: actual public page responds successfully.`);
          await page.locator('#rich-title').waitFor();
          await jsonData(page);
          await noOverflow(page, `${profile}/${width}/${name}`);
          // First genuine user gesture must survive Delay JS's event replay.
          await page.locator('#rich-menu-toggle').click();
          await page.waitForFunction(() => document.querySelector('#rich-menu-toggle').getAttribute('aria-expanded') === 'true');
          check(await page.locator('#rich-menu').isVisible(), `${profile}/${width}/${name}: first menu gesture works.`);
          await readyScripts(page);
          await page.locator('#rich-menu-toggle').click();
          check(await page.locator('#rich-menu-toggle').getAttribute('aria-expanded') === 'false', 'Second menu gesture closes the native menu.');
          if (await page.locator('#rich-dialog-open').count()) {
            await page.locator('#rich-dialog-open').focus();
            await page.keyboard.press('Enter');
            check(await page.locator('#rich-dialog').evaluate(node => node.open), 'Native dialog opens by keyboard.');
            await page.keyboard.press('Escape');
            check(!await page.locator('#rich-dialog').evaluate(node => node.open), 'Native dialog closes with Escape.');
            await page.locator('#rich-tab-two').click();
            check(await page.locator('#rich-tab-two').getAttribute('aria-selected') === 'true' && await page.locator('#rich-panel-two').isVisible(), 'Real tab reveals its associated panel.');
            await page.locator('#rich-carousel-next').click();
            check(await page.locator('#rich-slide-label').innerText() === 'Slide 2 of 3', 'Real carousel advances once.');
          }
          const hero = page.locator('#rich-hero-image');
          await hero.waitFor();
          check(await hero.getAttribute('loading') === 'eager', 'Explicit eager hero is preserved.');
          await hero.evaluate(node => node.decode());
          check(await hero.evaluate(node => node.complete && node.naturalWidth > 0), 'Real responsive hero resource decodes.');
          const images = page.locator('.rich-below-image');
          for (let index = 0; index < await images.count(); index++) {
            const image = images.nth(index);
            await image.scrollIntoViewIfNeeded();
            await page.waitForFunction(position => {
              const node = document.querySelectorAll('.rich-below-image')[position];
              return node && node.complete && node.naturalWidth > 0;
            }, index);
            check(await image.evaluate(node => node.currentSrc.length > 0), 'Real below-fold image loads after scrolling.');
          }
          const background = page.locator('#rich-late-background');
          let backgroundVisible = false;
          if (await background.count()) {
            await background.scrollIntoViewIfNeeded();
            await page.waitForFunction(() => getComputedStyle(document.querySelector('#rich-late-background')).backgroundImage !== 'none');
            backgroundVisible = true;
            check(backgroundVisible, 'Real stylesheet background becomes visible after intersection.');
          }
          await jsonData(page);
          await noOverflow(page, `${profile}/${width}/${name}/interacted`);
          check(errors.length === 0, `${profile}/${width}/${name}: frontend has no JavaScript exceptions.`);
          const source = await response.text();
          samples.push({ name, width, status: response.status(), belowImages: await images.count(), backgroundVisible,
            hasOptimizedAsset: /\/cache\/noobrocket\/min\//.test(source), hasLazyMarkup: /data-(?:lazy-src|noobrocket-lazy-bg|bg)/.test(source),
            moduleOriginalUrlRetained: /module\.js/.test(source), jsonldRetained: true, consoleErrors: errors.length });
        } finally { await page.close(); }
      }
    } finally { await target.close(); }
  }
  if (profile === 'optimized') check(samples.some(sample => sample.hasOptimizedAsset), 'Native optimized profile actually delivers a generated minified asset.');
  return { profile, samples };
}
async function javascriptOff(profile) {
  await nativePhase(`profile-${profile}`);
  const states = [];
  for (const width of [1365, 390]) {
    const target = await context({ javaScriptEnabled: false, viewport: { width, height: width === 390 ? 844 : 900 } });
    try {
      const page = await target.newPage();
      await page.goto(pageByName('Media').url, { waitUntil: 'networkidle' });
      check(await page.locator('#rich-title').isVisible(), `${profile}/${width}: native content remains readable without JavaScript.`);
      const images = page.locator('#rich-main img');
      let decoded = 0;
      for (let index = 0; index < await images.count(); index++) {
        const image = images.nth(index);
        if (!await image.isVisible()) continue;
        if (!await image.getAttribute('src') || (await image.getAttribute('src')).startsWith('data:')) continue;
        await image.scrollIntoViewIfNeeded();
        await image.evaluate(node => node.decode());
        if (await image.evaluate(node => node.complete && node.naturalWidth >= 100)) decoded++;
      }
      check(decoded >= 5, `${profile}/${width}: hero and four real fallback images decode without JavaScript.`);
      await page.locator('#rich-late-background').scrollIntoViewIfNeeded();
      check(await page.locator('#rich-late-background').evaluate(node => getComputedStyle(node).backgroundImage !== 'none'), `${profile}/${width}: stylesheet background survives JavaScript-disabled fallback.`);
      await noOverflow(page, `${profile}/${width}/JavaScript-off`);
      states.push({ width, decoded });
    } finally { await target.close(); }
  }
  return { profile, states };
}
async function formFlow() {
  const target = await context();
  try {
    const status = async () => {
      const url = new URL(contract.status_url, origin);
      assert.equal(url.origin, origin, 'Synthetic status endpoint is owned.');
      const response = await target.request.get(url.href, { maxRedirects: 0 });
      check(response.status() === 200, 'Owned read-only status request does not redirect.');
      const payload = await response.json();
      check(payload.success === true && payload.data.fixture === contract.protocol, 'Native status response identifies the selected fixture.');
      return payload.data.submissions;
    };
    const before = await status();
    const page = await target.newPage();
    await page.goto(pageByName('Form').url, { waitUntil: 'networkidle' });
    await page.locator('#rich-name').fill('Synthetic Browser Visitor');
    const response = await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('#rich-form-submit').click()]);
    check(response[0]?.request().method() === 'POST' && response[0].status() === 200, 'Real native form submits its authorized POST.');
    check((await page.locator('#rich-form-result').innerText()).includes('RICH_FORM_ACCEPTED Synthetic Browser Visitor'), 'Native nonce-authorized handler accepts the actual form.');
    check(await status() === before + 1, 'One user submission causes exactly one native mutation.');
    check(!/\bhit\b/i.test(response[0].headers()['cache-status'] || ''), 'POST receipt is not a shared page-cache hit.');
    await page.goto(pageByName('Form').url, { waitUntil: 'networkidle' });
    await page.locator('#rich-name').fill('Refused Synthetic Visitor');
    await page.locator('#rich-form input[name="_wpnonce"]').evaluate(node => { node.value = 'invalid-fixture-nonce'; });
    const refused = await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('#rich-form-submit').click()]);
    check(refused[0]?.status() === 403 && (await page.locator('body').innerText()).includes('RICH_FORM_DENIED'), 'Real native form refuses an invalid nonce.');
    check(await status() === before + 1, 'Refused form nonce causes no native mutation.');
  } finally { await target.close(); }
}
async function responseBoundaries() {
  const target = await context();
  const receipts = [];
  try {
    const page = await target.newPage();
    for (const name of ['Private', 'NoStore', 'SetCookie', 'Missing', 'Search']) {
      const response = await page.goto(pageByName(name).url, { waitUntil: 'networkidle' });
      const headers = await response.allHeaders();
      check(!/\bhit\b/i.test(headers['cache-status'] || ''), `${name}: excluded/private/action state is not delivered from shared cache.`);
      if (['Private', 'Missing'].includes(name)) check(response.status() === 404, `${name}: anonymous native response remains unavailable.`);
      if (['NoStore', 'SetCookie'].includes(name)) check(/private|no-store/i.test(headers['cache-control'] || ''), `${name}: native privacy header is retained.`);
      if (name === 'SetCookie') check(!!headers['set-cookie'], 'Synthetic Set-Cookie response still sets its cookie.');
      receipts.push({ name, status: response.status(), hasCacheHit: /\bhit\b/i.test(headers['cache-status'] || ''), privateOrNoStore: /private|no-store/i.test(headers['cache-control'] || '') });
    }
    await target.clearCookies();
    await page.goto(pageByName('Article').url, { waitUntil: 'networkidle' });
    check(await page.locator('#rich-session-state').innerText() === 'RICH_SESSION_ANONYMOUS', 'Anonymous session renders public variant.');
    await target.addCookies([{ name: 'wp_woocommerce_session_richfixture', value: 'owned-synthetic-session', url: origin }]);
    const session = await page.goto(pageByName('Article').url, { waitUntil: 'networkidle' });
    check(await page.locator('#rich-session-state').innerText() === 'RICH_SESSION_PRIVATE', 'Native session cookie bypasses anonymous cached markup.');
    check(!/\bhit\b/i.test(session.headers()['cache-status'] || ''), 'Native session cookie request is not a shared hit.');
    await target.clearCookies();
    await page.goto(pageByName('Password').url, { waitUntil: 'networkidle' });
    const password = page.locator('input[name="post_password"]');
    check(await password.isVisible(), 'Native protected page requires its password.');
    await password.fill(auth.password_page_password);
    const authorized = await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.locator('form.post-password-form input[type="submit"]').click()]);
    check(await page.locator('input[name="post_password"]').count() === 0, 'Native password POST grants this browser access.');
    check((await page.locator('#rich-main').innerText()).includes(contract.expected.password), 'Unlocked native page includes its synthetic protected sentinel.');
    check(!/\bhit\b/i.test(authorized[0]?.headers()['cache-status'] || ''), 'Authorized password response is not a shared hit.');
    await target.clearCookies();
    await page.goto(pageByName('Password').url, { waitUntil: 'networkidle' });
    check(await password.isVisible(), 'Fresh anonymous request cannot reuse unlocked protected content.');
    await login(target);
    const loggedIn = await page.goto(pageByName('Article').url, { waitUntil: 'networkidle' });
    check(!/\bhit\b/i.test(loggedIn.headers()['cache-status'] || ''), 'Authenticated native frontend request bypasses shared cache.');
    const privateResponse = await page.goto(pageByName('Private').url, { waitUntil: 'networkidle' });
    check(privateResponse.status() === 200, 'Real authenticated administrator can view native private content.');
    check((await page.locator('#rich-main').innerText()).includes(contract.expected.private), 'Authenticated private page contains its synthetic sentinel.');
    return { receipts };
  } finally { await target.close(); }
}
let originalCspBackground;
async function cspBackground(profile) {
  await nativePhase(`profile-${profile}`);
  const target = await context();
  try {
    await target.addInitScript(() => {
      window.richCspViolations = [];
      document.addEventListener('securitypolicyviolation', event => window.richCspViolations.push({ directive: event.effectiveDirective, blocked: event.blockedURI === 'inline' ? 'inline' : 'resource' }));
    });
    const page = await target.newPage();
    const response = await page.goto(pageByName('CSP').url, { waitUntil: 'networkidle' });
    const policy = response.headers()['content-security-policy'] || '';
    check(/style-src 'self'/.test(policy) && /script-src 'self'/.test(policy), 'Actual native response enforces self-only style/script CSP.');
    await page.locator('#rich-late-background').scrollIntoViewIfNeeded();
    const background = await page.locator('#rich-late-background').evaluate(node => getComputedStyle(node).backgroundImage);
    const observation = { profile, backgroundVisible: background !== 'none', violations: await page.evaluate(() => window.richCspViolations), rewrittenLazyStylesheet: /lazyload-css|noobrocket-lazyload-bg/.test(await response.text()) };
    (report.cspObservations ||= []).push(observation);
    if (profile === 'baseline') {
      check(background !== 'none', 'Original external stylesheet displays its background under enforcing CSP.');
      originalCspBackground = background;
    } else {
      check(background === originalCspBackground && background !== 'none', 'Optimization retains the functional external background under enforcing CSP.');
    }
    return observation;
  } finally { await target.close(); }
}
async function documentEagerness() {
  await nativePhase('profile-eager');
  const target = await context();
  try {
    const page = await target.newPage();
    const response = await page.goto(pageByName('Home').url, { waitUntil: 'networkidle' });
    const source = await response.text();
    check(/noobrocket-document-speculation-rules/.test(source) && /"eagerness":"eager"/.test(source), 'Native footer source visibly declares document Eager.');
    await page.waitForFunction(() => {
      const script = document.querySelector('#noobrocket-document-speculation-rules');
      return script && JSON.parse(script.textContent).prerender?.[0]?.where?.and?.some(condition => condition.href_matches);
    });
    const rule = await page.locator('#noobrocket-document-speculation-rules').evaluate(node => JSON.parse(node.textContent).prerender[0]);
    check(rule.source === 'document' && rule.eagerness === 'eager', 'Real browser replaces inert source with eligible Eager document rules.');
    check(await page.locator('link[rel="prefetch"]').count() === 0, 'Native document preparation does not insert legacy link prefetch.');
    check(await page.locator('script[type="speculationrules"]').count() === 1, 'Native core/plugin coordination keeps one document rule.');
    const destinations = rule.where.and.find(condition => condition.href_matches)?.href_matches || [];
    check(destinations.length > 0, 'Real native page contributes exact eligible destinations.');
    check(destinations.some(item => item.pathname === new URL(pageByName('Next').url).pathname), 'Native Next page is an eligible exact destination.');
    check(!destinations.some(item => /rich-password|rich-private|wp-admin/.test(item.pathname || '')), 'Native document destinations exclude private/password/action paths.');
    return { eligibleDestinations: destinations.length, eagerness: rule.eagerness, nativeActivationClaimed: false };
  } finally { await target.close(); }
}

try {
  await scenario('CSP-original-background', () => cspBackground('baseline'));
  await scenario('baseline-frontend-matrix', () => frontendMatrix('baseline'));
  await scenario('baseline-JavaScript-off', () => javascriptOff('baseline'));
  await scenario('optimized-frontend-matrix', () => frontendMatrix('optimized'));
  await scenario('optimized-JavaScript-off', () => javascriptOff('optimized'));
  await scenario('delay-frontend-matrix', () => frontendMatrix('delay'));
  await scenario('native-form-nonce-POST', formFlow);
  await scenario('native-response-privacy-boundaries', responseBoundaries);
  await scenario('CSP-optimized-background', () => cspBackground('optimized'));
  await scenario('native-document-Eager', documentEagerness);
  await scenario('native-admin-matrix', adminMatrix);
  await scenario('native-settings-save-cancel-nonce', adminSaveAndCancel);
  await scenario('native-final-profile-and-invariants', async () => { await nativePhase('profile-optimized'); await nativePhase('check'); });
} finally {
  await browser.close();
  report.checks = checks;
  if (report.blocked.length) report.cases.push({ name: 'strict-browser-egress', status: 'FAIL', checks: 0, error: `${report.blocked.length} off-origin requests blocked.` });
  report.status = report.cases.length && report.cases.every(item => item.status === 'PASS') ? 'PASS' : 'FAIL';
  await mkdir(dirname(output), { recursive: true });
  await writeFile(output, JSON.stringify(report, null, 2));
}
if (report.status !== 'PASS') throw new Error(`Rich WordPress browser proof failed: ${report.cases.filter(item => item.status === 'FAIL').map(item => item.name).join(', ')}.`);
console.log(`PASS: ${checks} rich WordPress browser assertions; ${report.cases.length} scenarios; ${report.adminStates.length} native admin states.`);
