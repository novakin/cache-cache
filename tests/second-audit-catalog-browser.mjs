/** Native regression checks on isolated settings fixtures; no WordPress state or operations. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const { chromium } = createRequire(new URL('../noobrocket/local-css/generator.mjs', import.meta.url))('playwright-core');
const executablePath = process.argv[2];
const php = process.argv[3];
if (!executablePath || !php) throw new Error('Provide Chromium and PHP executables.');
const root = new URL('../', import.meta.url);
const source = await readFile(new URL('noobrocket/assets/js/noobrocket-settings.js', root), 'utf8');
const moduleSource = number => {
  const marker = `],${number}:[function(require,module,exports){`;
  const start = source.indexOf(marker) + marker.length;
  const end = source.indexOf('\n},{', start);
  assert.ok(start >= marker.length && end > start, `settings bundle module ${number} exists`);
  return source.slice(start, end);
};
const [jquery, admin, settingsCss, adminCss] = await Promise.all([
  readFile(new URL('wp-includes/js/jquery/jquery.min.js', process.env.NOOBROCKET_WORDPRESS_SOURCE ? pathToFileURL(`${process.env.NOOBROCKET_WORDPRESS_SOURCE}/`) : new URL('.artifacts/wordpress/', root)), 'utf8'),
  readFile(new URL('noobrocket/assets/js/noobrocket-admin.js', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-settings.css', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-admin.css', root), 'utf8')
]);
const phpFixture = `
define('ABSPATH', '/'); define('NOOBROCKET_ASSETS_IMG_URL', '/');
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
function esc_url($value) { return esc_attr($value); }
function __($value, $domain) { return $value; }
function esc_html_e($value, $domain) { echo esc_html($value); }
function checked($value, $current = true) { if ($value == $current) echo 'checked="checked"'; }
$data = ['id' => 'delay_js_exclusions_selected', 'container_class' => '', 'parent' => '', 'label' => 'One-click exclusions', 'description' => 'Keep selected scripts available.', 'selected' => ['three'], 'items' => [
  'third_parties' => ['has_subcats' => true, 'analytics' => ['svg-icon' => 'category', 'title' => 'Analytics', 'items' => [['id' => 'one', 'title' => 'Analytics One', 'icon' => '/item.svg'], ['id' => 'two', 'title' => 'Analytics Two']]]],
  'wordpress' => ['has_subcats' => true, 'plugins' => ['svg-icon' => 'category', 'title' => 'Plugins', 'items' => [['id' => 'three', 'title' => 'Plugin One']]]]
]];
include $argv[1];`;
const rendered = spawnSync(php, ['-r', phpFixture, fileURLToPath(new URL('noobrocket/views/settings/fields/categorized_multiselect.php', root))], { encoding: 'utf8' });
assert.equal(rendered.status, 0, rendered.stderr);
const checkbox = (id, label, extra = '') => `<div class="noobrocket-checkbox"><input id="${id}" name="noobrocket_settings[${id}]" type="checkbox" value="1" ${extra}><label for="${id}">${label}</label></div>`;
const warning = (id, label, extra = '') => `<div class="noobrocket-warningContainer"><div class="noobrocket-field noobrocket-field--checkbox noobrocket-field--parent">${checkbox(id, label, extra)}</div><div class="noobrocket-fieldWarning"><button type="button" class="noobrocket-button">Activate ${label}</button></div></div>`;
const contextChoice = (kind, value) => `<div class="noobrocket-field noobrocket-field--select noobrocket-field--children" data-parent="control_heartbeat"><div class="noobrocket-select"><select id="heartbeat_${kind}_behavior" name="noobrocket_settings[heartbeat_${kind}_behavior]"><option value="${value}" selected>${value}</option><option value="">Do not limit</option></select><label for="heartbeat_${kind}_behavior">${kind}</label></div></div>`;
const html = `<!doctype html><html><head><meta charset="utf-8"><style>${settingsCss}\n${adminCss}</style></head><body><div class="noobrocket-app" data-theme="light"><div class="noobrocket-body"><nav id="noobrocket-navigation">${['dashboard', 'file_optimization', 'advanced_cache', 'preload', 'heartbeat', 'addons', 'cloudflare', 'sucuri'].map(id => `<a class="noobrocket-menuItem" id="noobrocket-nav-${id}" href="#${id}">${id}</a>`).join('')}</nav><main id="noobrocket-main" class="noobrocket-Content"><form id="noobrocket_options">
<section id="dashboard" class="noobrocket-Page"><h1 tabindex="-1">Overview</h1></section>
<section id="file_optimization" class="noobrocket-Page"><h1 tabindex="-1">CSS &amp; JavaScript</h1><div class="noobrocket-field noobrocket-isParent">${checkbox('minify_js', 'Minify JavaScript')}</div>${warning('minify_concatenate_js', 'Combine JavaScript', 'disabled')}<details class="noobrocket-field-disclosure"><summary>Inline exclusions</summary><div class="noobrocket-field noobrocket-field--children" data-parent="minify_concatenate_js"><label for="exclude_inline_js">Inline exclusions</label><textarea id="exclude_inline_js" name="noobrocket_settings[exclude_inline_js]">saved-menu-handler</textarea></div></details><div class="noobrocket-field noobrocket-isParent">${checkbox('delay_js', 'Delay JavaScript')}</div>${warning('delay_js_execution_safe_mode', 'Safe Mode')}<details class="noobrocket-field-disclosure"><summary>One-click exclusions</summary>${rendered.stdout}</details></section>
<section id="advanced_cache" class="noobrocket-Page"><h1 tabindex="-1">Cache rules</h1><details><summary>Never Cache URLs</summary><div class="noobrocket-field"><label for="cache_reject_uri">Never Cache URLs</label><textarea id="cache_reject_uri" name="noobrocket_settings[cache_reject_uri]"></textarea></div></details></section>
<section id="preload" class="noobrocket-Page"><h1 tabindex="-1">Preload</h1><div class="noobrocket-field noobrocket-isParent">${checkbox('manual_preload', 'Enable preloading')}<p id="manual_preload-description">Enable preloading to use URL exclusions.</p></div><details data-parent="manual_preload" class="noobrocket-field--children"><summary>Preload exclusions</summary><div class="noobrocket-field"><label for="preload_excluded_uri">Excluded URLs</label><textarea id="preload_excluded_uri" name="noobrocket_settings[preload_excluded_uri]"></textarea></div></details></section>
<section id="heartbeat" class="noobrocket-Page"><h1 tabindex="-1">Heartbeat</h1><section class="noobrocket-settings-card"><h2>Heartbeat activity</h2><div class="noobrocket-field noobrocket-isParent">${checkbox('control_heartbeat', 'Control Heartbeat')}</div>${contextChoice('admin', 'reduce_periodicity')}${contextChoice('editor', 'disable')}${contextChoice('site', 'reduce_periodicity')}</section></section>
<section id="addons" class="noobrocket-Page"><h1 tabindex="-1">Integrations</h1>${checkbox('do_cloudflare', 'Cloudflare integration')}${checkbox('sucury_waf_cache_sync', 'Sucuri integration')}</section>
<section id="cloudflare" class="noobrocket-Page"><h1 tabindex="-1">Cloudflare</h1><p data-noobrocket-provider-prerequisite><a href="#addons">Enable Cloudflare in Integrations</a></p><div data-noobrocket-provider-settings hidden><label for="cloudflare_api_key_mask">API key</label><input id="cloudflare_api_key_mask" name="noobrocket_settings[cloudflare_api_key_mask]" type="password" value=""><button type="button" data-noobrocket-secret-toggle aria-controls="cloudflare_api_key_mask" data-show="Show" data-hide="Hide" data-show-label="Show API key" data-hide-label="Hide API key">Show</button></div></section>
<section id="sucuri" class="noobrocket-Page"><h1 tabindex="-1">Sucuri</h1><p data-noobrocket-provider-prerequisite><a href="#addons">Enable Sucuri in Integrations</a></p><div data-noobrocket-provider-settings hidden><input id="sucury_waf_api_key" type="password" value="synthetic-sucuri"></div></section>
<input id="cloudflare_api_key" name="noobrocket_settings[cloudflare_api_key]" type="hidden" value="synthetic-key"></form><div class="noobrocket-settings-footer"><p data-noobrocket-save-state data-clean="No unsaved changes" data-dirty="Unsaved settings"></p><div class="noobrocket-save-summary"><ul data-noobrocket-changed-sections></ul></div><button id="noobrocket-options-cancel" type="button" data-noobrocket-confirm-cancel="Cancel unsaved settings?" hidden disabled>Cancel</button><input form="noobrocket_options" id="noobrocket-options-submit" type="submit" value="Save settings"></div></main></div></div>
<script>${jquery}</script><script>(function(require){${moduleSource(7)}})(function(){});${moduleSource(10)}\n${admin}\njQuery(function(){window.fixtureReady=true;});</script></body></html>`;
let operations = 0;
const server = createServer((request, response) => {
  if (request.method !== 'GET') { operations++; response.writeHead(405).end(); return; }
  if (request.url.endsWith('.svg')) { response.setHeader('Content-Type', 'image/svg+xml'); response.end('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"/>'); return; }
  const savedProvider = new URL(request.url, 'http://fixture').searchParams.get('saved-provider') === '1';
  response.setHeader('Content-Type', 'text/html'); response.end(savedProvider ? html.replace('<input id="do_cloudflare"', '<input id="do_cloudflare" checked') : html);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
try {
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  let visit = 0;
  const fresh = async (hash = 'file_optimization', savedProvider = false) => { await page.goto(`${origin}/?fixture=${++visit}${savedProvider ? '&saved-provider=1' : ''}#${hash}`); await page.waitForFunction(() => window.fixtureReady); };
  const clean = async () => check(await page.locator('#noobrocket-options-submit').isDisabled(), 'clean form disables Save');
  const dirty = async () => check(await page.locator('#noobrocket-options-submit').isEnabled() && (await page.locator('[data-noobrocket-save-state]').innerText()) === 'Unsaved settings', 'native change updates truthful Save state');
  await fresh('dashboard'); await clean();
  await page.evaluate(() => { location.hash = 'advanced_cache'; });
  await page.waitForFunction(() => document.querySelector('#advanced_cache').style.display === 'block');
  await clean();
  check(await page.evaluate(() => new FormData(document.querySelector('#noobrocket_options')).get('noobrocket_settings[exclude_inline_js]')) === 'saved-menu-handler', 'dormant saved inline exclusions remain successful during initialization and navigation');
  await fresh(); await clean();
  check(await page.locator('.noobrocket-list').last().locator('[data-noobrocket-selection-count]').textContent() === '1', 'saved catalog selection initializes count without counting Select all');
  check(await page.locator('#minify_concatenate_js').isDisabled(), 'Combine starts unavailable without minification');
  await page.locator('label[for="minify_js"]').click();
  check(await page.locator('#minify_concatenate_js').isEnabled(), 'draft Minify immediately enables Combine');
  await page.locator('label[for="minify_concatenate_js"]').click();
  check(!await page.locator('#minify_concatenate_js').isChecked(), 'warning keeps option off before acknowledgement');
  await page.getByRole('button', { name: 'Activate Combine JavaScript', exact: true }).click();
  check(await page.locator('#minify_concatenate_js').isChecked() && await page.locator('#exclude_inline_js').isEnabled(), 'acceptance enables option and dependent field');
  check(await page.locator('#minify_concatenate_js').evaluate(field => field === document.activeElement), 'warning acceptance restores native control focus');
  await dirty();
  await page.locator('label[for="delay_js"]').click();
  check(await page.locator('#minify_concatenate_js').isDisabled() && !await page.locator('#minify_concatenate_js').isChecked(), 'draft Delay disables and clears incompatible Combine');
  check(await page.evaluate(() => new FormData(document.querySelector('#noobrocket_options')).get('noobrocket_settings[exclude_inline_js]')) === 'saved-menu-handler', 'turning Combine off preserves dormant inline exclusion patterns for Save');
  await page.locator('label[for="delay_js"]').click();
  check(await page.locator('#minify_concatenate_js').isEnabled(), 'turning Delay off restores valid Combine availability');
  await fresh();
  await page.locator('label[for="delay_js_execution_safe_mode"]').click();
  await clean();
  await page.getByRole('button', { name: 'Activate Safe Mode', exact: true }).focus();
  await page.keyboard.press('Enter');
  check(await page.locator('#delay_js_execution_safe_mode').isChecked() && await page.locator('#delay_js_execution_safe_mode').evaluate(field => field === document.activeElement), 'keyboard warning acceptance keeps value and focus');
  await dirty();
  check(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'accepted warning protects departure through shared draft tracker');
  for (const [width, theme] of [[1440, 'light'], [1440, 'dark'], [390, 'light'], [320, 'dark']]) {
    await page.setViewportSize({ width, height: 960 }); await fresh();
    await page.locator('.noobrocket-app').evaluate((app, value) => app.dataset.theme = value, theme);
    await page.locator('#file_optimization details').last().locator('summary').click();
    const category = page.locator('.noobrocket-list').first();
    const opener = category.locator('.noobrocket-list-header');
    await opener.focus(); await page.keyboard.press('Enter');
    check(await opener.getAttribute('aria-expanded') === 'true' && await category.locator('.noobrocket-list-body').isVisible(), `native category keyboard opens at ${width}/${theme}`);
    check(await category.locator('input').evaluateAll(inputs => inputs.every(field => field.labels?.[0]?.textContent.trim())), 'each catalog checkbox has a native name');
    check(await page.locator('.noobrocket-multiple-select img').evaluateAll(images => images.every(image => image.hasAttribute('alt'))), 'decorative catalog images have alt attributes');
    const first = category.locator('input[name]').first();
    const firstId = await first.getAttribute('id');
    await category.locator(`label[for="${firstId}"]`).click(); await dirty();
    check(await first.isChecked() && await category.locator('[data-noobrocket-selection-count]').innerText() === '1', 'native label selects one value and updates count');
    check(await category.locator('.noobrocket-main-checkbox').evaluate(field => field.indeterminate), 'partial selection gives Select all native mixed state');
    await category.locator('.noobrocket-select-all label').click();
    check(await category.locator('input[name]:checked').count() === 2 && await category.locator('[data-noobrocket-selection-count]').innerText() === '2', 'Select all changes named items and excludes itself from count');
    await first.focus(); await page.keyboard.press('Space');
    check(!await first.isChecked() && await category.locator('[data-noobrocket-selection-count]').innerText() === '1', 'native Space updates selection and count');
    check(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'catalog label/keyboard changes protect departure through shared tracker');
    page.once('dialog', dialog => dialog.accept());
    await page.locator('#noobrocket-options-cancel').click();
    check(await category.locator('input[name]:checked').count() === 0 && await category.locator('[data-noobrocket-selection-count]').textContent() === '0' && !await category.locator('.noobrocket-badge-counter').isVisible(), 'Cancel restores saved selections and clears their visible badge');
    check(await category.locator('.noobrocket-main-checkbox').evaluate(field => !field.checked && !field.indeterminate), 'Cancel clears stale aggregate checked and mixed states');
    const savedCategory = page.locator('.noobrocket-list').last();
    check(await savedCategory.locator('input[name]:checked').count() === 1 && await savedCategory.locator('[data-noobrocket-selection-count]').textContent() === '1' && await savedCategory.locator('.noobrocket-main-checkbox').isChecked(), 'Cancel preserves the saved category selection and truthful aggregate');
    await clean();
    await category.locator('.noobrocket-main-checkbox').evaluate(field => field.click());
    await category.locator('.noobrocket-main-checkbox').evaluate(field => field.click());
    check(await category.locator('input[name]:checked').count() === 0 && !await category.locator('.noobrocket-main-checkbox').isChecked(), 'native Select all toggles named values once in each direction');
    await clean();
    const color = await opener.evaluate(button => ({ text: getComputedStyle(button).color, background: getComputedStyle(button.parentElement).backgroundColor }));
    const luminance = rgb => {
      const channels = rgb.match(/[\d.]+/g).slice(0, 3).map(value => Number(value) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
      return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
    };
    const foreground = luminance(color.text), background = luminance(color.background);
    check((Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05) >= 4.5, `category contrast meets normal text minimum at ${width}/${theme}`);
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `catalog has no page overflow at ${width}/${theme}`);
  }
  await fresh('advanced_cache=cache_reject_uri');
  check(await page.locator('#cache_reject_uri').evaluate(field => field === document.activeElement && field.closest('details').open), 'initial deep link opens ancestor disclosure and focuses field');
  await fresh('preload=preload_excluded_uri');
  check(await page.locator('#manual_preload').evaluate(field => field === document.activeElement && !field.checked), 'inactive deep link focuses prerequisite without enabling it');
  await fresh('heartbeat');
  const heartbeatContexts = page.locator('#heartbeat .noobrocket-field--children[data-parent="control_heartbeat"]');
  check(await heartbeatContexts.evaluateAll(nodes => nodes.length === 3 && nodes.every(node => !node.getClientRects().length)), 'master-off hides all three dormant Heartbeat contexts within the master card');
  const before = await page.evaluate(() => [...new FormData(document.querySelector('#noobrocket_options'))].filter(([name]) => name.startsWith('noobrocket_settings[heartbeat_')));
  check(before.length === 3, 'hidden Heartbeat context values remain successful form fields');
  await page.locator('label[for="control_heartbeat"]').click();
  check(await heartbeatContexts.evaluateAll(nodes => nodes.length === 3 && nodes.every(node => node.getClientRects().length)), 'master-on reveals all three contexts in the same card');
  await page.locator('label[for="control_heartbeat"]').click();
  assert.deepEqual(await page.evaluate(() => [...new FormData(document.querySelector('#noobrocket_options'))].filter(([name]) => name.startsWith('noobrocket_settings[heartbeat_'))), before); checks++;
  await page.locator('label[for="control_heartbeat"]').click();
  check(await heartbeatContexts.evaluateAll(nodes => nodes.length === 3 && nodes.every(node => node.getClientRects().length)), 're-enabling Heartbeat restores all three existing context controls');
  assert.deepEqual(await page.evaluate(() => [...new FormData(document.querySelector('#noobrocket_options'))].filter(([name]) => name.startsWith('noobrocket_settings[heartbeat_'))), before); checks++;
  await page.locator('label[for="control_heartbeat"]').click();
  await page.evaluate(() => { location.hash = 'heartbeat=heartbeat_editor_behavior'; });
  await page.waitForFunction(() => document.activeElement.id === 'control_heartbeat');
  check(!await page.locator('#control_heartbeat').isChecked(), 'dormant context deep link reveals inactive master without changing choices');
  await fresh('heartbeat=heartbeat_editor_behavior');
  check(await page.locator('#control_heartbeat').evaluate(field => field === document.activeElement && !field.checked), 'initial dormant Heartbeat deep link focuses inactive master');
  await fresh('cloudflare=cloudflare_api_key_mask');
  check(await page.locator('#cloudflare [data-noobrocket-provider-prerequisite] a').evaluate(link => link === document.activeElement) && !await page.locator('#do_cloudflare').isChecked(), 'inactive Cloudflare field deep link focuses visible prerequisite link without enabling provider');
  await fresh('sucuri=sucury_waf_api_key');
  check(await page.locator('#sucuri [data-noobrocket-provider-prerequisite] a').evaluate(link => link === document.activeElement) && !await page.locator('#sucury_waf_cache_sync').isChecked(), 'inactive Sucuri field deep link focuses visible prerequisite link without enabling provider');
  check(await page.locator('#cloudflare_api_key_mask').getAttribute('type') === 'password' && await page.locator('#cloudflare_api_key_mask').inputValue() === 'synthetic-key', 'secret proxy keeps real value under native password concealment');
  await fresh('cloudflare', true); await clean();
  await page.evaluate(() => { location.hash = 'addons'; });
  await page.waitForFunction(() => location.hash === '#addons' && document.querySelector('#addons').style.display === 'block');
  await page.evaluate(() => { location.hash = 'cloudflare'; });
  await page.waitForFunction(() => document.querySelector('#cloudflare').style.display === 'block');
  await clean();
  await page.locator('#cloudflare_api_key_mask').focus();
  check(await page.locator('#cloudflare_api_key_mask').getAttribute('type') === 'password', 'focus never reveals secret');
  await page.locator('[data-noobrocket-secret-toggle]').click();
  check(await page.locator('#cloudflare_api_key_mask').getAttribute('type') === 'text' && await page.locator('#cloudflare_api_key').getAttribute('type') === 'hidden', 'explicit Show reveals proxy without changing canonical hidden field');
  await page.locator('[data-noobrocket-secret-toggle]').click();
  check(await page.locator('#cloudflare_api_key_mask').getAttribute('type') === 'password', 'explicit Hide restores concealment');
  await clean();
  await page.locator('#cloudflare_api_key_mask').fill('changed-synthetic-key');
  check(await page.locator('#cloudflare_api_key').inputValue() === 'changed-synthetic-key', 'secret proxy input updates canonical named value');
  await dirty();
  check((await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Cloudflare'), 'canonical secret outside page is attributed to visible Cloudflare field ownership');
  check(errors.length === 0, `settings handlers produce no browser errors: ${errors.join('; ')}`);
  check(operations === 0, 'no fixture operation or WordPress write was submitted');
  console.log(`PASS: ${checks} isolated native-browser catalog/dependency checks, both themes and narrow/desktop sizes.`);
} finally {
  await browser.close(); await new Promise(resolve => server.close(resolve));
}
