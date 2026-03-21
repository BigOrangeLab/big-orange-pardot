# Big Orange Pardot Plugin

A WordPress block plugin that provides a Gutenberg block for embedding Pardot forms.

## Architecture

- **Main entry:** `big-orange-pardot.php` — registers block types via the WordPress 6.7+ `wp_register_block_types_from_metadata_collection()` API
- **Source:** `src/big-orange-pardot/` — JSX/SCSS source files
- **Build output:** `build/big-orange-pardot/` — compiled assets (do not edit directly)
- **Block manifest:** `build/blocks-manifest.php` — auto-generated, do not edit

### Block files

| File | Purpose |
|------|---------|
| `src/big-orange-pardot/block.json` | Block metadata, attributes, asset registration |
| `src/big-orange-pardot/index.js` | Block registration |
| `src/big-orange-pardot/edit.js` | React component rendered in the block editor |
| `src/big-orange-pardot/render.php` | PHP template — dynamic block frontend output |
| `src/big-orange-pardot/save.js` | Returns `null` (dynamic block, PHP renders output) |
| `assets/attribution.js` | Global cookie capture + hidden field population (enqueued on every page) |
| `src/big-orange-pardot/editor.scss` | Editor-only styles |
| `src/big-orange-pardot/style.scss` | Frontend (and editor) styles |

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

## Status

Early development / scaffolding phase. The block currently renders placeholder text; Pardot form integration is not yet implemented.
