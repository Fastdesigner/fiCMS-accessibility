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

## Resolution requests

The paid resolution button announces the checker-backed layout job `accessibility` to fiCMS-api. fiCRM creates the associated ticket and agent work through its regular layout-job intake and applies the maintenance-contract approval rule.

The working instruction is maintained centrally in fiCRM under the job key `accessibility`. The plugin writes no instruction or report snapshot into the customer layout. Agents read the current findings through the installation's `accessibility` MCP readers. Each new request sets a new creation timestamp so the checker requires fresh audits for that request. Existing open `accessibility-<hash>` jobs continue to prevent a duplicate request.
