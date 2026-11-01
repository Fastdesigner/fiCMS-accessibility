# fiCMS Accessibility

Optional fiCMS plugin for browser-based accessibility audits, persisted results and Health contributions.

The first implementation is intentionally fiCMS-native. WordPress and TYPO3 integrations are separate future projects and will follow their own platform conventions.

## Runtime flow

1. `build/accessibility.php` selects an anonymous visitor request and records its short-lived server-side context.
2. fiCMS loads `assets/js/services/accessibility_audit.js` through the regular `load_services` mechanism (the `accessibility` service name stays reserved for presentation assets like the statement widget CSS).
3. The bootstrap imports the audit module and submits its result through the regular fiCMS Settings AJAX.
4. `settings/info/accessibility.php` consumes the session context, validates and stores the result below `system/plugins/fiCMS-accessibility/data`.
5. `health/accessibility.php` contributes the latest score to the `legal` Health category.

## Structure

- `assets/js/services/`: small `load_services` bootstrap.
- `assets/js/accessibility/`: fiCMS-native audit module and lazy renderer.
- `build/`: visitor sampling before Core asset assembly.
- `src/`: session context, validation, file storage, daily statistics and shared score overview.
- `mcp/`: admin-only audit readers and interpretation guidance for local agents.
- `health/`: optional contribution to the Core `legal` category.
- `settings/` and `reports/`: native fiCMS consumers of the shared overview.
- `cron/` and `cleanup/`: installation, removal of the former audit tables and retention.
- `localization/`: plugin-owned admin, report and Health texts.
- `tests/`: executable backend contract tests.

## MCP

The Core discovers the plugin's admin-only `accessibility` get type automatically:

```text
get("accessibility", "summary")
get("accessibility", "pages")
get("accessibility", "page:10-0-de")
skill("accessibility")
```

The responses expose stored audit coverage, freshness and limitations so agents can distinguish an automated sampled finding from a compliance statement.

## Agent-run audit on other websites

The same maintained browser engine can run on a page without fiCMS. Build a
self-contained expression from this checkout, its vendored renderer and the
actual Core color helpers:

```sh
node scripts/build-browser-audit.mjs --core-root /path/to/fiCMS --output /tmp/browser-audit.js
```

The builder also writes `/tmp/browser-audit.js.steps.json`. Read its ordered
`steps` and pass each `expression` to the shared browser's `preview_evaluate`
in the same commissioned page tab. The final step returns the audit result.
Each step stays below the browser tool's 64,000-character input limit. The
source is assembled in a random nonenumerable page-memory handle and deleted
before execution; on interruption run `cleanup_expression` from the manifest.
If the page forbids this evaluation, report the failure instead of assuming
that any checks passed. The builder is agent tooling;
it is not a website task or API endpoint. Do not use the service bootstrap for
this purpose: that bootstrap sends results to the fiCMS admin collection path.

The expression returns `status`, `url`, `checked_at`, `viewport`, `provenance`
(source hashes), `audit_version` and the original `audit.scores`,
`audit.accessibility` and `audit.stats`. `done` means the automated run finished,
not that accessibility passed. `partial` includes counted failed checks or a
changed URL/viewport; `error` has `audit:null` and an explicit failure reason.
Missing measurements and zero applicable checks never establish a pass.
Unreadable cross-origin stylesheets are listed in `audit.stats.unreadableStylesheets`;
the standalone result is `partial`, with motion-rule coverage explicitly incomplete.
Keyboard journeys, focus behavior and narrow-layout checks still need the
commissioned browser verification; this is not a compliance certificate.

No helpers, fiCMS state or renderer globals are installed on the examined page.
The injected expression executes the original rules, uses a private renderer,
restores temporary DOM/styles and focus, and performs no submission or result
persistence. Renderer resource restrictions can fail the measurement and remain
an explicit limitation. Run it sequentially in the tab, after the page has
settled, and keep the URL/viewport fixed until it returns.

Browser tools bound their return size. Results above 48,000 JSON characters
therefore return `status:"stored"`, `audit_status`, a random `handle`, `chars`,
`chunk_size` (24,000) and `chunks`. Read every chunk with
`globalThis[handle].slice(offset, offset + chunk_size)`, concatenate locally,
check the full character count and parse JSON. Then execute
`delete globalThis[handle]` in the same tab. The nonenumerable page-memory
handle expires after ten minutes; no localStorage or server cache is used.
Expired/missing chunks are incomplete results, never a clean audit.

Regression: `node tests/browser-audit-runner.cjs` exercises synthetic plain HTML,
complex-background rendering, cleanup, untouched globals, and explicit failures.

## Resolution requests

The paid resolution button announces the checker-backed layout job `accessibility` to fiCMS-api. fiCRM creates the associated ticket and agent work through its regular layout-job intake and applies the maintenance-contract approval rule.

The working instruction is maintained centrally in fiCRM under the job key `accessibility`. The plugin writes no instruction or report snapshot into the customer layout. Agents read the current findings through the installation's `accessibility` MCP readers. Each new request sets a new creation timestamp so the checker requires fresh audits for that request. Existing open `accessibility-<hash>` jobs continue to prevent a duplicate request.
