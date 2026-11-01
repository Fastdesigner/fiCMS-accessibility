#!/usr/bin/env node
// Build an agent-executed expression from the maintained audit and its actual dependencies.
// No audit code is copied into this builder, and the examined website installs nothing.
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash, randomBytes } from 'node:crypto';
import { runInNewContext } from 'node:vm';

const pluginRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const helperNames = [
  'helper__color_parse', 'helper__color_composite', 'helper__get_color_effective',
  'helper__get_background_color', 'helper__get_contrast_colors', 'helper__get_contrast',
  'helper__get_contrast_sufficient', 'helper__get_by_attribute'
];
const digest = source => createHash('sha256').update(source).digest('hex');

export function buildBrowserAudit(coreRoot) {
  const audit = readFileSync(resolve(pluginRoot, 'assets/js/accessibility/accessibility-audit.js'), 'utf8');
  const renderer = readFileSync(resolve(pluginRoot, 'assets/js/accessibility/vendor/modern-screenshot.js'), 'utf8');
  const helperSource = readFileSync(resolve(coreRoot, 'designs/all/assets/js/helper.js'), 'utf8');
  // helper.js consists of declarations. A restricted context rejects new top-level dependencies.
  const context = {};
  runInNewContext(helperSource, context, { timeout: 1000 });
  const helpers = helperNames.map(name => {
    if (typeof context[name] !== 'function') throw new Error('Missing Core audit dependency: ' + name);
    return context[name].toString();
  }).join('\n');
  const exports = audit.match(/export\s+const\s+AccessibilityAudit\s*=/g) || [];
  if (exports.length !== 1 || /\bimport\s*(?:\(|["'{*])/.test(audit)) throw new Error('Audit module contract changed');
  const source = audit.replace(exports[0], 'const AccessibilityAudit =').replaceAll('import.meta.url', '"about:blank"');
  const provenance = {
    audit: { file: 'assets/js/accessibility/accessibility-audit.js', sha256: digest(audit) },
    helpers: { file: 'designs/all/assets/js/helper.js', sha256: digest(helperSource), functions: helperNames },
    renderer: { file: 'assets/js/accessibility/vendor/modern-screenshot.js', sha256: digest(renderer) }
  };
  return `(async () => {
    'use strict';
    const fiCMS = {host:location.href};
    const uniqueId = () => crypto.randomUUID().replaceAll('-', '');
    const provenance = ${JSON.stringify(provenance)};
    const metadata = {url:location.href,checked_at:new Date().toISOString(),viewport:{width:innerWidth,height:innerHeight},provenance};
    const result = await (async () => { try {
      ${helpers}
      const renderer = (() => { const exports = {}; const module = {exports}; ${renderer}\nreturn module.exports; })();
      ${source}
      const audit = await AccessibilityAudit.run({document,standalone:true,renderer});
      const contextChanged = metadata.url !== location.href || metadata.viewport.width !== innerWidth || metadata.viewport.height !== innerHeight;
      const limitations = contextChanged ? ['Page address or viewport changed during the audit.'] : [];
      if (audit.stats.unreadableStylesheets.length) limitations.push('Motion rules could not be inspected in ' + audit.stats.unreadableStylesheets.length + ' stylesheet(s); motion and reduced-motion coverage is incomplete.');
      return {...metadata,status:audit.stats.failedChecks || limitations.length ? 'partial' : 'done',limitations,audit_version:AccessibilityAudit.version,audit};
    } catch (error) {
      return {...metadata,status:'error',error:String(error?.message || error).slice(0,1000),audit:null};
    } })();
    const serialized = JSON.stringify(result);
    if (serialized.length <= 48000) return result;
    const handle = '__ficms_accessibility_audit_' + crypto.randomUUID().replaceAll('-', '');
    Object.defineProperty(globalThis,handle,{value:serialized,configurable:true});
    setTimeout(() => { if (globalThis[handle] === serialized) delete globalThis[handle]; },600000);
    return {...metadata,status:'stored',audit_status:result.status,audit_version:result.audit_version || null,handle,chars:serialized.length,chunk_size:24000,chunks:Math.ceil(serialized.length / 24000),expires_in_seconds:600};
  })()`;
}

// Shared browser tools cap the expression input as well as their return value.
export function buildBrowserAuditSteps(expression) {
  const handle = '__ficms_accessibility_source_' + randomBytes(16).toString('hex');
  const key = JSON.stringify(handle);
  const steps = [{phase:'prepare',expression:`(() => { Object.defineProperty(globalThis,${key},{value:'',writable:true,configurable:true}); setTimeout(() => { delete globalThis[${key}]; },600000); return true; })()`}];
  for(let offset=0;offset<expression.length;offset+=18000) {
    steps.push({phase:'append',expression:`(() => { if(typeof globalThis[${key}] !== 'string') throw new Error('Audit source handle missing'); globalThis[${key}] += ${JSON.stringify(expression.slice(offset,offset+18000))}; return globalThis[${key}].length; })()`});
  }
  steps.push({phase:'run',expression:`(async () => { const source=globalThis[${key}]; delete globalThis[${key}]; if(typeof source !== 'string' || source.length !== ${expression.length}) throw new Error('Audit source incomplete'); return await eval(source); })()`});
  if(steps.some(step=>step.expression.length>64000)) throw new Error('Audit injection step exceeds browser-tool limit');
  return {steps,cleanup_expression:`delete globalThis[${key}]`};
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  if (args.length !== 4 || args[0] !== '--core-root' || args[2] !== '--output') {
    throw new Error('Usage: node scripts/build-browser-audit.mjs --core-root /path/to/fiCMS --output /tmp/browser-audit.js');
  }
  const expression = buildBrowserAudit(resolve(args[1]));
  writeFileSync(resolve(args[3]), expression, { mode: 0o600 });
  const injection=buildBrowserAuditSteps(expression), stepsOutput=resolve(args[3])+'.steps.json';
  writeFileSync(stepsOutput,JSON.stringify(injection),{mode:0o600});
  process.stdout.write(JSON.stringify({ output: resolve(args[3]), bytes: Buffer.byteLength(expression), sha256: digest(expression),steps_output:stepsOutput,steps:injection.steps.length }) + '\n');
}
