/** Regenerate the beacon from its maintained readable runtime. */
import { readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const { minify } = createRequire(process.env.NOOBROCKET_TOOLS_DEPENDENCIES || new URL('./package.json', import.meta.url))('terser');
const source = new URL('../noobrocket/assets/js/noobrocket-beacon.js', import.meta.url);
const output = new URL('../noobrocket/assets/js/noobrocket-beacon.min.js', import.meta.url);
const result = await minify(await readFile(source, 'utf8'), { compress: true, mangle: true, format: { comments: /^!/ } });
if (!result.code) throw new Error('Beacon generation returned no JavaScript.');
await writeFile(output, `${result.code}\n`);
console.log(`Generated noobrocket-beacon.min.js: ${Buffer.byteLength(result.code)} bytes.`);
