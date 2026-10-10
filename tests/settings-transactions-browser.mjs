/** Native-browser transaction proof on synthetic forms. No WordPress or live operations. */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const { chromium } = createRequire(new URL('../noobrocket/local-css/generator.mjs', import.meta.url))('playwright-core');
const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const script = await readFile(new URL('../noobrocket/assets/js/noobrocket-admin.js', import.meta.url), 'utf8');
const toastTray = '<div class="noobrocket-toasts" aria-live="polite" aria-relevant="additions" data-dismiss="Dismiss this notice" data-info-label="Information"></div>';
const html = `<!doctype html><html><head><meta charset="utf-8"></head><body><div class="noobrocket-app">${toastTray}
<button type="button" class="noobrocket-menu-toggle" aria-expanded="false">Settings</button><nav id="noobrocket-navigation"><a class="noobrocket-menuItem" href="#database">Database</a><a class="noobrocket-menuItem" href="#heartbeat">Heartbeat</a><select id="noobrocket-appearance"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></nav><main id="noobrocket-main">
<form id="noobrocket_options" action="/saved" method="post">
<div id="database" class="noobrocket-Page"><h1>Database</h1><div class="noobrocket-field"><label for="database_auto_drafts">Auto Drafts <span class="noobrocket-field-count">3 drafts in your database.</span></label><input id="database_auto_drafts" name="noobrocket_settings[database_auto_drafts]" type="checkbox" value="1"></div><label for="schedule_automatic_cleanup">Schedule Automatic Cleanup</label><input id="schedule_automatic_cleanup" name="noobrocket_settings[schedule_automatic_cleanup]" type="checkbox" value="1"><select id="automatic_cleanup_frequency" name="noobrocket_settings[automatic_cleanup_frequency]"><option value="daily">Daily</option><option value="weekly">Weekly</option></select><button id="cleanup" type="button" data-noobrocket-cleanup-review>Review and run cleanup</button></div>
<div id="heartbeat" class="noobrocket-Page"><h1>Heartbeat</h1><label for="heartbeat_admin_behavior">Administration</label><select id="heartbeat_admin_behavior" name="noobrocket_settings[heartbeat_admin_behavior]"><option value="reduce_periodicity">Reduce</option><option value="disable">Disable</option></select></div><div id="file_optimization" class="noobrocket-Page"><h1>CSS & JavaScript</h1><fieldset><legend>CSS delivery mode</legend><label for="css-off">Original CSS</label><input id="css-off" type="radio" name="noobrocket_settings[local_css_mode]" value="off" checked><label for="css-used">Used CSS</label><input id="css-used" type="radio" name="noobrocket_settings[local_css_mode]" value="used"></fieldset></div>
<div id="page_cdn" class="noobrocket-Page"><h1>Content Delivery</h1>
<div id="noobrocket-cnames-list"><div id="saved-cdn-row"><input name="noobrocket_settings[cdn_cnames][0]" aria-label="CDN CNAME" value="cdn.example.com"><select name="noobrocket_settings[cdn_zone][0]" aria-label="Files to serve from this CDN"><option value="all">All files</option></select></div></div>
</div><div id="cloudflare" class="noobrocket-Page"><h1>Cloudflare</h1><input id="cloudflare_api_key" type="hidden" name="noobrocket_settings[cloudflare_api_key]" value="synthetic-saved-key"><label for="cloudflare_api_key_mask">Global API key</label><input id="cloudflare_api_key_mask" type="password" name="noobrocket_settings[cloudflare_api_key_mask]" value=""><input id="cloudflare_zone_id_mask" name="noobrocket_settings[cloudflare_zone_id_mask]" value=""></div></form>
<form id="noobrocket-database-cleanup" action="/cleanup" method="post"><input name="action" value="noobrocket_database_cleanup" type="hidden"><input id="noobrocket-cleanup-confirmed" name="confirmed" value="1" type="checkbox" required hidden><div data-noobrocket-cleanup-scope></div></form>
<form id="diagnostic" action="/operation" method="post"><input name="action" value="check_cache" type="hidden"><button name="scope" value="homepage">Check cache</button></form>
<button type="button" class="noobrocket-asset-create" data-asset-type="script" data-asset-handle="theme-navigation" data-asset-page="12">Create rule</button>
<form id="noobrocket-rule-editor" action="/asset" method="post"><select name="rule[type]"><option value="style">CSS</option><option value="script">JavaScript</option></select><input name="rule[handle]" required><select id="noobrocket-rule-scope" name="rule[scope]"><option value="post">One page</option><option value="post_type">Post type</option><option value="template">Template</option><option value="everywhere">Everywhere</option></select><input id="noobrocket-rule-expert" name="rule_expert" value="1" type="checkbox"><div data-asset-raw><input id="noobrocket-rule-value" name="rule[value]"></div><div data-asset-scope="post"><select id="noobrocket-rule-page" name="rule_page"><option value="">Choose page</option><option value="12">Contact</option></select></div><div data-asset-scope="post_type"><select id="noobrocket-rule-post-type" name="rule_post_type"><option value="page">Pages</option></select></div><div data-asset-scope="template"><select id="noobrocket-rule-template" name="rule_template"><option value="default">Default</option></select></div><div data-asset-scope="everywhere">All pages</div><input name="rule[exceptions]"><button>Add rule</button></form>
<div id="saved-private-draft">Private draft: contact-form on Contact</div><div id="published-rules">Published: theme-navigation on Contact</div>
<form id="discard-draft" action="/asset" method="post"><input type="hidden" name="action" value="noobrocket_assets"><input type="hidden" name="command" value="discard"><button type="submit" name="scope" value="private-draft" data-noobrocket-confirm-discard="Discard your private draft? Published rules stay unchanged.">Discard draft</button></form>
<div class="noobrocket-settings-footer"><p data-noobrocket-save-state data-clean="No unsaved changes" data-dirty="Unsaved settings" data-saving="Saving…" data-saved="Saved" data-pending="Working…" data-schedule-off="Off" data-unfinished="Leave and lose unfinished rule input?" data-replace-rule="Replace unfinished input?"></p><div class="noobrocket-save-summary"><ul data-noobrocket-changed-sections></ul></div><div class="noobrocket-save-actions"><div class="noobrocket-action-group"><button id="noobrocket-options-cancel" type="button" data-noobrocket-confirm-cancel="Cancel all unsaved settings? Saved settings and private asset drafts stay unchanged." data-noobrocket-cancel-url="/settings?page=noobrocket" hidden disabled>Cancel</button><input form="noobrocket_options" id="noobrocket-options-submit" type="submit" value="Save settings"></div></div></div>
</main><dialog id="noobrocket-cleanup-dialog"><h2 id="noobrocket-cleanup-title" tabindex="-1">Review database cleanup</h2><p id="noobrocket-cleanup-description">Permanent deletion. Back up first.</p><ul data-noobrocket-cleanup-selection></ul><p data-noobrocket-cleanup-empty hidden>Select a category.</p><details data-noobrocket-cleanup-pending hidden><summary>Pending edits discarded</summary><ul data-noobrocket-cleanup-changes></ul></details><button type="button" data-noobrocket-cleanup-cancel>Cancel</button><button type="button" data-noobrocket-cleanup-confirm>Save and run</button></dialog>
<dialog id="noobrocket-operation-dialog"><h2 id="noobrocket-operation-title" tabindex="-1" data-review="Review: %s">Review pending action</h2><p id="noobrocket-operation-description" data-saved="Uses saved settings." data-restore="Restoring replaces saved settings and discards pending edits."></p><p data-noobrocket-restore-retention hidden>Saving settings first can remove this oldest snapshot.</p><ul data-noobrocket-operation-changes></ul><button type="button" data-noobrocket-operation-cancel>Cancel</button><button type="button" data-noobrocket-operation-save>Save settings first</button><button type="button" data-noobrocket-operation-continue data-saved="Run %s using saved settings" data-restore="Restore snapshot and discard pending edits">Continue using saved settings</button></dialog>
</div><script>${script}</script></body></html>`;
let requestBody = '';
let posted;
const posts = [];
const gets = [];
let holdNextResponse = false;
let releaseResponse;
const server = createServer((request, response) => {
  if (request.method === 'POST') {
    let body = '';
    request.on('data', chunk => { body += chunk; });
    request.on('end', () => {
      requestBody = body;
      posts.push({ path: request.url, body });
      const finish = () => { response.setHeader('Content-Type', 'text/html'); response.end(html); };
      if (holdNextResponse) { holdNextResponse = false; releaseResponse = finish; }
      else setTimeout(finish, 500);
      posted?.();
    });
  } else {
    gets.push(request.url);
    response.setHeader('Content-Type', 'text/html');
    response.end(new URL(request.url, 'http://fixture').searchParams.has('no-tray') ? html.replace(toastTray, '') : html);
  }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
try {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const fresh = async () => { await page.goto(origin); };
  const cancelNextSubmit = async target => {
    await page.evaluate(target => {
      window.canceledSubmission = new Promise(resolve => {
        const owner = target === 'document' ? document : window;
        owner.addEventListener('submit', event => {
          event.preventDefault();
          const submission = { trusted: event.isTrusted, form: event.target.id, submitter: event.submitter?.id || event.submitter?.name };
          // The next task follows all submit listeners and the pending-state reset.
          setTimeout(() => resolve(submission), 0);
        }, { once: true });
      });
    }, target);
  };
  await fresh();
  await page.locator('.noobrocket-menu-toggle').click();
  await page.locator('#noobrocket-appearance').focus();
  await page.keyboard.press('Tab');
  check(await page.locator('.noobrocket-menu-toggle').getAttribute('aria-expanded') === 'false', 'menu closes when keyboard focus moves from last navigation control to main');
  await page.locator('.noobrocket-menu-toggle').click();
  await page.keyboard.press('Escape');
  check(await page.locator('.noobrocket-menu-toggle').evaluate(button => button === document.activeElement && button.getAttribute('aria-expanded') === 'false'), 'Escape closes navigation and returns focus');
  check(await page.locator('#noobrocket-options-submit').evaluate(button => button.form.id === 'noobrocket_options' && button.disabled), 'external Save keeps native settings form ownership and starts clean');
  check(await page.locator('#noobrocket-options-cancel').evaluate(button => button.type === 'button' && button.hidden && button.disabled && button.form === null), 'Cancel starts hidden and disabled without native submit ownership');
  await page.evaluate(() => {
    document.querySelector('#cloudflare_api_key_mask').value = 'synthetic-saved-key';
    document.querySelector('#cloudflare_zone_id_mask').value = '••••••';
    document.querySelector('#cloudflare_api_key_mask').dispatchEvent(new Event('input', { bubbles: true }));
  });
  check(await page.locator('#noobrocket-options-submit').isDisabled(), 'populating display-only Cloudflare proxies does not falsely dirty saved settings');
  await page.evaluate(() => {
    document.querySelector('#cloudflare_api_key').value = 'synthetic-edited-key';
    document.querySelector('#cloudflare_api_key_mask').dispatchEvent(new Event('input', { bubbles: true }));
  });
  check(await page.locator('#noobrocket-options-submit').isEnabled(), 'concrete credential edit is still tracked through its proxy event');

  await fresh();
  const savedSettings = await page.locator('#noobrocket_options').evaluate(form => [...new FormData(form)]);
  const privateDraftBeforeCancel = await page.locator('#saved-private-draft').innerText();
  const publishedBeforeCancel = await page.locator('#published-rules').innerText();
  await page.locator('#heartbeat_admin_behavior').selectOption('disable');
  await page.evaluate(() => {
    document.querySelector('#saved-cdn-row').remove();
    document.querySelector('#noobrocket-cnames-list').insertAdjacentHTML('beforeend', '<div id="new-cdn-row"><input name="noobrocket_settings[cdn_cnames][1]" aria-label="CDN CNAME" value="invalid edited CDN" required pattern="[a-z.]+"></div>');
    document.querySelector('#cloudflare_api_key').value = 'synthetic-edited-key';
    document.querySelector('#cloudflare_api_key_mask').value = 'synthetic-edited-key';
    document.querySelector('#cloudflare_zone_id_mask').value = 'edited-proxy';
    document.querySelector('#page_cdn').dispatchEvent(new Event('change', { bubbles: true }));
    window.location.hash = 'heartbeat';
  });
  await page.locator('.noobrocket-asset-create').click();
  const pendingSettings = await page.locator('#noobrocket_options').evaluate(form => [...new FormData(form)]);
  const pendingRule = await page.locator('#noobrocket-rule-editor').evaluate(form => [...new FormData(form)]);
  const beforeSettingsCancel = posts.length;
  const cancelPrompts = [];
  const declineSettingsCancel = async dialog => {
    cancelPrompts.push({ type: dialog.type(), message: dialog.message() });
    await dialog.dismiss();
  };
  page.on('dialog', declineSettingsCancel);
  await page.locator('#noobrocket-options-cancel').click();
  check(cancelPrompts.length === 1 && cancelPrompts[0].type === 'confirm' && cancelPrompts[0].message.includes('all unsaved settings') && cancelPrompts[0].message.includes('unfinished rule input'), 'Cancel reviews all optimization edits and separately warns about unfinished asset input');
  check(JSON.stringify(await page.locator('#noobrocket_options').evaluate(form => [...new FormData(form)])) === JSON.stringify(pendingSettings) && JSON.stringify(await page.locator('#noobrocket-rule-editor').evaluate(form => [...new FormData(form)])) === JSON.stringify(pendingRule) && posts.length === beforeSettingsCancel, 'declined Cancel preserves invalid fields, structural edits, credentials and unfinished rule input without posting');
  check(await page.locator('#noobrocket-options-cancel').evaluate(button => button === document.activeElement && !button.disabled) && await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))) && new URL(page.url()).hash === '#heartbeat', 'declined Cancel retains its focus, current page and unsaved-work departure guard');
  page.off('dialog', declineSettingsCancel);
  const acceptSettingsCancel = async dialog => {
    cancelPrompts.push({ type: dialog.type(), message: dialog.message() });
    await dialog.accept();
  };
  page.on('dialog', acceptSettingsCancel);
  const getsBeforeCancel = gets.length;
  await page.locator('#noobrocket-options-cancel').click();
  await page.waitForLoadState('networkidle');
  check(new URL(page.url()).hash === '#heartbeat' && cancelPrompts.length === 2 && posts.length === beforeSettingsCancel && gets.length === getsBeforeCancel, 'accepted Cancel restores in place, retains its page and makes no document request or POST');
  check(JSON.stringify(await page.locator('#noobrocket_options').evaluate(form => [...new FormData(form)])) === JSON.stringify(savedSettings) && await page.locator('#saved-cdn-row').count() === 1 && await page.locator('#new-cdn-row').count() === 0, 'in-place Cancel restores the complete saved structure and canonical/proxy credential values');
  check(await page.locator('#noobrocket_options').evaluate(form => form.checkValidity()) && await page.locator('#noobrocket-options-submit').isDisabled() && !await page.locator('#noobrocket-options-cancel').isVisible() && await page.locator('#saved-private-draft').innerText() === privateDraftBeforeCancel && await page.locator('#published-rules').innerText() === publishedBeforeCancel, 'Cancel returns to valid clean settings without discarding saved private drafts or published rules');
  await page.locator('#heartbeat_admin_behavior').selectOption('disable');
  const sameUrlGets = gets.length;
  await page.locator('#noobrocket-options-cancel').click();
  await page.waitForLoadState('networkidle');
  check(new URL(page.url()).hash === '#heartbeat' && gets.length === sameUrlGets && posts.length === beforeSettingsCancel && cancelPrompts.length === 3 && !cancelPrompts[2].message.includes('unfinished rule input') && await page.locator('#heartbeat_admin_behavior').inputValue() === 'reduce_periodicity', 'repeated Cancel restores saved values in place and warns only about existing pending work');
  page.off('dialog', acceptSettingsCancel);

  await fresh();
  await page.evaluate(() => {
    document.querySelector('#saved-cdn-row').remove();
    document.querySelector('#page_cdn').dispatchEvent(new Event('change', { bubbles: true }));
  });
  check(await page.locator('#noobrocket-options-submit').isEnabled() && (await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Content Delivery'), 'removing saved fields keeps their original dirty section ownership');
  await page.locator('#diagnostic button').click();
  const removedFieldReview = await page.locator('[data-noobrocket-operation-changes]').innerText();
  check(removedFieldReview.includes('CDN CNAME') && removedFieldReview.includes('Files to serve from this CDN') && !removedFieldReview.includes('noobrocket_settings['), 'removed controls retain human labels in pending operation review');
  await page.locator('[data-noobrocket-operation-cancel]').click();
  await fresh();
  await page.locator('#database_auto_drafts').check();
  await page.locator('#heartbeat_admin_behavior').selectOption('disable');
  await page.locator('#css-used').check();
  check((await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Database') && (await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Heartbeat'), 'dirty summary names both changed sections');
  await page.locator('#cleanup').click();
  check((await page.locator('[data-noobrocket-cleanup-selection]').innerText()).includes('Auto Drafts: 3 drafts') && (await page.locator('[data-noobrocket-cleanup-changes]').innerText()).includes('Heartbeat: Administration') && await page.locator('[data-noobrocket-cleanup-pending]').isVisible(), 'cleanup reviews category/count and warns about discarded pending edits');
  check(((await page.locator('[data-noobrocket-cleanup-selection]').innerText()).match(/3 drafts/g) || []).length === 1, 'inline database count appears once in cleanup review instead of becoming part of its field label');
  check((await page.locator('[data-noobrocket-cleanup-changes]').innerText()).includes('CSS & JavaScript: CSS delivery mode') && !(await page.locator('[data-noobrocket-cleanup-changes]').innerText()).includes('Original CSS'), 'changed radio group is named by its legend, not the first option');
  check(await page.locator('#noobrocket-cleanup-title').evaluate(heading => heading === document.activeElement), 'cleanup starts at its review heading');
  await page.locator('[data-noobrocket-cleanup-cancel]').click();
  check(await page.locator('#database_auto_drafts').isChecked() && await page.locator('#heartbeat_admin_behavior').inputValue() === 'disable', 'cleanup cancel preserves both drafts');
  await page.locator('#diagnostic button').click();
  check(await page.locator('#noobrocket-operation-dialog').evaluate(dialog => dialog.open) && (await page.locator('[data-noobrocket-operation-changes]').innerText()).includes('Heartbeat'), 'operation offers saved-state review with draft scope');
  await page.locator('[data-noobrocket-operation-cancel]').click();
  check(await page.locator('#heartbeat_admin_behavior').inputValue() === 'disable', 'operation cancel preserves settings draft');
  await page.evaluate(() => {
    document.querySelector('.noobrocket-app').insertAdjacentHTML('beforeend', '<form id="restore-oldest" data-noobrocket-operation-name="Restore October 1" data-snapshot-save-evicts="1"><input name="command" type="hidden" value="restore"><button type="submit">Restore snapshot</button></form>');
  });
  await page.locator('#restore-oldest button').click();
  check(await page.locator('#noobrocket-operation-title').innerText() === 'Review: Restore October 1', 'restore review identifies selected snapshot');
  check(await page.locator('[data-noobrocket-operation-save]').isDisabled() && await page.locator('[data-noobrocket-restore-retention]').isVisible(), 'save-first branch cannot evict selected oldest restore target');
  check((await page.locator('[data-noobrocket-operation-continue]').innerText()).includes('discard pending edits'), 'restore explicitly owns replacement and pending-edit loss');
  await page.keyboard.press('Escape');
  check(await page.locator('#restore-oldest button').evaluate(button => button === document.activeElement), 'restore cancellation returns focus to initiating action');
  await page.locator('#restore-oldest').evaluate(form => form.dataset.snapshotSaveEvicts = '0');
  await page.locator('#restore-oldest button').click();
  check(await page.locator('[data-noobrocket-operation-save]').isEnabled() && !await page.locator('[data-noobrocket-restore-retention]').isVisible(), 'non-evicting restore retains save-first choice');
  await page.locator('[data-noobrocket-operation-cancel]').click();
  await fresh();
  await page.locator('#cleanup').click();
  check(await page.locator('[data-noobrocket-cleanup-confirm]').isDisabled() && await page.locator('[data-noobrocket-cleanup-empty]').isVisible(), 'empty cleanup cannot be confirmed');
  await page.locator('[data-noobrocket-cleanup-cancel]').click();
  await page.locator('.noobrocket-asset-create').click();
  check(await page.locator('[name="rule[type]"]').inputValue() === 'script' && await page.locator('[name="rule[handle]"]').inputValue() === 'theme-navigation' && await page.locator('#noobrocket-rule-page').inputValue() === '12', 'inventory action starts a page-specific rule');
  check(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'unfinished rule protects departure');
  await page.locator('#noobrocket-rule-expert').check();
  await page.locator('#noobrocket-rule-value').fill('abc');
  check(await page.locator('#noobrocket-rule-value').evaluate(field => !field.checkValidity()), 'expert page IDs have native numeric validity');
  await page.locator('#noobrocket-rule-scope').selectOption('everywhere');
  check(await page.locator('#noobrocket-rule-value').isDisabled() && await page.locator('#noobrocket-rule-page').isDisabled(), 'everywhere requires no hidden scope value');
  await fresh();
  await page.evaluate(() => {
    document.querySelector('.noobrocket-app').insertAdjacentHTML('beforeend', '<input id="qa-key" type="password" value="synthetic-value"><button type="button" data-noobrocket-secret-toggle aria-controls="qa-key" aria-pressed="false" data-show="Show" data-hide="Hide" data-show-label="Show API key" data-hide-label="Hide API key">Show</button>');
  });
  await page.locator('#qa-key').focus();
  await page.keyboard.press('Tab');
  check(await page.locator('#qa-key').getAttribute('type') === 'password', 'focus and Tab do not reveal a secret');
  await page.keyboard.press('Enter');
  check(await page.locator('#qa-key').getAttribute('type') === 'text' && await page.locator('[data-noobrocket-secret-toggle]').getAttribute('aria-label') === 'Hide API key', 'explicit Show exposes synthetic credential and names reverse action');
  await page.locator('[data-noobrocket-secret-toggle]').click();
  check(await page.locator('#qa-key').getAttribute('type') === 'password' && await page.locator('#noobrocket-options-submit').isDisabled(), 'Hide conceals credential without dirtying settings');
  await fresh();
  holdNextResponse = true;
  const operationsBeforePending = posts.length;
  const responseReceived = page.waitForURL(origin + '/operation').catch(error => error);
  const postReceived = new Promise(resolve => { posted = resolve; });
  const pending = await page.evaluate(() => {
    const form = document.querySelector('#diagnostic');
    form.requestSubmit(form.querySelector('button'));
    return new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => {
      const button = form.querySelector('button');
      const feedback = {
        busy: form.getAttribute('aria-busy'), label: button.textContent,
        accessibleLabel: button.getAttribute('aria-label'), role: button.querySelector('span')?.getAttribute('role'),
        pendingButtons: form.querySelectorAll('button.noobrocket-operation-pending').length,
        pendingToasts: document.querySelectorAll('.noobrocket-toasts .noobrocket-operation-pending').length,
      };
      form.requestSubmit(form.querySelector('button'));
      feedback.stillBusy = form.getAttribute('aria-busy') === 'true' && form.querySelector('button').getAttribute('aria-disabled') === 'true';
      resolve(feedback);
    })));
  });
  await postReceived;
  check(pending.busy === 'true' && pending.pendingButtons === 1 && pending.label === 'Working…' && pending.pendingToasts === 0, 'delayed native operation keeps progress inside its button without a duplicate toast');
  check(pending.accessibleLabel === 'Check cache: Working…' && pending.role === 'status', 'busy button retains its action context and status semantics');
  check(pending.stillBusy && posts.length === operationsBeforePending + 1 && posts.at(-1).path === '/operation', 'button-owned progress preserves guards and repeat submission sends no second request');
  check(new URLSearchParams(requestBody).get('scope') === 'homepage' && new URLSearchParams(requestBody).get('action') === 'check_cache', 'pending feedback preserves native submitter name and value');
  releaseResponse();
  releaseResponse = null;
  check(!((await responseReceived) instanceof Error), 'delayed native operation finishes navigation');

  await page.goto(`${origin}/?no-tray=1`);
  holdNextResponse = true;
  const fallbackReceived = page.waitForURL(origin + '/operation');
  const fallbackPost = new Promise(resolve => { posted = resolve; });
  const fallback = await page.evaluate(() => {
    const form = document.querySelector('#diagnostic');
    const input = document.createElement('input');
    input.type = 'submit'; input.name = 'scope'; input.value = 'homepage';
    form.append(input);
    form.requestSubmit(input);
    const notice = form.querySelector('.noobrocket-operation-pending');
    return { label: notice?.textContent, role: notice?.getAttribute('role'), value: input.value };
  });
  await fallbackPost;
  check(fallback.label === 'homepage: Working…' && fallback.role === 'status' && fallback.value === 'homepage' && new URLSearchParams(requestBody).get('scope') === 'homepage', 'input submitter fallback keeps readable feedback without altering submitted values');
  releaseResponse();
  releaseResponse = null;
  await fallbackReceived;
  await fresh();
  const restoredButton = await page.evaluate(() => {
    const form = document.querySelector('#diagnostic');
    const button = form.querySelector('button');
    const original = document.createElement('span');
    original.textContent = 'Check cache';
    button.replaceChildren(original);
    button.setAttribute('aria-label', 'Check homepage cache');
    button.style.minWidth = '150px';
    window.addEventListener('submit', event => event.preventDefault(), { once: true });
    form.requestSubmit(button);
    const pendingLabel = button.getAttribute('aria-label');
    return new Promise(resolve => setTimeout(() => {
      resolve({ sameChild: button.firstChild === original, label: button.getAttribute('aria-label'), pendingLabel, minWidth: button.style.minWidth, pending: button.classList.contains('noobrocket-operation-pending') });
    }, 0));
  });
  check(restoredButton.sameChild && restoredButton.label === 'Check homepage cache' && restoredButton.pendingLabel === 'Check homepage cache: Working…' && restoredButton.minWidth === '150px' && !restoredButton.pending, 'canceled action preserves contextual accessible name and restores original child nodes and sizing');
  await fresh();
  const restoredPage = await page.evaluate(() => {
    const form = document.querySelector('#diagnostic');
    const button = form.querySelector('button');
    // Model restoration after a previously submitted document returns from bfcache.
    form.requestSubmit(button);
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    return { content: button.textContent, label: button.getAttribute('aria-label'), busy: form.getAttribute('aria-busy'), pending: !!form.dataset.noobrocketPending };
  });
  check(restoredPage.content === 'Check cache' && restoredPage.label === null && restoredPage.busy === null && !restoredPage.pending, 'persisted pageshow restores original action and clears its busy guard');
  await page.waitForURL(origin + '/operation');
  await fresh();
  await page.locator('#database_auto_drafts').check();
  await page.locator('#cleanup').click();
  const saveReceived = page.waitForURL(origin + '/cleanup').catch(error => error);
  const cleanupPost = new Promise(resolve => { posted = resolve; });
  await page.evaluate(() => document.querySelector('[data-noobrocket-cleanup-confirm]').click());
  await cleanupPost;
  const cleanupPayload = new URLSearchParams(requestBody);
  check(cleanupPayload.get('action') === 'noobrocket_database_cleanup' && cleanupPayload.get('confirmed') === '1' && cleanupPayload.getAll('noobrocket_cleanup[]').join() === 'database_auto_drafts' && ![...cleanupPayload.keys()].some(key => key.startsWith('noobrocket_settings[')), 'confirmed cleanup posts only its reviewed scope/confirmation to its independent endpoint');
  check(!((await saveReceived) instanceof Error), 'confirmed cleanup finishes its independent native navigation');
  await fresh();
  await page.evaluate(() => {
    document.addEventListener('submit', event => event.preventDefault(), { once: true });
    document.querySelector('#diagnostic').requestSubmit(document.querySelector('#diagnostic button'));
  });
  check(await page.locator('#diagnostic').getAttribute('aria-busy') === null && await page.locator('.noobrocket-operation-pending').count() === 0, 'canceled submission recovers from pending state');
  const lateCanceledFeedback = await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve(document.querySelectorAll('.noobrocket-operation-pending').length)))));
  check(lateCanceledFeedback === 0, 'the deferred tray announcement cannot resurrect a canceled native action toast');

  await fresh();
  await page.locator('#heartbeat_admin_behavior').selectOption('disable');
  const beforeCanceledSave = posts.length;
  await cancelNextSubmit('document');
  await page.locator('#noobrocket-options-submit').click();
  const canceledSave = await page.evaluate(() => window.canceledSubmission);
  check(canceledSave.trusted && canceledSave.form === 'noobrocket_options' && canceledSave.submitter === 'noobrocket-options-submit', 'pointer Save reaches the late document cancellation listener with native form ownership');
  check(await page.locator('[data-noobrocket-save-state]').innerText() === 'Unsaved settings' && await page.locator('#noobrocket-options-submit').isEnabled(), 'late document cancellation restores the dirty Save state and permits retry');
  check(await page.locator('#noobrocket-options-cancel').isVisible() && await page.locator('#noobrocket-options-cancel').isEnabled(), 'canceled native Save restores the pending-settings Cancel action');
  check(await page.locator('#noobrocket_options').evaluate(form => !form.hasAttribute('aria-busy') && !form.dataset.noobrocketSaving && !form.dataset.noobrocketPending) && await page.locator('#noobrocket-options-submit').getAttribute('aria-disabled') === null && await page.locator('.noobrocket-operation-pending').count() === 0, 'canceled pointer Save removes all busy and pending markers');
  check(await page.locator('#heartbeat_admin_behavior').inputValue() === 'disable' && (await page.locator('[data-noobrocket-changed-sections]').innerText()).includes('Heartbeat'), 'canceled pointer Save preserves changed fields and their dirty summary');
  check(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'canceled pointer Save restores the unsaved-settings departure guard');
  check(posts.length === beforeCanceledSave, 'canceled pointer Save sends no native request');
  const saveRetryReceived = page.waitForURL(origin + '/saved');
  const saveRetryPost = new Promise(resolve => { posted = resolve; });
  let recordPendingNavigation;
  const pendingNavigation = new Promise(resolve => { recordPendingNavigation = resolve; });
  await page.exposeFunction('recordPendingNavigation', recordPendingNavigation);
  await page.evaluate(() => {
    window.addEventListener('submit', () => {
      setTimeout(() => {
        const button = document.querySelector('#noobrocket-options-submit');
        button.value = button.defaultValue;
        document.dispatchEvent(new CustomEvent('noobrocketJsAfterPageNavigation', { detail: { pageId: 'heartbeat', submitButton: button } }));
        window.recordPendingNavigation({
          value: button.value,
          disabled: button.disabled,
          state: document.querySelector('[data-noobrocket-save-state]').textContent,
          busy: document.querySelector('#noobrocket_options').getAttribute('aria-busy'),
          pending: document.querySelectorAll('.noobrocket-operation-pending').length,
          cancelDisabled: document.querySelector('#noobrocket-options-cancel').disabled,
        });
      }, 0);
    }, { once: true });
  });
  holdNextResponse = true;
  await page.locator('#noobrocket-options-submit').click({ noWaitAfter: true });
  await saveRetryPost;
  const savedRetry = new URLSearchParams(requestBody);
  check(posts.length === beforeCanceledSave + 1 && posts.at(-1).path === '/saved' && savedRetry.get('noobrocket_settings[heartbeat_admin_behavior]') === 'disable' && !savedRetry.has('noobrocket_settings[submit_optimize]') && !savedRetry.has('scope'), 'one pointer Save retry posts only the native settings payload to its owning endpoint');
  const navigationState = await pendingNavigation;
  check(navigationState.value === 'Saving…' && navigationState.disabled && navigationState.state === 'Saving…', 'navigation synchronization restores the pending Save label while its native response is held');
  check(navigationState.busy === 'true' && navigationState.pending === 0 && posts.length === beforeCanceledSave + 1, 'pending settings Save keeps its busy state without a redundant Working toast');
  check(navigationState.cancelDisabled, 'Cancel stays disabled while its owning native settings Save is in flight');
  releaseResponse();
  releaseResponse = null;
  await saveRetryReceived;

  await fresh();
  const beforeCanceledOperation = posts.length;
  const operationValues = await page.locator('#diagnostic').evaluate(form => [...new FormData(form)]);
  await cancelNextSubmit('window');
  await page.locator('#diagnostic button').click();
  const canceledOperation = await page.evaluate(() => window.canceledSubmission);
  check(canceledOperation.trusted && canceledOperation.form === 'diagnostic' && canceledOperation.submitter === 'scope', 'pointer operation reaches the late window cancellation listener with its native submitter');
  check(await page.locator('#diagnostic').evaluate(form => !form.hasAttribute('aria-busy') && !form.dataset.noobrocketPending) && await page.locator('#diagnostic button').getAttribute('aria-disabled') === null && await page.locator('.noobrocket-operation-pending').count() === 0, 'late window cancellation removes independent operation busy markers and permits retry');
  check(JSON.stringify(await page.locator('#diagnostic').evaluate(form => [...new FormData(form)])) === JSON.stringify(operationValues) && posts.length === beforeCanceledOperation, 'canceled pointer operation preserves its form values without posting');
  await page.locator('#heartbeat_admin_behavior').selectOption('disable');
  check(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'canceled independent operation does not suppress the next unsaved-settings departure guard');
  await page.locator('#heartbeat_admin_behavior').selectOption('reduce_periodicity');
  const operationRetryReceived = page.waitForURL(origin + '/operation');
  const operationRetryPost = new Promise(resolve => { posted = resolve; });
  await page.locator('#diagnostic button').click();
  await operationRetryPost;
  const operationRetry = new URLSearchParams(requestBody);
  check(posts.length === beforeCanceledOperation + 1 && posts.at(-1).path === '/operation' && operationRetry.get('scope') === 'homepage' && operationRetry.get('action') === 'check_cache' && ![...operationRetry.keys()].some(name => name.startsWith('noobrocket_settings[')), 'one pointer operation retry retains its submitter payload without posting unrelated settings');
  await operationRetryReceived;

  await fresh();
  const beforeDiscard = posts.length;
  const savedDraft = await page.locator('#saved-private-draft').innerText();
  const publishedRules = await page.locator('#published-rules').innerText();
  const discardDialogs = [];
  const dismissDiscard = async dialog => {
    discardDialogs.push({ type: dialog.type(), message: dialog.message() });
    await dialog.dismiss();
  };
  page.on('dialog', dismissDiscard);
  await page.evaluate(() => {
    window.discardSubmissions = [];
    document.addEventListener('submit', event => {
      if (event.target.id === 'discard-draft') window.discardSubmissions.push({ trusted: event.isTrusted, form: event.target.id, submitter: event.submitter?.name });
    }, { capture: true });
  });
  await page.locator('#discard-draft button').click();
  check(discardDialogs.length === 1 && discardDialogs[0].type === 'confirm' && discardDialogs[0].message.includes('Published rules stay unchanged'), 'trusted Discard click reviews saved private draft deletion with its published-rule boundary');
  check(await page.evaluate(() => window.discardSubmissions.length === 1 && window.discardSubmissions[0].trusted && window.discardSubmissions[0].submitter === 'scope'), 'discard confirmation is reached through its native trusted submitter');
  check(posts.length === beforeDiscard && await page.locator('#saved-private-draft').innerText() === savedDraft && await page.locator('#published-rules').innerText() === publishedRules, 'canceling Discard sends no request and preserves displayed private and published rules');
  check(await page.locator('#discard-draft').evaluate(form => !form.hasAttribute('aria-busy') && !form.dataset.noobrocketPending) && await page.locator('#discard-draft button').getAttribute('aria-disabled') === null && await page.locator('.noobrocket-operation-pending').count() === 0, 'canceling Discard never leaves an independent busy state');

  await page.locator('#heartbeat_admin_behavior').selectOption('disable');
  await page.locator('.noobrocket-asset-create').click();
  await page.locator('#discard-draft button').click();
  check(await page.locator('#noobrocket-operation-dialog').evaluate(dialog => dialog.open) && discardDialogs.length === 1, 'pending global settings and unfinished editor are reviewed before saved draft deletion');
  await page.locator('[data-noobrocket-operation-cancel]').click();
  check(posts.length === beforeDiscard && await page.locator('#heartbeat_admin_behavior').inputValue() === 'disable' && await page.locator('[name="rule[handle]"]').inputValue() === 'theme-navigation', 'canceling the first review preserves global settings and unfinished asset input');
  await page.locator('#discard-draft button').click();
  await page.locator('[data-noobrocket-operation-continue]').click();
  check(discardDialogs.length === 2 && posts.length === beforeDiscard && await page.locator('#saved-private-draft').innerText() === savedDraft, 'continue using saved settings still requires separate discard confirmation');
  check(await page.locator('#heartbeat_admin_behavior').inputValue() === 'disable' && await page.locator('[name="rule[handle]"]').inputValue() === 'theme-navigation' && await page.locator('#noobrocket-options-submit').isEnabled(), 'canceling final discard preserves both independent pending drafts');
  check(await page.evaluate(() => !window.dispatchEvent(new Event('beforeunload', { cancelable: true }))), 'canceling final discard retains departure protection for unsaved work');
  check(await page.locator('#discard-draft').getAttribute('aria-busy') === null && await page.locator('.noobrocket-operation-pending').count() === 0, 'canceling final discard review leaves no Working state');

  page.off('dialog', dismissDiscard);
  const acceptDiscard = async dialog => {
    discardDialogs.push({ type: dialog.type(), message: dialog.message() });
    await dialog.accept();
  };
  page.on('dialog', acceptDiscard);
  await page.locator('#discard-draft button').click();
  const discardReceived = page.waitForURL(origin + '/asset');
  const discardPosted = new Promise(resolve => { posted = resolve; });
  await page.locator('[data-noobrocket-operation-continue]').click({ noWaitAfter: true });
  await discardPosted;
  const discardPayload = new URLSearchParams(requestBody);
  check(discardDialogs.length === 3 && posts.length === beforeDiscard + 1 && posts.at(-1).path === '/asset', 'accepting final discard confirmation sends exactly one owning native POST');
  check(discardPayload.get('action') === 'noobrocket_assets' && discardPayload.get('command') === 'discard' && discardPayload.get('scope') === 'private-draft' && ![...discardPayload.keys()].some(name => name.startsWith('noobrocket_settings[') || name.startsWith('rule[')), 'discard POST retains native submitter and contains neither global settings nor unfinished rule fields');
  await discardReceived;
  page.off('dialog', acceptDiscard);
  check(errors.length === 0, 'transaction handlers produce no browser errors');
  console.log(`PASS: ${checks} native-browser transaction checks; synthetic forms only.`);
} finally {
  releaseResponse?.();
  await browser.close();
  await new Promise(resolve => server.close(resolve));
}
