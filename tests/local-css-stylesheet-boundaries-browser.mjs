import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { generatePageCss } from '../noobrocket/local-css/generator.mjs';

const [php, executablePath] = process.argv.slice(2);
if (!php || !executablePath) throw new Error('Provide PHP and Chromium executables.');
const require = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url));
const { chromium } = require('playwright-core');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
let fixture;
const generatedSheets = new Map();
let deliveredHtml;
const served = [];
const server = createServer((request, response) => {
  served.push(new URL(request.url, 'http://fixture').pathname);
  if (generatedSheets.has(request.url)) {
    response.writeHead(200, { 'Content-Type': 'text/css' });
    response.end(generatedSheets.get(request.url));
    return;
  }
  if (request.url.startsWith('/active.css')) {
    response.writeHead(200, { 'Content-Type': 'text/css' });
    response.end('.hero { color: rgb(7, 8, 9); } .unused { outline: 4px solid red; }');
    return;
  }
  if (request.url.startsWith('/inert.css')) {
    response.writeHead(404);
    response.end('An inert link must not be requested.');
    return;
  }
  response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
  response.end(deliveredHtml || fixture.html);
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
let browser;
try {
  const rendered = spawnSync(php, [fileURLToPath(new URL('./local-css-stylesheet-boundaries.php', import.meta.url)), '--render', origin], { encoding: 'utf8' });
  if (rendered.status !== 0) throw new Error(`PHP fixture failed (${rendered.status}): ${rendered.stderr.slice(0, 1000)}`);
  fixture = JSON.parse(rendered.stdout);
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
  for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    for (const mode of ['used', 'critical']) {
      // The manifest comes from the actual PHP owner, rather than a hand-built job.
      const generated = await generatePageCss(browser, { url: origin + '/', mode, styles: fixture.styles, viewports: [viewport] });
      check(generated.results.length === 1 && generated.results[0].href === origin + '/active.css', `${mode} ${viewport.width}: actual manifest generates only the active sheet`);
      check(generated.results[0].css.includes('.hero') && !generated.results[0].css.includes('.unused'), `${mode} ${viewport.width}: visible CSS survives and unused rule is removed`);
      generatedSheets.set(`/generated-${mode}.css`, generated.results[0].css);
      const delivery = spawnSync(php, [fileURLToPath(new URL('./local-css-stylesheet-boundaries.php', import.meta.url)), '--render-ready', origin, mode], { encoding: 'utf8', input: JSON.stringify(generated.results) });
      if (delivery.status !== 0) throw new Error(`PHP completed delivery failed (${delivery.status}): ${delivery.stderr.slice(0, 1000)}`);
      deliveredHtml = JSON.parse(delivery.stdout).html;
      const context = await browser.newContext({ viewport });
      try {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(origin, { waitUntil: 'load' });
        const state = await page.evaluate(() => ({
          color: getComputedStyle(document.querySelector('.hero')).color,
          critical: document.querySelectorAll('style[data-noobrocket-local-css="critical"]').length,
          link: document.querySelector('link#active').getAttribute('href'),
          fakeHead: document.querySelector('[data-head]').getAttribute('data-head'),
          inert: document.querySelector('textarea').value,
        }));
        check(state.color === 'rgb(7, 8, 9)', `${mode} ${viewport.width}: actual PHP completed delivery applies generated CSS`);
        check(state.critical === (mode === 'critical' ? 1 : 0) && state.link.endsWith(mode === 'used' ? '/generated-used.css' : '/active.css'), `${mode} ${viewport.width}: actual delivery follows its mode`);
        check(state.fakeHead === '</head>' && state.inert.includes('/inert.css') && errors.length === 0, `${mode} ${viewport.width}: fake boundary and inert content survive without exceptions`);
      } finally {
        await context.close();
        deliveredHtml = undefined;
      }
    }
    const context = await browser.newContext({ viewport });
    try {
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      await page.goto(origin, { waitUntil: 'load' });
      const state = await page.evaluate(() => ({
        links: [...document.querySelectorAll('link[rel~="stylesheet"]')].map((link) => link.href),
        color: getComputedStyle(document.querySelector('.hero')).color,
        templateLinks: document.querySelector('template').content.querySelector('template').content.querySelectorAll('link').length,
        textarea: document.querySelector('textarea').value,
        preview: document.querySelector('[data-preview]').getAttribute('data-preview'),
      }));
      check(state.links.length === 1 && state.links[0] === origin + '/active.css', `${viewport.width}: browser and PHP agree on active links`);
      check(state.color === 'rgb(7, 8, 9)', `${viewport.width}: original active sheet applies`);
      check(state.templateLinks === 1 && state.textarea.includes('/inert.css') && state.preview.includes('/inert.css'), `${viewport.width}: inert content remains available to its owner`);
      check(errors.length === 0, `${viewport.width}: no browser exception`);
    } finally {
      await context.close();
    }
  }
  check(!served.includes('/inert.css'), 'inert stylesheet bytes cause no browser/server request');
  console.log(`PASS local CSS stylesheet boundary browser: ${checks} assertions; 1365x900 and 390x844; real PHP manifest and generator`);
} finally {
  if (browser) await browser.close();
  await new Promise((resolve) => server.close(resolve));
}
