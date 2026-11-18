# UAMSWP Per-site Wayfinding

A WordPress Multisite plugin that serves unique `robots.txt`, `llms.txt`, and `llms-full.txt` files for every subsite in the network. Each subsite maintains its own content through a simple admin UI, with a safety guard against accidentally blocking all crawlers. A network-level sitemap block generator produces a deterministic, alphabetically-sorted list of every public subsite's sitemap for pasting into physical `robots.txt` files. Optional GA4 Measurement Protocol logging tracks every request to `llms.txt` and `llms-full.txt` so you can see which AI agents are fetching them.

Built for the UAMS multisite federation but written to work on any WordPress Multisite install.

## What it does

- Overrides WordPress's virtual `robots.txt` on a per-subsite basis (where that makes sense: see [Where robots.txt can be edited](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/wiki/Where-robots-txt-can-be-edited) in the wiki).
- Registers virtual routes for `/llms.txt` and `/llms-full.txt` on every subsite.
- Stores content in per-site options so each subsite has its own three files.
- Prevents WordPress's canonical redirect from appending a trailing slash to `/llms.txt` and `/llms-full.txt` (which would otherwise 301 to `/llms.txt/`).
- Adds a **Settings: Site Files** admin page, restricted to super admins.
- Guards against accidentally blocking all crawlers by requiring explicit confirmation before saving a `User-agent: *` block that contains `Disallow: /`.
- Detects physical `robots.txt` files at the web root and warns super admins when the editor is being shadowed by a file on disk.
- Provides a network-level **Sitemap Block Generator** that emits a sorted `# Sitemaps` block listing every public subsite for copy-paste into physical `robots.txt` files.
- Optionally logs every request for `llms.txt` and `llms-full.txt` to GA4 via the Measurement Protocol, with the requested file, user agent, requester IP, and site host recorded as event parameters.
- Flushes rewrite rules on activation so `/llms.txt` and `/llms-full.txt` resolve on the site where the plugin is activated.

## Documentation

The [wiki](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/wiki) holds the walkthrough, the task guides (a physical `robots.txt`, GA4 tracking), the reference for every admin page, option and served URL, and troubleshooting. It is published from [`docs/wiki/`](docs/wiki/Home.md) in this repository.

## Requirements

- WordPress Multisite (network activation required; plugin declares `Network: true`).
- WordPress 6.8 or later.
- PHP 8.2 or later.
- Super admin access to edit site files.
- A GA4 property and Measurement Protocol API secret if you want to enable request tracking (optional).

## Installation

1. Copy the plugin folder into `wp-content/plugins/` on the multisite install.
2. Go to **Network Admin: Plugins** and network-activate **UAMSWP Per-Site robots.txt, llms.txt, llms-full.txt**.
3. On each subsite, go to **Settings: Site Files** to edit that subsite's content.
4. Activation flushes rewrite rules on the site where the plugin is activated; WordPress runs that step once, not on every subsite. If `/llms.txt` or `/llms-full.txt` returns a 404 on another subsite, visit **Settings: Permalinks** on that subsite and click Save to flush its rules.
5. (Optional) Configure GA4 tracking under **Network Admin: Settings: Site Files Tracking**.
6. (Optional) If the site uses a physical `robots.txt` file, visit **Network Admin: Settings: Sitemap Block Generator** and paste the generated block into the physical file.

## Tests

The unit suite runs on [Pest](https://pestphp.com) 5 with [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) mocking WordPress, so it needs no database and no WordPress checkout. It develops and tests on PHP 8.4 (`config.platform.php` in `composer.json` is `8.4.27`); the Composer packages are development dependencies only.

```bash
composer install
vendor/bin/pest
```

`composer test` runs the same suite. Remote CI is the gate: on every pull request it lints the PHP, runs Pest, PHPStan, Rector and Pint, and checks that the GitHub download holds only the plugin.

## Version

1.3.3

### Changelog

- **1.3.3** No change to what the plugin does. The `query_vars` filter is typed, and the `redirect_canonical` filter reads its requested URL as text, so a non-text value is passed through unchanged. The GitHub download now holds only the plugin file and its license. The code is under PHPStan level 10, Rector, Pint and a full Pest suite with 100% mutation score, run by CI.
- **1.3.2** Guarded the `redirect_canonical` filter against a request with no path, so it returns WordPress's redirect unchanged. The plugin header's name changed to "UAMSWP Per-Site robots.txt, llms.txt, llms-full.txt".
- **1.3.1** Sitemap Block Generator now sorts output: network root site first, then child subsites alphabetically by URL (case-insensitive).
- **1.3.0** Added multisite-type awareness (robots.txt editor shows on subdomain subsites and subdirectory root only). Added physical-`robots.txt` detection with shadow warning. Added Sitemap Block Generator at network admin. Added `register_activation_hook` to flush rewrites automatically. Narrowed GA4 tracking to `llms.txt` and `llms-full.txt` only; `robots.txt` tracking removed because physical files bypass WordPress.
- **1.1.0** Added optional GA4 Measurement Protocol tracking with a network-level settings page. Per-subsite page now displays tracking status.
- **1.0.0** Initial production build: per-subsite robots.txt, llms.txt, llms-full.txt with Disallow guard.

## Credits

Developed and maintained by UAMS Web Services.

Developers:

- Josh Daugherty
- Brent Passmore

## License

Copyright 2026 UAMS Web Services.

This plugin is released under the GNU General Public License, version 2 or later (GPL-2.0-or-later). See [`LICENSE`](LICENSE) for the full text.
