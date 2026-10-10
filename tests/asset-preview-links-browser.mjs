/** Native preview navigation proof using synthetic forms and a local endpoint; no WordPress writes. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';

const { chromium } = createRequire(new URL('../noobrocket/local-css/generator.mjs', import.meta.url))('playwright-core');
const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const artifactDirectory = resolve(process.argv[3] || '.artifacts/asset-preview-links-20261010/browser');
const script = await readFile(new URL('../noobrocket/assets/js/noobrocket-admin.js', import.meta.url), 'utf8');
const requests = [];
const checks = [];
const errors = [];
const check = (condition, message) => { assert.ok(condition, message); checks.push(message); };
const fixedFields = { action: 'noobrocket_assets', command: 'preview', _wpnonce: 'synthetic-preview-nonce', draft_revision: 'synthetic-draft-revision' };
let origin;
const fields = command => `<input type="hidden" name="action" value="noobrocket_assets"><input type="hidden" name="command" value="${command}"><input type="hidden" name="_wpnonce" value="${fixedFields._wpnonce}"><input type="hidden" name="draft_revision" value="${fixedFields.draft_revision}">`;
const html = () => `<!doctype html><html><head><meta charset="utf-8"><title>Asset preview proof</title><style>body{font:16px Arial;margin:20px}input,button,a{margin:8px}a{display:inline-block}dialog{max-width:500px}</style></head><body><div class="noobrocket-app"><main id="noobrocket-main">
<form id="noobrocket_options" action="/saved" method="post"><section id="heartbeat" class="noobrocket-Page"><h1>Heartbeat</h1><label for="behavior">Administration</label><select id="behavior" name="noobrocket_settings[heartbeat_admin_behavior]"><option value="reduce">Reduce</option><option value="disable">Disable</option></select></section></form>
<form id="inspect" action="/wp-admin/admin-post.php" method="post">${fields('preview')}<label for="inspect-url">Page URL to inspect</label><input id="inspect-url" name="url" type="url" required value="${origin}/article/"><button type="submit">Inspect page</button></form>
<form id="preview" action="/wp-admin/admin-post.php" method="post" rel="noopener">${fields('preview')}<label for="preview-url">Page URL</label><input id="preview-url" name="url" type="url" required value="${origin}/article/"><button type="submit" class="noobrocket-button" formtarget="_blank" data-noobrocket-preview-link>Preview saved draft</button></form>
<form id="noobrocket-rule-editor" action="/wp-admin/admin-post.php" method="post">${fields('add')}<label for="handle">Asset handle</label><input id="handle" name="rule[handle]" required><button type="submit">Add rule</button></form>
<div id="saved-draft">Saved private draft: theme-navigation on Contact</div><div id="published">Published rules: 1</div>
<form id="publish" action="/wp-admin/admin-post.php" method="post">${fields('publish')}<button type="submit">Publish rules</button></form>
<div class="noobrocket-settings-footer"><p data-noobrocket-save-state data-clean="No unsaved changes" data-dirty="Unsaved settings" data-saving="Saving…" data-pending="Working…" data-unfinished="Leave and lose unfinished rule input?"></p><div class="noobrocket-save-summary"><ul data-noobrocket-changed-sections></ul></div><button id="noobrocket-options-cancel" type="button" hidden disabled data-noobrocket-confirm-cancel="Cancel all unsaved settings?">Cancel</button><input id="noobrocket-options-submit" form="noobrocket_options" type="submit" value="Save settings"></div>
<dialog id="noobrocket-operation-dialog"><h2 id="noobrocket-operation-title" tabindex="-1" data-review="Review: %s">Review action</h2><p id="noobrocket-operation-description" data-saved="Uses saved settings." data-restore="Restoring replaces settings."></p><ul data-noobrocket-operation-changes></ul><button type="button" data-noobrocket-operation-cancel>Cancel</button><button type="button" data-noobrocket-operation-save>Save first</button><button type="button" data-noobrocket-operation-continue data-saved="Run %s using saved settings" data-restore="Restore">Continue</button></dialog>
</main></div><script>${script}</script></body></html>`;
const server = createServer(async (request, response) => {
  const url = new URL(request.url, origin);
  if (url.pathname === '/favicon.ico') { response.writeHead(204); response.end(); return; }
  let body = '';
  for await (const chunk of request) body += chunk;
  requests.push({ method: request.method, path: url.pathname, query: [...url.searchParams], body: [...new URLSearchParams(body)] });
  if (url.pathname === '/wp-admin/admin-post.php') {
    const submitted = request.method === 'POST' ? new URLSearchParams(body) : url.searchParams;
    if (submitted.get('command') === 'preview') {
      response.writeHead(303, { Location: '/public-preview/' });
      response.end();
      return;
    }
  }
  response.setHeader('Content-Type', 'text/html');
  response.end(url.pathname === '/public-preview/' ? '<!doctype html><title>Saved draft preview</title><h1>Saved draft preview</h1>' : html());
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let context;
try {
  context = await browser.newContext();
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  page.on('dialog', async dialog => { errors.push(`Unexpected ${dialog.type()}: ${dialog.message()}`); await dialog.dismiss(); });
  await page.goto(origin + '/settings#assets');
  const link = page.locator('#preview a');
  const input = page.locator('#preview-url');
  check(await link.count() === 1 && await page.locator('#preview button').count() === 0, 'preview is a normal anchor after progressive enhancement');
  check(await link.getAttribute('target') === '_blank' && (await link.getAttribute('rel')).split(' ').includes('noopener'), 'enhanced preview advertises a separate browsing context without an opener');
  check(await link.getAttribute('href') !== null && await link.getAttribute('role') === null, 'native link destination and browser context-menu semantics are retained');

  const selectedUrl = origin + '/article/?one=1&two=with space#section';
  await input.fill(selectedUrl);
  const destination = new URL(await link.getAttribute('href'));
  check(destination.searchParams.get('url') === selectedUrl && Object.entries(fixedFields).every(([name, value]) => destination.searchParams.get(name) === value), 'edited URL is encoded without changing nonce, revision or command');
  await page.locator('#preview').evaluate(form => form.reset());
  await page.waitForFunction(() => new URL(document.querySelector('#preview a').href).searchParams.get('url') === document.querySelector('#preview-url').value);
  check(new URL(await link.getAttribute('href')).searchParams.get('url') === origin + '/article/', 'native form reset updates the link destination to the restored URL');
  await input.fill(selectedUrl);
  await page.locator('#behavior').selectOption('disable');
  await page.locator('#handle').fill('unfinished-theme-script');
  const sourceState = async () => page.evaluate(() => ({
    location: location.href,
    settings: [...new FormData(document.querySelector('#noobrocket_options'))],
    editor: [...new FormData(document.querySelector('#noobrocket-rule-editor'))],
    savedDraft: document.querySelector('#saved-draft').textContent,
    published: document.querySelector('#published').textContent,
    status: document.querySelector('[data-noobrocket-save-state]').textContent,
    saveEnabled: !document.querySelector('#noobrocket-options-submit').disabled,
    cancelEnabled: !document.querySelector('#noobrocket-options-cancel').disabled,
    pending: !!document.querySelector('[data-noobrocket-pending], .noobrocket-operation-pending, [aria-busy="true"]'),
    reviewOpen: document.querySelector('#noobrocket-operation-dialog').open,
    departureGuard: !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))
  }));
  const before = await sourceState();
  check(before.saveEnabled && before.cancelEnabled && before.departureGuard, 'dirty settings and unfinished editor start protected and editable');
  const assertPreserved = async label => {
    check(JSON.stringify(await sourceState()) === JSON.stringify(before), `${label}: source URL, dirty settings, unfinished editor, drafts, controls and departure guard stay unchanged`);
    check(!requests.some(request => request.path === '/saved' || request.body.some(([name]) => name.startsWith('noobrocket_settings['))), `${label}: preview never saves settings`);
  };
  const openPreview = async (label, activate, method = 'GET') => {
    const start = requests.length;
    const opened = context.waitForEvent('page');
    await activate();
    const preview = await opened;
    try { await preview.waitForURL(origin + '/public-preview/', { timeout: 5000 }); }
    catch (error) { throw new Error(`${label}: preview stayed at ${preview.url()}; requests=${JSON.stringify(requests.slice(start))}`, { cause: error }); }
    check(await preview.locator('h1').innerText() === 'Saved draft preview' && await preview.evaluate(() => window.opener === null), `${label}: real preview opens separately with null opener`);
    const request = requests.slice(start).find(item => item.path === '/wp-admin/admin-post.php');
    const submitted = new URLSearchParams(method === 'POST' ? request?.body : request?.query);
    check(request?.method === method && submitted.get('url') === selectedUrl && Object.entries(fixedFields).every(([name, value]) => submitted.get(name) === value), `${label}: native ${method} carries only the selected saved-draft preview request`);
    await assertPreserved(label);
    await preview.close();
  };
  await openPreview('Primary click', () => link.click());
  await openPreview('Repeated primary click', () => link.click());
  await openPreview('Middle click', () => link.click({ button: 'middle' }));
  await openPreview('Control click', () => link.click({ modifiers: ['Control'] }));
  await openPreview('Shift click', () => link.click({ modifiers: ['Shift'] }));
  await openPreview('Keyboard Enter on link', async () => { await link.focus(); await page.keyboard.press('Enter'); });
  await openPreview('Implicit Enter in URL field', async () => { await input.focus(); await page.keyboard.press('Enter'); }, 'POST');

  await page.evaluate(() => { window.previewContextMenus = 0; document.querySelector('#preview a').addEventListener('contextmenu', () => { window.previewContextMenus++; }); });
  await link.click({ button: 'right' });
  check(await page.evaluate(() => window.previewContextMenus) === 1 && requests.at(-1).path === '/public-preview/', 'right click exposes the normal link context menu without submitting or navigating');
  await page.keyboard.press('Escape');
  await assertPreserved('Context menu');

  for (const value of ['', 'invalid URL']) {
    await input.fill(value);
    const count = requests.length;
    const tabs = context.pages().length;
    await link.click();
    await page.waitForTimeout(80);
    check(await input.evaluate(field => !field.validity.valid) && context.pages().length === tabs && requests.length === count, `invalid ${value ? 'URL' : 'empty URL'} blocks primary navigation with native validation`);
    await input.focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(80);
    check(context.pages().length === tabs && requests.length === count, `invalid ${value ? 'URL' : 'empty URL'} blocks implicit native submission`);
  }
  await input.fill('invalid URL');
  const countBeforeMiddle = requests.length;
  const pagesBeforeMiddle = context.pages().length;
  await link.click({ button: 'middle' });
  await page.waitForTimeout(150);
  check(context.pages().length === pagesBeforeMiddle && requests.length === countBeforeMiddle, 'invalid URL also blocks middle-click navigation');
  await input.fill(selectedUrl);

  for (const form of ['inspect', 'publish', 'noobrocket-rule-editor']) {
    const requestCount = requests.length;
    await page.locator(`#${form} button`).click();
    check(await page.locator('#noobrocket-operation-dialog').evaluate(dialog => dialog.open) && requests.length === requestCount, `${form} keeps the existing pending-work action review`);
    await page.locator('[data-noobrocket-operation-cancel]').click();
    await assertPreserved(`${form} review cancel`);
  }
  check(errors.length === 0, 'enhanced interactions produce no script errors or unexpected confirmation dialogs');
  await context.close();

  const fallback = await browser.newContext({ javaScriptEnabled: false });
  const fallbackPage = await fallback.newPage();
  await fallbackPage.goto(origin + '/settings#assets');
  check(await fallbackPage.locator('#preview button').count() === 1 && await fallbackPage.locator('#preview a').count() === 0, 'without JavaScript the native preview submit button remains available');
  await fallbackPage.locator('#preview-url').fill(selectedUrl);
  await fallbackPage.locator('#behavior').selectOption('disable');
  await fallbackPage.locator('#handle').fill('unfinished-without-js');
  const fallbackSource = await fallbackPage.locator('body').innerText();
  const fallbackLocation = fallbackPage.url();
  const fallbackSettings = await fallbackPage.locator('#behavior').inputValue();
  const fallbackEditor = await fallbackPage.locator('#handle').inputValue();
  const fallbackStart = requests.length;
  const fallbackOpened = fallback.waitForEvent('page');
  await fallbackPage.locator('#preview button').click();
  const fallbackPreview = await fallbackOpened;
  await fallbackPreview.waitForURL(origin + '/public-preview/');
  check(await fallbackPreview.locator('h1').innerText() === 'Saved draft preview', 'without JavaScript formtarget opens a real separate preview');
  // Inspect the opener through CDP without enabling document scripts.
  const cdp = await fallback.newCDPSession(fallbackPreview);
  const opener = await cdp.send('Runtime.evaluate', { expression: 'window.opener === null', returnByValue: true });
  check(opener.result.value === true, 'native POST fallback also has a null window opener');
  await cdp.detach();
  const fallbackRequest = requests.slice(fallbackStart).find(request => request.path === '/wp-admin/admin-post.php');
  const fallbackBody = new URLSearchParams(fallbackRequest?.body);
  check(fallbackRequest?.method === 'POST' && fallbackBody.get('url') === selectedUrl && Object.entries(fixedFields).every(([name, value]) => fallbackBody.get(name) === value), 'without JavaScript the native POST preserves preview nonce, saved revision and URL');
  check(fallbackPage.url() === fallbackLocation && await fallbackPage.locator('body').innerText() === fallbackSource && await fallbackPage.locator('#behavior').inputValue() === fallbackSettings && await fallbackPage.locator('#handle').inputValue() === fallbackEditor, 'without JavaScript the source form stays on its page with all edits intact');
  await fallbackPreview.close();
  await fallback.close();
  await mkdir(artifactDirectory, { recursive: true });
  await writeFile(resolve(artifactDirectory, 'proof.json'), JSON.stringify({ result: 'PASS', checks: checks.length, assertions: checks, previewRequests: requests.filter(request => request.path === '/wp-admin/admin-post.php'), limitations: ['Synthetic local responses prove browser behavior, not WordPress authorization or preview token generation.', 'Native browser context menu presence is verified by a real contextmenu event on a normal anchor; its OS-rendered menu entries are outside Playwright DOM access.'] }, null, 2));
  console.log(`PASS ${checks.length} asset preview browser assertions.`);
} finally {
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
