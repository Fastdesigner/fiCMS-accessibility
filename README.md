# fiCMS Accessibility

Optional fiCMS plugin for browser-based accessibility audits, persisted results and Health contributions.

The first implementation is intentionally fiCMS-native. WordPress and TYPO3 integrations are separate future projects and will follow their own platform conventions.

## Runtime flow

1. `build/accessibility.php` selects an anonymous visitor request and records its short-lived server-side context.
2. fiCMS loads `assets/js/services/accessibility.js` through the regular `load_services` mechanism.
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
get("skill", "accessibility")
```

The responses expose stored audit coverage, freshness and limitations so agents can distinguish an automated sampled finding from a compliance statement.
