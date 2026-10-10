#!/usr/bin/env node
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { writeFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const options = {};
for (let index = 2; index < process.argv.length; index += 2) {
  const key = process.argv[index].slice(2);
  if (key === 'help') { process.stdout.write('node tools/production-check.mjs --wordpress /path/wp --php /path/php --browser /path/chromium --out preflight.json [--site-url https://staging.example.com/subsite/] [--require-linux yes] [--require-mysql yes] [--require-multisite yes|no] [--dependencies /path/local-css/package.json]\nRead-only WordPress inventory and real sandbox-enabled Chromium launch. No environment is marked passed without running it.\n'); process.exit(0); }
  if (!['wordpress', 'php', 'browser', 'out', 'site-url', 'require-linux', 'require-mysql', 'require-multisite', 'dependencies'].includes(key) || !process.argv[index + 1] || options[key]) throw new Error(`Invalid or duplicate argument: ${process.argv[index]}`);
  options[key] = process.argv[index + 1];
}
for (const key of ['wordpress', 'php', 'browser', 'out']) if (!options[key]) throw new Error(`--${key} required.`);
for (const key of ['require-linux', 'require-mysql']) if (options[key] && options[key] !== 'yes') throw new Error(`--${key} accepts yes.`);
if (options['require-multisite'] && !['yes', 'no'].includes(options['require-multisite'])) throw new Error('--require-multisite accepts yes or no.');

const result = { protocol: 'noobrocket-production-check-v1', started: new Date().toISOString(), checks: [], wordpress: null, browser: null };
const add = (name, passed, evidence) => result.checks.push({ name, passed, evidence });
try {
  const inventory = await new Promise((resolveResult, reject) => {
    const child = spawn(options.php, [fileURLToPath(new URL('./production-preflight.php', import.meta.url)), options.wordpress, options['site-url'] || ''], { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    let stdout = '';
    let stderr = '';
    const timer = setTimeout(() => child.kill(), 60000);
    child.stdout.on('data', (chunk) => { stdout += chunk; if (stdout.length > 1048576) child.kill(); });
    child.stderr.on('data', (chunk) => { if (stderr.length < 4096) stderr += chunk; });
    child.on('error', reject);
    child.on('close', (code) => { clearTimeout(timer); try { resolveResult({ code, value: JSON.parse(stdout), stderr }); } catch { reject(new Error(`PHP inventory failed (${code}): ${stderr.slice(0, 1000) || stdout.slice(0, 1000)}`)); } });
  });
  result.wordpress = inventory.value;
  add('wordpress_preflight', inventory.code === 0 && !inventory.stderr.trim(), inventory.stderr.trim() || inventory.value.checks);
  if (options['require-mysql']) add('mysql_or_mariadb', ['mysql', 'mariadb'].includes(inventory.value.database), inventory.value.database_version);
  if (options['require-multisite']) add('multisite_mode', inventory.value.multisite === (options['require-multisite'] === 'yes'), inventory.value.multisite);
} catch (error) { add('wordpress_preflight', false, error.message); }

try {
  const require = createRequire(resolve(options.dependencies || fileURLToPath(new URL('../noobrocket/local-css/package.json', import.meta.url))));
  const { chromium } = require('playwright-core');
  const browser = await chromium.launch({ executablePath: options.browser, headless: true, chromiumSandbox: process.platform !== 'win32', args: ['--enable-automation'] });
  try {
    const session = await browser.newBrowserCDPSession();
    const { arguments: argumentsList } = await session.send('Browser.getBrowserCommandLine');
    const page = await browser.newPage();
    await page.setContent('<!doctype html><h1>Chromium production preflight</h1>');
    const text = await page.locator('h1').textContent();
    const sandboxEnabled = process.platform === 'linux' && !argumentsList.includes('--no-sandbox') && !argumentsList.includes('--disable-setuid-sandbox');
    result.browser = { version: browser.version(), platform: process.platform, sandboxEnabled, rendered: text === 'Chromium production preflight' };
    add('chromium_render', result.browser.rendered, browser.version());
    if (process.platform === 'linux' || options['require-linux']) add('linux_sandbox', sandboxEnabled, process.platform === 'linux' ? 'Chromium launched without sandbox-disable flags.' : `Unavailable on ${process.platform}.`);
    await session.detach();
  } finally { await browser.close(); }
} catch (error) { add('chromium_render', false, error.message.slice(0, 2000)); }

result.finished = new Date().toISOString();
result.passed = result.checks.every((check) => check.passed);
await mkdir(dirname(resolve(options.out)), { recursive: true });
await writeFile(options.out, JSON.stringify(result, null, 2) + '\n', { flag: 'wx' });
process.stdout.write(`${result.passed ? 'PASS' : 'FAIL'}: ${result.checks.filter((check) => check.passed).length}/${result.checks.length} preflight checks; ${options.out}\n`);
if (!result.passed) process.exitCode = 1;
