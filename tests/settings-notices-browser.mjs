/** Native notice ownership, shared toast semantics and interaction. Synthetic state only; no WordPress. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const { chromium } = createRequire(new URL('../noobrocket/local-css/generator.mjs', import.meta.url))('playwright-core');
const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const root = new URL('../', import.meta.url);
const [settings, admin, settingsCss, adminCss, shell, appearance] = await Promise.all([
  readFile(new URL('noobrocket/assets/js/noobrocket-settings.js', root), 'utf8'),
  readFile(new URL('noobrocket/assets/js/noobrocket-admin.js', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-settings.css', root), 'utf8'),
  readFile(new URL('noobrocket/assets/css/noobrocket-admin.css', root), 'utf8'),
  readFile(new URL('noobrocket/views/settings/page.php', root), 'utf8'),
  readFile(new URL('noobrocket/assets/js/noobrocket-appearance.js', root), 'utf8')
]);
const marker = '],10:[function(require,module,exports){';
const start = settings.indexOf(marker);
const end = settings.indexOf('\n},{', start + marker.length);
assert.ok(start !== -1 && end > start, 'maintained PageManager module exists');
assert.ok(shell.includes('class="noobrocket-global-feedback"') && shell.includes('class="wp-header-end"'), 'maintained shell owns the native global-notice anchor');
const pageManager = settings.slice(start + marker.length, end);
const pages = [
  ['dashboard', 'Site overview'], ['sucuri', 'Sucuri'], ['cloudflare', 'Cloudflare'],
  ['page_cdn', 'Content Delivery'], ['advanced_cache', 'Cache rules'], ['addons', 'Integrations']
];
const errorFixtures = [
  ['sucuri_waf_api_key_invalid', 'Sucuri API key must use the required format.'],
  ['cloudflare_api_key_invalid', 'Cloudflare Zone ID is missing.'],
  ['cdn_cname_invalid_1', 'CDN row 2 has an invalid hostname.'],
  ['cdn_cname_invalid_4', 'CDN row 5 has an invalid hostname.'],
  ['reject_uri_global_exclusion', 'Never Cache URLs cannot exclude every page.']
];
const notice = (id, type, text, attributes = '') => `<div id="${id}" class="settings-error notice notice-${type} is-dismissible" ${attributes}><p>${text}</p></div>`;
// WordPress common.js moves unscoped notices using this selector, before adding native dismiss controls.
// Keep that class contract here; running all of common.js would require unrelated WordPress globals.
const nativeCore = `window.runNativeNoticeCore = () => {
  const target = document.querySelector('.wp-header-end') || document.querySelector('.wrap h1, .wrap h2');
  if (target) {
    let previous = target;
    document.querySelectorAll('div.updated, div.error, div.notice').forEach(notice => {
      if (notice.matches('.inline, .below-h2')) return;
      previous.after(notice);
      previous = notice;
    });
  }
  document.querySelectorAll('.notice.is-dismissible').forEach(notice => {
    if (notice.querySelector('.notice-dismiss')) return;
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'notice-dismiss';
    button.setAttribute('aria-label', 'Dismiss this notice.');
    button.addEventListener('click', () => notice.remove());
    notice.append(button);
  });
};`;
const render = url => {
  const coreFirst = url.searchParams.get('core') === 'before';
  const withErrors = url.searchParams.get('errors') !== '0';
  const settingsUpdated = url.searchParams.get('settings-updated');
  const serverSaveEvidence = !!settingsUpdated && settingsUpdated !== '0';
  const scenario = url.searchParams.get('scenario') || 'ownership';
  const fixtures = {
    ownership: [
      notice('setting-error-settings_updated', 'success', 'Settings saved.'),
      notice('setting-error-noobrocket_history_failed', 'error', 'The change was saved, but history could not be recorded.'),
      notice('site-warning', 'warning', 'Site-wide worker setup is incomplete. <a href="#addons">Review setup</a>', 'data-noobrocket-notice="domain_changed" data-noobrocket-dismissible="false"'),
      notice('thirdparty-warning', 'warning', 'Third-party plugin needs attention.'),
      notice('unknown-actionable-error', 'error', 'External integration failed. <a href="#addons">Review configuration</a>', 'data-noobrocket-notice="fixture_failure"')
    ],
    semantics: [
      notice('probe-failure', 'error', 'The anonymous homepage probe failed.', 'data-noobrocket-toast role="alert" aria-live="assertive"'),
      notice('probe-unverified', 'warning', 'No PHP cache hit header observed.', 'data-noobrocket-toast'),
      notice('preload-resumed', 'success', 'Preloading resumed.', 'data-noobrocket-toast'),
      notice('service-information', 'info', 'The preload service is activated.', 'data-noobrocket-notice="preload_info"')
    ],
    controls: [
      notice('recovery-notice', 'warning', 'Configuration needs recovery. <a id="learn-more" href="#addons">Learn more</a><ul><li>Preserve this recovery instruction.</li></ul><label for="recovery-code">Recovery configuration</label><textarea id="recovery-code" readonly>location / { try_files $uri $uri/ /index.php?$args; }</textarea><button type="submit" id="recovery-action" form="fixture-recovery" class="noobrocket-button">Regenerate configuration</button>', 'data-noobrocket-notice="domain_changed"')
    ],
    'timer-one': [notice('timed-success', 'success', 'Settings saved.', 'data-noobrocket-toast')],
    'timer-list': [notice('timed-success-list', 'success', 'Cleanup complete. <ul><li>3 revisions deleted.</li></ul>', 'data-noobrocket-toast')],
    'save-error': [notice('setting-error-settings_updated', 'error', 'The settings import failed. Previous saved settings are preserved.')],
    timers: [
      notice('timed-success', 'success', 'Settings saved.', 'data-noobrocket-toast'),
      notice('timed-info', 'info', 'Informational completion.', 'data-noobrocket-toast'),
      notice('persistent-error', 'error', 'Probe failed.', 'data-noobrocket-toast'),
      notice('persistent-warning', 'warning', 'Probe unverified.', 'data-noobrocket-toast'),
      notice('persistent-success-action', 'success', 'Configuration saved. <a href="#addons">Review configuration</a>', 'data-noobrocket-toast'),
      notice('persistent-info-details', 'info', 'Recovery information. <ul><li>Preserve this instruction.</li></ul>', 'data-noobrocket-toast'),
      notice('persistent-explicit', 'success', 'Ongoing operation is recorded.', 'data-noobrocket-toast data-noobrocket-persistent'),
      notice('nondismissible-info', 'info', 'Configuration needs an administrator action.', 'data-noobrocket-toast data-noobrocket-dismissible="false"')
    ]
  };
  const records = [
    ...(withErrors ? errorFixtures.map(([code, text]) => notice(`setting-error-${code}`, 'error', text)) : []),
    ...fixtures[scenario]
  ];
  // Native admin notices can precede the app; partial-view outcomes are rendered inside it.
  const notices = records.filter(html => !html.includes(' data-noobrocket-toast')).join('');
  const outcomes = records.filter(html => html.includes(' data-noobrocket-toast')).join('');
  const sections = pages.map(([id, title]) => `<section id="${id}" class="noobrocket-Page"><div class="noobrocket-sectionHeader"><h1 tabindex="-1" class="noobrocket-title1">${title}</h1></div><section class="noobrocket-settings-card"><label for="${id}-field">${title} option</label><input id="${id}-field" name="noobrocket_settings[${id}]" value="saved"><p>Settings for ${title}.</p>${id === 'addons' ? '<div id="contextual-validation" class="notice notice-error inline" data-noobrocket-toast data-noobrocket-inline-feedback><p>Correct the value beside this setting.</p></div>' : ''}</section></section>`).join('');
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>
  html, body { margin: 0; padding: 0; } body { font: 14px/1.5 system-ui; }
  .notice { background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; margin: 5px 0 15px; padding: 1px 12px; position: relative; }
  .notice p { margin: 8px 0; padding: 2px; } .notice.is-dismissible { padding-right: 38px; }
  .notice-dismiss { position: absolute; top: 0; right: 1px; border: 0; background: none; cursor: pointer; padding: 9px; }
  .notice-dismiss::before { content: '×'; font-size: 20px; } .notice-dismiss { width: 38px; height: 38px; }
  ${settingsCss}\n${adminCss}
  </style><script>${appearance}</script></head><body>${notices}<div class="noobrocket-app wrap" data-noobrocket-settings-updated="${serverSaveEvidence}">
  <div class="noobrocket-toasts" aria-live="polite" aria-relevant="additions" data-dismiss="Dismiss this notice" data-success-label="Success" data-error-label="Error" data-warning-label="Warning" data-info-label="Information"></div>
  <header class="noobrocket-brand-bar"><div class="noobrocket-brand">NoobRocket</div><div class="noobrocket-brand-actions"><div class="noobrocket-navigation-preferences"><label class="noobrocket-appearance" for="noobrocket-appearance">Appearance<select id="noobrocket-appearance"><option value="light">Light</option><option value="dark">Dark</option></select></label></div><button type="button" class="noobrocket-menu-toggle" aria-controls="noobrocket-navigation" aria-expanded="false">Settings</button></div></header>
  <div class="noobrocket-global-feedback"><hr class="wp-header-end" hidden>${outcomes}</div>
  <div class="noobrocket-body"><nav id="noobrocket-navigation" class="noobrocket-Header">${pages.map(([id, title]) => `<a id="noobrocket-nav-${id}" class="noobrocket-menuItem" href="#${id}">${title}</a>`).join('')}</nav><main id="noobrocket-main" class="noobrocket-Content" tabindex="-1"><form id="noobrocket_options">${sections}</form><div class="noobrocket-settings-footer"><p data-noobrocket-save-state data-clean="No unsaved changes" data-dirty="Unsaved settings"></p><div class="noobrocket-save-summary"><ul data-noobrocket-changed-sections></ul></div><input form="noobrocket_options" type="submit" id="noobrocket-options-submit" value="Save settings"></div></main></div>
  </div><form id="fixture-recovery" method="post" action="/operation"></form><script>${nativeCore}
  window.fixtureHistoryLength = history.length;
  // WordPress wp_admin_canonical_url removes transient query arguments before plugin footer scripts.
  const canonical = new URL(location.href);
  canonical.searchParams.delete('settings-updated');
  history.replaceState(history.state, '', canonical);
  ${coreFirst ? 'runNativeNoticeCore();' : ''}
  ${pageManager}\n${admin}
  ${coreFirst ? '' : 'runNativeNoticeCore();'}
  document.addEventListener('DOMContentLoaded', () => requestAnimationFrame(() => requestAnimationFrame(() => { window.fixtureReady = true; })));
  </script></body></html>`;
};
let operations = 0;
const server = createServer((request, response) => {
  if (request.method !== 'GET') { operations++; response.writeHead(405).end(); return; }
  response.setHeader('Content-Type', 'text/html');
  response.end(render(new URL(request.url, 'http://fixture')));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
const owners = Object.fromEntries(errorFixtures.map(([code]) => [code, code.startsWith('cdn_') ? 'page_cdn' : code.startsWith('sucuri_') ? 'sucuri' : code.startsWith('cloudflare_') ? 'cloudflare' : 'advanced_cache']));
try {
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const ready = async (target = page) => {
    try { await target.waitForFunction(() => window.fixtureReady, null, { timeout: 5000 }); }
    catch (error) { throw new Error(`Fixture initialization failed: ${errors.join('; ') || error.message}`); }
  };
  for (const [width, theme] of [[1440, 'light'], [1440, 'dark'], [390, 'light'], [390, 'dark'], [320, 'light'], [320, 'dark']]) {
    await page.setViewportSize({ width, height: 960 });
    for (const core of ['before', 'after']) {
      await page.goto(`${origin}/?core=${core}#dashboard`);
      await ready();
      await page.locator('#noobrocket-appearance').evaluate((select, value) => {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }, theme);
      const context = `${width}/${theme}, core ${core}`;
      check(await page.locator('#dashboard .notice-error').count() === 0, `Overview owns no provider validation: ${context}`);
      check(await page.locator('#dashboard').isVisible(), `ordinary visit retains requested Overview: ${context}`);
      for (const [code, owner] of Object.entries(owners)) {
        const notice = page.locator(`#setting-error-${code}`);
        check(await notice.evaluate((node, id) => node.closest('.noobrocket-Page')?.id === id && node.classList.contains('inline'), owner), `${code} stays inline in ${owner}: ${context}`);
      }
      check(await page.locator('#page_cdn .settings-error').evaluateAll(nodes => nodes.map(node => node.id).join(',')) === 'setting-error-cdn_cname_invalid_1,setting-error-cdn_cname_invalid_4', `multiple CDN row errors retain source order: ${context}`);
      check(await page.locator('.noobrocket-global-feedback > .notice').evaluateAll(nodes => nodes.map(node => node.id).join(',')) === 'thirdparty-warning', `unmarked third-party notices retain native shell ownership: ${context}`);
      check(await page.locator('.noobrocket-toasts .noobrocket-toast').count() === 4 && await page.locator('#setting-error-settings_updated').evaluate(node => !node.matches('.notice, .is-dismissible') && !node.querySelector('.notice-dismiss')), `Save and native NoobRocket outcomes share the tray without native dismiss state: ${context}`);
      check(await page.locator('#setting-error-noobrocket_history_failed').evaluate(node => node.closest('.noobrocket-toasts') && node.classList.contains('notice-error') && !node.classList.contains('notice')), `history persistence failure keeps its error severity in the shared tray: ${context}`);
      check(await page.locator('#site-warning').evaluate(node => node.parentElement.classList.contains('noobrocket-toasts') && node.classList.contains('notice-warning')), `configuration warning uses the same tray and retains warning severity: ${context}`);
      check(await page.locator('#site-warning .noobrocket-toast-dismiss').count() === 0, `nondismissible configuration warning preserves its recovery requirement: ${context}`);
      check(await page.locator('#contextual-validation').evaluate(node => node.closest('.noobrocket-Page')?.id === 'addons' && !node.closest('.noobrocket-toasts')), `contextual validation remains beside its setting despite a toast marker: ${context}`);
      check(await page.locator('#unknown-actionable-error').isVisible() && await page.locator('#unknown-actionable-error a').isVisible(), `actionable errors remain persistent and reachable: ${context}`);
      await page.evaluate(() => runNativeNoticeCore());
      check(await page.locator('#unknown-actionable-error').evaluate(node => node.parentElement.classList.contains('noobrocket-toasts') && !node.querySelector('.notice-dismiss')), `another native core pass cannot relocate or duplicate the toast dismiss control: ${context}`);
      for (const [code, owner] of Object.entries(owners)) {
        check(await page.locator(`#setting-error-${code}`).evaluate((node, id) => node.closest('.noobrocket-Page')?.id === id, owner), `.inline survives another native relocation: ${code}, ${context}`);
      }
      for (const [owner] of pages.slice(1, 5)) {
        await page.evaluate(id => { location.hash = id; }, owner);
        await page.waitForFunction(id => document.querySelector(`#${id}`).style.display === 'block', owner);
        check(await page.locator(`#${owner} .notice-error`).evaluateAll(nodes => nodes.length > 0 && nodes.every(node => node.getClientRects().length > 0)), `${owner} exposes only its own validation after navigation: ${context}`);
      }
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `no horizontal overflow: ${context}`);
      await page.locator('#advanced_cache-field').focus();
      await page.waitForTimeout(40);
      check(await page.locator('#advanced_cache-field').evaluate(node => {
        const rect = node.getBoundingClientRect();
        const header = document.querySelector('.noobrocket-brand-bar').getBoundingClientRect();
        const toast = document.querySelector('.noobrocket-toasts').getBoundingClientRect();
        const overlappingToast = document.querySelector('.noobrocket-toasts').childElementCount && rect.right > toast.left && rect.left < toast.right && rect.bottom > toast.top && rect.top < toast.bottom;
        return node === document.activeElement && rect.top >= header.bottom && rect.bottom <= innerHeight && !overlappingToast;
      }), `settings focus remains clear of sticky header and toast: ${context}`);
      await page.goto(`${origin}/?core=${core}&settings-updated=true#dashboard`);
      await ready();
      check(await page.evaluate(() => !new URLSearchParams(location.search).has('settings-updated') && document.querySelector('.noobrocket-app').dataset.noobrocketSettingsUpdated === 'true'), `canonical cleanup preserves only native server Save evidence: ${context}`);
      check(await page.evaluate(() => location.hash === '#sucuri' && history.length === window.fixtureHistoryLength), `post-save error reveals first failed provider without extra history: ${context}`);
      check(await page.locator('#setting-error-sucuri_waf_api_key_invalid').evaluate(node => node === document.activeElement && node.getClientRects().length > 0), `post-save first error receives visible focus: ${context}`);
      check(await page.locator('#setting-error-sucuri_waf_api_key_invalid').evaluate(node => {
        const rect = node.getBoundingClientRect();
        const header = document.querySelector('.noobrocket-brand-bar').getBoundingClientRect();
        const toast = document.querySelector('.noobrocket-toasts').getBoundingClientRect();
        return rect.top >= header.bottom && rect.bottom <= innerHeight && !(rect.right > toast.left && rect.left < toast.right && rect.bottom > toast.top && rect.top < toast.bottom);
      }), `focused validation is clear of sticky header and toast: ${context}`);
      check(await page.locator('#dashboard .notice-error').count() === 0, `post-save Overview retains zero provider errors: ${context}`);
    }
  }
  await page.goto(`${origin}/?core=before&errors=0&settings-updated=true#dashboard`);
  await ready();
  check(await page.locator('#dashboard').isVisible() && await page.evaluate(() => history.length === window.fixtureHistoryLength), 'successful Save without validation retains destination and history');
  await page.locator('#thirdparty-warning .notice-dismiss').click();
  check(await page.locator('#thirdparty-warning').count() === 0 && await page.locator('#unknown-actionable-error').isVisible(), 'native dismiss controls affect only selected notice');
  for (const value of ['', '0']) {
    await page.goto(`${origin}/?core=before&settings-updated=${value}#dashboard`);
    await ready();
    check(await page.evaluate(() => location.hash === '#dashboard' && document.querySelector('.noobrocket-app').dataset.noobrocketSettingsUpdated === 'false'), `empty server Save evidence (${JSON.stringify(value)}) never redirects existing validation`);
  }
  const severityFixtures = [
    ['probe-failure', 'error', 'Error'], ['probe-unverified', 'warning', 'Warning'],
    ['preload-resumed', 'success', 'Success'], ['service-information', 'info', 'Information']
  ];
  for (const [width, theme] of [[1440, 'light'], [1440, 'dark'], [390, 'light'], [390, 'dark'], [320, 'light'], [320, 'dark']]) {
    await page.setViewportSize({ width, height: 960 });
    await page.goto(`${origin}/?core=before&errors=0&scenario=semantics#dashboard`);
    await ready();
    await page.locator('#noobrocket-appearance').evaluate((select, value) => {
      select.value = value; select.dispatchEvent(new Event('change', { bubbles: true }));
    }, theme);
    const context = `${width}/${theme}`;
    check(await page.evaluate(() => document.activeElement === document.body), `standalone feedback never steals focus: ${context}`);
    const icons = [];
    for (const [id, severity, label] of severityFixtures) {
      const toast = page.locator(`#${id}`);
      check(await toast.evaluate((node, expected) => node.parentElement.classList.contains('noobrocket-toasts') && node.classList.contains(`notice-${expected}`), severity), `${id} uses the shared tray with correct severity: ${context}`);
      check((await toast.textContent()).includes(label) && await toast.locator('svg[aria-hidden="true"]').count() === 1, `${id} conveys localized severity through text and icon: ${context}`);
      check(await toast.locator('.noobrocket-toast-dismiss').getAttribute('aria-label') === 'Dismiss this notice', `${id} has a named keyboard dismiss control: ${context}`);
      icons.push(await toast.locator('svg').innerHTML());
    }
    check(new Set(icons).size === 4, `four severities have distinct noncolor icon cues: ${context}`);
    check(await page.locator('.noobrocket-toasts').evaluate(node => node.getAttribute('aria-live') === 'polite' && !node.querySelector('[role="alert"], [role="status"], [aria-live]')), `shared live region owns announcements without duplicate nested alerts: ${context}`);
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `shared severity tray does not overflow horizontally: ${context}`);
    await page.goto(`${origin}/?core=after&errors=0&scenario=controls#dashboard`);
    await ready();
    await page.locator('#noobrocket-appearance').evaluate((select, value) => {
      select.value = value; select.dispatchEvent(new Event('change', { bubbles: true }));
    }, theme);
    check(await page.locator('#recovery-notice').evaluate(node => node.parentElement.classList.contains('noobrocket-toasts') && node.querySelectorAll('ul > li').length === 1 && node.querySelector('li').textContent === 'Preserve this recovery instruction.'), `actionable notice preserves its instruction list: ${context}`);
    check(await page.locator('#recovery-code').inputValue() === 'location / { try_files $uri $uri/ /index.php?$args; }' && await page.locator('#recovery-code').getAttribute('readonly') !== null, `readonly recovery configuration survives toast conversion: ${context}`);
    check(await page.locator('#recovery-action').getAttribute('form') === 'fixture-recovery' && await page.locator('#learn-more').getAttribute('href') === '#addons', `toast conversion preserves operation form ownership and links: ${context}`);
    check(await page.locator('#learn-more').evaluate(node => {
      const style = getComputedStyle(node);
      const background = getComputedStyle(node.closest('.noobrocket-toast')).backgroundColor;
      const luminance = color => {
        const channels = color.match(/[\d.]+/g).slice(0, 3).map(value => {
          const channel = Number(value) / 255;
          return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
        });
        return .2126 * channels[0] + .7152 * channels[1] + .0722 * channels[2];
      };
      const foreground = luminance(style.color); const surface = luminance(background);
      return (Math.max(foreground, surface) + .05) / (Math.min(foreground, surface) + .05) >= 4.5;
    }), `recovery link has readable contrast instead of browser-default blue: ${context}`);
    await page.evaluate(() => {
      window.fixtureActionSubmissions = 0;
      document.querySelector('#fixture-recovery').addEventListener('submit', event => {
        event.preventDefault(); window.fixtureActionSubmissions++;
        window.fixtureActionSubmitter = event.submitter.id;
      });
    });
    await page.locator('#recovery-action').click();
    check(await page.evaluate(() => window.fixtureActionSubmissions === 1 && window.fixtureActionSubmitter === 'recovery-action'), `preserved recovery button remains operable without submitting settings: ${context}`);
    await page.locator('#dashboard-field').focus();
    await page.waitForTimeout(50);
    check(await page.locator('#recovery-notice').count() === 1 && await page.locator('#dashboard-field').evaluate(node => {
      const field = node.getBoundingClientRect();
      const tray = document.querySelector('.noobrocket-toasts').getBoundingClientRect();
      return node === document.activeElement && !(field.right > tray.left && field.left < tray.right && field.bottom > tray.top && field.top < tray.bottom);
    }), `focused setting stays unobscured without losing persistent recovery feedback: ${context}`);
    await page.locator('#dashboard-field').evaluate(node => {
      const spacer = document.createElement('div'); spacer.style.height = '1600px'; node.before(spacer); node.blur();
    });
    await page.locator('#dashboard-field').focus();
    await page.waitForTimeout(50);
    check(await page.locator('#recovery-notice').count() === 1 && await page.locator('#dashboard-field').evaluate(node => {
      const field = node.getBoundingClientRect(); const tray = document.querySelector('.noobrocket-toasts').getBoundingClientRect();
      const header = document.querySelector('.noobrocket-brand-bar').getBoundingClientRect();
      return node === document.activeElement && field.top >= header.bottom && field.bottom <= innerHeight && !(field.right > tray.left && field.left < tray.right && field.bottom > tray.top && field.top < tray.bottom);
    }), `last setting remains visible above a tall persistent tray at the document end: ${context}`);
    await page.locator('#dashboard-field').fill('pending');
    await page.waitForTimeout(50);
    await page.locator('#dashboard-field').evaluate(node => node.blur());
    await page.locator('#dashboard-field').focus();
    await page.waitForTimeout(50);
    check(await page.locator('.noobrocket-settings-footer').isVisible() && await page.locator('#dashboard-field').evaluate(node => {
      const field = node.getBoundingClientRect(); const tray = document.querySelector('.noobrocket-toasts').getBoundingClientRect();
      const footer = document.querySelector('.noobrocket-settings-footer').getBoundingClientRect();
      const crossesTray = field.right > tray.left && field.left < tray.right;
      return node === document.activeElement && field.bottom <= footer.top && (!crossesTray || field.bottom <= tray.top) && !document.querySelector('#recovery-notice').hidden;
    }), `last setting clears both pending Save footer and tall persistent tray: ${context}`);
    await page.locator('#dashboard-field').fill('saved');
    await page.locator('#learn-more').focus();
    check(await page.locator('#learn-more').evaluate(node => node === document.activeElement && node.getClientRects().length > 0), `recovery link remains keyboard reachable: ${context}`);
    await page.locator('#recovery-notice .noobrocket-toast-dismiss').focus();
    await page.keyboard.press('Enter');
    check(await page.locator('#recovery-notice').count() === 0 && await page.evaluate(() => document.activeElement !== document.body && document.activeElement.closest('.noobrocket-app') && document.activeElement.getClientRects().length > 0), `keyboard dismissal removes only the selected notice and restores visible app focus: ${context}`);
  }

  const timedPage = await browser.newPage({ viewport: { width: 1440, height: 960 }, reducedMotion: 'reduce' });
  timedPage.on('pageerror', error => errors.push(error.message));
  await timedPage.clock.install({ time: new Date('2026-10-09T12:00:00Z') });
  await timedPage.clock.pauseAt(new Date('2026-10-09T12:00:01Z'));
  let timedVisitCount = 0;
  const timedVisit = async scenario => {
    // A distinct query ensures repeated fragment URLs create a fresh document.
    await timedPage.goto(`${origin}/?core=after&errors=0&scenario=${scenario}&visit=${++timedVisitCount}#dashboard`);
    await timedPage.clock.runFor(100);
    await ready(timedPage);
  };
  await timedVisit('timers');
  await timedPage.clock.fastForward(9000);
  const timedState = await timedPage.evaluate(() => [...document.querySelectorAll('#timed-success, #timed-info')].map(node => node.id));
  check(timedState.length === 2, 'simple success and informational feedback remain beyond the former eight-second expiry');
  for (const id of ['persistent-error', 'persistent-warning', 'persistent-success-action', 'persistent-info-details', 'persistent-explicit', 'nondismissible-info']) {
    check(await timedPage.locator(`#${id}`).count() === 1, `${id} retains actionable or unresolved feedback alongside completion outcomes`);
  }
  check(await timedPage.locator('#nondismissible-info .noobrocket-toast-dismiss').count() === 0, 'nondismissible metadata preserves the source notice contract');
  await timedPage.clock.fastForward(60000);
  check(await timedPage.locator('.noobrocket-toasts .noobrocket-toast').count() === 8, 'all outcome severities remain readable without a time limit');
  await timedPage.locator('#timed-success .noobrocket-toast-dismiss').focus();
  await timedPage.keyboard.press('Enter');
  check(await timedPage.locator('#timed-success').count() === 0 && await timedPage.locator('#timed-info').isVisible(), 'manual dismissal removes only the chosen completion outcome');
  check(await timedPage.evaluate(() => document.activeElement === document.querySelector('#dashboard h1')), 'keyboard completion dismissal restores visible page heading focus');
  await timedVisit('save-error');
  await timedPage.clock.fastForward(9000);
  check(await timedPage.locator('#setting-error-settings_updated').evaluate(node => node.parentElement.classList.contains('noobrocket-toasts') && node.classList.contains('notice-error')), 'Settings API import failure uses the shared persistent error tray');
  await timedVisit('timer-list');
  check(await timedPage.locator('#timed-success-list li').textContent() === '3 revisions deleted.', 'cleanup completion retains its item list in the shared tray');
  await timedPage.clock.fastForward(9000);
  check(await timedPage.locator('#timed-success-list').isVisible(), 'cleanup completion list remains available beyond the former expiry');
  await timedPage.locator('#timed-success-list .noobrocket-toast-dismiss').click();
  check(await timedPage.locator('#timed-success-list').count() === 0, 'cleanup receipt closes only after explicit dismissal');
  for (const pauseMode of ['hover', 'focus', 'hidden']) {
    await timedVisit('timer-one');
    check(await timedPage.locator('#timed-success').count() === 1, `completion feedback is present before ${pauseMode}`);
    await timedPage.clock.fastForward(2000);
    check(await timedPage.locator('#timed-success').count() === 1, `completion feedback stays available before ${pauseMode}`);
    if (pauseMode === 'hover') await timedPage.locator('#timed-success').hover();
    else if (pauseMode === 'focus') await timedPage.locator('#timed-success .noobrocket-toast-dismiss').focus();
    else await timedPage.evaluate(() => {
      window.fixtureHidden = true;
      Object.defineProperty(document, 'hidden', { configurable: true, get: () => window.fixtureHidden });
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await timedPage.clock.fastForward(12000);
    check(await timedPage.locator('#timed-success').count() === 1, `completion feedback stays readable during ${pauseMode}`);
    if (pauseMode === 'hover') await timedPage.mouse.move(0, 0);
    else if (pauseMode === 'focus') await timedPage.locator('#dashboard-field').focus();
    else await timedPage.evaluate(() => { window.fixtureHidden = false; document.dispatchEvent(new Event('visibilitychange')); });
    await timedPage.clock.fastForward(60000);
    check(await timedPage.locator('#timed-success').isVisible(), `completion feedback does not expire after ${pauseMode} ends`);
    await timedPage.locator('#timed-success .noobrocket-toast-dismiss').focus();
    await timedPage.keyboard.press('Enter');
    check(await timedPage.locator('#timed-success').count() === 0, `completion feedback remains manually dismissible after ${pauseMode}`);
  }
  await timedPage.close();
  check(errors.length === 0, `maintained notice/PageManager scripts produce no browser errors: ${errors.join('; ')}`);
  check(operations === 0, 'fixture issues no operation or WordPress write requests');
  console.log(`PASS: ${checks} native-browser settings/notification checks; core order, semantic severity, controls, keyboard, persistent outcomes; both themes, desktop/390px/320px; synthetic state only.`);
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
