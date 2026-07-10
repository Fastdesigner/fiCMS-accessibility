# fiCMS Accessibility

Optional fiCMS plugin for browser-based accessibility audits, persisted results and Health contributions.

The first implementation is intentionally fiCMS-native. WordPress and TYPO3 integrations are separate future projects and will follow their own platform conventions.

## Runtime flow

1. `build/accessibility.php` selects an anonymous visitor request and records its short-lived server-side context.
2. fiCMS loads `assets/js/services/accessibility.js` through the regular `load_services` mechanism.
3. The bootstrap imports the audit module and submits its result through the regular fiCMS Settings AJAX.
4. `settings/info/accessibility.php` consumes the session context, validates and stores the result in `accessibility_audits`.
5. `health/accessibility.php` contributes the latest score to the `legal` Health category.

## Structure

- `assets/js/services/`: small `load_services` bootstrap.
- `assets/js/accessibility/`: fiCMS-native audit module and lazy renderer.
- `build/`: visitor sampling before Core asset assembly.
- `src/`: schema, migration, session context, validation, storage and shared score overview.
- `health/`: optional contribution to the Core `legal` category.
- `settings/` and `reports/`: native fiCMS consumers of the shared overview.
- `cron/` and `cleanup/`: schema ownership, migration and retention.
- `localization/`: plugin-owned admin, report and Health texts.
- `tests/`: executable backend contract tests.
