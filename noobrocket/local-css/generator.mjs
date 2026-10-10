import { createHash } from 'node:crypto';
import postcss from 'postcss';
import selectorParser from 'postcss-selector-parser';
import valueParser from 'postcss-value-parser';

const dynamicStates = new Set([
  ':hover', ':focus', ':focus-visible', ':focus-within', ':active', ':visited',
  ':target', ':target-within', ':checked', ':indeterminate', ':disabled', ':enabled',
  ':open', ':popover-open', ':placeholder-shown', ':valid', ':invalid', ':user-valid',
  ':user-invalid', ':required', ':optional', ':in-range', ':out-of-range', ':autofill',
]);
const simplePseudoElements = new Set([':before', ':after', ':first-line', ':first-letter']);
const stateAttributes = new Set(['open', 'hidden', 'popover', 'aria-expanded', 'aria-selected', 'aria-hidden', 'aria-pressed', 'aria-current']);

function unsupported(message) {
  const error = new Error(message);
  error.category = 'unsupported';
  return error;
}

function selectorPlan(selector, safelist) {
  let preserve = safelist.some((item) => item && selector.includes(item));
  const selectors = [];
  try {
    const ast = selectorParser().astSync(selector);
    ast.each((part) => {
	  // Browser samples bypass HTML optimization. Delivered picture wrappers may appear later.
	  part.walkTags((tag) => {
	    if (['picture', 'source'].includes(tag.value.toLowerCase())) preserve = true;
	  });
      part.walkAttributes((attribute) => {
        if (stateAttributes.has(attribute.attribute.toLowerCase())) preserve = true;
      });
      part.walkPseudos((pseudo) => {
        const name = pseudo.value.toLowerCase();
        if (dynamicStates.has(name)) preserve = true;
        if (name.startsWith('::') || simplePseudoElements.has(name)) {
          if (pseudo.nodes?.length) preserve = true;
          else pseudo.remove();
        }
      });
      selectors.push(part.toString().trim() || '*');
    });
  } catch {
    preserve = true;
  }
  return { preserve, selectors };
}

function hasRuleAncestor(node) {
  for (let parent = node.parent; parent; parent = parent.parent) {
    if (parent.type === 'rule') return true;
  }
  return false;
}

function isProtected(node) {
  // These blocks can apply in another browser, media state, container or animation frame.
  // Keeping their complete contents also preserves unknown/future CSS grammar.
  if (node.noobrocketImported || hasRuleAncestor(node)) return true;
  for (let parent = node.parent; parent?.type !== 'root'; parent = parent.parent) {
    if (parent.noobrocketImported) return true;
    if (parent.type === 'atrule') return true;
  }
  return false;
}

function importPlan(node, baseURL) {
  const parsed = valueParser(node.params);
  const parts = parsed.nodes.filter((part) => part.type !== 'space' && part.type !== 'comment');
  const address = parts.shift();
  let href;
  if (address?.type === 'string') href = address.value;
  else if (address?.type === 'function' && address.value.toLowerCase() === 'url') {
    const values = address.nodes.filter((part) => part.type !== 'space' && part.type !== 'comment');
    if (values.length === 1 && ['word', 'string'].includes(values[0].type)) href = values[0].value;
  }
  if (!href || href.includes('\\')) throw unsupported('Unsupported CSS import URL.');
  const absolute = new URL(href, baseURL);
  if (!['http:', 'https:'].includes(absolute.protocol) || absolute.username || absolute.password) throw unsupported('Unsupported CSS import URL.');
  let layer = null;
  let supports = null;
  if (parts[0]?.value.toLowerCase() === 'layer') {
    const part = parts.shift();
    if (part.type === 'word') layer = '';
    else if (part.type === 'function') layer = valueParser.stringify(part.nodes).trim();
    else throw unsupported('Unsupported CSS import layer.');
  }
  if (parts[0]?.type === 'function' && parts[0].value.toLowerCase() === 'supports') {
    supports = valueParser.stringify(parts.shift().nodes).trim();
    // A declaration form is allowed by @import but @supports needs parentheses.
    if (!supports.startsWith('(') && !/^(not\s|selector\(|font-tech\(|font-format\()/i.test(supports)) supports = `(${supports})`;
  }
  const media = parts.length ? node.params.slice(parts[0].sourceIndex).trim() : '';
  return { href: absolute.href, layer, supports, media, address, parsed };
}

function rebaseImportURLs(root, baseURL) {
  root.walkAtRules(/^import$/i, (node) => {
    const plan = importPlan(node, baseURL);
    Object.assign(plan.address, { type: 'string', quote: '"', value: plan.href });
    delete plan.address.nodes;
    node.params = plan.parsed.toString();
  });
}

function rebaseValues(root, baseURL) {
  const absoluteURL = (original) => {
    if (!original || original.startsWith('#') || /^(data:|blob:)/i.test(original)) return original;
    if (original.includes('\\')) throw unsupported('Unsupported escaped CSS URL.');
    const absolute = new URL(original, baseURL);
    if (!['http:', 'https:'].includes(absolute.protocol)) throw unsupported('Unsupported CSS URL protocol.');
    return absolute.href;
  };
  root.walkDecls((decl) => {
    const parsed = valueParser(decl.value);
    parsed.walk((node) => {
      if (node.type !== 'function') return;
      if (['image-set', '-webkit-image-set'].includes(node.value.toLowerCase())) {
        for (const child of node.nodes) if (child.type === 'string') child.value = absoluteURL(child.value);
      }
      if (node.value.toLowerCase() !== 'url') return;
      const content = node.nodes.filter((part) => part.type !== 'space' && part.type !== 'comment');
      if (content.length !== 1 || !['word', 'string'].includes(content[0].type)) {
        throw unsupported('Unsupported CSS URL expression.');
      }
      const original = content[0].value;
      node.nodes = [{ type: 'string', quote: '"', value: absoluteURL(original), sourceIndex: 0, sourceEndIndex: 0 }];
      return false;
    });
    decl.value = parsed.toString();
  });
}

/** Parse one complete sheet. Imports must first be expanded from browser-loaded sources. */
export function prepareStylesheet(text, href, safelist = []) {
  if (/<\/style(?:\s|>|\/)/i.test(text)) throw new Error('CSS contains an HTML style boundary.');
  const root = postcss.parse(text, { from: href });
  return prepareRoot(root, safelist);
}

function prepareRoot(root, safelist) {
  root.walkAtRules((node) => {
    if (node.name.toLowerCase() === 'import') throw new Error('Stylesheet imports have not been resolved.');
  });
  const rules = [];
  root.walkRules((node) => {
    if (isProtected(node)) return;
    rules.push({
      selector: node.selector,
      start: node.source.start.offset,
      end: node.source.end.offset + 1,
      ...selectorPlan(node.selector, safelist),
      // Native nesting stays intact, including parents absent from the sampled DOM.
      ...(node.nodes?.some((child) => ['rule', 'atrule'].includes(child.type)) ? { preserve: true } : {}),
    });
  });
  return { root, rules };
}

function expandImports(source, sources, dependencies = [], ancestors = new Set()) {
  if (ancestors.has(source.finalURL) || ancestors.size > 10 || dependencies.length > 64) throw new Error('CSS imports contain a cycle or exceed the generation limit.');
  if (/<\/style(?:\s|>|\/)/i.test(source.text)) throw new Error('CSS contains an HTML style boundary.');
  ancestors = new Set([...ancestors, source.finalURL]);
  dependencies.push(source);
  // Use the exact browser text for coverage offsets after redirect URL rebasing.
  const root = postcss.parse(source.browserText, { from: source.finalURL });
  rebaseValues(root, source.finalURL);
  let importsAllowed = true;
  for (const node of root.nodes) {
    if (node.type === 'comment' || (node.type === 'atrule' && (node.name.toLowerCase() === 'charset' || (node.name.toLowerCase() === 'layer' && !node.nodes)))) continue;
    if (node.type === 'atrule' && node.name.toLowerCase() === 'import') {
      if (!importsAllowed) throw new Error('CSS import appears after a stylesheet rule.');
    } else importsAllowed = false;
  }
  root.walkAtRules(/^import$/i, (node) => {
    if (node.parent.type !== 'root') throw new Error('CSS import is not at the stylesheet root.');
    const plan = importPlan(node, source.finalURL);
    const imported = sources.get(plan.href);
    if (!imported) throw new Error(`Imported stylesheet was not loaded or was blocked: ${plan.href}`);
    const expanded = expandImports(imported, sources, dependencies, ancestors);
    expanded.walkAtRules(/^namespace$/i, () => { throw new Error('Imported CSS namespaces cannot be moved safely.'); });
    let contents = expanded.nodes.map((child) => { child.noobrocketImported = true; return child; });
    for (const [name, params] of [['media', plan.media], ['supports', plan.supports], ['layer', plan.layer]]) {
      if (params === null || params === '') {
        if (name !== 'layer' || params === null) continue;
      }
      const wrapper = postcss.atRule({ name, params });
      wrapper.append(contents);
      wrapper.noobrocketImported = true;
      contents = [wrapper];
    }
    node.replaceWith(...contents);
  });
  return root;
}

export function compileStylesheet(prepared, matches, coverage, baseURL, mode) {
  const root = prepared.root.clone();
  const byStart = new Map(prepared.rules.map((rule, index) => [rule.start, { rule, match: matches[index] }]));
  root.walkRules((node) => {
    if (isProtected(node)) return;
    const entry = byStart.get(node.source.start.offset);
    if (!entry) throw new Error('Stylesheet rule mapping failed.');
    const usedByBrowser = coverage.some((range) => range.used && range.startOffset < entry.rule.end && range.endOffset > entry.rule.start);
    const keep = entry.rule.preserve || entry.match?.unknown || (mode === 'critical' ? entry.match?.visible || entry.match?.hidden : entry.match?.exists || usedByBrowser);
    if (!keep) node.remove();
  });
  // @charset is only legal at the start of an external CSS file, never inside <style>.
  root.walkAtRules(/^charset$/i, (node) => node.remove());
  rebaseValues(root, baseURL);
  const css = root.toString();
  if (/<\/style(?:\s|>|\/)/i.test(css)) throw new Error('CSS contains an HTML style boundary.');
  return css.trim() ? css : '/* NoobRocket: no matching rules */';
}

async function matchRules(page, rules) {
  return page.evaluate((plans) => plans.map((plan) => {
    let exists = false;
    let visible = false;
    let hidden = false;
    let unknown = false;
    for (const selector of plan.selectors) {
      let elements;
      try { elements = document.querySelectorAll(selector); }
      catch { unknown = true; continue; }
      for (const element of elements) {
        exists = true;
        const style = getComputedStyle(element);
        const rects = element.getClientRects();
        // Hidden menus/modals and display:contents still need their initial hiding/layout CSS.
        if (style.display === 'none' || style.visibility === 'hidden' || style.contentVisibility === 'hidden' || rects.length === 0) { hidden = true; continue; }
        for (const rect of rects) {
          if (rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.right > 0 && rect.top < innerHeight && rect.left < innerWidth) visible = true;
        }
      }
    }
    return { exists, visible, hidden, unknown };
  }), rules.map(({ selectors }) => ({ selectors })));
}

/** Generate per-link CSS; inline/dynamic styles remain owned by the page. */
async function captureViewport(browser, job, viewport, { assetOrigins = [], timeout = 30000 } = {}) {
  const target = new URL(job.url);
  if (!['http:', 'https:'].includes(target.protocol) || target.username || target.password) throw new Error('A public HTTP(S) page URL is required.');
  const allowedOrigins = new Set([target.origin, ...assetOrigins.map((origin) => new URL(origin).origin)]);
  const context = await browser.newContext({
    viewport,
    ...(job.mobile ? { isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1' } : {}),
    serviceWorkers: 'block',
  });
  const warnings = [];
  const deadline = setTimeout(() => { context.close().catch(() => {}); }, timeout + 5000);
  try {
    const page = await context.newPage();
    const sources = new Map();
    let navigationRedirect = null;
    let expectedNavigation;
    const failedDependencies = [];
    const importantTypes = new Set(['stylesheet', 'script', 'font']);
    page.on('requestfailed', (request) => {
      if (importantTypes.has(request.resourceType())) failedDependencies.push(request.url());
    });
    page.on('pageerror', (error) => failedDependencies.push(`Page JavaScript: ${error.message}`));
    await context.route('**/*', async (route) => {
      const abort = (reason = 'blockedbyclient') => route.abort(reason).catch(() => {});
      const request = route.request();
      let url;
      try { url = new URL(request.url()); } catch { return abort(); }
      if (url.username || url.password || (request.isNavigationRequest() && url.origin !== target.origin)) return abort();
      const allowed = ['GET', 'HEAD'].includes(request.method()) &&
        (['data:', 'blob:', 'about:'].includes(url.protocol) || (['http:', 'https:'].includes(url.protocol) && allowedOrigins.has(url.origin)));
      if (!allowed) return abort();
      try {
        // Never give Chromium a redirect to follow: Playwright does not reliably
        // re-route each hop. Fetch assets one validated hop at a time instead.
        let current = url;
        let response;
        for (let hop = 0; hop <= 10; hop++) {
          const headers = { ...request.headers() };
          if (current.origin !== url.origin) { delete headers.cookie; delete headers.authorization; }
          response = await route.fetch({ url: current.href, headers, maxRedirects: 0, timeout });
          const location = response.headers().location;
          if (!(response.status() >= 300 && response.status() < 400 && location)) break;
          const next = new URL(location, current);
          if (!['http:', 'https:'].includes(next.protocol) || next.username || next.password || !allowedOrigins.has(next.origin) || (request.isNavigationRequest() && next.origin !== target.origin)) return abort();
          if (hop === 10) return abort();
          if (request.isNavigationRequest()) {
            if (request.frame() === page.mainFrame() && request.url() === expectedNavigation) {
              navigationRedirect = next;
              // Finish this navigation cleanly before goto() opens the validated
              // next hop; aborting creates a racing chrome-error navigation.
              return await route.fulfill({ status: 200, contentType: 'text/html', body: '' });
            }
            return abort();
          }
          current = next;
        }
        if (request.resourceType() === 'stylesheet' && response.ok()) {
          const body = await response.body();
          if (body.length > 2097152) return abort();
          const text = body.toString('utf8');
          let browserText = text;
          if (current.href !== url.href) {
            // The browser sees the intercepted URL. Rebase redirected sheets to
            // their real final directory before handing them to Chromium.
            const root = postcss.parse(text, { from: current.href });
            rebaseValues(root, current.href);
            rebaseImportURLs(root, current.href);
            browserText = root.toString();
          }
          sources.set(url.href, { href: url.href, text, browserText, hash: createHash('sha256').update(body).digest('hex'), finalURL: current.href });
          return await route.fulfill({ response, body: browserText });
        }
        return await route.fulfill({ response });
      } catch { return abort('failed'); }
    });
    // Browser WebSockets bypass HTTP routing; CSS generation does not require them.
    await context.routeWebSocket('**/*', (socket) => socket.close());
    page.on('response', (response) => {
      if (!response.ok() && response.status() >= 400 && importantTypes.has(response.request().resourceType())) failedDependencies.push(response.url());
    });
    const client = await context.newCDPSession(page);
    const sheets = new Map();
    const changedSheets = new Set();
    client.on('CSS.styleSheetAdded', ({ header }) => sheets.set(header.styleSheetId, header));
    client.on('CSS.styleSheetChanged', ({ styleSheetId }) => changedSheets.add(styleSheetId));
    const validateSource = async (source) => {
      if (source.text.includes('\uFFFD')) throw new Error(`Stylesheet is not valid UTF-8: ${source.href}`);
      const accepted = [...sheets].filter(([, header]) => [source.href, source.finalURL].includes(header.sourceURL));
      if (!accepted.length) throw new Error(`Stylesheet was not accepted by the browser: ${source.href}`);
      for (const [styleSheetId] of accepted) {
        if (changedSheets.has(styleSheetId)) throw new Error(`Stylesheet was changed by page JavaScript: ${source.href}`);
        const { text } = await client.send('CSS.getStyleSheetText', { styleSheetId });
        if (text.replace(/^\uFEFF/, '') !== source.browserText.replace(/^\uFEFF/, '')) throw new Error(`Stylesheet was changed by page JavaScript: ${source.href}`);
      }
    };
    await client.send('DOM.enable');
    await client.send('CSS.enable');
    await client.send('CSS.startRuleUsageTracking');
    target.searchParams.set('nonoobrocket', '1');
    let destination = target;
    let response;
    for (let hop = 0; hop <= 10; hop++) {
      destination.searchParams.set('nonoobrocket', '1');
      expectedNavigation = destination.href;
      navigationRedirect = null;
      try { response = await page.goto(destination.href, { waitUntil: 'load', timeout }); }
      catch (error) { if (!navigationRedirect) throw error; }
      if (!navigationRedirect) break;
      if (hop === 10) throw new Error('Page navigation exceeded the redirect limit.');
      destination = navigationRedirect;
    }
    if (!response || response.status() !== 200 || new URL(page.url()).origin !== target.origin) throw new Error('Page generation requires a successful same-origin HTML response.');
    if (!/text\/html/i.test(response.headers()['content-type'] || '')) throw new Error('Page response is not HTML.');
    const enforcingMeta = await page.evaluate(() => [...document.querySelectorAll('meta[http-equiv]')].some((element) => element.httpEquiv.toLowerCase() === 'content-security-policy'));
    if (job.mode === 'critical' && (response.headers()['content-security-policy'] || enforcingMeta)) throw unsupported('Critical CSS cannot replace blocking styles under an enforcing Content-Security-Policy.');
    await page.evaluate(() => Promise.race([document.fonts.ready, new Promise((resolve) => setTimeout(resolve, 1500))]));
    await page.waitForTimeout(200);
    if (failedDependencies.length) throw new Error(`Page prerequisite failed or was blocked: ${failedDependencies[0]}`);
    const links = await page.evaluate(() => Array.from(document.querySelectorAll('link[rel~="stylesheet"]')).filter((link) => !link.disabled && !link.relList.contains('alternate')).map((link) => ({ href: link.href, media: link.media || 'all' })));
    const pageLinks = new Set(links.map(({ href }) => href));
    const requested = job.styles || links;
    const ready = [];
    for (const link of requested) {
      if (!pageLinks.has(link.href)) throw new Error(`Requested stylesheet is absent from the unoptimized page: ${link.href}`);
      const source = sources.get(link.href);
      if (!source) throw new Error(`Requested stylesheet was not loaded or was blocked: ${link.href}`);
      const dependencies = [];
      const expanded = expandImports(source, sources, dependencies);
      for (const dependency of dependencies) await validateSource(dependency);
      const prepared = prepareRoot(expanded, job.safelist || []);
      if (prepared.rules.length > 20000) throw new Error(`Stylesheet has too many rules: ${link.href}`);
      const criticalMatches = await matchRules(page, prepared.rules);
      const combinedHash = dependencies.length === 1 ? source.hash : createHash('sha256').update(JSON.stringify(dependencies.map(({ finalURL, hash }) => [finalURL, hash]))).digest('hex');
      ready.push({ link, source: { ...source, hash: combinedHash }, dependencies, prepared, criticalMatches });
    }
    // Trigger normal scrolling/lazy rendering without activating links, forms or controls.
    const height = await page.evaluate(() => Math.min(document.documentElement.scrollHeight, 30000));
    const step = job.mobile ? 700 : 800;
    for (let y = step; y < height; y += step) {
      await page.evaluate((position) => scrollTo(0, position), y);
      await page.waitForTimeout(25);
    }
    await page.evaluate(() => scrollTo(0, 0));
    const { ruleUsage } = await client.send('CSS.stopRuleUsageTracking');
    if (failedDependencies.length) throw new Error(`Page prerequisite failed or was blocked: ${failedDependencies[0]}`);
    const stylesheets = [];
    for (const { link, source, dependencies, prepared, criticalMatches } of ready) {
        for (const dependency of dependencies) await validateSource(dependency);
        const coverage = ruleUsage.filter((range) => {
          const sheet = sheets.get(range.styleSheetId);
          return sheet && [link.href, source.finalURL].includes(sheet.sourceURL);
        });
        const usedMatches = await matchRules(page, prepared.rules);
        stylesheets.push({ link, source, prepared, criticalMatches, usedMatches, coverage });
    }
    return { stylesheets, warnings };
  } finally {
    clearTimeout(deadline);
    await context.close();
  }
}

export async function generatePageCss(browser, job, options = {}) {
  if (!['critical', 'used'].includes(job.mode)) throw new Error('Unknown CSS generation mode.');
  if (!Array.isArray(job.styles) || !job.styles.length) throw new Error('A job must contain stylesheet links.');
  if (!Array.isArray(job.safelist || []) || (job.safelist || []).some((entry) => typeof entry !== 'string')) throw new Error('Invalid CSS safelist.');
  if (new Set(job.styles.map(({ href }) => href)).size !== job.styles.length) throw new Error('Duplicate job stylesheet links.');
  const viewports = job.viewports?.length ? job.viewports : [job.mobile ? { width: 390, height: 844 } : { width: 1365, height: 900 }];
  if (viewports.length > 6 || viewports.some(({ width, height }) => !Number.isInteger(width) || !Number.isInteger(height) || width < 240 || width > 3840 || height < 240 || height > 2160)) throw new Error('Invalid CSS generation viewports.');
  const captured = [];
  for (const viewport of viewports) captured.push(await captureViewport(browser, job, viewport, options));
  const results = [];
  for (let index = 0; index < job.styles.length; index++) {
    const first = captured[0].stylesheets[index];
    const sheets = captured.map(({ stylesheets }) => stylesheets[index]);
    if (sheets.some(({ source }) => source.hash !== first.source.hash || source.finalURL !== first.source.finalURL)) throw new Error(`Stylesheet changed between viewports: ${first.link.href}`);
    const matches = first.prepared.rules.map((_, ruleIndex) => ({
      exists: sheets.some(({ usedMatches }) => usedMatches[ruleIndex].exists),
      visible: sheets.some(({ criticalMatches }) => criticalMatches[ruleIndex].visible),
      hidden: sheets.some(({ criticalMatches }) => criticalMatches[ruleIndex].hidden),
      unknown: sheets.some(({ criticalMatches, usedMatches }) => criticalMatches[ruleIndex].unknown || usedMatches[ruleIndex].unknown),
    }));
    const css = compileStylesheet(first.prepared, matches, sheets.flatMap(({ coverage }) => coverage), first.source.finalURL, job.mode);
    results.push({ href: first.link.href, css, source_hash: first.source.hash });
  }
  return { results, warnings: captured.flatMap(({ warnings }) => warnings) };
}
