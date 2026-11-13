# UAMSWP Per-site Wayfinding

A WordPress Multisite plugin that serves unique `robots.txt`, `llms.txt`, and `llms-full.txt` files for every subsite in the network. Each subsite maintains its own content through a simple admin UI, with a safety guard against accidentally blocking all crawlers. A network-level sitemap block generator produces a deterministic, alphabetically-sorted list of every public subsite's sitemap for pasting into physical `robots.txt` files. Optional GA4 Measurement Protocol logging tracks every request to `llms.txt` and `llms-full.txt` so you can see which AI agents are fetching them.

Built for the UAMS multisite federation but written to work on any WordPress Multisite install.

## What it does

- Overrides WordPress's virtual `robots.txt` on a per-subsite basis (where that makes sense: see Multisite-type awareness below).
- Registers virtual routes for `/llms.txt` and `/llms-full.txt` on every subsite.
- Stores content in per-site options so each subsite has its own three files.
- Prevents WordPress's canonical redirect from appending a trailing slash to `/llms.txt` and `/llms-full.txt` (which would otherwise 301 to `/llms.txt/`).
- Adds a **Settings: Site Files** admin page, restricted to super admins.
- Guards against accidentally blocking all crawlers by requiring explicit confirmation before saving a `User-agent: *` block that contains `Disallow: /`.
- Detects physical `robots.txt` files at the web root and warns super admins when the editor is being shadowed by a file on disk.
- Provides a network-level **Sitemap Block Generator** that emits a sorted `# Sitemaps` block listing every public subsite for copy-paste into physical `robots.txt` files.
- Optionally logs every request for `llms.txt` and `llms-full.txt` to GA4 via the Measurement Protocol, with the requested file, user agent, requester IP, and site host recorded as event parameters.
- Flushes rewrite rules automatically on activation so `/llms.txt` and `/llms-full.txt` resolve without a manual Save Permalinks step.

## Multisite-type awareness

`robots.txt` is host-scoped per the HTTP spec: exactly one `robots.txt` per host, served at the host root. The plugin respects this:

- **Subdomain multisites (`site1.example.com`, `site2.example.com`):** every subsite is its own host, so every subsite gets its own `robots.txt` editor.
- **Subdirectory multisites (`example.com/site1/`, `example.com/site2/`):** only the network root site owns the host's `robots.txt`. Child subsites share the host and cannot have their own `robots.txt` that crawlers will honor. On these subsites, the plugin hides the `robots.txt` editor and shows an info notice pointing to the root-site editor.

The `llms.txt` and `llms-full.txt` editors are available on every subsite regardless of multisite type, since those files are not host-scoped by any spec and can meaningfully differ per subdirectory.

## Physical `robots.txt` files

If a physical `robots.txt` file exists at the web root, the web server serves that file in preference to anything WordPress produces. The plugin detects this and shows a warning in the admin UI, explaining that edits to the virtual editor will not reach crawlers until the physical file is removed.

This situation is common on production hosts that hand-maintain a large, carefully-ordered `robots.txt` with AI allowlists, bad-bot blocklists, and WordPress hardening rules. In that case, the plugin's robots.txt editor is informational only, and the Sitemap Block Generator (described below) is the main tool for keeping that physical file in sync.

## Requirements

- WordPress Multisite (network activation required; plugin declares `Network: true`).
- PHP 7.4 or later.
- Super admin access to edit site files.
- A GA4 property and Measurement Protocol API secret if you want to enable request tracking (optional).

## Installation

1. Copy the plugin folder into `wp-content/plugins/` on the multisite install.
2. Go to **Network Admin: Plugins** and network-activate **UAMSWP Per-site Wayfinding**.
3. On each subsite, go to **Settings: Site Files** to edit that subsite's content.
4. Rewrite rules flush automatically on activation. If `/llms.txt` or `/llms-full.txt` returns a 404 on an existing subsite, visit **Settings: Permalinks** on that subsite and click Save to force a manual flush.
5. (Optional) Configure GA4 tracking under **Network Admin: Settings: Site Files Tracking**.
6. (Optional) If the site uses a physical `robots.txt` file, visit **Network Admin: Settings: Sitemap Block Generator** and paste the generated block into the physical file.

## How to use

### Per-subsite content

Navigate to **Settings: Site Files** on any subsite. Depending on multisite type, you'll see two or three editors:

**robots.txt** (subdomain subsites always, subdirectory root site only)
Leave blank to fall back to the WordPress default. The plugin's default includes an `Allow: /` rule for all user agents and a `Sitemap:` directive pointing at the subsite's own `/sitemap.xml`. If a physical file exists at the web root, a warning banner explains that the physical file shadows this editor.

**llms.txt**
Short index describing the site's content for AI agents. Served at `/llms.txt`.

**llms-full.txt**
Long-form content version. Served at `/llms-full.txt`.

Content is saved per subsite, so `sitea.example.com/llms.txt` and `siteb.example.com/llms.txt` can differ.

The page also displays the current GA4 tracking status (Enabled or Disabled) so super admins can confirm at a glance whether request logging is active.

### Sitemap Block Generator

Navigate to **Network Admin: Settings: Sitemap Block Generator**. The page generates a `# Sitemaps` block containing one `Sitemap:` directive per public subsite on the network, with the network root site listed first and all child subsites sorted alphabetically by URL.

Example output:

```
# Sitemaps
Sitemap: https://example.com/sitemaps.xml
Sitemap: https://example.com/ar-impact/sitemaps.xml
Sitemap: https://example.com/clinical-laboratory/sitemaps.xml
Sitemap: https://example.com/coronavirus/sitemaps.xml
```

The sitemap path is configurable (default `/sitemaps.xml` to match SEOPress output; use `/sitemap.xml` for plugins that emit singular). Only subsites with Search Engine visibility enabled are included.

This is intended for networks that serve `robots.txt` as a static file on disk. Regenerate and copy-paste into the physical `robots.txt` whenever subsites are added or removed.

### GA4 request tracking (optional)

Navigate to **Network Admin: Settings: Site Files Tracking**. You'll see two fields:

**GA4 Measurement ID**
The `G-XXXXXXXXXX` identifier from your GA4 web stream. Found in GA4 under Admin, then Data Streams, then select the web stream.

**Measurement Protocol API Secret**
Generated inside the same GA4 data stream under Measurement Protocol API secrets. Click Create, then paste the secret *value* (not the secret name) into this field.

Leave both fields blank to disable tracking. Credentials are stored as site options (network-wide), so one configuration drives telemetry for every subsite.

When tracking is enabled, every request for `llms.txt` or `llms-full.txt` fires a `wayfinding_request` event to GA4 with the following parameters:

| Parameter | Description |
| --- | --- |
| `file_name` | Which file was requested (`llms.txt` or `llms-full.txt`). |
| `user_agent` | The requesting client's user agent string (truncated to 500 chars). |
| `requester_ip` | The requesting client's IP address. |
| `site_host` | The subsite hostname that served the file. |

To report on these parameters in GA4 Explorations, register them as custom dimensions under Admin, then Custom definitions. If the parameter names don't yet appear in the dropdown, type them in manually: GA4 only populates the dropdown from parameters it has already received. Realtime reports display event parameters without the custom-dimension step.

**`robots.txt` is not tracked** by the plugin. Physical `robots.txt` files served by the web server bypass WordPress entirely and cannot be observed through plugin code. For `robots.txt` analytics, use server access logs.

## Disallow guard

If the `robots.txt` you submit contains `Disallow: /` inside a `User-agent: *` block, the plugin refuses to save until you tick a confirmation box. This prevents a common accident where an admin blocks the entire site from all crawlers.

The parser is block-scoped: it only flags `Disallow: /` when it appears under `User-agent: *`. Rules targeted at specific bots like `SemrushBot` or `AhrefsBot` are not flagged, so you can block specific crawlers without triggering the guard. A subsequent `Allow: /` in the same `User-agent: *` block neutralizes the check.

## File structure

```
uamswp-per-site-wayfinding/
├── per-site-wayfinding.php   # Main plugin file
└── README.md
```

The plugin is a single file. All logic (filters, rewrite rules, template redirect, admin pages, the robots.txt parser, the sitemap block generator, and the GA4 Measurement Protocol client) lives in `per-site-wayfinding.php`.

## Technical notes

- `robots.txt` is served through WordPress's `robots_txt` filter when the editor applies to the current subsite (subdomain subsites always; subdirectory root only). The filter is a no-op on subdirectory child subsites to avoid confusing per-host HTTP semantics.
- `llms.txt` and `llms-full.txt` are served by registering rewrite rules, adding query vars, and short-circuiting `template_redirect` with a `Content-Type: text/plain; charset=utf-8` response.
- Per-site options used: `custom_robots_txt`, `custom_llms_txt`, `custom_llms_full_txt`. These are per-site options (not network options), so each subsite stores its own copy.
- Network-wide site options used: `ptxt_ga4_measurement_id`, `ptxt_ga4_api_secret`. One configuration applies across every subsite.
- The per-subsite admin page checks `is_super_admin()` and the save handler uses `manage_network_options` capability plus a nonce. The network tracking and sitemap generator pages use `manage_network_options` and their own nonces.
- GA4 events are sent via `wp_remote_post` with `blocking => false` and a 1-second timeout, so tracking cannot delay or break the response to crawlers. If credentials are missing, the tracking helper is a no-op.
- A synthetic `client_id` is generated per user agent (`bot.<md5 prefix>`), which causes GA4 to group hits from the same crawler together rather than treating every request as a new user.
- `register_activation_hook` fires per-subsite during network activation and calls `flush_rewrite_rules(false)` so the `/llms.txt` and `/llms-full.txt` routes resolve immediately on new subsites.
- Physical `robots.txt` detection uses `file_exists(ABSPATH . 'robots.txt')` at page render time; no HTTP calls are made.
- The sitemap block generator iterates `get_sites(['public' => 1])`, filters by each subsite's `blog_public` option, and sorts child subsites with `sort($lines, SORT_STRING | SORT_FLAG_CASE)` for deterministic output. The root site always sorts to the top regardless of alphabetical position.

## Tests

The unit suite runs on [Pest](https://pestphp.com) 5 with [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) mocking WordPress, so it needs no database and no WordPress checkout. It develops and tests on PHP 8.4 (`config.platform.php` in `composer.json` is `8.4.27`); the Composer packages are development dependencies only.

```bash
composer install
vendor/bin/pest
```

`composer test` runs the same suite. To run it through the shared local CI runner, which also lints the PHP and checks the GitHub download:

```bash
node .claude/skills/local-ci/local-ci.mjs
```

## Version

1.3.2

### Changelog

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

Internal UAMS plugin. Contact the UAMS Web team before redistributing.
