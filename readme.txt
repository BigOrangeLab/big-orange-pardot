=== Big Orange Pardot ===
Contributors:      georgestephanis
Tags:              pardot, account engagement, salesforce, forms, gutenberg
Tested up to:      6.8
Stable tag:        1.0.0
Requires at least: 6.8
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Embed Pardot (Account Engagement) form handlers as native HTML forms using Gutenberg blocks — no iframes, full control over styling.

== Description ==

Big Orange Pardot adds a Gutenberg block that renders your Pardot (Account Engagement) form handlers as native HTML forms directly on any page or post. Rather than embedding an iframe, it outputs a real `<form>` that submits straight to your Pardot form handler URL — giving you full control over styling, layout, and field order.

**Two ways to connect:**

* **Connected (OAuth)** — link the plugin to your Pardot account via Salesforce OAuth. Select a form handler from a dropdown; missing fields are inserted automatically, and a sync status shows how many Pardot fields are present in your form.
* **Unconnected (URL)** — paste the form handler URL directly into the block. A "Common Pardot Fields" panel lets you add standard field names in one click, no API access required.

**Key features:**

* Native form rendering — submits directly to your Pardot form handler; no iframe or JS embed.
* Gutenberg innerBlocks — each field is an independent block. Reorder, remove, or add fields freely.
* Flexible two-column CSS grid layout — fields can be full-width or half-width.
* Shared field styling — label colour, input background, border, focus accent, and radius configured once and applied to every field.
* Full submit button customisation — text colour, background or gradient, hover colour, padding, border, radius, shadow, and alignment.
* Form-level styling — WordPress block supports for background colour, padding, margin, and border on the outer wrapper.
* Marketing attribution tracking — captures UTM parameters, Google Click ID, landing page URL, and referrer into cookies on every page, then injects them as hidden fields on any Pardot form (including dynamically loaded ones).
* Admin bar attribution inspector — administrators see current attribution cookie values in the toolbar and can clear them instantly for testing.
* Safe unconfigured preview — when no handler URL is set, the form is hidden from visitors; editors see a notice and a non-submittable preview in context.

Works great with [Kadence Blocks](https://wordpress.org/plugins/kadence-blocks/) — when active, the form fields and submit button automatically inherit your global palette colours and button styles. Fully functional without it.

== Installation ==

= From a release zip =

1. Download the latest release zip from the [GitHub Releases page](https://github.com/BigOrangeLab/big-orange-pardot/releases).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin** and upload the zip.
3. Activate **Big Orange Pardot**.

= From source =

1. Clone the repository and run `npm install && npm run build`.
2. Copy or symlink the directory into `wp-content/plugins/`.
3. Activate the plugin.

= Connecting to Pardot (optional) =

Full OAuth setup instructions — including how to create a Salesforce Connected App and find your Business Unit ID — are on the plugin's **Settings → Big Orange Pardot → Help** tab after activation. You can also skip OAuth entirely and paste a form handler URL directly into the block.

== Frequently Asked Questions ==

= Do I need a Pardot/Account Engagement account? =

You need a Pardot account with at least one form handler configured. The OAuth connection is optional — you can paste the form handler URL directly into the block without connecting the API.

= What is a form handler? =

A Pardot form handler is a Pardot object that accepts POST submissions from external HTML forms. It maps submitted field names to Pardot prospect fields. You create form handlers in Pardot under Marketing → Forms → Form Handlers.

= Does this work without Kadence Blocks? =

Yes. Kadence Blocks is optional. When active, the form and button automatically inherit your Kadence global palette colours. Without it, the plugin falls back to WordPress preset colours and sensible hard-coded defaults.

= The form is not visible on the frontend. Why? =

If no form handler URL is configured, the form is hidden from site visitors to prevent an unusable form from appearing. Editors who are logged in will see a notice and a non-submittable preview. Configure the URL (either by connecting OAuth and selecting a handler, or by pasting it in manually) to make the form visible to all visitors.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
