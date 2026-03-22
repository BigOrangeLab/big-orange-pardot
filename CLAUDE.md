# Big Orange Pardot Plugin

A WordPress Gutenberg block plugin integrating Pardot (Account Engagement) form handlers into WordPress pages.

## Architecture

- **Main entry:** `big-orange-pardot.php` — registers block types, REST routes, admin page, and scripts
- **Source:** `src/` — JSX/SCSS source files for all three block types
- **Build output:** `build/` — compiled assets per block (do not edit directly)
- **Block manifest:** `build/blocks-manifest.php` — auto-generated, do not edit
- **PHP includes:** `includes/` — server-side API client and admin page classes

### Block architecture

The plugin provides three block types:

**`bigorangelab/big-orange-pardot`** — parent form block (`src/big-orange-pardot/`)

| File | Purpose |
|------|---------|
| `src/big-orange-pardot/block.json` | Block metadata, attributes (`pardotFormUrl`, `pardotFormHandlerId`), `providesContext`, `allowedBlocks` |
| `src/big-orange-pardot/index.js` | Block registration |
| `src/big-orange-pardot/edit.js` | Editor component — handler dropdown, "Import fields from Pardot" button, `useInnerBlocksProps` with default 7-field template |
| `src/big-orange-pardot/render.php` | PHP template — wraps `$content` (rendered inner blocks) in `<form action="...">` + hidden attribution inputs |
| `src/big-orange-pardot/save.js` | Returns `null` (dynamic block) |
| `src/big-orange-pardot/editor.scss` | Editor-only styles (inspector notice, loading spinner) |
| `src/big-orange-pardot/style.scss` | Frontend (and editor) styles — CSS grid two-column layout on `form` |
| `assets/attribution.js` | Global cookie capture + hidden field population (enqueued on every page, no build step) |

**`bigorangelab/pardot-field`** — individual form field (`src/pardot-field/`)

| File | Purpose |
|------|---------|
| `src/pardot-field/block.json` | Metadata — `parent: ["bigorangelab/big-orange-pardot"]`; attributes: `fieldName`, `label`, `fieldType` (text/email/tel/textarea), `isRequired`, `placeholder`, `width` (full/half) |
| `src/pardot-field/edit.js` | InspectorControls (5 field settings), visual preview with label wrapping input |
| `src/pardot-field/render.php` | Outputs `<div class="bol-pardot-field bol-pardot-field--{width}">…</div>` — no `get_block_wrapper_attributes()` so CSS grid works cleanly |
| `src/pardot-field/save.js` | Returns `null` (dynamic block) |

**`bigorangelab/pardot-submit`** — submit button (`src/pardot-submit/`)

| File | Purpose |
|------|---------|
| `src/pardot-submit/block.json` | Metadata — `parent: ["bigorangelab/big-orange-pardot"]`; attribute: `label` |
| `src/pardot-submit/edit.js` | InspectorControls (button label), disabled preview button |
| `src/pardot-submit/render.php` | Outputs `<div class="bol-pardot-submit"><button type="submit">…</button></div>` |
| `src/pardot-submit/save.js` | Returns `null` (dynamic block) |

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
- **Help tab:** `BOL_Admin_Page::render_help_tab()` contains user-facing setup documentation. **Update it whenever the plugin's behaviour, setup steps, form fields, or attribution tracking changes.**
- **Attribution fields:** 8 hidden fields populated by `assets/attribution.js` cookies: `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `referrer_url`, `landing_page_url`, `gclid`.

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
- Use CSS custom properties (`--wp--preset--*`, `--global-palette*`) with hardcoded fallbacks for theme compatibility.

## Before marking any task as done

Run **both** linters and fix any reported issues:

```bash
npm run lint:js && npm run lint:css
composer lint:php
```

## Requirements

- WordPress 6.8+
- PHP 7.4+
- Node.js (for building assets)
- Composer (for PHP linting)
- Kadence Blocks plugin (declared as a plugin dependency via `Requires Plugins` header)
