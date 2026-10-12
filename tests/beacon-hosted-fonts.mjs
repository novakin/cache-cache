/** Actual hosted-font collector with modeled CSSOM/DOM/resource timing; no browser or HTTP. */
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../noobrocket/assets/js/noobrocket-beacon.js', import.meta.url), 'utf8');
const start = source.indexOf('  var BeaconPreloadFonts = class {');
const end = source.indexOf('  var BeaconPreloadFonts_default =', start);
assert(start >= 0 && end > start, 'Actual collector class boundary remains available');
const origin = 'https://fonts.fixture.test';
const urls = Object.fromEntries(['italic', 'normal', 'subset', 'variable', 'unloaded', 'excluded', 'fallback'].map(name => [name, `${origin}/${name}.woff2`]));
class FontRule {
  constructor(family, weight, style, url) {
    this.style = { getPropertyValue: name => ({ 'font-family': `"${family}"`, 'font-weight': weight, 'font-style': style, src: `url("${url}")` })[name] || '' };
  }
}
const rules = [
  new FontRule('Fixture Sans', '400', 'italic', urls.italic),
  new FontRule('Fixture Sans', '400', 'normal', urls.normal),
  new FontRule('Fixture Sans', '700', 'normal', urls.normal),
  new FontRule('Fixture Sans', '400', 'normal', urls.normal), // duplicate rule
  new FontRule('Fixture Sans', '400', 'normal', urls.subset), // same weight, distinct unicode subset URL
  new FontRule('Fixture Sans', '100 900', 'normal', urls.variable),
  new FontRule('Fixture Sans', '900', 'normal', urls.unloaded),
  new FontRule('Excluded Family', '400', 'normal', urls.excluded),
];
const style = (family, weight = '400', fontStyle = 'normal', content = 'none') => ({ fontFamily: `"${family}", sans-serif`, fontWeight: weight, fontStyle, content });
const elements = [
  { tagName: 'P', textContent: 'Normal', style: style('Fixture Sans') },
  { tagName: 'P', textContent: 'Italic', style: style('Fixture Sans', '400', 'italic') },
  { tagName: 'SPAN', textContent: '', style: style('System'), before: style('Fixture Sans', '700', 'normal', '"Pseudo"') },
  { tagName: 'P', textContent: 'Excluded', style: style('Excluded Family') },
  { tagName: 'P', textContent: 'Below fold', style: style('Fixture Sans'), below: true },
];
elements.forEach(element => { element.getBoundingClientRect = () => ({ top: element.below ? 1200 : 10 }); });
const loaded = [urls.italic, urls.normal, urls.subset, urls.variable, urls.excluded];
const document = {
  fonts: { ready: Promise.resolve() },
  styleSheets: [{ href: `${origin}/fonts.css`, cssRules: rules }],
  querySelectorAll: () => [],
  getElementsByTagName: () => elements,
  documentElement: { clientHeight: 900, scrollTop: 0 },
};
const window = {
  location: { href: `${origin}/page` }, innerHeight: 900, pageYOffset: 0,
  performance: { getEntriesByType: () => loaded.map(name => ({ name })) },
  getComputedStyle: (element, pseudo) => pseudo ? element[pseudo === '::before' ? 'before' : 'after'] || style('System') : element.style,
};
const Collector = vm.runInNewContext(source.slice(start, end) + '\nBeaconPreloadFonts;', {
  URL, window, document, console,
  CSSFontFaceRule: FontRule, CSSImportRule: class {},
  Utils_default: { isElementVisible: () => true },
  fetch: () => { throw new Error('Modeled collector must not fetch resources'); },
});
const collector = new Collector({ processed_extensions: ['woff2'], preload_fonts_exclusions: ['Excluded Family'], external_font_exclusions: [] }, { logMessage() {} });
let summary;
const summarize = collector.summarizeMatches;
collector.summarizeMatches = function (...args) { summary = summarize.apply(this, args); return summary; };
await collector.run();
assert.deepEqual([...collector.getResults()].sort(), [urls.italic, urls.normal, urls.subset, urls.variable].sort(), 'Every loaded hosted face URL survives, including normal/italic and same-weight subsets');
const faces = summary.allFonts['Fixture Sans'].variations.map(({ weight, style, url }) => ({ weight, style, url }));
assert.equal(faces.length, 5, 'Duplicate rules deduplicate while shared URLs retain distinct weights');
assert(faces.some(face => face.weight === '400' && face.style === 'italic' && face.url === urls.italic));
assert(faces.some(face => face.weight === '400' && face.style === 'normal' && face.url === urls.normal));
assert(faces.some(face => face.weight === '700' && face.url === urls.normal));
assert(faces.some(face => face.weight === '400' && face.url === urls.subset));
assert(faces.some(face => face.weight === '100 900' && face.url === urls.variable), 'Variable weight descriptor remains intact');
assert.equal(summary.allFonts['Fixture Sans'].elementCount.aboveFold, 3, 'Every visible family element, including generated pseudo content, participates');
assert.equal(summary.allFonts['Fixture Sans'].elementCount.belowFold, 0);
assert(!summary.allFonts['Excluded Family']);
assert(!faces.some(face => face.url === urls.unloaded));
assert(summary.allFonts['Fixture Sans'].variations.every(face => face.elementCount.aboveFold === (face.style === 'italic' ? 1 : 2)), 'Face counts include only visible elements or pseudos using that style');
elements[1].below = true;
const belowItalic = new Collector({ processed_extensions: ['woff2'], preload_fonts_exclusions: ['Excluded Family'], external_font_exclusions: [] }, { logMessage() {} });
await belowItalic.run();
assert.deepEqual([...belowItalic.getResults()].sort(), [urls.normal, urls.subset, urls.variable].sort(), 'A loaded below-fold italic face is not promoted through an above-fold normal family');
const external = await belowItalic.processExternalFonts({
  [urls.italic]: [{ family: 'Fixture Sans', weight: '400', style: 'italic' }],
  [urls.normal]: [{ family: 'Fixture Sans', weight: '400', style: 'normal' }],
});
assert.deepEqual(Object.keys(external), [urls.normal], 'Existing external-provider matching still recognizes normal visible text and omits below-fold italic');
rules.splice(0, rules.length, new FontRule('Only Italic', '400', 'italic', urls.fallback));
loaded.splice(0, loaded.length, urls.fallback);
elements.splice(0, elements.length,
  { tagName: 'P', textContent: 'Normal fallback', style: style('Only Italic') },
  { tagName: 'P', textContent: 'Oblique fallback', style: style('Only Italic', '400', 'oblique 14deg') });
elements.forEach(element => { element.getBoundingClientRect = () => ({ top: 10 }); });
const styleFallback = new Collector({ processed_extensions: ['woff2'], preload_fonts_exclusions: [], external_font_exclusions: [] }, { logMessage() {} });
await styleFallback.run();
assert.deepEqual([...styleFallback.getResults()], [urls.fallback], 'Normal/angled oblique requests retain the loaded italic fallback when no preferred style is available');
rules.splice(0, rules.length, new FontRule('Only Normal', '400', 'normal', urls.fallback));
elements[0].style = style('Only Normal', '400', 'italic');
elements[1].style = style('Only Normal', '400', 'oblique 14deg');
const syntheticStyle = new Collector({ processed_extensions: ['woff2'], preload_fonts_exclusions: [], external_font_exclusions: [] }, { logMessage() {} });
await syntheticStyle.run();
assert.deepEqual([...syntheticStyle.getResults()], [urls.fallback], 'Italic/oblique requests retain the loaded normal face when the browser synthesizes their style');
console.log('PASS: actual hosted-font collector retains normal/italic URL identity, shared/multiple/range faces, duplicates, unloaded/excluded controls, visible/pseudo style counts and below-fold/external-provider boundaries. Modeled boundaries; no browser or HTTP.');
