# Big Orange Pardot

A WordPress Gutenberg block plugin that embeds Pardot (Account Engagement) form handlers directly on any page or post as native HTML forms — no iframes, full control over styling and layout.

[![Try in WordPress Playground](https://img.shields.io/badge/Try%20in-WordPress%20Playground-3858e9?logo=wordpress)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/BigOrangeLab/big-orange-pardot/main/.github/blueprint.json)

---

## Features

- **Native form rendering** — submits directly to your Pardot form handler URL; no iframe, no JS embed.
- **Gutenberg innerBlocks** — each field is an independent block. Reorder, remove, or add fields freely in the block editor.
- **Two-path editor UX** — when connected to Pardot, selecting a form handler auto-inserts missing fields and shows live sync status; when not connected, a URL input and "Common Pardot Fields" panel let you build the form manually without API access.
- **Flexible layout** — two-column CSS grid; fields can be full-width or half-width, with two adjacent half-width fields displayed side by side.
- **Shared field styling** — label colour, input background, border colour, focus/accent colour, and border radius are configured once and applied to every field in the form.
- **Full submit button customisation** — text colour, background or gradient, hover colour, padding, border, border radius, shadow, and alignment.
- **Form-level styling** — native WordPress block supports for background colour, padding, margin, and border on the outer form wrapper.
- **Marketing attribution tracking** — captures UTM parameters, Google Click ID, landing page URL, and referrer into cookies on every page, then injects them as hidden fields on any Pardot form found on the page (including dynamically loaded forms).
- **Admin bar attribution inspector** — administrators see an "Attribution (N)" menu in the WordPress toolbar showing current cookie values and a one-click "Clear all" for testing fresh-visitor attribution flows.
- **Safe unconfigured preview** — when no form handler URL is set, the form is hidden from visitors; editors previewing the page see a notice and a non-submittable form preview so they can review the layout before go-live.

---

## Requirements

- WordPress 6.8+
- PHP 7.4+
- A Pardot (Account Engagement) account with API access (for the handler dropdown and field import; a form handler URL can also be entered manually).

> **Works great with [Kadence Blocks](https://wordpress.org/plugins/kadence-blocks/)** — when Kadence is active, the submit button and form fields automatically inherit your global palette colours and button styles. The plugin works fine without it, falling back to WordPress preset colours and sensible defaults.

---

## Installation

### From a release zip

1. Download the latest release zip from the [Releases page](https://github.com/BigOrangeLab/big-orange-pardot/releases).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin** and upload the zip.
3. Activate **Big Orange Pardot**.

### From source

```bash
git clone https://github.com/BigOrangeLab/big-orange-pardot.git
cd big-orange-pardot
npm install
npm run build
```

Copy (or symlink) the directory into your WordPress `wp-content/plugins/` folder, then activate the plugin.

---

## Connecting to Pardot

The plugin connects to Pardot via the Salesforce OAuth 2.0 Web Server Flow. Full setup instructions — including how to create a Salesforce Connected App and find your Business Unit ID — are on the plugin's **Settings → Big Orange Pardot → Help** tab after activation.

You can also paste a form handler URL directly into the block without connecting to Pardot, which skips the OAuth setup entirely.

---

## Development

See [CLAUDE.md](CLAUDE.md) for the full architecture reference and code style guide.

```bash
npm run build        # Production build
npm run start        # Development watch mode
npm run lint:js      # JavaScript lint
npm run lint:css     # CSS/SCSS lint
npm run format       # Auto-format code
npm run plugin-zip   # Create distributable zip

composer install     # Install PHP linting tools (first time)
composer lint:php    # PHP lint (WordPress Coding Standards)
composer lint:php:fix  # Auto-fix PHP lint issues
```

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
