# Big Orange Pardot Plugin

A WordPress Gutenberg block plugin integrating Pardot (Account Engagement) form handlers into WordPress pages.

**GitHub:** https://github.com/BigOrangeLab/big-orange-pardot
**Playground demo:** `.github/blueprint.json` — installs Kadence Blocks + this plugin from the `main` branch, then runs `.github/setup.php` to create a "Contact Us" page pre-populated with the default 7-field pardot-form block and set it as the front page.

## Architecture

- **Main entry:** `big-orange-pardot.php` — registers block types, REST routes, admin page, scripts, and admin bar attribution inspector
- **Source:** `src/` — JSX/SCSS source files for all two block types
- **Build output:** `build/` — compiled assets per block (do not edit directly)
- **Block manifest:** `build/blocks-manifest.php` — auto-generated, do not edit
- **PHP includes:** `includes/` — server-side API client and admin page classes

### Block architecture

The plugin provides two block types:

**`bigorangelab/pardot-form`** — parent form block (`src/pardot-form/`)

| File | Purpose |
|------|---------|
| `src/pardot-form/block.json` | Block metadata, attributes (`pardotFormUrl`, `pardotFormHandlerId`, field style attrs, button style attrs), `providesContext`, `allowedBlocks`, native `color`/`spacing`/`border` supports |
| `src/pardot-form/index.js` | Block registration |
| `src/pardot-form/edit.js` | Editor component — two-path UX: **connected** mode shows handler dropdown, auto-inserts missing fields on handler select, field sync status ("X of Y present"), "Add missing field(s)" button, and "Replace all with Pardot fields" reset; **unconnected** mode shows URL input as primary control plus a "Common Pardot Fields" panel for one-click field insertion. `COMMON_PARDOT_FIELDS` constant defines 8 standard fields. `existingFieldNames` via `useSelect(getBlocks)` drives both sync status and common-fields panel. `BlockControls` alignment toolbar; `InspectorControls` for button colors/appearance; inline `RichText` submit button preview; emits field CSS custom props via `useBlockProps` |
| `src/pardot-form/render.php` | PHP template — wraps `$content` (rendered inner blocks) in `<form action="...">` + inline submit button + hidden attribution inputs; emits field and button CSS custom props via `get_block_wrapper_attributes()` |
| `src/pardot-form/save.js` | Returns `<InnerBlocks.Content />` (serializes inner blocks to post_content) |
| `src/pardot-form/editor.scss` | Editor-only styles — CSS grid mirroring via `:has()` on `.block-editor-block-list__layout` so half-width fields sit side-by-side in the editor |
| `src/pardot-form/style.scss` | Frontend (and editor) styles — CSS grid two-column layout on `form`; all field colours driven by `--bol-*` CSS custom properties |
| `src/attribution.js` | Global cookie capture + hidden field population (built to `build/attribution.js`, enqueued on every frontend page) |
| `src/admin-bar-attribution.js` | Admin bar "Clear all cookies" click handler — expires all 8 attribution cookies and reloads (built to `build/admin-bar-attribution.js`, enqueued via `bol_enqueue_admin_bar_assets()`) |
| `src/admin-bar-attribution.css` | Styles for the admin bar Attribution panel — imported from `admin-bar-attribution.js`, built to `build/admin-bar-attribution.css` |

**Submit button pattern:** The submit button is part of the parent `pardot-form` block, not a separate child block. Button style attributes (`submitLabel`, `buttonTextColor`, `buttonBgColor`, `buttonBgGradient`, `buttonHoverBgColor`, `buttonBorderColor`, `buttonBorderWidth`, `buttonBorderStyle`, `buttonBorderRadius`, `buttonPadding`, `buttonShadow`, `buttonAlignment`) are all stored on the parent block. The editor renders the button as an inline `RichText` element; `render.php` outputs it as a `<button type="submit">` with inline styles. Hover color is emitted as `--bol-btn-hover-bg` CSS custom property.

**`bigorangelab/pardot-field`** — individual form field (`src/pardot-field/`)

| File | Purpose |
|------|---------|
| `src/pardot-field/block.json` | Metadata — `parent: ["bigorangelab/pardot-form"]`; attributes: `fieldName`, `label`, `fieldType` (text/email/tel/textarea), `isRequired`, `placeholder`, `width` (full/half) |
| `src/pardot-field/edit.js` | InspectorControls (field settings + linked Field Styling panel that reads/writes the *parent* block's style attributes via `getBlockRootClientId`+`updateBlockAttributes`) |
| `src/pardot-field/render.php` | Outputs `<div class="bol-pardot-field bol-pardot-field--{width}">…</div>` — no `get_block_wrapper_attributes()` so CSS grid works cleanly |
| `src/pardot-field/save.js` | Returns `null` (dynamic block) |

**Field styling pattern:** style attributes (`fieldLabelColor`, `fieldInputBg`, `fieldBorderColor`, `fieldFocusColor`, `fieldBorderRadius`) are stored on the **parent** block, not on each field. The UI appears when any field is selected, keeping all fields visually consistent. The parent emits `--bol-label-color`, `--bol-input-bg`, `--bol-border-color`, `--bol-focus-color`, `--bol-field-radius` CSS custom properties on its wrapper element; `style.scss` consumes them via `var()` with fallback chains.

### PHP includes

| File | Purpose |
|------|---------|
| `includes/class-bol-pardot-api.php` | Static API client — OAuth token management, Pardot v5 API requests, form handler cache |
| `includes/class-bol-admin-page.php` | Settings page (Settings submenu) — two tabs: **Settings** (credentials, OAuth connect/disconnect, form handler inspector) and **Help** (user-facing setup documentation) |

### Pardot API integration

- **OAuth flow:** Salesforce authorization code flow. Credentials (`client_id`, `client_secret`, `business_unit_id`) stored in `wp_options` (autoload off). Tokens refreshed automatically by `BOL_Pardot_API::get_access_token()` when within 60 seconds of expiry.
- **Form handlers:** `BOL_Pardot_API::get_form_handlers()` fetches from `https://pi.pardot.com/api/v5/objects/form-handlers`, caches in transient `big_orange_pardot_form_handlers` (15 min). Returns `[{id, name, url}]` — the `url` is parsed from the handler's `embedCode` HTML via regex.
- **Form handler fields:** `BOL_Pardot_API::get_form_handler_fields( $handler_id )` fetches `/form-handler-fields?formHandlerId={id}`. Returns raw Pardot field objects with `name`, `isRequired`, `dataFormat` (Email/Phone/TextArea → mapped to fieldType; everything else → 'text').
- **REST endpoints:**
  - `GET /wp-json/big-orange-pardot/v1/form-handlers` — requires `manage_options`. Powers the block editor handler dropdown.
  - `GET /wp-json/big-orange-pardot/v1/form-handler-fields?handler_id={id}` — requires `manage_options`. Powers the "Import fields from Pardot" button in the editor.
- **Help tab:** `BOL_Admin_Page::render_help_tab()` contains user-facing setup documentation. **Update it whenever the plugin's behaviour, setup steps, form fields, or attribution tracking changes.** This includes any significant new features — e.g. new block controls, new field options, changes to how the form or submit button work.
- **Attribution fields:** 8 hidden fields populated by `src/attribution.js` cookies: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `referrer_url`, `landing_page_url`, `gclid`. The field names are also listed in `BOL_Pardot_API::ATTRIBUTION_FIELDS` (PHP constant).
- **Admin bar inspector:** `bol_register_admin_bar_node()` in `big-orange-pardot.php` adds an "Attribution (N)" menu to the WP admin bar (visible to `manage_options` users only, on both frontend and wp-admin). PHP reads `$_COOKIE` for the 8 attribution values; `src/admin-bar-attribution.js` handles the "Clear all cookies" button (expires cookies + reloads). Styles in `src/admin-bar-attribution.css` (CSS imported from JS, built alongside it); both enqueued by `bol_enqueue_admin_bar_assets()` (hooked to `wp_enqueue_scripts` and `admin_enqueue_scripts`).

## Build System

Uses `@wordpress/scripts` (wraps webpack + Babel). The `--blocks-manifest` flag auto-generates `build/blocks-manifest.php`.

```bash
npm run build       # Production build
npm run start       # Development watch mode
npm run lint:js     # JavaScript lint
npm run lint:css    # CSS/SCSS lint
npm run format      # Auto-format code
npm run plugin-zip  # Create distributable zip
```

> Always run a build before testing PHP-side block registration changes — the manifest is generated at build time.

## PHP Linting (WPCS / PHPCS)

PHP coding standards are enforced via [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards). Config is in `phpcs.xml.dist`.

```bash
composer install          # First time — installs PHPCS + WPCS into vendor/
composer lint:php         # Run PHPCS
composer lint:php:fix     # Run PHPCBF (auto-fix)
```

## Code Style

This project follows **WordPress Core coding standards** throughout, enforced by linting.

### PHP
- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/): Yoda conditions, `array()` (not `[]`), tabs for indentation, spaces inside parentheses, `snake_case` for functions/variables, `PascalCase` for classes.
- Escape all output (`esc_html__()`, `esc_url()`, `esc_attr()`). Sanitize all input (`sanitize_text_field()`, `wp_unslash()`). Use `check_admin_referer()` for nonce verification before processing POST data.
- No direct database calls; use WordPress option and transient APIs.

### JavaScript / JSX
- [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/): tabs for indentation, spaces inside braces and parentheses, single quotes.
- Use `const`/`let`, never `var`.
- All user-visible strings must be wrapped in `__()` or `sprintf()` from `@wordpress/i18n`. `sprintf()` calls require a `/* translators: ... */` comment **inside the same JSX expression block** as the call (not on a separate line).
- Avoid flanking whitespace in translation strings — use `{ ' ' }` for explicit spaces between JSX nodes.
- `/* global SomeGlobal */` comments are required for browser globals not in the default ESLint environment (e.g. `MutationObserver`), and for WP-localized script globals (e.g. `bolPardot`).

### CSS / SCSS
- [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/): tabs for indentation, space before `{`, lowercase properties.
- Order pseudo-class selectors least-specific first: `&:disabled` before `&:focus` before `&:hover`.
- Avoid `input, textarea` combined rules when textarea needs additional properties — use separate blocks to satisfy `no-descending-specificity`.
- Use CSS custom property fallback chains: block-level `--bol-*` first, then `--global-palette*` (Kadence, if active), then `--wp--preset--*`, then a hardcoded default. Never rely on any single source being present.

## Debugging approach

- **Check actual output before speculating.** When diagnosing a frontend issue, make an HTTP request to the local site (`https://pardot.wp.local/`) and inspect the rendered HTML or loaded CSS first. This is faster and more reliable than reasoning about what the output might be.
- **Ask the user questions early.** If a problem has multiple possible causes, ask a targeted question rather than running through all hypotheses. The user can often point you straight to the answer.

## Before marking any task as done

1. Run **both** linters and fix any reported issues:

```bash
npm run lint:js && npm run lint:css
composer lint:php
```

2. If the task adds or changes user-visible behaviour, update the **Help tab** in `includes/class-bol-admin-page.php` (`render_help_tab()`).
3. Update **`CLAUDE.md`** and **`.github/copilot-instructions.md`** with any architectural changes.

## Requirements

- WordPress 6.8+
- PHP 7.4+
- Node.js (for building assets)
- Composer (for PHP linting)
- Kadence Blocks is **optional** — when active, the form fields and submit button automatically inherit Kadence's global palette CSS custom properties (`--global-palette*`). Without it, styles fall back to WordPress preset colours and hardcoded defaults. The Playground demo installs Kadence to showcase the best experience, but it is not listed as a `Requires Plugins` dependency.
