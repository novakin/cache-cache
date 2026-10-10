import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';
import { writeFile } from 'node:fs/promises';
import { gzipSync } from 'node:zlib';
import { generatePageCss } from '../../noobrocket/local-css/generator.mjs';

const [executablePath, output] = process.argv.slice(2);
if (!executablePath || !output) throw new Error('Provide Chromium and a new output JSON path.');
const { chromium } = createRequire(new URL('../../noobrocket/local-css/package.json', import.meta.url))('playwright-core');
const source = 'body{margin:0;font-family:system-ui}.hero{height:200px;color:navy}.below{margin-top:1500px;color:green}.menu{display:none}.menu-open .menu{display:block}' + Array.from({ length: 1000 }, (_, index) => `.unused-${index}{padding:${index % 20}px;color:rgb(1,2,3)}`).join('') + '@media print{' + Array.from({ length: 100 }, (_, index) => `.print-${index}{color:red}`).join('') + '}';
const page = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/main.css"></head><body><main class="hero">Article</main><nav class="menu">Menu</nav><section class="below">Below the fold</section></body></html>';
const server = createServer((request, response) => {
  response.setHeader('Content-Type', request.url.startsWith('/main.css') ? 'text/css' : 'text/html');
  response.end(request.url.startsWith('/main.css') ? source : page);
});
let browser;
try {
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const origin = `http://127.0.0.1:${server.address().port}`;
  browser = await chromium.launch({ executablePath, headless: true, chromiumSandbox: process.platform !== 'win32' });
  const profiles = [];
  for (const mode of ['critical', 'used']) {
    const samples = [];
    let results;
    for (let sample = 0; sample < 3; sample++) {
      const start = performance.now();
      ({ results } = await generatePageCss(browser, { url: origin + '/', mode, mobile: false, styles: [{ href: origin + '/main.css', media: 'all' }], safelist: ['menu-open'], viewports: [{ width: 1366, height: 768 }, { width: 1024, height: 768 }, { width: 390, height: 844 }, { width: 768, height: 1024 }] }));
      samples.push(performance.now() - start);
      assert.ok(results[0].css.includes('.menu-open') && results[0].css.includes('@media print'), 'dynamic state and conditional grammar remain protected');
      assert.match(results[0].source_hash, /^[a-f0-9]{64}$/);
    }
    const generated = results[0].css;
    profiles.push({ mode, viewports: 4, job_ms: samples, source_bytes: Buffer.byteLength(source), source_gzip_bytes: gzipSync(source).length, generated_bytes: Buffer.byteLength(generated), generated_gzip_bytes: gzipSync(generated).length, conditional_print_retained: generated.includes('.print-99'), source_identity_present: true });
  }
  const result = { protocol: 'noobrocket-optimizer-isolated-profile-v1', platform: process.platform, node: process.version, chromium: browser.version(), profiles, limits: 'Synthetic article, loopback HTTP, unthrottled Windows Chromium. Job wall time includes browser work; CPU/RSS for Chromium/PHP and installed-site FCP/LCP/CLS are not measured. Bytes describe this fixture, not production savings.' };
  await writeFile(output, JSON.stringify(result, null, 2) + '\n', { flag: 'wx' });
  console.log(`PASS: bounded optimizer profile (6 jobs, 24 viewport captures; ${output}).`);
} finally {
  if (browser) await browser.close();
  await new Promise((resolve) => server.close(resolve));
}
