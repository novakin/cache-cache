/** Regenerate link preparation from its maintained runtime. */
import { readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';

const { minify } = createRequire(process.env.NOOBROCKET_TOOLS_DEPENDENCIES || new URL('./package.json', import.meta.url))('terser');
const source = new URL('../noobrocket/assets/js/preload-links.js', import.meta.url);
const output = new URL('../noobrocket/assets/js/preload-links.min.js', import.meta.url);
const result = await minify(await readFile(source, 'utf8'), { compress: true, mangle: true, format: { comments: /^!/ } });
if (!result.code) throw new Error('Link preparation generation returned no JavaScript.');
await writeFile(output, `${result.code}\n`);
console.log(`Generated preload-links.min.js: ${Buffer.byteLength(result.code)} bytes.`);
