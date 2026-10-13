/** Actual Firefox/Chromium with delivered DelayJS and owned consent controls; no WordPress or providers. */
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { createServer } from 'node:http';
import { readFile, writeFile, mkdtemp, rm, realpath } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join, sep } from 'node:path';

const chromium = process.argv[2];
const firefox = process.argv[3] || process.env.NOOBROCKET_FIREFOX_EXECUTABLE || (process.platform === 'win32' ? 'C:/Program Files/Mozilla Firefox/firefox.exe' : '/usr/bin/firefox');
if (!chromium) throw new Error('Provide the Chromium executable; Firefox must also be installed or set NOOBROCKET_FIREFOX_EXECUTABLE.');
const puppeteer = createRequire(new URL('../tools/package.json', import.meta.url))('puppeteer-core');
const runtime = await readFile(new URL('../noobrocket/assets/js/lazyload-scripts.min.js', import.meta.url), 'utf8');
const receipt = { runtimeSHA256: createHash('sha256').update(runtime).digest('hex'), cases: [], limits: ['Actual browser execution with synthetic consent hooks. No installed proprietary consent plugin, WordPress bootstrap or provider requests.', 'The Chromium reuse path does not call the modeled createElement hook; its success is a control, not a claimed consent-blocker integration.'] };
let origin;
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
const server = createServer((request, response) => {
  const url = new URL(request.url, origin);
  if (url.pathname === '/tracked.js') {
    response.setHeader('Content-Type', 'application/javascript');
    if (url.searchParams.has('fail')) { response.statusCode = 404; response.end('Not found'); return; }
    response.end('window.trackedRuns=(window.trackedRuns||0)+1;'); return;
  }
  if (url.pathname === '/app.js') {
    response.setHeader('Content-Type', 'application/javascript');
    response.end('window.appRuns=(window.appRuns||0)+1;document.querySelector("#trigger").addEventListener("click",()=>window.clicks=(window.clicks||0)+1);'); return;
  }
  const mode = url.searchParams.get('mode');
  const blockedType = mode === 'plain' ? 'text/plain' : 'application/usercentrics';
  const blocker = !['plain', 'usercentrics'].includes(mode) ? '' : `
    const originalCreate=document.createElement.bind(document);
    let consentGranted=false;
    document.createElement=function(name,...args){
      const node=originalCreate(name,...args);
      if(name.toLowerCase()==='script'){
        const originalAttribute=node.setAttribute.bind(node);
        node.setAttribute=function(key,value){
          originalAttribute(key,value);
          if(!consentGranted&&key.toLowerCase()==='src'&&value.includes('/tracked.js')){
            originalAttribute('type',${JSON.stringify(blockedType)});
            ${mode === 'usercentrics' ? "node.addEventListener('beforescriptexecute',event=>event.preventDefault());" : ''}
            window.blockedScripts=(window.blockedScripts||0)+1;
          }
        };
      }
      return node;
    };
    window.grantConsent=function(){
      consentGranted=true;
      const blocked=document.querySelector('script[type='+JSON.stringify(${JSON.stringify(blockedType)})+']');
      if(!blocked)return;
      const allowed=document.createElement('script');
      allowed.type='text/javascript';allowed.src=blocked.src;blocked.replaceWith(allowed);
    };`;
  const type = url.searchParams.get('type') || 'text/javascript';
  response.setHeader('Content-Type', 'text/html');
  response.end(`<!doctype html><html><head><script>${blocker}</script><script>${runtime}</script></head><body><button id="trigger">Click me</button><script type="text/noobrocketlazyloadscript" data-noobrocket-type="${type}" data-noobrocket-src="/tracked.js${mode === 'network-failure' ? '?fail' : ''}"></script><script type="text/noobrocketlazyloadscript" data-noobrocket-type="text/javascript" data-noobrocket-src="/app.js"></script><script>window.addEventListener('noobrocket-allScriptsLoaded',()=>window.completions=(window.completions||0)+1,{isRocket:true});</script></body></html>`);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
origin = `http://127.0.0.1:${server.address().port}`;
const state = page => page.evaluate(() => ({ appRuns: window.appRuns || 0, trackedRuns: window.trackedRuns || 0, clicks: window.clicks || 0, completions: window.completions || 0, blockedScripts: window.blockedScripts || 0, trackedType: document.querySelector('script[src*="/tracked.js"]')?.type, trackedStatus: document.querySelector('script[src*="/tracked.js"]')?.getAttribute('data-noobrocket-status') }));
try {
  for (const [name, executablePath] of [['firefox', firefox], ['chrome', chromium]]) {
    const profile = await mkdtemp(join(tmpdir(), 'noobrocket-consent-'));
    let browser;
    try {
      browser = await puppeteer.launch({ browser: name, executablePath, headless: true, userDataDir: profile, timeout: 15000 });
      const version = await browser.version();
      const types = ['text/javascript', 'module', 'application/javascript', 'application/ecmascript', 'application/x-ecmascript', 'application/x-javascript', 'text/ecmascript', 'text/javascript1.0', 'text/javascript1.1', 'text/javascript1.2', 'text/javascript1.3', 'text/javascript1.4', 'text/javascript1.5', 'text/jscript', 'text/livescript', 'text/x-ecmascript', 'text/x-javascript'];
      for (const [mode, type] of [...types.map(type => ['ordinary', type]), ['network-failure', 'text/javascript'], ['plain', 'text/javascript'], ['usercentrics', 'text/javascript']]) {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setRequestInterception(true);
        page.on('request', request => new URL(request.url()).origin === origin ? request.continue() : request.abort());
        try {
          await page.goto(`${origin}/?mode=${mode}&type=${encodeURIComponent(type)}`, { waitUntil: 'load' });
          await page.click('#trigger');
          await page.waitForFunction(() => window.completions === 1, { timeout: 3000 });
          await page.click('#trigger');
          const initial = await state(page);
          check(initial.appRuns === 1 && initial.clicks === 2 && initial.completions === 1, `${name}/${mode}/${type}: subsequent application scripts and both clicks complete exactly once.`);
          if (['plain', 'usercentrics'].includes(mode) && name === 'firefox') {
            check(initial.trackedRuns === 0 && initial.blockedScripts === 1 && initial.trackedType === (mode === 'plain' ? 'text/plain' : 'application/usercentrics') && initial.trackedStatus === 'skipped-type', `${mode}: consent keeps the tracker inert while the delay queue completes.`);
            await page.evaluate(() => window.grantConsent());
            await page.waitForFunction(() => window.trackedRuns === 1);
            await page.evaluate(() => window.grantConsent());
            await page.click('#trigger');
            const granted = await state(page);
            check(granted.trackedRuns === 1 && granted.appRuns === 1 && granted.clicks === 3 && granted.completions === 1, `${mode}: consent-owned replacement executes once and does not restart DelayJS or replay old clicks.`);
          } else if (mode === 'network-failure') {
            check(initial.trackedRuns === 0 && initial.trackedStatus === 'failed-network', `${name}: a failed resource does not stall the queue.`);
          } else {
            check(initial.trackedRuns === 1, `${name}/${mode}/${type}: ordinary executable script retains execution.`);
          }
          check(errors.length === 0, `${name}/${mode}/${type}: no script exception.`);
          receipt.cases.push({ browser: name, version, mode, type, initial, final: await state(page) });
        } finally { await page.close(); }
      }
    } finally {
      if (browser) await browser.close();
      const actual = await realpath(profile), expected = await realpath(tmpdir());
      assert.ok(actual.toLowerCase().startsWith((expected + sep).toLowerCase()), 'Cleanup only removes an owned temporary profile.');
      await rm(actual, { recursive: true, force: true });
    }
  }
  receipt.status = 'PASS';
} catch (error) { receipt.status = 'FAIL'; receipt.error = error.message; throw error; }
finally {
  await new Promise(resolve => server.close(resolve));
  receipt.checks = checks;
  if (process.env.NOOBROCKET_CONSENT_REPORT) await writeFile(process.env.NOOBROCKET_CONSENT_REPORT, JSON.stringify(receipt, null, 2) + '\n');
  console.log(`${receipt.status}: ${checks} DelayJS consent assertions, ${receipt.cases.length} actual Firefox/Chromium cases; ${receipt.error || 'blocked scripts remain inert until later consent.'}`);
}
