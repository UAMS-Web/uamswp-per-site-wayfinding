# Per-Site robots.txt, llms.txt, llms-full.txt

A WordPress Multisite plugin that serves unique `robots.txt`, `llms.txt`, and `llms-full.txt` files for every subsite in the network. Each subsite maintains its own content through a simple admin UI, with a safety guard against accidentally blocking all crawlers. Optional GA4 Measurement Protocol logging tracks every request to these files so you can see which AI agents are fetching them.

Built for the UAMS multisite federation but written to work on any WordPress Multisite install.

## What it does

- Overrides WordPress's virtual `robots.txt` on a per-subsite basis.
- Registers virtual routes for `/llms.txt` and `/llms-full.txt` on every subsite.
- Stores content in per-site options so each subsite in the network has its own three files.
- Prevents WordPress's canonical redirect from appending a trailing slash to `/llms.txt` and `/llms-full.txt` (which would otherwise 301 to `/llms.txt/`).
- Adds a **Settings: Site Files** admin page, restricted to super admins.
- Guards against accidentally blocking all crawlers by requiring explicit confirmation before saving a `User-agent: *` block that contains `Disallow: /`.
- Optionally logs every request for `robots.txt`, `llms.txt`, and `llms-full.txt` to GA4 via the Measurement Protocol, with the requested file, user agent, requester IP, and site host recorded as event parameters.

## Requirements

- WordPress Multisite (network activation is required; the plugin declares `Network: true`).
- PHP 7.4 or later.
- Super admin access to edit site files.
- A GA4 property and Measurement Protocol API secret if you want to enable request tracking (optional).

## Installation

1. Copy the plugin folder into `wp-content/plugins/` on the multisite install.
2. Go to **Network Admin: Plugins** and network-activate **Per-Site robots.txt, llms.txt, llms-full.txt**.
3. On each subsite, go to **Settings: Site Files** to edit that subsite's content.
4. After first activation, visit **Network Admin: Settings: Permalinks** or save permalinks on each subsite once, so the rewrite rules for `/llms.txt` and `/llms-full.txt` flush.
5. (Optional) Configure GA4 tracking under **Network Admin: Settings: Site Files Tracking**.

## How to use

### Per-subsite content

Navigate to **Settings: Site Files** on any subsite. You'll see three textareas:

**robots.txt**
Leave blank to fall back to the WordPress default. The plugin's default includes an `Allow: /` rule for all user agents and a `Sitemap:` directive pointing at the subsite's own `/sitemap.xml`.

**llms.txt**
Short index describing the site's content for AI agents. Served at `/llms.txt`.

**llms-full.txt**
Long-form content version. Served at `/llms-full.txt`.

Content is saved per subsite, so `sitea.example.com/robots.txt` and `siteb.example.com/robots.txt` can differ.

The page also displays the current GA4 tracking status (Enabled or Disabled) so super admins can confirm at a glance whether request logging is active.

### GA4 request tracking (optional)

Navigate to **Network Admin: Settings: Site Files Tracking**. You'll see two fields:

**GA4 Measurement ID**
The `G-XXXXXXXXXX` identifier from your GA4 web stream. Found in GA4 under Admin, then Data Streams, then select the web stream.

**Measurement Protocol API Secret**
Generated inside the same GA4 data stream under Measurement Protocol API secrets. Click Create, then paste the secret *value* (not the secret name) into this field.

Leave both fields blank to disable tracking. Credentials are stored as site options (network-wide), so one configuration drives telemetry for every subsite.

When tracking is enabled, every request for `robots.txt`, `llms.txt`, or `llms-full.txt` fires a `wayfinding_request` event to GA4 with the following parameters:

| Parameter | Description |
| --- | --- |
| `file_name` | Which file was requested (`robots.txt`, `llms.txt`, or `llms-full.txt`). |
| `user_agent` | The requesting client's user agent string (truncated to 500 chars). |
| `requester_ip` | The requesting client's IP address. |
| `site_host` | The subsite hostname that served the file. |

To report on these parameters in GA4 Explorations, register them as custom dimensions under Admin, then Custom definitions. Realtime reports display them without that step.

## Disallow guard

If the `robots.txt` you submit contains `Disallow: /` inside a `User-agent: *` block, the plugin refuses to save until you tick a confirmation box. This prevents a common accident where an admin blocks the entire site from all crawlers.

The parser is block-scoped: it only flags `Disallow: /` when it appears under `User-agent: *`. Rules targeted at specific bots like `SemrushBot` or `AhrefsBot` are not flagged, so you can block specific crawlers without triggering the guard. A subsequent `Allow: /` in the same `User-agent: *` block neutralizes the check.

## File structure

```
per-site-txt-files/
├── per-site-txt-files.php   # Main plugin file
└── README.md
```

The plugin is a single file. All logic (filters, rewrite rules, template redirect, admin pages, the robots.txt parser, and the GA4 Measurement Protocol client) lives in `per-site-txt-files.php`.

## Technical notes

- `robots.txt` is served through WordPress's `robots_txt` filter (the virtual file WordPress already generates).
- `llms.txt` and `llms-full.txt` are served by registering rewrite rules, adding query vars, and short-circuiting `template_redirect` with a `Content-Type: text/plain; charset=utf-8` response.
- Per-site options used: `custom_robots_txt`, `custom_llms_txt`, `custom_llms_full_txt`. These are per-site options (not network options), so each subsite stores its own copy.
- Network-wide site options used: `ptxt_ga4_measurement_id`, `ptxt_ga4_api_secret`. One configuration applies across every subsite.
- The per-subsite admin page checks `is_super_admin()` and the save handler uses `manage_network_options` capability plus a nonce. The network tracking page uses `manage_network_options` and its own nonce.
- GA4 events are sent via `wp_remote_post` with `blocking => false` and a 1-second timeout, so tracking cannot delay or break the response to crawlers. If credentials are missing, the tracking helper is a no-op.
- A synthetic `client_id` is generated per user agent (`bot.<md5 prefix>`), which causes GA4 to group hits from the same crawler together rather than treating every request as a new user.

## Version

1.1.0

### Changelog

- **1.1.0** Added optional GA4 Measurement Protocol tracking with a network-level settings page. Per-subsite page now displays tracking status.
- **1.0.0** Initial production build: per-subsite robots.txt, llms.txt, llms-full.txt with Disallow guard.

## License

Internal UAMS plugin. Contact the UAMS Web team before redistributing.
