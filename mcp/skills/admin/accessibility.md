---
name: accessibility
short: Read and interpret the site's stored automated accessibility audits
description: Inspect sampled page and viewport audits, explain what was tested, and make evidence-bound accessibility statements.
scope: admin
---

## Tools

- `get("accessibility", "summary")` - current aggregate score, category scores, audit coverage, freshness, and grouped findings
- `get("accessibility", "pages")` - audited page/language combinations, available desktop/mobile snapshots, missing viewports, and page scores
- `get("accessibility", "page:<mid-tid-lid>")` - latest desktop/mobile snapshots and concrete findings for one page
- `get("page", "<mid-tid-lid>")` - current page content when a finding needs content context
- `get("section", "<id>")` - current section data when the affected block is known
- `get("media", "<id>")` and `get("media_context", "<id>")` - media metadata and placements when an image finding must be investigated

## Required workflow

1. Call `get("accessibility", "summary")` first.
2. Check `status`, `coverage`, `assessment.last`, and `limitations` before making a statement.
3. For site-wide comparisons, call `get("accessibility", "pages")` and distinguish audited pages from pages with no stored audit.
4. For a concrete problem, call `get("accessibility", "page:<mid-tid-lid>")` and cite its path, viewport, and timestamp.
5. Load current page, section, or media data only when the admin asks for cause, current state, or remediation. The stored selector describes the audited snapshot and may no longer match current content.

## How to phrase results

Use evidence-bound language:

- "The latest stored mobile audit for /contact from <date> reported ..."
- "Among <n> audited page/language combinations, the automated audit found ..."
- "No stored desktop audit is available for this page."
- "This rule was not reported in the stored snapshot. Manual verification is still required."

Do not say that a website is accessible, WCAG-compliant, BFSG-compliant, legally compliant, or free of accessibility barriers based on these audits alone. A score is a prioritization signal over the checks below, not a certification.

## Sampling and coverage

Audits are browser snapshots collected from eligible anonymous, non-bot visits to a main page view. They are stored separately by `mid`, `tid`, language, and desktop/mobile viewport. A context is normally sampled again only after the configured freshness period; the global sampler also limits automatic collection frequency. A developer can force a page audit separately.

Consequences:

- Missing page or viewport data means "not audited", never "passed".
- `pages` lists only contexts with stored audit data; it is not a complete sitemap.
- The latest snapshots for different pages can have different ages.
- Results describe the rendered state reached during that visit. They do not cover every user state, personalization, modal, form error, or interaction path.
- Open/closed `details` and `dialog` descendants are inspected through a temporary rendered clone, but arbitrary application states are not exhaustively exercised.
- Admin UI, hidden, disabled, `aria-hidden`, and non-rendered elements are excluded from the element traversal.

## Checks performed by engine 0.1.2

### Media

- Images without an `alt` attribute: error.
- Images declared with a non-empty `alt` value: warn when the value contains fewer than two words.
- Images declared as presentation/decorative or with an explicitly empty `alt`: ignore as decorative.
- Videos: error when no captions, subtitles, or descriptions track is present.

### Keyboard and navigation

- Empty links: require a usable accessible label.
- Elements with inline mouse handlers: require keyboard support and warn when a semantic role is absent.
- Interactive targets: warn below 16 x 16 CSS pixels or 576 square pixels, with an associated label included in the target union where available.
- Fixed interactive elements: detect coverage by another fixed pointer-active element at their corners.
- Visible focusable controls: attempt focus and compare relevant visual styles for a visible focus change.
- Visible interactive elements that reject focus: warn.
- Skip link: require a valid link to the main content before navigation/main traversal.
- Non-hidden inputs styled with `display:none` while associated with a label: error.

These checks do not reproduce a full keyboard journey or screen-reader interaction.

### Forms

- Inputs, textareas, and selects: accept associated native labels, wrapping labels, non-empty `aria-label`, or valid text-bearing `aria-labelledby`.
- Placeholder-only labeling: warning.
- No recognized label: error.

The engine does not validate form instructions, error recovery, validation announcements, autocomplete purpose, or the quality of label wording.

### Semantics and ARIA

- Headings: warn below three visible characters; error for missing/multiple `h1` or skipped heading levels.
- Landmarks: require exactly one `main`, require at least one `nav`, and reject multiple global `header` or `footer` landmarks.
- `aria-labelledby`, `aria-describedby`, and `aria-controls`: verify that the referenced id exists.
- Selected native/ARIA role combinations: flag forbidden or redundant roles.
- Unlabelled `section` and `aside` regions: warn when no heading or ARIA label is present.

This is not a complete accessible-name, ARIA ownership, HTML validity, or landmark-nesting audit.

### Readability and contrast

- Visible direct text nodes: warn below 16 CSS pixels.
- Sentences longer than 40 words: warn.
- Text contrast: evaluate computed colors and use rendered pixel capture for complex backgrounds; error when the fiCMS contrast sufficiency helper rejects the ratio.

This does not assess reading level, language correctness, zoom/reflow, line spacing, text spacing overrides, orientation, or all non-text contrast requirements.

### Motion preferences

- Motion keyframe animations and recognized motion-property transitions lasting over 150 ms: inspect `prefers-reduced-motion` coverage.
- Warn when no applicable reduction rule exists or when the reduction still exceeds 150 ms.
- Explicit `prefers-reduced-motion: no-preference` opt-ins are recognized.

Generic `transition-property: all` is not evaluated as complete evidence for every motion property.

## Findings and counts

- `error` and `warning` are engine severities, not legal severity levels.
- `occurrences` counts reported rule occurrences in stored current page/viewport snapshots. A page-wide rule such as missing `h1` has one occurrence even when it has no element item.
- `affected_pages` counts audited page/language ids containing the rule.
- The same issue can appear in both desktop and mobile snapshots.
- Selectors and element names are sanitized at collection time. Media markup, screenshot data, and image alternative text values are not retained in findings.
