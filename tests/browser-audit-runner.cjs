// Isolated, synthetic documents: no production page, transport or audit persistence.
const assert = require('node:assert/strict');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require(process.env.PW_ROOT || '/tmp/pw/node_modules/playwright-core');

(async () => {
  const { buildBrowserAudit, buildBrowserAuditSteps } = await import(pathToFileURL(path.join(__dirname, '../scripts/build-browser-audit.mjs')));
  const expression = buildBrowserAudit(process.env.FICMS_CORE_ROOT || '/Users/maierstefan/projects/fiCMS');
  const injection = buildBrowserAuditSteps(expression);
  assert.ok(injection.steps.every(step => step.expression.length <= 64000));
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage();
    const network = [], errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('https://audit-fixture.invalid/**', route => {
      if (new URL(route.request().url()).pathname !== '/') { network.push(route.request().url()); return route.abort(); }
      return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="de"><head><style>
        body{background:white;color:black;font:16px Arial} a:focus-visible{outline:3px solid blue}
        .gradient{background:linear-gradient(white,#eee)}
      </style></head><body><a href="#main">Zum Inhalt</a><header>Beispiel</header><nav><a href="#main">Angebot</a></nav>
        <main id="main"><h1>Ein geprüfter Seitentitel</h1><h3>Eine übersprungene Ebene</h3>
        <p class="gradient">Ein ausreichend kurzer und gut lesbarer Satz.</p><img alt="" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==">
        <form><label for="email">E-Mail</label><input id="email"><input id="missing"></form>
        <details id="details"><summary>Mehr erfahren</summary><p>Ein weiterer kurzer Text.</p></details>
        <button id="active" onclick="window.clicked=true">Kontakt</button></main><footer>Ende</footer></body></html>`});
    });
    await page.goto('https://audit-fixture.invalid/');
    await page.locator('#active').focus();
    await page.evaluate(() => { window.fiCMS = {sentinel:'keep'}; window.modernScreenshot = {sentinel:'keep'}; });
    const before = await page.evaluate(() => ({html:document.body.innerHTML,attrs:[...document.body.attributes].map(a=>[a.name,a.value]),focus:document.activeElement.id}));
    let result;
    for(const step of injection.steps) result=await page.evaluate(step.expression);
    assert.deepEqual(await page.evaluate(() => Object.getOwnPropertyNames(window).filter(key=>key.startsWith('__ficms_accessibility_source_'))), [], 'Large source handle is deleted before execution');
    assert.equal(result.status, 'done', JSON.stringify(result));
    assert.ok(result.audit.stats.checkRuns > 10);
    assert.equal(result.audit.stats.failedChecks, 0);
    assert.ok(Object.hasOwn(result.audit.accessibility.error, '_accessibility_form_missing_label'), JSON.stringify(result.audit.accessibility));
    assert.match(result.provenance.audit.sha256, /^[a-f0-9]{64}$/);
    assert.equal(result.url, 'https://audit-fixture.invalid/');
    assert.equal(await page.evaluate(() => window.fiCMS.sentinel), 'keep', 'Foreign page state stays untouched');
    assert.equal(await page.evaluate(() => window.modernScreenshot.sentinel), 'keep', 'Renderer stays private');
    assert.equal(await page.evaluate(() => window.clicked), undefined, 'No form or button action');
    assert.deepEqual(await page.evaluate(() => ({html:document.body.innerHTML,attrs:[...document.body.attributes].map(a=>[a.name,a.value]),focus:document.activeElement.id})), before, 'Temporary styles, clones, open state and focus cleaned up');
    assert.deepEqual(network, [], 'No renderer download, submission or external transport');

    // Cross-origin CSS applies visually, but the browser forbids inspecting its rules.
    const external = await browser.newPage();
    await external.route('https://external-css.invalid/motion.css', route => route.fulfill({contentType:'text/css',body:'@keyframes slide { from { transform:translateX(0) } to { transform:translateX(10px) } } .moving { animation:slide 2s infinite }'}));
    await external.route('https://external-page.invalid/', route => route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="de"><head><title>Motion</title><link rel="stylesheet" href="https://external-css.invalid/motion.css"></head><body><main><h1 class="moving">Moving content</h1></main></body></html>'}));
    await external.goto('https://external-page.invalid/');
    assert.equal(await external.evaluate(() => getComputedStyle(document.querySelector('h1')).animationName), 'slide');
    const inaccessible = await external.evaluate(expression);
    assert.equal(inaccessible.status, 'partial', 'Unreadable motion rules never establish a complete audit');
    assert.deepEqual(inaccessible.audit.stats.unreadableStylesheets, ['https://external-css.invalid/motion.css']);
    assert.ok(inaccessible.limitations.some(text => text.includes('motion') && text.includes('incomplete')));
    await external.close();

    // A measurement exception must remain incomplete instead of becoming a success.
    await page.evaluate(() => { const input=document.querySelector('#email'), get=input.getAttribute.bind(input); input.getAttribute=name=>{if(name==='aria-labelledby')throw new Error('single check unavailable');return get(name);}; });
    const partial = await page.evaluate(expression);
    assert.equal(partial.status, 'partial');
    assert.ok(partial.audit.stats.failedChecks > 0);
    assert.ok(partial.audit.stats.failures.some(failure => failure.reason === 'single check unavailable'));
    // Explicitly inject a platform failure in traversal.
    await page.evaluate(() => { document.querySelector('#email').matches = () => { throw new Error('measurement unavailable'); }; });
    const failed = await page.evaluate(expression);
    assert.equal(failed.status, 'error', 'An unavailable traversal API is never reported as clean');
    assert.equal(failed.audit, null);
    assert.match(failed.error, /measurement unavailable/);

    await page.goto('https://audit-fixture.invalid/');
    await page.evaluate(() => { const fragment=document.createDocumentFragment(); for(let i=0;i<600;i++){const input=document.createElement('input');input.id='unlabelled-'+i;fragment.appendChild(input);}document.querySelector('form').appendChild(fragment); });
    const stored = await page.evaluate(expression);
    assert.equal(stored.status, 'stored', 'Large results use a retrievable handle, never silent truncation');
    assert.equal(stored.audit_status, 'done');
    assert.match(stored.handle, /^__ficms_accessibility_audit_[a-f0-9]{32}$/);
    assert.ok(stored.chars > 48000);
    assert.equal(await page.evaluate(handle => Object.keys(window).includes(handle), stored.handle), false);
    let serialized = '';
    for(let offset=0;offset<stored.chars;offset+=stored.chunk_size) serialized += await page.evaluate(({handle,offset,size}) => globalThis[handle].slice(offset,offset+size), {handle:stored.handle,offset,size:stored.chunk_size});
    assert.equal(serialized.length, stored.chars);
    const complete=JSON.parse(serialized);
    assert.equal(complete.audit.accessibility.error._accessibility_form_missing_label.length, 601, 'All original findings survive chunk extraction');
    await page.evaluate(handle => delete globalThis[handle], stored.handle);
    assert.equal(await page.evaluate(handle => Object.hasOwn(globalThis,handle), stored.handle), false, 'Explicit cleanup removes the temporary handle');
    console.log('Large audit: ' + stored.chars + ' JSON characters retrieved in ' + stored.chunks + ' bounded chunks; all 601 label findings retained');
    assert.deepEqual(errors, []);
    console.log('Browser audit runner: plain HTML, original rules and renderer, provenance, no global/transport mutation, cleanup and explicit failures passed');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
