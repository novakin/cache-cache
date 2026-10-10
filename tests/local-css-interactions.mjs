import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';

const generatorURL = process.argv[2] ? pathToFileURL(process.argv[2]) : new URL('../noobrocket/local-css/generator.mjs', import.meta.url);
const { generatePageCss } = await import(generatorURL);
const { chromium } = createRequire(generatorURL)('playwright-core');
const executablePath = process.argv[3];
if (!executablePath) throw new Error('Provide generator path and Chromium executable.');
let checks = 0;
const check = (condition, message) => { assert.ok(condition, message); checks++; };
const css = `body{margin:0;font-family:sans-serif}.menu,.dialog,.tab-panel,.ajax-result{display:none}.menu-open .menu,.dialog-open .dialog,.tab-panel.active,.ajax-result.ajax-ready{display:block}.menu a{color:rgb(0,128,0)}.dialog{border:3px solid rgb(128,0,128)}.tab-panel{padding:14px}.ajax-ready{background:rgb(255,255,0)}.woocommerce .cart-added{color:rgb(0,0,255)}.woocommerce .quantity:focus{outline:2px solid rgb(255,0,0)}.unused{color:red}`;
let generated = null;
const html = () => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="${generated ? '/generated.css' : '/source.css'}"><title>Interaction proof</title></head><body><button id="menu" onclick="document.body.classList.toggle('menu-open')">Menu</button><nav class="menu"><a href="#">Navigation</a></nav><button id="dialog" onclick="document.body.classList.add('dialog-open')">Dialog</button><section class="dialog">Dialog contents</section><button id="tab" onclick="document.querySelector('.tab-panel').classList.add('active')">Tab</button><section class="tab-panel">Tab contents</section><button id="ajax" onclick="document.querySelector('.ajax-result').innerHTML='<span class=ajax-ready>AJAX contents</span>';document.querySelector('.ajax-result').classList.add('ajax-ready')">Load</button><section class="ajax-result"></section><div class="woocommerce"><input class="quantity" type="number" value="1"><button id="cart" onclick="document.querySelector('.cart-status').classList.add('cart-added');document.querySelector('.cart-status').textContent='Added to cart'">Add to cart</button><span class="cart-status"></span></div></body></html>`;
const server = createServer((request, response) => { if (request.url.endsWith('.css')) { response.setHeader('Content-Type', 'text/css'); response.end(request.url === '/generated.css' ? generated : css); } else { response.setHeader('Content-Type', 'text/html'); response.end(html()); } });
await new Promise((resolveResult) => server.listen(0, '127.0.0.1', resolveResult));
const origin = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
try {
  const result = await generatePageCss(browser, { url: origin + '/', mode: 'used', mobile: false, styles: [{ href: origin + '/source.css', media: 'all' }], safelist: ['menu-open', 'dialog-open', '.active', 'ajax-ready', 'cart-added'], viewports: [{ width: 1365, height: 900 }, { width: 390, height: 844 }] });
  generated = result.results[0].css;
  check(!generated.includes('.unused'), 'ordinary unused CSS removed');
  check(['menu-open', 'dialog-open', '.active', 'ajax-ready', 'cart-added'].every((selector) => generated.includes(selector)), 'future selector states preserved by explicit safelist');
  for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
    const page = await browser.newPage({ viewport });
    try {
      await page.goto(origin + '/');
      check(!(await page.locator('.menu').isVisible()) && !(await page.locator('.dialog').isVisible()), 'initial hidden menu/dialog retained');
      await page.locator('#menu').click();
      check(await page.locator('.menu').isVisible() && await page.locator('.menu a').evaluate((node) => getComputedStyle(node).color === 'rgb(0, 128, 0)'), 'opened menu retains styling');
      await page.locator('#dialog').click();
      check(await page.locator('.dialog').isVisible() && await page.locator('.dialog').evaluate((node) => getComputedStyle(node).borderTopWidth === '3px'), 'opened dialog retains styling');
      await page.locator('#tab').click();
      check(await page.locator('.tab-panel').isVisible() && await page.locator('.tab-panel').evaluate((node) => getComputedStyle(node).paddingTop === '14px'), 'activated tab retains styling');
      await page.locator('#ajax').click();
      check(await page.locator('.ajax-result').isVisible() && await page.locator('.ajax-result').evaluate((node) => getComputedStyle(node).backgroundColor === 'rgb(255, 255, 0)'), 'new AJAX content retains styling');
      await page.locator('#cart').click();
      check(await page.locator('.cart-status').textContent() === 'Added to cart' && await page.locator('.cart-status').evaluate((node) => getComputedStyle(node).color === 'rgb(0, 0, 255)'), 'WooCommerce-like cart state retains styling');
      await page.locator('.quantity').focus();
      check(await page.locator('.quantity').evaluate((node) => getComputedStyle(node).outlineColor === 'rgb(255, 0, 0)'), 'quantity focus state retained');
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'interactive fixture fits viewport');
    } finally { await page.close(); }
  }
  console.log(`${checks} interactive CSS assertions passed; WooCommerce-like fixture, not installed WooCommerce.`);
} finally { await browser.close(); await new Promise((resolveResult) => server.close(resolveResult)); }
