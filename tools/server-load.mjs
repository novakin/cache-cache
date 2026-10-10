#!/usr/bin/env node
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

const options = { duration: 30, interval: 1000 };
for (let index = 2; index < process.argv.length; index += 2) {
  const key = process.argv[index].slice(2);
  if (key === 'help') { process.stdout.write('node tools/server-load.mjs --out load.json [--duration 30] [--interval 1000]\nLinux same-host CPU, memory and load samples. Run beside a bounded worker or benchmark. Includes unrelated host activity; never attributes remote CPU to browser timing.\n'); process.exit(0); }
  if (!['out', 'duration', 'interval'].includes(key) || !process.argv[index + 1]) throw new Error(`Invalid argument: ${process.argv[index]}`);
  options[key] = key === 'out' ? process.argv[index + 1] : Number(process.argv[index + 1]);
}
if (process.platform !== 'linux') throw new Error('Linux /proc required. Run on the WordPress/worker host.');
if (!options.out || !Number.isInteger(options.duration) || options.duration < 1 || options.duration > 600 || !Number.isInteger(options.interval) || options.interval < 100 || options.interval > 10000) throw new Error('Require --out, duration 1–600 seconds, interval 100–10000 ms.');

async function snapshot() {
  const [stat, memory, load] = await Promise.all([readFile('/proc/stat', 'utf8'), readFile('/proc/meminfo', 'utf8'), readFile('/proc/loadavg', 'utf8')]);
  const ticks = stat.split('\n')[0].trim().split(/\s+/).slice(1, 9).map(Number);
  const memoryKB = Object.fromEntries([...memory.matchAll(/^(MemTotal|MemAvailable):\s+(\d+) kB$/gm)].map((match) => [match[1], Number(match[2])]));
  return { at: new Date().toISOString(), ticks, total: ticks.reduce((sum, value) => sum + value, 0), idle: ticks[3] + ticks[4], memoryKB, load: load.trim().split(/\s+/).slice(0, 3).map(Number) };
}
const result = { protocol: 'noobrocket-host-load-v1', platform: process.platform, started: new Date().toISOString(), durationSeconds: options.duration, intervalMs: options.interval, attribution: 'Entire host including unrelated activity; pair idle baseline and worker run on the same host. This is not per-plugin CPU.', samples: [] };
let previous = await snapshot();
const stopAt = performance.now() + options.duration * 1000;
while (performance.now() < stopAt) {
  await new Promise((resolveResult) => setTimeout(resolveResult, Math.min(options.interval, stopAt - performance.now())));
  const current = await snapshot();
  const total = current.total - previous.total;
  result.samples.push({ at: current.at, cpuBusyPercent: total > 0 ? 100 * (1 - (current.idle - previous.idle) / total) : null, memoryKB: current.memoryKB, load: current.load });
  previous = current;
}
result.finished = new Date().toISOString();
result.peakCpuBusyPercent = Math.max(...result.samples.map((sample) => sample.cpuBusyPercent ?? 0));
await mkdir(dirname(resolve(options.out)), { recursive: true });
await writeFile(options.out, JSON.stringify(result, null, 2) + '\n', { flag: 'wx' });
process.stdout.write(`${result.samples.length} host-load samples; peak busy ${result.peakCpuBusyPercent.toFixed(1)}%; ${options.out}\n`);
