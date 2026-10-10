/** Served generated settings bundle, native jQuery and real Tools template/catalog bytes. Synthetic HTTP outcomes; no site mutation. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const [php, executablePath, wordpress] = process.argv.slice(2);
if (!php || !executablePath || !wordpress) throw new Error('Provide PHP, Chromium and a WordPress core copy.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/generator.mjs', import.meta.url))('playwright-core');
const root = new URL('../', import.meta.url);
const scripts = new Map(await Promise.all([
  ['settings.js', 'noobrocket/assets/js/noobrocket-settings.min.js'],
  ['admin.js', 'noobrocket/assets/js/noobrocket-admin.js'],
  ['settings.css', 'noobrocket/assets/css/noobrocket-settings.css'],
  ['admin.css', 'noobrocket/assets/css/noobrocket-admin.css']
].map(async ([url, path]) => [url, await readFile(new URL(path, root))])));
scripts.set('jquery.js', await readFile(`${wordpress}/wp-includes/js/jquery/jquery.min.js`));
scripts.set('jquery-migrate.js', await readFile(`${wordpress}/wp-includes/js/jquery/jquery-migrate.min.js`));
const views = new Map(['en', 'fr'].map(locale => [locale, execFileSync(php, [fileURLToPath(new URL('./tools-action-feedback-render.php', import.meta.url)), wordpress, locale], { encoding: 'utf8' })]));
const labels = {
  en: ['Error', 'Dismiss this notice'],
  fr: ['Erreur', 'Fermer cette notification']
};
const html = (locale, tray = true) => `<!doctype html><html lang="${locale}"><head><meta charset="utf-8"><link rel="stylesheet" href="/settings.css"><link rel="stylesheet" href="/admin.css"></head><body><div class="noobrocket-app">
${tray ? `<div class="noobrocket-toasts" aria-live="polite" aria-relevant="additions" data-error-label="${labels[locale][0]}" data-dismiss="${labels[locale][1]}"></div>` : ''}
<main id="noobrocket-main" tabindex="-1"><div class="noobrocket-global-feedback"></div><form id="noobrocket_options"><div id="tools" class="noobrocket-Page"><h1 tabindex="-1">Tools</h1>${views.get(locale)}</div><input id="cache_mobile" type="hidden" value="0"><input id="do_caching_mobile_files" type="hidden" value="0"><input id="minify_google_fonts" type="hidden" value="0"></form></main></div>
<script src="/jquery.js"></script><script src="/jquery-migrate.js"></script><script>window.ajaxurl='/ajax';window.noobrocket_ajax_data={nonce:'synthetic-nonce'};</script><script src="/settings.js"></script>${tray ? '<script src="/admin.js"></script>' : ''}</body></html>`;
let outcome = 'refuse';
let requests = 0;
let pending = null;
const server = createServer(async (request, response) => {
  const url = new URL(request.url, 'http://127.0.0.1');
  const asset = scripts.get(url.pathname.slice(1));
  if (asset) {
    response.writeHead(200, { 'Content-Type': url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript' });
    response.end(asset); return;
  }
  if (url.pathname === '/ajax') {
    requests++;
    let body = ''; for await (const chunk of request) body += chunk;
    const payload = new URLSearchParams(body);
    assert.equal(payload.get('_ajax_nonce'), 'synthetic-nonce', 'existing nonce is submitted');
    assert.ok(['noobrocket_enable_mobile_cache', 'noobrocket_enable_google_fonts'].includes(payload.get('action')));
    const finish = () => {
      if (outcome === 'network') { response.destroy(); return; }
      response.writeHead(200, { 'Content-Type': 'application/json' });
      response.end(outcome === 'malformed' ? '{}' : JSON.stringify({ success: outcome === 'success' }));
    };
    if (outcome === 'pending') pending = finish; else finish();
    return;
  }
  response.writeHead(200, { 'Content-Type': 'text/html' });
  response.end(html(url.searchParams.get('locale') || 'en', url.searchParams.get('tray') !== '0'));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true });
let assertions = 0;
const check = (value, description) => { assert.ok(value, description); assertions++; };
try {
  for (const locale of ['en', 'fr']) for (const theme of ['light', 'dark']) for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    const page = await browser.newPage({ viewport });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
    for (const [buttonId, fields] of [
      ['nbr_enable_mobile_cache', ['cache_mobile', 'do_caching_mobile_files']],
      ['noobrocket-action-noobrocket_enable_google_fonts', ['minify_google_fonts']]
    ]) {
      await page.goto(`${origin}/?locale=${locale}#tools`);
      await page.evaluate(theme => document.querySelector('.noobrocket-app').dataset.theme = theme, theme);
      const button = page.locator(`#${buttonId}`);
      outcome = 'pending'; pending = null;
      const before = requests;
      await button.focus(); await page.keyboard.press('Enter');
      await page.waitForFunction(id => document.getElementById(id).disabled, buttonId);
      check(await button.getAttribute('aria-busy') === 'true' && await button.evaluate(node => node.classList.contains('noobrocket-isLoading')), 'pending action is busy and duplicate clicks are disabled');
      await page.evaluate(id => document.getElementById(id).click(), buttonId);
      await page.waitForFunction(() => document.readyState === 'complete');
      while (!pending) await new Promise(resolve => setTimeout(resolve, 5));
      check(requests === before + 1, 'one request for a pending action');
      outcome = 'refuse'; pending(); pending = null;
      await page.waitForFunction(id => !document.getElementById(id).disabled && !document.getElementById(id).hasAttribute('aria-busy'), buttonId);
      check(await button.isVisible() && !await button.evaluate(node => node.classList.contains('noobrocket-isLoading')), 'refused action remains visible and leaves loading state');
      for (const field of fields) check(await page.locator(`#${field}`).inputValue() === '0', 'failure retains hidden saved values');
      const toast = page.locator('.noobrocket-toast.notice-error').last();
      await toast.waitFor();
      check(await toast.innerText().then(text => text.includes(labels[locale][0]) && text.includes(locale === 'fr' ? 'Réessayez' : 'Try again')), 'translated error reaches the existing live tray');
      check(await toast.getAttribute('role') === null && await page.locator('.noobrocket-toasts').getAttribute('aria-live') === 'polite', 'one live region announces the outcome');
      check(await button.evaluate(node => node === document.activeElement), 'failure retains initiating keyboard focus');
      await toast.locator('button').click();
      await button.focus();
      outcome = 'success'; await page.keyboard.press('Space');
      await page.waitForFunction(id => getComputedStyle(document.getElementById(id)).display === 'none', buttonId);
      for (const field of fields) check(await page.locator(`#${field}`).inputValue() === '1', 'successful retry updates requested hidden values');
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'affected output has no horizontal page overflow');
    }
    check(errors.length === 0, `generated bundle has no browser errors: ${errors.join('; ')}`);
    await page.close();
  }
  const page = await browser.newPage();
  for (const failure of ['network', 'malformed']) {
    await page.goto(`${origin}/#tools`);
    outcome = failure;
    await page.locator('#nbr_enable_mobile_cache').click();
    await page.locator('.noobrocket-toast.notice-error').waitFor();
    await page.waitForFunction(() => !document.getElementById('nbr_enable_mobile_cache').disabled);
    check(await page.locator('#nbr_enable_mobile_cache').isEnabled() && await page.locator('#cache_mobile').inputValue() === '0', `${failure} outcome restores a usable action without changing values`);
  }
  await page.goto(`${origin}/?tray=0#tools`);
  outcome = 'refuse'; await page.locator('#nbr_enable_mobile_cache').click();
  const nativeNotice = page.locator('.notice.notice-error[role="alert"]');
  await nativeNotice.waitFor();
  check(await nativeNotice.isVisible(), 'failed shell initialization retains readable native alert feedback');
  await page.close();
  console.log(`PASS: Tools action client feedback, ${assertions} assertions; generated bundle, EN/FR light/dark desktop/narrow, keyboard/retry and failed transport.`);
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
