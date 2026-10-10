/** Actual WordPress/forms + packaged legacy/admin CSS; isolated browser, no WordPress requests. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile, mkdir, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const baseline = process.argv.includes('--baseline');
const root = new URL('../', import.meta.url);
const output = new URL('.artifacts/cache-fixes-20261009/controls/', root);
await mkdir(output, { recursive: true });
const [forms, legacy, css, script] = await Promise.all([
  readFile(new URL('wp-admin/css/forms.css', process.env.NOOBROCKET_WORDPRESS_SOURCE ? pathToFileURL(`${process.env.NOOBROCKET_WORDPRESS_SOURCE}/`) : new URL('.artifacts/wordpress/', root)), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-settings.css', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-admin.css', root), 'utf8'),
  readFile(new URL('noobrocket/assets/js/noobrocket-admin.js', root), 'utf8')
]);
const render = theme => `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>:root{--wp-admin-theme-color:#2271b1}body{margin:0}#wpbody-content{margin-left:180px;padding-top:32px}@media(max-width:783px){#wpbody-content{margin-left:10px;padding-top:46px}}@media(max-width:600px){#wpbody-content{padding-top:0}}</style>
<style>${forms}</style><style>${legacy}</style><style>${css}</style></head><body class="wp-admin"><div id="wpbody-content"><div class="noobrocket-app noobrocket-wrap" data-theme="${theme}">
<div class="noobrocket-brand-bar"><strong class="noobrocket-brand">Cache-Cache</strong><div class="noobrocket-brand-actions"></div><button type="button" class="noobrocket-menu-toggle" aria-controls="noobrocket-navigation" aria-expanded="false">Navigation</button></div>
<div class="noobrocket-body"><nav id="noobrocket-navigation" class="noobrocket-Header" aria-label="Navigation"><div class="noobrocket-navigation-preferences"></div><div class="noobrocket-Header-nav"><a id="noobrocket-nav-preload" class="noobrocket-menuItem isActive" href="#preload"><span class="noobrocket-menuItem-title">Préchargement</span></a><a id="noobrocket-nav-file_optimization" class="noobrocket-menuItem" href="#file_optimization"><span class="noobrocket-menuItem-title">CSS et JavaScript</span></a></div></nav>
<main id="noobrocket-main" class="noobrocket-Content"><form id="noobrocket_options"><section id="preload" class="noobrocket-Page"><h1 class="noobrocket-title1">Contrôles des réglages</h1><div class="noobrocket-settings-card"><h2 class="noobrocket-title2">États et confirmations</h2>
${['Expert', 'Publication', 'Historique', 'Import'].map((label, index) => `<p><label><input class="fixture-native" name="noobrocket_settings[confirmation_${index}]" type="checkbox" checked> ${label}</label></p>`).join('')}
<div class="noobrocket-mode-grid">${['off', 'critical', 'used'].map((mode, index) => `<label class="noobrocket-mode-option"><input name="noobrocket_settings[local_css_mode]" type="radio" value="${mode}" ${index === 0 ? 'checked' : ''}><span><strong>${['Désactivé', 'CSS critique', 'CSS utilisé'][index]}</strong><small>Les fichiers originaux restent disponibles.</small></span></label>`).join('')}</div>
<div class="noobrocket-radio"><input id="fixture-switch" type="checkbox" name="noobrocket_settings[switch]" checked><label for="fixture-switch">Activer le préchargement</label></div>
<div class="noobrocket-checkbox"><input id="fixture-cleanup" type="checkbox" name="noobrocket_settings[cleanup]" checked><label for="fixture-cleanup">Révisions sélectionnées</label></div>
<div class="noobrocket-field"><div class="noobrocket-action-group"><button id="fixture-inspect" type="button" class="noobrocket-button">Inspecter la page</button><button id="fixture-publish" type="button" class="noobrocket-button noobrocket-button-primary" disabled>Publier les règles</button><button id="fixture-purple" type="button" class="noobrocket-button noobrocket-button--purple" disabled>Action indisponible</button></div></div>
<a id="fixture-help" class="noobrocket-infoAction" href="#file_optimization">Aide sur ces réglages</a></div></section><section id="file_optimization" class="noobrocket-Page" hidden><h1 class="noobrocket-title1">CSS et JavaScript</h1></section></form></main></div></div></div><script>${script}</script></body></html>`;
const server = createServer((request, response) => {
  const url = new URL(request.url, 'http://fixture');
  response.setHeader('Content-Type', 'text/html; charset=utf-8');
  response.end(render(url.searchParams.get('theme') === 'dark' ? 'dark' : 'light'));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
const results = [];
let checks = 0;
const check = (value, message) => { if (!baseline) assert.ok(value, message); checks++; return !!value; };
try {
  for (const width of [1440, 1024, 390, 320]) for (const theme of ['light', 'dark']) for (const motion of ['no-preference', 'reduce']) {
    const context = await browser.newContext({ viewport: { width, height: 960 }, reducedMotion: motion, colorScheme: theme });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${origin}/?theme=${theme}#preload`);
    const state = `${width}-${theme}-${motion}`;
    const sample = await page.evaluate(() => {
      const app = document.querySelector('.noobrocket-app');
      const tokenColor = name => { const probe = document.createElement('span'); app.append(probe); probe.style.backgroundColor = `var(--nbr-${name})`; const result = getComputedStyle(probe).backgroundColor; probe.remove(); return result; };
      const checkboxes = [...document.querySelectorAll('.fixture-native')].map(node => ({ background: getComputedStyle(node).backgroundColor, glyph: getComputedStyle(node, '::before').content }));
      const radio = document.querySelector('.noobrocket-mode-option input:checked');
      const button = node => ({ background: getComputedStyle(node).backgroundColor, color: getComputedStyle(node).color, marginTop: getComputedStyle(node).marginTop, marginBottom: getComputedStyle(node).marginBottom });
      const inventory = document.createElement('div');
      inventory.className = 'noobrocket-asset-inventory';
      const inventoryContent = document.createElement('div');
      inventoryContent.style.height = '600px';
      inventory.append(inventoryContent);
      app.append(inventory);
      const boundedInventory = inventory.clientHeight < inventory.scrollHeight && getComputedStyle(inventory).overflowY === 'auto';
      inventory.classList.add('noobrocket-asset-inventory--short');
      const naturalInventory = getComputedStyle(inventory).maxHeight === 'none' && getComputedStyle(inventory).overflowY === 'visible' && inventory.clientHeight >= inventory.scrollHeight;
      inventory.remove();
      return { action: tokenColor('action'), surface: tokenColor('surface'), background: tokenColor('background'), muted: tokenColor('muted'), checkboxes,
        disabled: [button(document.querySelector('#fixture-publish')), button(document.querySelector('#fixture-purple'))],
        radioBefore: { display: getComputedStyle(radio, '::before').display, content: getComputedStyle(radio, '::before').content },
        inspect: button(document.querySelector('#fixture-inspect')), helpHeight: document.querySelector('#fixture-help').getBoundingClientRect().height,
        helpDecoration: getComputedStyle(document.querySelector('#fixture-help')).textDecorationLine,
        overflow: document.documentElement.scrollWidth > innerWidth + 1, contentWidth: document.querySelector('.noobrocket-Content').getBoundingClientRect().width,
        menuVisible: !!document.querySelector('.noobrocket-menu-toggle').getClientRects().length, boundedInventory, naturalInventory };
    });
    check(sample.checkboxes.every(control => control.background === sample.action && control.glyph.includes('svg')), `${state}: visible checked native indicators`);
    check(sample.disabled.every(control => control.background === sample.background && control.color === sample.muted), `${state}: authoritative disabled primary/purple states`);
    check(sample.radioBefore.display === 'none' || sample.radioBefore.content === 'none', `${state}: no competing native radio pseudo indicator`);
    check(sample.inspect.marginTop === '0px' && sample.inspect.marginBottom === '0px', `${state}: action rows own vertical spacing`);
    check(sample.helpHeight >= 44, `${state}: standalone help target`);
    check(sample.helpDecoration === 'none', `${state}: standalone help shares navigation decoration`);
    check(!sample.overflow, `${state}: no horizontal overflow`);
    check(sample.menuVisible === (width <= 1100), `${state}: compact navigation uses available width`);
    check(sample.boundedInventory, `${state}: large asset inventory stays bounded`);
    check(sample.naturalInventory, `${state}: short draft inventory keeps natural height`);
    const inspect = page.locator('#fixture-inspect');
    await inspect.scrollIntoViewIfNeeded();
    const bounds = () => inspect.evaluate(node => { const rect = node.getBoundingClientRect(); return { x: rect.x + scrollX, y: rect.y + scrollY, width: rect.width, height: rect.height }; });
    const before = await bounds();
    await inspect.hover(); await page.waitForTimeout(250);
    const hovered = await bounds();
    await inspect.focus(); await page.waitForTimeout(250);
    const focused = await bounds();
    check([hovered, focused].every(rect => Math.abs(rect.x - before.x) < .25 && Math.abs(rect.y - before.y) < .25 && Math.abs(rect.width - before.width) < .25), `${state}: stationary hover/focus bounds`);
    await page.locator('.fixture-native').first().focus();
    await page.keyboard.press('Space');
    check(!await page.locator('.fixture-native').first().isChecked(), `${state}: native checkbox keyboard behavior`);
    await page.locator('.noobrocket-mode-option input').first().focus();
    await page.keyboard.press('ArrowRight');
    check(await page.locator('.noobrocket-mode-option input').nth(1).isChecked(), `${state}: native grouped-radio keyboard behavior`);
    if (sample.menuVisible) {
      const menu = page.locator('.noobrocket-menu-toggle');
      await menu.focus(); await page.keyboard.press('Enter');
      check(await menu.getAttribute('aria-expanded') === 'true', `${state}: compact navigation opens with keyboard`);
      await page.locator('#noobrocket-nav-file_optimization').focus();
      await page.keyboard.press('Escape');
      check(await menu.getAttribute('aria-expanded') === 'false' && await menu.evaluate(node => node === document.activeElement), `${state}: Escape closes and returns focus`);
    }
    check(errors.length === 0, `${state}: no runtime exceptions`);
    if (motion === 'reduce') await page.screenshot({ path: new URL(`${baseline ? 'before-' : 'after-'}${state}.png`, output).pathname.replace(/^\/([A-Za-z]:)/, '$1'), fullPage: true });
    results.push({ state, ...sample, bounds: { before, hovered, focused }, errors });
    await context.close();
  }
  await writeFile(new URL(`${baseline ? 'before' : 'proof'}.json`, output), JSON.stringify({ baseline, checks, states: results }, null, 2));
  console.log(`${baseline ? 'RECORDED' : 'PASS'}: ${checks} shared control/layout checks across16 theme/width/motion cases; no WordPress writes.`);
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
