/** Actual maintained stylesheet-background runtime bytes; intercepted images, no WordPress or providers. */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const [executablePath] = process.argv.slice(2);
if (!executablePath) throw new Error('Provide a Chromium executable path.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', 'base64');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
const pair = (name, selector) => ({ selector, style: `${selector}{--bg-${name}:url("https://background-images.test/${name}.png")}`, hash: name });
const instrumentation = `
window.runtimeEvidence={queries:0,observed:[],mutations:0,disconnected:0};
const nativeQuery=document.querySelectorAll.bind(document);
document.querySelectorAll=function(selector){window.runtimeEvidence.queries++;return nativeQuery(selector);};
const NativeIntersection=window.IntersectionObserver;
if(NativeIntersection)window.IntersectionObserver=class extends NativeIntersection {
  observe(element){window.runtimeEvidence.observed.push(element.id);super.observe(element);}
};
const NativeMutation=window.MutationObserver;
if(NativeMutation)window.MutationObserver=class extends NativeMutation {
  constructor(callback){super(callback);window.runtimeEvidence.mutations++;}
  disconnect(){window.runtimeEvidence.disconnected++;super.disconnect();}
};`;

async function open(runtime, pairs, content, { excluded = [], fallback = false } = {}) {
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  const errors = [];
  const requests = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.hostname !== 'background-images.test') return route.abort();
    requests.push(url.pathname);
    return route.fulfill({ contentType: 'image/png', body: image });
  });
  const configuration = `const noobrocket_pairs=${JSON.stringify(pairs)};const noobrocket_excluded_pairs=${JSON.stringify(excluded)};const noobrocket_lazyload_css_data={threshold:300};`;
  await page.setContent(`<!doctype html><html><head><style id="noobrocket-lazyload-bg-container"></style>
    <style>${excluded.map(item => item.style).join('')}
      body{margin:0}.first,.below,.nested,.sibling,.ajax,.excluded{width:32px;height:32px}
      .first{background-image:var(--bg-first)}.below{background-image:var(--bg-below)}
      .nested{background-image:var(--bg-ancestor)}.sibling{background-image:var(--bg-sibling)}
      .ajax{background-image:var(--bg-ajax)}.excluded{background-image:var(--bg-excluded)}
      .spacer{height:1900px}
    </style><script>${fallback ? 'window.IntersectionObserver=undefined;' : ''}${instrumentation}${configuration}</script></head>
    <body>${content}<script>${runtime}</script></body></html>`, { waitUntil: 'load' });
  return { page, errors, requests };
}

async function nextFrame(page) {
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}

try {
  for (const asset of ['lazyload-css.js', 'lazyload-css.min.js']) {
    const runtime = await readFile(new URL(`../noobrocket/assets/js/${asset}`, import.meta.url), 'utf8');
    const empty = await open(runtime, [], '<button id="ordinary">Ordinary content</button>');
    check(await empty.page.evaluate(() => runtimeEvidence.mutations === 0 && runtimeEvidence.observed.length === 0), `${asset}: no mapping installs no observers`);
    check(empty.errors.length === 0, `${asset}: empty page has no runtime error`);
    await empty.page.close();

    const pairs = [pair('first', '.first'), pair('below', '.below'), pair('ancestor', '.enabled > .nested'), pair('sibling', '.anchor + .sibling'), pair('ajax', '.ajax')];
    const active = await open(runtime, pairs, '<div id="first" class="first"></div><div id="parent"><div id="nested" class="nested"></div></div><div id="anchor"></div><div id="sibling" class="sibling"></div><div id="excluded" class="excluded"></div><button id="ordinary">Ordinary content</button><div class="spacer"></div><div id="below" class="below"></div>', { excluded: [pair('excluded', '.excluded')] });
    await active.page.waitForFunction(() => document.querySelector('#first').getAttribute('data-noobrocket-lazy-bg-first') === 'loaded', { timeout: 2000 });
    await active.page.waitForFunction(() => performance.getEntriesByName('https://background-images.test/first.png').length > 0);
    check(active.requests.includes('/first.png') && !active.requests.includes('/below.png'), `${asset}: visible background loads initially and below-fold image stays lazy`);
    check(await active.page.locator('#excluded').getAttribute('data-noobrocket-lazy-bg-excluded') === 'excluded', `${asset}: exclusion markers remain intact`);
    const before = await active.page.evaluate(() => ({ ...runtimeEvidence }));
    await active.page.evaluate(() => {
      const button = document.querySelector('#ordinary');
      for (let index = 0; index < 30; index++) button.setAttribute('data-state', String(index));
    });
    await nextFrame(active.page);
    const after = await active.page.evaluate(() => ({ ...runtimeEvidence }));
    check(after.queries - before.queries <= 4, `${asset}: mutation burst scans each remaining selector at most once`);
    check(after.observed.filter(id => id === 'below').length === 1, `${asset}: unchanged elements are not repeatedly observed or retained`);

    await active.page.evaluate(() => {
      document.querySelector('#parent').classList.add('enabled');
      document.querySelector('#anchor').classList.add('anchor');
      document.body.insertAdjacentHTML('afterbegin', '<div id="ajax" class="ajax"></div>');
    });
    await active.page.waitForFunction(() => ['nested', 'sibling', 'ajax'].every((id, index) => document.getElementById(id).getAttribute('data-noobrocket-lazy-bg-' + ['ancestor', 'sibling', 'ajax'][index]) === 'loaded'));
    await active.page.waitForFunction(() => ['ancestor', 'sibling', 'ajax'].every(name => performance.getEntriesByName(`https://background-images.test/${name}.png`).length > 0));
    check(['/ancestor.png', '/sibling.png', '/ajax.png'].every(path => active.requests.includes(path)), `${asset}: ancestor/sibling changes and AJAX targets retain loading behavior`);
    await active.page.locator('#below').scrollIntoViewIfNeeded();
    await active.page.waitForFunction(() => document.querySelector('#below').getAttribute('data-noobrocket-lazy-bg-below') === 'loaded');
    await active.page.waitForFunction(() => performance.getEntriesByName('https://background-images.test/below.png').length > 0);
    check(active.requests.includes('/below.png'), `${asset}: below-fold background loads after scrolling`);
    await nextFrame(active.page);
    const done = await active.page.evaluate(() => ({ ...runtimeEvidence }));
    await active.page.evaluate(() => {
      document.body.insertAdjacentHTML('beforeend', '<div id="later" class="first"></div>');
      document.querySelector('#ordinary').classList.add('changed');
    });
    await nextFrame(active.page);
    check(await active.page.evaluate(previous => runtimeEvidence.queries === previous && runtimeEvidence.disconnected > 0, done.queries), `${asset}: published mappings stop mutation rescans`);
    check(await active.page.locator('#later').evaluate(element => getComputedStyle(element).backgroundImage.includes('/first.png')), `${asset}: published CSS also covers later targets`);
    check(active.errors.length === 0, `${asset}: dynamic/background paths produce no JavaScript errors`);
    await active.page.close();

    const fallback = await open(runtime, [pair('first', '.first')], '<div id="first" class="first"></div>', { fallback: true });
    check(await fallback.page.locator('#first').evaluate(element => getComputedStyle(element).backgroundImage.includes('/first.png')), `${asset}: unavailable IntersectionObserver preserves background delivery`);
    check(fallback.errors.length === 0, `${asset}: unavailable observer has no runtime error`);
    await fallback.page.close();
  }
  console.log(`PASS: stylesheet-background runtime (${checks} assertions; readable and served bytes).`);
} finally {
  await browser.close();
}
