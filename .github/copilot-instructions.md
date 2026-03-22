# Big Orange Pardot - Copilot Instructions

## Repository purpose

This plugin provides Gutenberg blocks for embedding and configuring Pardot (Account Engagement) form handlers in WordPress pages, using the Pardot v5 API via a Salesforce OAuth 2.0 authorization code flow.

## High-level structure

- `big-orange-pardot.php`: Main plugin bootstrap. Registers blocks, REST routes, admin page, and scripts.
- `src/`: Source of truth for block code (JSX/SCSS/PHP render templates).
  - `src/big-orange-pardot/`: Parent form block — handler dropdown, "Import fields from Pardot" button, `useInnerBlocksProps` with default 7-field template.
  - `src/pardot-field/`: Child field block — attributes: `fieldName`, `label`, `fieldType` (text/email/tel/textarea), `isRequired`, `placeholder`, `width` (full/half). `render.php` does NOT call `get_block_wrapper_attributes()` so the CSS grid on the parent `<form>` applies cleanly.
  - `src/pardot-submit/`: Child submit block — single `label` attribute.
- `includes/`: PHP classes for Pardot API integration and plugin admin/settings UI.
  - `includes/class-bol-pardot-api.php`: Static API client — OAuth token management, form handler cache, form handler fields.
  - `includes/class-bol-admin-page.php`: Two-tab settings page (Settings + Help). **Keep `render_help_tab()` current whenever plugin behaviour changes.**
- `assets/attribution.js`: Front-end attribution and hidden field population script. No build step.
- `build/`: Generated assets and block manifest output. Treat as generated artifacts.

## Block context flow

The parent block declares `"providesContext": { "big-orange-pardot/formUrl": "pardotFormUrl" }`. Child blocks declare `"usesContext": ["big-orange-pardot/formUrl"]` in `block.json` if they need the form action URL at render time.

## CSS grid layout

The parent `<form>` uses a two-column CSS grid. Child block wrapper divs are direct grid children:
- `.bol-pardot-field--full` → `grid-column: span 2`
- `.bol-pardot-field--half` → `grid-column: span 1`
- `.bol-pardot-submit` → `grid-column: span 2`

## REST endpoints

- `GET /wp-json/big-orange-pardot/v1/form-handlers` — lists all form handlers. Powers the editor dropdown.
- `GET /wp-json/big-orange-pardot/v1/form-handler-fields?handler_id={id}` — returns fields for a handler. Powers the "Import fields from Pardot" button.

Both require `manage_options`.

## Working guidance

- Prefer editing source files in `src/`, `includes/`, `assets/`, and `big-orange-pardot.php`.
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
- Focus commit/review descriptions on meaningful source changes in `src/`, `includes/`, `assets/`, and root plugin PHP files.
- Only discuss `build/` changes when they are explicitly requested or required to explain release packaging artifacts.

## Quality checks

Before considering changes complete, prefer running:

- `npm run lint:js && npm run lint:css`
- `composer lint:php`
