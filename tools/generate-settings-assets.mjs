/** Regenerate the legacy settings bundle from its maintained, readable input. */
import { readFile, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
const { minify } = createRequire(process.env.NOOBROCKET_TOOLS_DEPENDENCIES || new URL('./package.json', import.meta.url))('terser');

const source = new URL('../noobrocket/assets/js/noobrocket-settings.js', import.meta.url);
const target = new URL('../noobrocket/assets/js/noobrocket-settings.min.js', import.meta.url);
const result = await minify(await readFile(source, 'utf8'), {
  compress: true,
  mangle: true,
  format: { comments: /^!/ }
});
if (!result.code) throw new Error('Settings bundle generation returned no JavaScript.');
await writeFile(target, `${result.code}\n`);
console.log(`Generated noobrocket-settings.min.js: ${Buffer.byteLength(result.code)} bytes.`);
