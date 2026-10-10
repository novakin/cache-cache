#!/usr/bin/env node
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import { generatePageCss } from './generator.mjs';

const help = `NoobRocket local CSS worker (Node.js 22+, Chromium)
Usage: node worker.mjs --wordpress /path/to/wordpress --php /path/to/php --browser /path/to/chromium [--site-url https://example.com/subsite/] [--once] [--limit 10] [--max-seconds 120] [--delay-ms 1000] [--check] [--asset-origin https://cdn.example.com]

--once              Drain ready jobs and exit when the queue is empty.
--limit NUMBER      Exit after NUMBER claimed jobs (default: no limit).
--max-seconds NUMBER Stop between jobs after this time budget.
--delay-ms NUMBER   Pause between jobs (default: 1000 milliseconds).
--check             Check WordPress, PHP, Node.js and sandboxed Chromium; process no jobs.
--site-url URL      Select a WordPress site, including a multisite subsite.
--asset-origin URL  Allow an additional trusted asset origin; repeat for each CDN.
Only the page origin and explicitly allowed asset origins can load resources.
CSS failures preserve the original page styles through the PHP job bridge.
`;

function argumentsFrom(argv) {
  const result = { php: 'php', assetOrigins: [], once: false, check: false, limit: Infinity, maxSeconds: Infinity, delayMs: 1000 };
  for (let index = 0; index < argv.length; index++) {
    const flag = argv[index];
    if (flag === '--help' || flag === '-h') return { help: true };
    if (flag === '--once') { result.once = true; continue; }
    if (flag === '--check') { result.check = true; continue; }
    const name = { '--wordpress': 'wordpress', '--php': 'php', '--browser': 'browser', '--site-url': 'siteUrl', '--limit': 'limit', '--max-seconds': 'maxSeconds', '--delay-ms': 'delayMs', '--asset-origin': 'assetOrigins' }[flag];
    if (!name || !argv[index + 1] || argv[index + 1].startsWith('--')) throw new Error(`Missing value or unknown argument: ${flag}`);
    const value = argv[++index];
    if (name === 'assetOrigins') {
      const origin = new URL(value);
      if (!['http:', 'https:'].includes(origin.protocol) || origin.username || origin.password || origin.pathname !== '/' || origin.search || origin.hash) throw new Error('Asset origins must be HTTP(S) origins without paths or credentials.');
      result.assetOrigins.push(origin.origin);
    } else if (name === 'siteUrl') {
      const site = new URL(value);
      if (!['http:', 'https:'].includes(site.protocol) || site.username || site.password || site.search || site.hash) throw new Error('Site URL must be an HTTP(S) site root without credentials, query or fragment.');
      result.siteUrl = site.href;
    } else result[name] = value;
  }
  if (!result.wordpress || !result.browser) throw new Error('--wordpress and --browser are required.');
  if (result.limit !== Infinity) {
    result.limit = Number(result.limit);
    if (!Number.isSafeInteger(result.limit) || result.limit < 1) throw new Error('--limit must be a positive integer.');
  }
  for (const [name,minimum,maximum] of [['maxSeconds',1,86400],['delayMs',0,60000]]) {
    if (result[name] === Infinity) continue;
    result[name] = Number(result[name]);
    if (!Number.isSafeInteger(result[name]) || result[name] < minimum || result[name] > maximum) throw new Error(`Invalid ${name}: expected an integer from ${minimum} to ${maximum}.`);
  }
  return result;
}

async function bridge(options, input) {
  const filename = fileURLToPath(new URL('./bridge.php', import.meta.url));
  return new Promise((resolve, reject) => {
    const child = spawn(options.php, [filename, options.wordpress, ...(options.siteUrl ? [options.siteUrl] : [])], { windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
    let stdout = '';
    let stderr = '';
    let finished = false;
    const finish = (error, output) => {
      if (finished) return;
      finished = true;
      clearTimeout(timer);
      if (error) reject(error); else resolve(output);
    };
    const timer = setTimeout(() => { child.kill(); finish(new Error('PHP bridge timed out.')); }, 60000);
    child.on('error', (error) => finish(error));
    child.stdin.on('error', (error) => finish(error));
    child.stdout.on('data', (chunk) => {
      stdout += chunk;
      if (stdout.length > 1048576) { child.kill(); finish(new Error('PHP bridge response exceeded 1 MB.')); }
    });
    child.stderr.on('data', (chunk) => { if (stderr.length < 4096) stderr += chunk; });
    child.on('close', (code) => {
      if (code !== 0) return finish(new Error(`PHP bridge failed (${code}): ${stderr.trim().slice(0, 1000)}`));
      try {
        const output = JSON.parse(stdout);
        if (output.ok !== true) throw new Error(output.error || 'PHP bridge rejected the request.');
        finish(null, output);
      } catch (error) { finish(new Error(`Invalid PHP bridge response: ${error.message}`)); }
    });
    child.stdin.end(JSON.stringify(input));
  });
}

async function main() {
  const options = argumentsFrom(process.argv.slice(2));
  if (options.help) { process.stdout.write(help); return; }
  if (Number(process.versions.node.split('.')[0]) < 22) throw new Error('Node.js 22 or later is required.');
  const runtime = { node: process.versions.node, platform: process.platform, sandbox: process.platform !== 'win32', processed: 0, failed: 0, job_id: 0, error: '' };
  const { worker } = await bridge(options, { action: 'worker_start', runtime });
  if (!worker) { process.stdout.write('Another local CSS worker is active for this site.\n'); return; }
  let browser;
  let processed = 0;
  let failed = 0;
  let fatal;
  const deadline = Date.now() + options.maxSeconds * 1000;
  const report = () => {
    const usage = process.resourceUsage();
    return { ...runtime, processed, failed, node_cpu_ms: Math.round((usage.userCPUTime + usage.systemCPUTime) / 1000), peak_node_memory_mb: Math.round(usage.maxRSS / 1024) };
  };
  try {
    browser = await chromium.launch({ executablePath: options.browser, headless: true, chromiumSandbox: runtime.sandbox });
    runtime.chromium = browser.version();
    await bridge(options, { action: 'worker_update', worker, state: 'ready', runtime: report() });
    if (options.check) {
      process.stdout.write(`Ready: Node.js ${runtime.node}, Chromium ${runtime.chromium}, WordPress/PHP bridge; Chromium sandbox ${runtime.sandbox ? 'enabled' : 'unavailable on Windows'}.\n`);
      return;
    }
    while (processed < options.limit && Date.now() < deadline) {
      const { job, paused } = await bridge(options, { action: 'claim', worker, runtime: report() });
      if (!job) {
        if (paused) process.stdout.write('Local CSS generation is paused.\n');
        if (options.once) break;
        await new Promise((resolve) => setTimeout(resolve, Math.min(5000, Math.max(0, deadline - Date.now()))));
        continue;
      }
      processed++;
      const identity = { id: job.id, token: job.token };
      try {
        const { results } = await generatePageCss(browser, job, { assetOrigins: options.assetOrigins });
        await bridge(options, { action: 'complete', ...identity, fingerprint: job.fingerprint, results: results.map(({ href, css, source_hash }) => ({ href, css, source_hash })) });
        process.stdout.write(`Completed job ${job.id} (${job.mode}, ${results.length} stylesheets).\n`);
      } catch (error) {
        failed++;
        runtime.error = error.message.slice(0, 1000);
        process.stderr.write(`Failed job ${job.id}: ${error.message}\n`);
        try {
          const category = error.category || (error.name === 'CssSyntaxError' ? 'unsupported' : 'transient');
          await bridge(options, { action: 'fail', ...identity, error: error.message.slice(0, 1000), category });
        } catch (cleanupError) {
          // Content edits can invalidate both the completion token and the failure mark.
          // Continue draining; a broken bridge will still stop the next claim.
          process.stderr.write(`Could not mark job ${job.id} as failed: ${cleanupError.message}\n`);
        }
      }
      runtime.job_id = 0;
      await bridge(options, { action: 'worker_update', worker, state: 'idle', runtime: report() });
      if (processed < options.limit && Date.now() < deadline && options.delayMs) await new Promise((resolve) => setTimeout(resolve, Math.min(options.delayMs, Math.max(0, deadline - Date.now()))));
    }
  } catch (error) {
    fatal = error;
    runtime.error = error.message.slice(0, 1000);
    throw error;
  } finally {
    try { if (browser) await browser.close(); } catch (error) {
      runtime.error = error.message.slice(0, 1000);
      fatal ||= error;
      process.exitCode = 1;
    }
    try {
      await bridge(options, { action: 'worker_stop', worker, state: fatal ? 'error' : options.check ? 'ready' : failed ? 'error' : 'stopped', runtime: report() });
    } catch (error) {
      process.stderr.write(`Could not release worker lease: ${error.message}\n`);
      process.exitCode = 1;
    }
  }
  process.stdout.write(`Processed ${processed} jobs; ${failed} failed.\n`);
  if (failed) process.exitCode = 1;
}

main().catch((error) => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
