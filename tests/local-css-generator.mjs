import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

// The generator/dependencies can be copied into a disposable proof directory.
const generatorURL = process.argv[2] ? pathToFileURL(process.argv[2]) : new URL('../noobrocket/local-css/generator.mjs', import.meta.url);
const { generatePageCss, prepareStylesheet, compileStylesheet } = await import(generatorURL);
const require = createRequire(generatorURL);
const { chromium } = require('playwright-core');
const executablePath = process.argv[3];
if (!executablePath) throw new Error('Provide the Chromium executable after the generator path.');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }

const css = `/* Unicode offsets: π 🚀 */
@charset "UTF-8";
:root { --accent: red; }
.hero { color: red; background-image: url("../img/pixel.svg?v=1#icon"); border-image-source: image-set("../img/other.svg" 1x); }
.unused { color: rgb(1, 2, 3); }
.future-picture picture > img { border-radius: 7px; }
picture source[type="image/avif"] { color: teal; }
.below { border-color: green; }
.menu { display: none; }
.menu span { color: gold; }
.transient { outline-color: orange; }
.hero:hover { color: blue; }
.modal-open .modal { opacity: 1; }
dialog[open] { border-top-width: 9px; }
.tabs [aria-selected="true"] { border-bottom: 7px solid purple; }
.ajax-result { color: rgb(7, 8, 9); }
.pseudo::before { content: "*"; }
@media (max-width: 800px) { .unseen-mobile { color: purple; } }
@media print { .print-only { color: black; } }
@supports (unknown-feature: no) { .future-support { color: cyan; } }
@font-face { font-family: "fixture"; src: url("./fonts/font.woff2"); }
@keyframes slide { from { transform: translateX(0); } to { transform: translateX(10px); } }
@property --offset { syntax: "<length>"; inherits: false; initial-value: 0px; }
@layer theme { .unseen-layer { margin: 0; } }
@future-rule example { .future-rule { padding: 0; } }
`;
let foreignRequests = 0;
let forbiddenRequests = 0;
let changingImports = 0;
const forbidden = createServer((_request, response) => { forbiddenRequests++; response.end('forbidden'); });
await new Promise((resolve) => forbidden.listen(0, '127.0.0.1', resolve));
const forbiddenOrigin = `http://127.0.0.1:${forbidden.address().port}`;
const foreign = createServer((request, response) => {
  foreignRequests++;
  if (request.url === '/escape.css') { response.writeHead(302, { Location: forbiddenOrigin + '/blocked.css' }); response.end(); return; }
  if (request.url === '/redirect.css') { response.writeHead(302, { Location: '/cdn/final.css' }); response.end(); return; }
  response.writeHead(200, { 'Content-Type': 'text/css' });
  response.end('.hero { color: brown; background-image: url("../pixel.svg"); }');
});
await new Promise((resolve) => foreign.listen(0, '127.0.0.1', resolve));
const foreignOrigin = `http://127.0.0.1:${foreign.address().port}`;
const server = createServer((request, response) => {
  const url = new URL(request.url, 'http://fixture');
  if (url.pathname === '/redirect') { response.writeHead(302, { Location: foreignOrigin + '/page' }); response.end(); return; }
  if (url.pathname === '/canonical') { response.writeHead(302, { Location: '/final-page' }); response.end(); return; }
  if (url.pathname === '/loop') { response.writeHead(302, { Location: '/loop' }); response.end(); return; }
  if (url.pathname === '/assets/redirect.css') { response.writeHead(302, { Location: '/other/final.css' }); response.end(); return; }
  if (url.pathname === '/other/final.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.hero { background: url("images/pixel.svg"); color: orange; }'); return; }
  if (url.pathname === '/assets/import-redirect.css') { response.writeHead(302, { Location: '/other/import-final.css' }); response.end(); return; }
  if (url.pathname === '/other/import-final.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@import "parts/dependency.css"; .hero { background: url("images/pixel.svg"); } .transient { outline: solid red; }'); return; }
  if (url.pathname === '/other/parts/dependency.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.child { background: url("icons/pixel.svg"); }'); return; }
  if (url.pathname === '/assets/cdn.css') { response.writeHead(302, { Location: foreignOrigin + '/redirect.css' }); response.end(); return; }
  if (url.pathname === '/assets/escape.css') { response.writeHead(302, { Location: foreignOrigin + '/escape.css' }); response.end(); return; }
  if (url.pathname === '/assets/main.css') { response.writeHead(200, { 'Content-Type': 'text/css; charset=utf-8' }); response.end(css); return; }
  if (url.pathname === '/assets/second.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.hero { color: maroon; }'); return; }
  if (url.pathname === '/assets/nested.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.hero { color: black; & .child { color: rgb(11, 12, 13); } @media (min-width: 1px) { & .child { padding-left: 13px; } } } .future-parent { & .future-child { color: pink; } }'); return; }
  if (url.pathname === '/assets/import.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@layer base, theme; @import "parts/base.css" layer(base) supports(display: grid) screen; @import url("parts/anon.css") layer; .hero { color: yellow; }'); return; }
  if (url.pathname === '/assets/parts/base.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@import "deep/shared.css"; .child { color: rgb(21, 22, 23); background: url("../../img/pixel.svg"); } .future-import { display: grid; }'); return; }
  if (url.pathname === '/assets/parts/deep/shared.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.child { border-left: 4px solid green; }'); return; }
  if (url.pathname === '/assets/parts/anon.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.child { border-top: 3px solid red; }'); return; }
  if (url.pathname === '/assets/cycle.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@import "cycle.css"; .hero { color: red; }'); return; }
  if (url.pathname === '/assets/namespace.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@import "parts/namespace.css"; .hero { color: red; }'); return; }
  if (url.pathname === '/assets/parts/namespace.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@namespace svg url(http://www.w3.org/2000/svg); svg|a { fill: red; }'); return; }
  if (url.pathname === '/assets/late-import.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('.hero { color: red; } @import "parts/anon.css";'); return; }
  if (url.pathname === '/assets/changing.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end('@import "parts/changing.css"; .hero { color: red; }'); return; }
  if (url.pathname === '/assets/parts/changing.css') { response.writeHead(200, { 'Content-Type': 'text/css' }); response.end(`.child { border-bottom: ${++changingImports}px solid red; }`); return; }
  if (url.pathname.startsWith('/img/') || url.pathname.endsWith('.woff2')) { response.writeHead(200, { 'Content-Type': 'image/svg+xml' }); response.end('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>'); return; }
  const special = { '/nested': 'nested', '/import': 'import', '/missing': 'missing', '/asset-redirect': 'redirect', '/import-redirect': 'import-redirect', '/cdn-redirect': 'cdn', '/escape-redirect': 'escape', '/cycle': 'cycle', '/namespace': 'namespace', '/late-import': 'late-import', '/changing': 'changing', '/mutated': 'import' };
  const styles = special[url.pathname] ? [`/assets/${special[url.pathname]}.css`] : url.pathname === '/foreign' ? [`${foreignOrigin}/external.css`] : ['/assets/main.css', '/assets/second.css'];
  if (url.pathname === '/assets/missing.css') { response.writeHead(404); response.end('missing'); return; }
  const headers = { 'Content-Type': 'text/html; charset=utf-8' };
  if (url.pathname === '/csp') headers['Content-Security-Policy'] = "default-src 'self'; style-src 'self'; script-src 'self'";
  if (url.pathname === '/csp-report') headers['Content-Security-Policy-Report-Only'] = "default-src 'self'; style-src 'self'; script-src 'self'";
  const meta = url.pathname === '/csp-meta' ? '<meta http-equiv="Content-Security-Policy" content="default-src &apos;self&apos;; style-src &apos;self&apos; &apos;unsafe-inline&apos;; script-src &apos;self&apos; &apos;unsafe-inline&apos;">' : '';
  const mutation = url.pathname === '/mutated' ? "addEventListener('scroll', () => { const sheet = document.styleSheets[0].cssRules[1].styleSheet; sheet.insertRule('.hero { color: pink; }', sheet.cssRules.length); }, {once:true});" : '';
  response.writeHead(200, headers);
  response.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${meta}<title>CSS proof</title>${styles.map((href) => `<link rel="stylesheet" href="${href}">`).join('')}<style>.inline-only { color: navy; } body { margin: 0; } .spacer { height: 1800px; }</style></head><body><div class="hero transient pseudo">Hero<span class="child">Child</span></div><div class="inline-only">Inline CSS remains</div><div class="modal">Modal</div><nav class="menu"><span>Hidden menu</span></nav><dialog>Dialog</dialog><div class="tabs"><button aria-selected="false">Tab</button></div><div class="spacer"></div><div class="below">Below fold</div><script>addEventListener('scroll', () => document.querySelector('.hero').classList.remove('transient'), {once:true});${mutation}</script></body></html>`);
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
try {
  const job = { url: origin + '/', mode: 'used', mobile: false, styles: [{ href: origin + '/assets/main.css', media: 'all' }, { href: origin + '/assets/second.css', media: 'all' }], safelist: ['modal-open', 'ajax-result'], viewports: [{ width: 1365, height: 900 }, { width: 768, height: 900 }] };
  const used = await generatePageCss(browser, job);
  check(used.results.length === 2 && used.results[0].href === job.styles[0].href && used.results[1].href === job.styles[1].href, 'per-stylesheet output retains the source order and exact hrefs');
  const generated = used.results[0].css;
  check(!generated.includes('.unused'), 'an unmatched ordinary rule is removed');
  check(generated.includes('.future-picture picture > img') && generated.includes('picture source[type="image/avif"]'), 'picture/source rules survive optimization-bypassed browser samples for later native image delivery');
  check(generated.includes('.below'), 'below-fold DOM rules survive used CSS generation');
  check(generated.includes('.transient'), 'actual browser coverage preserves a rule whose DOM class disappeared after scrolling');
  check(generated.includes('.hero:hover') && generated.includes('.modal-open'), 'dynamic states and safelisted classes survive');
  check(generated.includes('.pseudo::before'), 'pseudo-element rules match their visible host');
  check(['@media', '.unseen-mobile', '.print-only', '@supports', '.future-support', '@font-face', '@keyframes', '@property', '@layer', '@future-rule'].every((token) => generated.includes(token)), 'conditional blocks and CSS dependencies retain their wrappers and declarations');
  check(generated.includes(origin + '/img/pixel.svg?v=1#icon') && generated.includes(origin + '/assets/fonts/font.woff2') && generated.includes(origin + '/img/other.svg'), 'relative CSS asset URLs and image-set strings are rebased against their own stylesheet URL');
  check(!generated.includes('@charset'), 'external charset directive is removed for inline-safe output');
  check(used.results[0].source_hash === createHash('sha256').update(css).digest('hex'), 'source SHA256 covers the fetched stylesheet bytes');
  check(!generated.includes('.inline-only'), 'page-owned inline styles are not copied or removed');
  const critical = await generatePageCss(browser, { ...job, mode: 'critical', viewports: [{ width: 1365, height: 900 }] });
  check(critical.results[0].css.includes('.hero') && !critical.results[0].css.includes('.below'), 'critical output uses the initial visible viewport rather than whole-page coverage');
  check(critical.results[1].css.includes('maroon'), 'second stylesheet remains independent for cascade-position insertion');
  const criticalOnly = await browser.newPage();
  try {
    await criticalOnly.setContent(`<style>${critical.results[0].css}</style><nav class="menu"><span>Hidden menu</span></nav>`);
    check(await criticalOnly.locator('.menu').evaluate((element) => getComputedStyle(element).display === 'none'), 'critical CSS alone preserves the initial hidden menu without a flash');
  } finally { await criticalOnly.close(); }
  const mobile = await generatePageCss(browser, { ...job, mobile: true, viewports: [{ width: 390, height: 844 }] });
  check(mobile.results[0].css.includes('.hero'), 'mobile browser generation succeeds');
  const singleJob = (endpoint, filename) => ({ ...job, url: origin + '/' + endpoint, styles: [{ href: origin + '/assets/' + filename + '.css', media: 'all' }], viewports: [{ width: 1365, height: 900 }] });
  const nested = await generatePageCss(browser, singleJob('nested', 'nested'));
  check(nested.results[0].css.includes('& .child') && nested.results[0].css.includes('.future-parent') && nested.results[0].css.includes('@media'), 'native nested CSS retains parent, nested selectors and conditions conservatively');
  const imported = await generatePageCss(browser, singleJob('import', 'import'));
  check(!imported.results[0].css.includes('@import') && imported.results[0].css.includes('@layer base') && imported.results[0].css.includes('@supports (display: grid)') && imported.results[0].css.includes('@media screen'), 'imported sheets retain layer, supports and media conditions');
  check(imported.results[0].css.includes('border-left') && imported.results[0].css.includes('.future-import') && imported.results[0].css.includes(origin + '/img/pixel.svg'), 'recursive imports retain unknown states and rebase each source directory');
  const render = await browser.newPage();
  try {
    await render.setContent(`<style>${nested.results[0].css}</style><div class="hero"><span class="child">Child</span></div>`);
    check(await render.locator('.child').evaluate((element) => getComputedStyle(element).color === 'rgb(11, 12, 13)' && getComputedStyle(element).paddingLeft === '13px'), 'native nested selectors and nested media work in generated browser CSS');
    await render.setContent(`<style>${imported.results[0].css}</style><div class="hero"><span class="child">Child</span></div>`);
    check(await render.locator('.child').evaluate((element) => getComputedStyle(element).borderLeftWidth === '4px' && getComputedStyle(element).borderTopWidth === '3px'), 'expanded imports preserve real browser layer/cascade effects');
    await render.setContent(`<style>${generated}</style><nav class="menu"><span>Menu</span></nav><dialog>Dialog</dialog><div class="tabs"><button aria-selected="false">Tab</button></div><div class="modal">Modal</div>`);
    await render.evaluate(() => {
      document.querySelector('.menu').style.display = 'block';
      document.querySelector('dialog').showModal();
      document.querySelector('.tabs button').setAttribute('aria-selected', 'true');
      document.body.classList.add('modal-open');
      document.body.insertAdjacentHTML('beforeend', '<p class="ajax-result">AJAX result</p>');
    });
    check(await render.locator('.menu span').evaluate((element) => getComputedStyle(element).color === 'rgb(255, 215, 0)'), 'hidden menu styles survive when the menu opens');
    check(await render.locator('dialog').evaluate((element) => getComputedStyle(element).borderTopWidth === '9px'), 'dialog open attribute state survives generation');
    check(await render.locator('.tabs button').evaluate((element) => getComputedStyle(element).borderBottomWidth === '7px'), 'tab aria-selected state survives generation');
    check(await render.locator('.ajax-result').evaluate((element) => getComputedStyle(element).color === 'rgb(7, 8, 9)'), 'safelisted AJAX content styles survive insertion after generation');
  } finally { await render.close(); }
  for (const type of ['missing', 'cycle']) {
    await assert.rejects(generatePageCss(browser, singleJob(type, type)));
    checks++;
  }
  const sameRedirect = await generatePageCss(browser, { ...job, url: origin + '/canonical', viewports: [{ width: 1365, height: 900 }] });
  check(sameRedirect.results[0].css.includes('.hero'), 'same-origin document canonical redirects succeed with native final-page URL');
  const assetRedirect = await generatePageCss(browser, singleJob('asset-redirect', 'redirect'));
  check(assetRedirect.results[0].css.includes(origin + '/other/images/pixel.svg'), 'same-origin stylesheet redirects preserve the final stylesheet asset base');
  const importRedirect = await generatePageCss(browser, singleJob('import-redirect', 'import-redirect'));
  check(importRedirect.results[0].css.includes(origin + '/other/parts/icons/pixel.svg') && importRedirect.results[0].css.includes('.transient'), 'redirected imports preserve child asset bases and browser coverage offsets');
  await assert.rejects(generatePageCss(browser, singleJob('namespace', 'namespace')), /namespaces cannot be moved safely/);
  checks++;
  await assert.rejects(generatePageCss(browser, singleJob('late-import', 'late-import')), /import appears after/);
  checks++;
  await assert.rejects(generatePageCss(browser, { ...singleJob('changing', 'changing'), viewports: [{ width: 1365, height: 900 }, { width: 768, height: 900 }] }), /changed between viewports/);
  checks++;
  await assert.rejects(generatePageCss(browser, singleJob('mutated', 'import')), /changed by page JavaScript/);
  checks++;
  const cdnRedirect = await generatePageCss(browser, singleJob('cdn-redirect', 'cdn'), { assetOrigins: [foreignOrigin] });
  check(cdnRedirect.results[0].css.includes('brown') && cdnRedirect.results[0].css.includes(foreignOrigin + '/pixel.svg'), 'explicitly trusted asset redirect chains preserve final CDN asset bases');
  await assert.rejects(generatePageCss(browser, singleJob('escape-redirect', 'escape'), { assetOrigins: [foreignOrigin] }));
  check(forbiddenRequests === 0, 'a later unapproved redirect hop is rejected before its server receives a request');
  await assert.rejects(generatePageCss(browser, { ...job, url: origin + '/loop', viewports: [{ width: 1365, height: 900 }] }));
  checks++;
  const foreignJob = { ...job, url: origin + '/foreign', styles: [{ href: foreignOrigin + '/external.css', media: 'all' }], viewports: [{ width: 1365, height: 900 }] };
  const beforeForeign = foreignRequests;
  await assert.rejects(generatePageCss(browser, foreignJob), /not loaded or was blocked|prerequisite failed or was blocked/);
  check(foreignRequests === beforeForeign, 'unapproved foreign CSS is blocked before the request reaches its server');
  const approved = await generatePageCss(browser, foreignJob, { assetOrigins: [foreignOrigin] });
  check(approved.results[0].css.includes('brown') && foreignRequests > 0, 'an explicitly allowlisted CDN stylesheet can be generated');
  const beforeRedirect = foreignRequests;
  await assert.rejects(generatePageCss(browser, { ...job, url: origin + '/redirect', viewports: [{ width: 1365, height: 900 }] }, { assetOrigins: [foreignOrigin] }));
  check(foreignRequests === beforeRedirect, 'trusted asset origins cannot receive a document-navigation redirect');
  for (const endpoint of ['csp', 'csp-meta']) {
    await assert.rejects(generatePageCss(browser, { ...job, url: origin + '/' + endpoint, mode: 'critical', viewports: [{ width: 1365, height: 900 }] }), (error) => error.category === 'unsupported' && /enforcing Content-Security-Policy/.test(error.message));
    checks++;
  }
  const reportOnly = await generatePageCss(browser, { ...job, url: origin + '/csp-report', mode: 'critical', viewports: [{ width: 1365, height: 900 }] });
  check(reportOnly.results[0].css.includes('.hero'), 'CSP report-only headers do not disable critical generation');
  check((await (await fetch(origin + '/csp')).text()).includes('<link rel="stylesheet" href="/assets/main.css">'), 'a rejected enforcing-CSP job leaves its original blocking stylesheet intact');
  assert.throws(() => prepareStylesheet('.hero { content: "</style><script>bad()</script>"; }', origin + '/unsafe.css'), /style boundary/);
  checks++;
  const escaped = prepareStylesheet('.hero { background: url("im\\61 ge.png"); }', origin + '/escaped.css');
  assert.throws(() => compileStylesheet(escaped, [{ exists: true, visible: true }], [], origin + '/escaped.css', 'used'), (error) => error.category === 'unsupported' && /escaped CSS URL/.test(error.message));
  checks++;
  const empty = prepareStylesheet('.absent { color: red; }', origin + '/empty.css');
  check(compileStylesheet(empty, [{ exists: false, visible: false }], [], origin + '/empty.css', 'used') === '/* NoobRocket: no matching rules */', 'a legitimately empty subset has an explicit successful CSS comment');
  console.log(`PASS: ${checks} local Chromium/CSS generator assertions.`);
} finally {
  await browser.close();
  await Promise.all([new Promise((resolve) => server.close(resolve)), new Promise((resolve) => foreign.close(resolve)), new Promise((resolve) => forbidden.close(resolve))]);
}
