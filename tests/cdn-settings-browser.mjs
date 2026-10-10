/** Native CDN-row regression proof using maintained PHP, CSS and settings handlers. No WordPress state or operations. */
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
const [source, jquery, admin, settingsCss, adminCss] = await Promise.all([
  readFile(new URL('noobrocket/assets/js/noobrocket-settings.js', root), 'utf8'),
  readFile(new URL('wp-includes/js/jquery/jquery.min.js', process.env.NOOBROCKET_WORDPRESS_SOURCE ? pathToFileURL(`${process.env.NOOBROCKET_WORDPRESS_SOURCE}/`) : new URL('.artifacts/wordpress/', root)), 'utf8'),
  readFile(new URL('noobrocket/assets/js/noobrocket-admin.js', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-settings.css', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-admin.css', root), 'utf8'),
]);
const moduleSource = number => {
  const marker = `],${number}:[function(require,module,exports){`;
  const start = source.indexOf(marker) + marker.length;
  const end = source.indexOf('\n},{', start);
  assert.ok(start >= marker.length && end > start, `settings bundle module ${number} exists`);
  return source.slice(start, end);
};
const phpFixture = `
define('ABSPATH', '/');
require dirname($argv[1], 4) . '/inc/Engine/Admin/Settings/Settings.php';
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_html($value) { return esc_attr($value); }
function __($value, $domain) { return $value; }
function esc_html_e($value, $domain) { echo esc_html($value); }
function esc_attr_e($value, $domain) { echo esc_attr($value); }
function selected($value, $current) { if ($value == $current) echo 'selected="selected"'; }
function wpm_apply_filters_typed($type, $name, $default) { return $default; }
function get_noobrocket_option($name, $default) {
  return ['cdn_cnames' => ['cdn.one.test', 'https://cdn.two.test/static'], 'cdn_zone' => ['css', 'images']][$name] ?? $default;
}
$data = ['label' => 'CDN hostnames', 'description' => 'Enter the hostname supplied by your CDN provider.'];
include $argv[1];`;
const rendered = spawnSync(php, ['-r', phpFixture, fileURLToPath(new URL('noobrocket/views/settings/fields/cnames.php', root))], { encoding: 'utf8' });
assert.equal(rendered.status, 0, rendered.stderr);
const html = `<!doctype html><html><head><meta charset="utf-8"><style>${settingsCss}\n${adminCss}</style></head><body>
<div class="noobrocket-app"><div class="noobrocket-body">
<nav id="noobrocket-navigation"><a id="noobrocket-nav-dashboard" class="noobrocket-menuItem" href="#dashboard">Overview</a><a id="noobrocket-nav-page_cdn" class="noobrocket-menuItem" href="#page_cdn">Content Delivery</a><a id="noobrocket-nav-media" class="noobrocket-menuItem" href="#media">Media</a></nav>
<main id="noobrocket-main" class="noobrocket-Content"><form id="noobrocket_options" action="/saved" method="post">
<section id="dashboard" class="noobrocket-Page"><h1 tabindex="-1">Overview</h1></section>
<section id="page_cdn" class="noobrocket-Page"><h1 tabindex="-1">Content Delivery</h1><section class="noobrocket-settings-card noobrocket-cdn-settings">${rendered.stdout}</section></section>
<section id="media" class="noobrocket-Page"><h1 tabindex="-1">Media</h1><label for="image_dimensions">Image Dimensions</label><input id="image_dimensions" name="noobrocket_settings[image_dimensions]" type="checkbox" value="1"></section>
</form><div class="noobrocket-settings-footer"><p data-noobrocket-save-state data-clean="No unsaved changes" data-dirty="Unsaved settings" data-saving="Saving…"></p><div class="noobrocket-save-summary"><ul data-noobrocket-changed-sections></ul></div><input form="noobrocket_options" id="noobrocket-options-submit" type="submit" value="Save settings"></div></main></div></div>
<script>${jquery}</script><script>
window.fixtureSubmissions = 0;
document.querySelector('#noobrocket_options').addEventListener('submit', event => { event.preventDefault(); window.fixtureSubmissions++; });
(function(require){${moduleSource(7)}})(function(){});${moduleSource(10)}\n${admin}
jQuery(function(){window.fixtureReady=true;});</script></body></html>`;
let operations = 0;
const server = createServer((request, response) => {
  if (request.method !== 'GET') { operations++; response.writeHead(405).end(); return; }
  response.setHeader('Content-Type', 'text/html'); response.end(html);
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
  const fresh = async () => {
    await page.goto(`${origin}/?fixture=${++visit}#page_cdn`);
    await page.waitForFunction(() => window.fixtureReady);
  };
  const hosts = () => page.locator('#noobrocket-cnames-list [data-noobrocket-cname]');
  const save = () => page.locator('#noobrocket-options-submit');
  const submit = async () => { await save().focus(); await page.keyboard.press('Enter'); };
  const clean = async () => check(await save().isDisabled(), 'unchanged rows disable Save');
  const dirty = async () => check(await save().isEnabled() && await page.locator('[data-noobrocket-save-state]').innerText() === 'Unsaved settings', 'native CDN edits update the shared dirty state');
  const associations = async () => {
    check(await page.locator('#noobrocket-cnames-list input, #noobrocket-cnames-list select').evaluateAll(fields => {
      const ids = fields.map(field => field.id);
      return new Set(ids).size === ids.length && fields.every(field => field.labels.length === 1 && field.labels[0].getClientRects().length && field.labels[0].textContent.trim());
    }), 'every saved or added control has a unique ID and visible native label: ' + JSON.stringify(await page.locator('#noobrocket-cnames-list input, #noobrocket-cnames-list select').evaluateAll(fields => fields.map(field => ({ id: field.id, labels: [...field.labels].map(label => ({ text: label.textContent.trim(), visible: label.getClientRects().length > 0 })) })))));
    check(await hosts().evaluateAll(inputs => inputs.every(input => input.getAttribute('aria-describedby').split(/\s+/).every(id => !!document.getElementById(id)))), 'every hostname describes existing help and its own inline error');
    check(await page.locator('#noobrocket-cnames-list .noobrocket-multiple-close').evaluateAll(buttons => buttons.every(button => {
      const rect = button.getBoundingClientRect();
      return rect.width >= 44 && rect.height >= 44;
    })), 'every rendered Remove target is at least 44px in both dimensions');
  };
  for (const [width, theme] of [[1440, 'light'], [1440, 'dark'], [390, 'light'], [320, 'dark']]) {
    await page.setViewportSize({ width, height: 960 });
    await fresh();
    await page.locator('.noobrocket-app').evaluate((app, value) => app.dataset.theme = value, theme);
    await clean();
    check(await hosts().count() === 2 && await page.locator('#noobrocket-cname-model').evaluate(template => template.content.querySelectorAll('input').length === 1), 'row template remains inert while saved rows render normally');
    check(await page.evaluate(() => [...new FormData(document.querySelector('#noobrocket_options'))].filter(([name]) => name.includes('[cdn_cnames]')).map(([, value]) => value).join('|')) === 'cdn.one.test|https://cdn.two.test/static', 'inert template adds no blank successful hostname value');
    await associations();

    await page.getByRole('button', { name: 'Add hostname', exact: true }).click();
    await dirty();
    check(await hosts().count() === 3 && await hosts().last().evaluate(field => field === document.activeElement), 'Add appends a row and focuses its hostname');
    await associations();
    check((await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Content Delivery'), 'added row retains Content Delivery ownership');
    await page.locator('#noobrocket-cnames-list .noobrocket-multiple-close').last().click();
    check(await hosts().count() === 2 && await hosts().last().evaluate(field => field === document.activeElement), 'removing the last added row focuses the previous hostname');
    await clean();
    await page.locator('#noobrocket-cnames-list .noobrocket-multiple-close').first().click();
    await dirty();
    check(await hosts().count() === 1 && await hosts().first().evaluate(field => field === document.activeElement), 'removing a saved row focuses the next hostname');
    check((await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Content Delivery'), 'removed saved row retains section ownership');
    await page.locator('#noobrocket-cnames-list .noobrocket-multiple-close').click();
    check(await hosts().count() === 0 && await page.getByRole('button', { name: 'Add hostname', exact: true }).evaluate(button => button === document.activeElement), 'removing every row focuses Add hostname');
    await page.getByRole('button', { name: 'Add hostname', exact: true }).click();
    await associations();

    const input = hosts().first();
    await input.fill(' bad host ');
    await dirty();
    await submit();
    await page.waitForFunction(() => document.querySelector('#noobrocket-cnames-list input') === document.activeElement);
    check(await page.evaluate(() => window.fixtureSubmissions === 0), 'invalid hostname blocks native submission');
    check(await input.inputValue() === ' bad host ' && await input.getAttribute('aria-invalid') === 'true' && await page.locator('[data-noobrocket-cname-error]').last().isVisible(), 'rejected hostname remains editable with a visible associated error');
    check(await input.evaluate(field => field === document.activeElement), 'validation returns focus to the invalid hostname');
    await input.fill('cdn.recovered.test');
    check(await input.evaluate(field => field.checkValidity()) && await input.getAttribute('aria-invalid') === null && await page.locator('[data-noobrocket-cname-error]').last().isHidden(), 'correcting the value clears validity and inline error');
    for (const value of ['', 'cdn.example.test', 'https://cdn.example.test/static', 'http://cdn.example.test:8080/assets?version=1', '//cdn.example.test/assets']) {
      await input.fill(value);
      check(await input.evaluate(field => field.checkValidity()), `optional hostname and HTTP(S) path accepted: ${value || '(empty)'}`);
    }
    for (const value of ['ftp://cdn.example.test/assets', 'javascript:alert(1)', 'https://bad..example.test']) {
      await input.fill(value);
      await submit();
      check(await page.evaluate(() => window.fixtureSubmissions === 0) && await input.inputValue() === value && await input.getAttribute('aria-invalid') === 'true', `unsupported scheme or malformed host rejected without dropping input: ${value}`);
    }
    await input.fill('https://cdn.recovered.test/assets');
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `rows and errors fit ${width}/${theme}`);
    await submit();
    check(await page.evaluate(() => window.fixtureSubmissions === 1), 'valid corrected hostname reaches the intercepted native submission');

    await fresh();
    await page.locator('.noobrocket-app').evaluate((app, value) => app.dataset.theme = value, theme);
    await hosts().first().fill('first invalid host');
    await hosts().last().fill('ftp://cdn.example.test/assets');
    await page.evaluate(() => { location.hash = 'media'; });
    await page.waitForFunction(() => document.querySelector('#media').style.display === 'block');
    check(await hosts().first().isHidden(), 'invalid CDN draft can be hidden behind another settings page');
    await submit();
    await page.waitForFunction(() => location.hash === '#page_cdn' && document.querySelector('#noobrocket-cnames-list input') === document.activeElement);
    check(await page.locator('#page_cdn').isVisible() && await hosts().first().isVisible() && await hosts().first().evaluate(field => field === document.activeElement), 'native invalid event opens Content Delivery and focuses the first invalid hostname');
    check(await page.evaluate(() => window.fixtureSubmissions === 0) && await hosts().first().inputValue() === 'first invalid host', 'off-page validation blocks submission and preserves rejected input');
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `off-page recovery fits ${width}/${theme}`);
  }
  check(errors.length === 0, `maintained handlers produce no browser errors: ${errors.join('; ')}`);
  check(operations === 0, 'no fixture operation or WordPress write was submitted');
  console.log(`PASS: ${checks} isolated native-browser CDN checks, both themes and narrow/desktop sizes.`);
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
