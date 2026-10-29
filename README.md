# Home Search real estate portfolio

A WordPress portfolio build with a **Home Search** theme and an **Estate Search** companion plugin. The plugin owns property and agent content so changing the theme does not remove listings.

## Local Docker demo

On Windows with Docker Desktop and PowerShell 7, run `pwsh -NoProfile -File .\setup-local.ps1 -SeedDemo` from this folder. The script installs WordPress, Advanced Custom Fields, the theme, the companion plugin, pretty permalinks, and three demo listings. It prints a generated local administrator password once. Open `http://127.0.0.1:57106/`. Use `-Port 57107 -Project another-name` if that port is occupied. Re-running the command keeps the existing site and data.

The `.docker-test` stack uses example database passwords and intercepts mail for testing. **Do not deploy it to a VPS.** The printable [setup and VPS guide](docs/Home-Search-Setup.pdf) covers production installation and launch checks.

## Install

1. Use WordPress 6.2+ and PHP 7.4+ with pretty permalinks enabled.
2. Copy `estate-search` to `wp-content/plugins/` and activate it.
3. Install and activate [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/). The companion plugin registers the field groups automatically.
4. Copy `property-search` to `wp-content/themes/` and activate it. The theme creates the Contact, Compare, and Saved Homes pages if those slugs do not exist.
5. Create agents first, including an email address. Then create properties with price, location, bedrooms, type, featured image, coordinates, and assigned agent. Add a native WordPress Gallery block to the property content for extra photos.
6. Set the WordPress administration email to the office inbox. Configure a transactional mail provider and send a test contact and viewing request.

The design uses Philippine pesos and Philippine map defaults. Change `home_search_money()` in `property-search/functions.php`, the `money()` formatter in `property-search/assets/js/site.js`, and map defaults if this build is used elsewhere.

## Features

- The homepage offers quick search and featured listings. The property archive supports server rendered filtering and pagination, then upgrades to live REST search when JavaScript is available.
- The map uses Leaflet and OpenStreetMap tiles. Only currently loaded result pages have pins. Add latitude and longitude to a property to show its pin.
- Property pages include a monthly principal and interest estimate. It excludes taxes, fees, insurance, and lender adjustments.
- Viewing requests go to the assigned agent's address. General contact requests go to the WordPress administration address. Both forms validate inputs, use a nonce, a honeypot, and a 30 second repeat limit.
- Favorites and up to three comparison choices are saved in the visitor's browser. They do not follow a visitor between devices.

## Plugin choices

**Install:** [ACF](https://wordpress.org/plugins/advanced-custom-fields/) for the field editor and [FluentSMTP](https://wordpress.org/plugins/fluent-smtp/) for reliable `wp_mail()` delivery through a provider. Configure only one SMTP plugin.

**Optional:** [Fluent Forms](https://wordpress.org/plugins/fluentform/) if office staff need to edit forms and notification rules themselves. The included forms already route viewing requests to the assigned agent, so it is not required.

Leaflet is loaded directly by the theme to keep search results and pins in sync. A separate map plugin or page builder is not needed. For a busy commercial site, use a dedicated map tile provider and follow its usage policy.

## Verification

Run `node tests/site.test.cjs` and `node --check property-search/assets/js/site.js`. The isolated Docker stack in `.docker-test/compose.yml` passed PHP lint, WordPress route checks, REST filters, form routing, and the Playwright browser checks in `tests/browser.py`. Its sample content and captured mail remain only in the local Docker volumes.

The local mobile Lighthouse audit scored **93 performance, 100 accessibility, 100 best practices, and 100 SEO**. See `.docker-test/lighthouse-home.json`, `.docker-test/homepage-browser.png`, and `.docker-test/mobile-browser.png`. These are local test results, not production PageSpeed measurements.

A fresh Chrome MCP mobile audit after the final review scored **100 accessibility, 100 best practices, and 100 SEO**, with no failed audits. Its report is `.docker-test/chrome-lighthouse.json`.
