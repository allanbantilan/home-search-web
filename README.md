# Home Search

Home Search is a WordPress real estate site with property search, an interactive map, agent profiles, and lead routing. The custom theme handles presentation; the Estate Search plugin owns listings, agents, fields, search, and enquiries so content remains available if the theme changes.

## Features

- Filter properties by location, price, bedrooms, and type. Results update through a WordPress REST endpoint, with server rendered results and pagination available without JavaScript.
- Switch search results between property cards and a Leaflet map with pins for listings that have coordinates.
- View property details, photo galleries, and an estimated monthly principal and interest payment.
- Save properties and compare up to three homes. Selections are stored in the visitor's browser.
- Send viewing requests to the assigned agent and general enquiries to the WordPress administration email.

## Components

| Path | Purpose |
| --- | --- |
| [`property-search/`](property-search/) | WordPress theme and frontend assets |
| [`estate-search/`](estate-search/) | Custom post types, ACF field groups, search API, and form handlers |
| [`setup-local.ps1`](setup-local.ps1) | Repeatable local WordPress setup |
| [`tests/`](tests/) | Integration, browser, and calculator checks |
| [`docs/Home-Search-Setup.pdf`](docs/Home-Search-Setup.pdf) | Installation and VPS setup guide |

## Local setup

On Windows, start Docker Desktop and run this from the project directory with PowerShell 7:

```powershell
pwsh -NoProfile -File .\setup-local.ps1 -SeedDemo
```

Open [http://127.0.0.1:57106/](http://127.0.0.1:57106/). On first installation, the script prints a generated administrator password. Save it securely. To use a different localhost port and isolated Docker volumes:

```powershell
pwsh -NoProfile -File .\setup-local.ps1 -Port 57107 -Project home-search-local-2 -SeedDemo
```

The setup installs WordPress, activates [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/), the Estate Search plugin and Home Search theme, enables pretty permalinks, and adds sample listings when `-SeedDemo` is set. Re-running it preserves the existing database and uploads.

The [local Compose file](.docker-test/compose.yml) uses example database credentials and captures outgoing mail. It is for local development and testing only; use a separate secured configuration for a public server.

## Install on an existing WordPress site

1. Use maintained WordPress and PHP releases with pretty permalinks enabled.
2. Copy `estate-search/` into `wp-content/plugins/` and activate **Estate Search**.
3. Install and activate [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/). The plugin registers its property and agent field groups automatically.
4. Copy `property-search/` into `wp-content/themes/` and activate **Home Search**. Theme activation creates Contact, Compare, and Saved Homes pages if they do not already exist.
5. Add agents with valid email addresses, then add properties with price, location, type, bedrooms, coordinates, a featured image, and an assigned agent. Add a WordPress Gallery block to the property description for additional photos.
6. Set the administration email to the office inbox. Configure [FluentSMTP](https://wordpress.org/plugins/fluent-smtp/) with an authenticated mail provider, then verify delivery of both contact and viewing requests.

The included forms handle agent routing. [Fluent Forms](https://wordpress.org/plugins/fluentform/) is optional if staff need to change form fields or notification rules in WordPress.

## Configuration notes

- Prices use Philippine pesos. For another market, update `home_search_money()` in [functions.php](property-search/functions.php), the `money()` formatter in [site.js](property-search/assets/js/site.js), and the map's default center.
- Map pins are shown for the currently loaded search results. The theme loads Leaflet directly and uses OpenStreetMap tiles; choose a dedicated tile provider for production traffic if required by the [tile usage policy](https://operations.osmfoundation.org/policies/tiles/).
- The calculator is an estimate of principal and interest only. It excludes taxes, insurance, fees, and lender adjustments.
- Favorites and comparisons use browser storage, so they do not sync across devices.
- Forms validate input and use a nonce, honeypot, and short repeat limit. Configure mail delivery and monitor submissions on a public site.

## Verification

```powershell
node tests/site.test.cjs
node --check property-search/assets/js/site.js
python tests/integration.py http://127.0.0.1:57106
python tests/browser.py http://127.0.0.1:57106
```

The browser check requires Python Playwright and Chrome at the standard Windows installation path. The integration test uses the sample listings created by `-SeedDemo`; its email requests are captured by the local test plugin.

The local mobile Lighthouse report recorded **93 performance** and **100 accessibility, best practices, and SEO**. A later Chrome MCP audit recorded **100 accessibility, best practices, and SEO** with no failed checks. See the [Lighthouse report](.docker-test/lighthouse-home.json), [Chrome audit](.docker-test/chrome-lighthouse.json), [desktop screenshot](.docker-test/homepage-browser.png), and [mobile screenshot](.docker-test/mobile-browser.png). These are local measurements.

## Production deployment

Use the [setup and VPS guide](docs/Home-Search-Setup.pdf) for the deployment sequence. Before public launch, configure HTTPS, unique credentials, real SMTP delivery, production content, a suitable map tile service, and offsite backups with a tested restore. Do not deploy the local test Compose file or its mail-capture plugin.
