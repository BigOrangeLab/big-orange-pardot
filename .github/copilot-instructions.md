# Big Orange Pardot - Copilot Instructions

## Repository purpose

This plugin provides Gutenberg blocks for embedding and configuring Pardot (Account Engagement) form handlers in WordPress pages, using the Pardot v5 API via a Salesforce OAuth 2.0 authorization code flow.

## High-level structure

- `big-orange-pardot.php`: Main plugin bootstrap. Registers blocks, REST routes, admin page, scripts, and admin bar attribution inspector.
- `src/`: Source of truth for block code (JSX/SCSS/PHP render templates).
    - `src/pardot-form/`: Parent form block (`bigorangelab/pardot-form`) — two-path editor UX: **connected** mode (OAuth active) shows the handler dropdown, auto-inserts any missing fields when a handler is selected, displays a sync status ("X of Y Pardot fields present"), an "Add missing field(s)" button, and a "Replace all with Pardot fields" reset button; **unconnected** mode shows a prominent URL input and a "Common Pardot Fields" panel for one-click insertion of 8 standard Pardot field names. `existingFieldNames` from `useSelect(getBlocks(clientId))` drives both sync status and common-fields presence checks. Stores shared field style attributes and emits them as `--bol-*` CSS custom properties. Also owns all submit button attributes — rendered inline, not as a separate child block. `save.js` returns `<InnerBlocks.Content />` (not `null`) to serialize inner blocks.
    - `src/pardot-field/`: Child field block (`bigorangelab/pardot-field`) — attributes: `fieldName`, `label`, `fieldType` (text/email/tel/textarea), `isRequired`, `placeholder`, `width` (full/half). The "Field Styling" panel in `edit.js` reads and writes style attributes on the **parent** block (via `getBlockRootClientId`/`updateBlockAttributes`), keeping styling consistent across all fields. `render.php` does NOT call `get_block_wrapper_attributes()` so the CSS grid on the parent `<form>` applies cleanly.
- `includes/`: PHP classes for Pardot API integration and plugin admin/settings UI.
    - `includes/class-bol-pardot-api.php`: Static API client — OAuth token management, optional Salesforce Business Unit discovery/cache (requires `api` scope), optional JSONL API request logging to uploads (sensitive values redacted), form handler cache, form handler fields.
    - `includes/class-bol-admin-page.php`: Three-tab settings page (Settings + Help + Logs), including connect-flow Business Unit auto-discovery, API logging toggle, and log viewer/delete controls. **Keep `render_help_tab()` current whenever plugin behaviour changes.**
- `src/attribution.js`: Front-end attribution, hidden field population, client-side submit validation, and Pardot error-redirect handling. Built to `build/attribution.js`.
- `src/admin-bar-attribution.js`: Admin bar "Clear all cookies" handler — expires all 9 attribution cookies and reloads. Built to `build/admin-bar-attribution.js`. Imports `src/admin-bar-attribution.scss`.
- `src/admin-bar-attribution.scss`: Styles for the admin bar Attribution panel. Imported from `admin-bar-attribution.js`; built to `build/admin-bar-attribution.css`.
- `src/log-viewer.js` + `src/log-viewer-app.js` + `src/log-viewer.scss`: Logs tab app and styles. Built to `build/log-viewer.js` and `build/log-viewer.css`. Renders parsed API log entries in a WordPress DataViews table with status badges, a failed-only toggle, and row inspection actions.
- `build/`: Generated assets and block manifest output. Treat as generated artifacts.

## Block context flow

The parent block declares `"providesContext": { "big-orange-pardot/formUrl": "pardotFormUrl" }`. Child blocks declare `"usesContext": ["big-orange-pardot/formUrl"]` in `block.json` if they need the form action URL at render time.

## CSS grid layout

The parent `<form>` uses a two-column CSS grid. Child block wrapper divs are direct grid children:

- `.bol-pardot-field--full` → `grid-column: span 2`
- `.bol-pardot-field--half` → `grid-column: span 1`
- `.bol-pardot-errors` → `grid-column: span 2`
- `.bol-pardot-submit` → `grid-column: span 2`

In the block editor, Gutenberg wraps each inner block in a `.wp-block` div, which breaks the grid. `editor.scss` targets `.block-editor-block-list__layout` as the grid container and uses `:has()` to apply `grid-column` to the `.wp-block` wrappers based on their child classes.

## Field styling CSS custom properties

The parent block wrapper emits these custom properties, consumed by `style.scss`:

- `--bol-label-color` — label text colour
- `--bol-input-bg` — input/textarea background
- `--bol-border-color` — input/textarea border colour
- `--bol-focus-color` — focus outline/border accent
- `--bol-field-radius` — input/textarea border radius
- `--bol-btn-hover-bg` — submit button hover background (set on the `<button>` element)

## REST endpoints

- `GET /wp-json/big-orange-pardot/v1/form-handlers` — lists all form handlers. Powers the editor dropdown.
- `GET /wp-json/big-orange-pardot/v1/form-handler-fields?handler_id={id}` — returns fields for a handler. Powers the "Import fields from Pardot" button.

Both require `manage_options`.

## Admin bar attribution inspector

`bol_register_admin_bar_node()` adds an "Attribution (N)" menu to the WP admin bar, visible only to `manage_options` users. PHP reads `$_COOKIE` for the 9 cookie-backed attribution field values (`BOL_Pardot_API::ATTRIBUTION_FIELDS` — note: `last_form_submission_url` is not in this constant as it is set from `window.location.href`, not a cookie). `src/admin-bar-attribution.js` handles the "Clear all cookies" link (expires all cookies + reloads the page). Styles in `src/admin-bar-attribution.scss` (built to `build/admin-bar-attribution.css`). Both assets enqueued by `bol_enqueue_admin_bar_assets()`, hooked to both `wp_enqueue_scripts` and `admin_enqueue_scripts`.

## Debugging approach

- **Check actual output before speculating.** When diagnosing a frontend issue, make an HTTP request to `https://pardot.wp.local/` and inspect the rendered HTML or loaded CSS first rather than reasoning about what the output might be.
- **Ask the user questions early.** If a problem has multiple possible causes, ask a targeted question rather than running through all hypotheses.

## Working guidance

- Prefer editing source files in `src/`, `includes/`, and `big-orange-pardot.php`.
- Do not hand-edit generated files in `build/` unless explicitly requested.
- If source changes affect block registration or front-end/editor assets, run `npm run build` to regenerate build output.
- When updating `CLAUDE.md`, also update this file to match.

## Code style

Follows **WordPress Core coding standards** throughout, enforced by linting.

**PHP:** Yoda conditions, `array()` not `[]`, tabs, spaces inside parens, `snake_case`/`PascalCase`. Escape all output (`esc_html__()`, `esc_url()`, `esc_attr()`). Sanitize all input. No direct DB calls.

**JS/JSX:** Tabs, spaces inside braces, single quotes, `const`/`let` only. All user-visible strings in `__()` or `sprintf()` from `@wordpress/i18n`. `sprintf()` requires a `/* translators: ... */` comment inside the same JSX expression block. Avoid flanking whitespace in i18n strings — use `{ ' ' }` for explicit spaces.

**CSS/SCSS:** Tabs, space before `{`, lowercase. Order pseudo-classes least-specific first (`&:disabled` before `&:focus` before `&:hover`). Avoid combined `input, textarea` rules when textarea needs extra properties — use separate blocks.

## Commit and review guidance

- For commit message generation and code review summaries, ignore changes under `build/` by default.
- Focus commit/review descriptions on meaningful source changes in `src/`, `includes/`, and root plugin PHP files.
- Only discuss `build/` changes when they are explicitly requested or required to explain release packaging artifacts.

## Quality checks

Before considering changes complete, prefer running:

- `npm run lint:js && npm run lint:css`
- `composer lint:php`
