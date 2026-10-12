/** Actual beacon assets, native CSSOM/fonts and intercepted fixture HTTP; no WordPress/provider access. */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const executablePath = process.argv[2];
if (!executablePath) throw new Error('Provide the Chromium executable.');
const { chromium } = createRequire(new URL('../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const source = await readFile(new URL('../noobrocket/assets/js/noobrocket-beacon.js', import.meta.url), 'utf8');
const minified = await readFile(new URL('../noobrocket/assets/js/noobrocket-beacon.min.js', import.meta.url), 'utf8');
// Reuse the shipped font and its private-use glyphs; no newly vendored fonts or glyph-shape claim.
const font = await readFile(new URL('../noobrocket/assets/fonts/icomoon.woff', import.meta.url));
const origin = 'http://fonts.fixture.test';
const provider = 'http://provider.fixture.test';
const url = name => `${origin}/${name}.woff`;
const providerNormal = `${provider}/normal.woff`;
const providerItalic = `${provider}/italic.woff`;
const face = (family, href, weight = '400', style = 'normal', range = '') => `@font-face{font-family:"${family}";src:url("${href}") format("woff");font-weight:${weight};font-style:${style};${range ? 'unicode-range:' + range + ';' : ''}}`;
const css = [
  face('Primary Fixture', url('italic'), '400', 'italic'),
  face('Primary Fixture', url('normal')),
  face('Primary Fixture', url('normal')), // duplicate face
  face('Shared Fixture', url('shared')),
  face('Shared Fixture', url('shared'), '700'),
  face('Subset Fixture', url('subset-a'), '400', 'normal', 'U+E900'),
  face('Subset Fixture', url('subset-b'), '400', 'normal', 'U+E901'),
  face('Range Fixture', url('range'), '100 900'),
  face('Below Fixture', url('above-normal')),
  face('Below Fixture', url('below-italic'), '400', 'italic'),
  face('Only Italic Fixture', url('fallback-italic'), '400', 'italic'),
  face('Only Normal Fixture', url('fallback-normal')),
  face('Oblique Fixture', url('oblique'), '400', 'oblique 0deg 20deg'),
  face('Excluded Fixture', url('excluded')),
  face('Unloaded Fixture', url('unloaded')),
  'body{margin:0;color:#111}p{margin:1px;font-size:20px;line-height:24px}',
  '.pseudo::before{content:"\\e900";font-family:"Primary Fixture";font-style:italic}',
].join('\n');
const providerCss = face('Provider Fixture', providerNormal) + face('Provider Fixture', providerItalic, '400', 'italic');
const expected = [url('italic'), url('normal'), url('shared'), url('subset-a'), url('subset-b'), url('range'), url('above-normal'), url('fallback-italic'), url('fallback-normal'), url('oblique'), providerNormal].sort();
const fields = body => Object.fromEntries([...body.matchAll(/name="([^"]+)"\r\n\r\n([\s\S]*?)(?=\r\n--)/g)].map(match => [match[1], match[2]]));
const glyph = '&#xe900;';
const p = (id, family, style = 'normal', weight = '400', text = glyph) => `<p id="${id}" style="font-family:'${family}';font-style:${style};font-weight:${weight}">${text}</p>`;
let cases = 0;
const browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
try {
  for (const [variant, script] of Object.entries({ source, minified })) {
    for (const width of [390, 1365]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } });
      const requests = [];
      const unexpected = [];
      const errors = [];
      let saved;
      const config = { nonce: 'fixture-nonce', url: `${origin}/page`, ajax_url: `${origin}/admin-ajax.php`,
        is_mobile: false, delay: 0, debug: false, width_threshold: 1, height_threshold: 1,
        processed_extensions: ['woff'], preload_fonts_exclusions: ['Excluded Fixture'], external_font_exclusions: [],
        status: { atf: false, lrc: false, preload_fonts: true, preconnect_external_domain: false } };
      const html = `<!doctype html><html><head><meta charset="utf-8"><style>${css}</style><link rel="stylesheet" crossorigin="anonymous" href="${provider}/fonts.css"></head><body>
        ${p('normal', 'Primary Fixture')}${p('italic', 'Primary Fixture', 'italic')}
        <p class="pseudo"></p>
        ${p('shared-normal', 'Shared Fixture')}${p('shared-bold', 'Shared Fixture', 'normal', '700')}
        ${p('subsets', 'Subset Fixture', 'normal', '400', '&#xe900;&#xe901;')}
        ${p('range', 'Range Fixture', 'normal', '650')}${p('above-normal', 'Below Fixture')}
        ${p('normal-uses-italic', 'Only Italic Fixture')}${p('oblique-uses-italic', 'Only Italic Fixture', 'oblique 14deg')}
        ${p('italic-uses-normal', 'Only Normal Fixture', 'italic')}${p('oblique', 'Oblique Fixture', 'oblique 12deg')}
        ${p('excluded', 'Excluded Fixture')}${p('provider-normal', 'Provider Fixture')}
        <div style="margin-top:1200px">${p('below-italic', 'Below Fixture', 'italic')}${p('provider-below-italic', 'Provider Fixture', 'italic')}</div>
        <script>window.noobrocket_beacon_data=${JSON.stringify(config)};</script>
        <script data-name="noobrocket-noobrocket-beacon">${script}</script>
      </body></html><!--Debug: cached-->`;
      await context.route('**/*', route => {
        const request = route.request();
        const href = request.url();
        requests.push({ url: href, type: request.resourceType() });
        if (href === `${origin}/page`) return route.fulfill({ contentType: 'text/html', body: html });
        if (href === `${provider}/fonts.css`) return route.fulfill({ contentType: 'text/css', headers: { 'access-control-allow-origin': '*' }, body: providerCss });
        if (href.endsWith('.woff') && (href.startsWith(origin + '/') || href === providerNormal || href === providerItalic)) {
          return route.fulfill({ contentType: 'font/woff', headers: { 'access-control-allow-origin': '*' }, body: font });
        }
        if (href === config.ajax_url) {
          const input = fields(request.postData());
          if (input.action === 'noobrocket_check_beacon') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { preload_fonts: false } }) });
          if (input.action === 'noobrocket_beacon') {
            saved = JSON.parse(input.results).preload_fonts;
            return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { preload_fonts: { status: 'completed' } } }) });
          }
        }
        unexpected.push(href);
        return route.abort();
      });
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      await page.goto(`${origin}/page`);
      await page.waitForFunction(() => document.querySelector('[data-name="noobrocket-noobrocket-beacon"]').getAttribute('beacon-status') === 'saved');
      assert.deepEqual(saved.sort(), expected, `${variant}/${width}: exact visible-style font URLs`);
      const proof = await page.evaluate(async () => {
        await document.fonts.ready;
        const faces = [...document.fonts].map(face => ({ family: face.family, weight: face.weight, style: face.style, status: face.status }));
        const styles = Object.fromEntries(['normal', 'italic', 'range', 'normal-uses-italic', 'oblique-uses-italic', 'italic-uses-normal', 'oblique'].map(id => {
          const style = getComputedStyle(document.getElementById(id));
          return [id, { family: style.fontFamily, weight: style.fontWeight, style: style.fontStyle }];
        }));
        return { faces, styles, resources: performance.getEntriesByType('resource').filter(entry => entry.name.endsWith('.woff')).map(entry => entry.name) };
      });
      assert.equal(proof.styles.normal.style, 'normal');
      assert.equal(proof.styles.italic.style, 'italic');
      assert.equal(proof.styles.range.weight, '650');
      assert(proof.styles['oblique-uses-italic'].style.startsWith('oblique'));
      assert(proof.faces.some(face => face.family === 'Range Fixture' && face.weight === '100 900' && face.status === 'loaded'));
      assert(proof.faces.some(face => face.family === 'Only Italic Fixture' && face.style === 'italic' && face.status === 'loaded'));
      assert(proof.faces.some(face => face.family === 'Only Normal Fixture' && face.style === 'normal' && face.status === 'loaded'));
      assert(proof.faces.some(face => face.family === 'Oblique Fixture' && face.style.startsWith('oblique') && face.status === 'loaded'));
      assert(proof.faces.some(face => face.family === 'Unloaded Fixture' && face.status === 'unloaded'));
      for (const href of expected) assert(proof.resources.includes(href), `Collected URL was actually loaded: ${href}`);
      for (const href of [url('below-italic'), providerItalic, url('excluded')]) {
        assert(proof.resources.includes(href), 'Negative control is actually loaded');
        assert(!saved.includes(href), 'Below-fold/excluded loaded faces stay excluded');
      }
      const session = await context.newCDPSession(page);
      await session.send('DOM.enable');
      await session.send('CSS.enable');
      const { root } = await session.send('DOM.getDocument');
      for (const selector of ['#normal', '#italic', '#range', '#normal-uses-italic', '#oblique-uses-italic', '#italic-uses-normal']) {
        const { nodeId } = await session.send('DOM.querySelector', { nodeId: root.nodeId, selector });
        const { fonts } = await session.send('CSS.getPlatformFontsForNode', { nodeId });
        assert(fonts.some(font => font.isCustomFont && font.glyphCount > 0), `${selector}: native custom font supplies fixture glyphs`);
      }
      assert.deepEqual(errors, []);
      assert.deepEqual(unexpected, []);
      await context.close();
      cases++;
    }
  }
  console.log(`PASS: ${cases} actual source/minified beacon cases at390/1365; native font-face styles/weights/loads, shared/subset/range descriptors, pseudos, style fallback, unloaded/excluded/below-fold and mocked external-provider controls. No WordPress or provider requests; no glyph-shape/performance claim.`);
} finally {
  await browser.close();
}
