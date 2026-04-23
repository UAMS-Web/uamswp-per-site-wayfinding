# Per-Site robots.txt, llms.txt, llms-full.txt

A WordPress Multisite plugin that serves unique `robots.txt`, `llms.txt`, and `llms-full.txt` files for every subsite in the network. Each subsite maintains its own content through a simple admin UI, with a safety guard against accidentally blocking all crawlers.

Built for the UAMS multisite federation but written to work on any WordPress Multisite install.

## What it does

- Overrides WordPress's virtual `robots.txt` on a per-subsite basis.
- Registers virtual routes for `/llms.txt` and `/llms-full.txt` on every subsite.
- Stores content in per-site options so each subsite in the network has its own three files.
- Prevents WordPress's canonical redirect from appending a trailing slash to `/llms.txt` and `/llms-full.txt` (which would otherwise 301 to `/llms.txt/`).
- Adds a **Settings: Site Files** admin page, restricted to super admins.
- Guards against accidentally blocking all crawlers by requiring explicit confirmation before saving a `User-agent: *` block that contains `Disallow: /`.

## Requirements

- WordPress Multisite (network activation is required; the plugin declares `Network: true`).
- PHP 7.4 or later.
- Super admin access to edit site files.

## Installation

1. Copy the plugin folder into `wp-content/plugins/` on the multisite install.
2. Go to **Network Admin: Plugins** and network-activate **Per-Site robots.txt, llms.txt, llms-full.txt**.
3. On each subsite, go to **Settings: Site Files** to edit that subsite's content.
4. After first activation, visit **Network Admin: Settings: Permalinks** or save permalinks on each subsite once, so the rewrite rules for `/llms.txt` and `/llms-full.txt` flush.

## How to use

Navigate to **Settings: Site Files** on any subsite. You'll see three textareas:

**robots.txt**
Leave blank to fall back to the WordPress default. The plugin's default includes an `Allow: /` rule for all user agents and a `Sitemap:` directive pointing at the subsite's own `/sitemap.xml`.

**llms.txt**
Short index describing the site's content for AI agents. Served at `/llms.txt`.

**llms-full.txt**
Long-form content version. Served at `/llms-full.txt`.

Content is saved per subsite, so `sitea.example.com/robots.txt` and `siteb.example.com/robots.txt` can differ.

## Disallow guard

If the `robots.txt` you submit contains `Disallow: /` inside a `User-agent: *` block, the plugin refuses to save until you tick a confirmation box. This prevents a common accident where an admin blocks the entire site from all crawlers.

The parser is block-scoped: it only flags `Disallow: /` when it appears under `User-agent: *`. Rules targeted at specific bots like `SemrushBot` or `AhrefsBot` are not flagged, so you can block specific crawlers without triggering the guard. A subsequent `Allow: /` in the same `User-agent: *` block neutralizes the check.

## File structure

```
per-site-txt-files/
├── per-site-txt-files.php   # Main plugin file
└── README.md
```

The plugin is a single file. All logic (filters, rewrite rules, template redirect, admin page, and the robots.txt parser) lives in `per-site-txt-files.php`.

## Technical notes

- `robots.txt` is served through WordPress's `robots_txt` filter (the virtual file WordPress already generates).
- `llms.txt` and `llms-full.txt` are served by registering rewrite rules, adding query vars, and short-circuiting `template_redirect` with a `Content-Type: text/plain; charset=utf-8` response.
- Options used: `custom_robots_txt`, `custom_llms_txt`, `custom_llms_full_txt`. These are per-site options (not network options), so each subsite stores its own copy.
- The admin page checks `is_super_admin()` and the save handler uses `manage_network_options` capability plus a nonce.

## Version

1.0.0

## License

Internal UAMS plugin. Contact the UAMS Web team before redistributing.
