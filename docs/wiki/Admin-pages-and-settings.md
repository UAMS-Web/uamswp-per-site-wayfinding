Written against the unreleased `main` branch, after v1.3.3.

The plugin adds one page to each subsite's dashboard and two to the network admin. This page lists each one, who can open it, every field, and the option the field saves to.

## Site Files

**Where:** each subsite's dashboard, Settings, then Site Files (`options-general.php?page=site-txt-files`).

**Who:** super admins only. The menu item is not added for anyone else, and the page refuses with **You do not have permission to access this page.** The form is protected by a nonce (`save_site_txt_files`).

| Field | Shown | Saves to (per-subsite option) | Before anything is saved |
| --- | --- | --- | --- |
| robots.txt | Where the subsite owns its host ([Where robots.txt can be edited](Where-robots-txt-can-be-edited.md)) | `custom_robots_txt` | Filled with `User-agent: *`, `Allow: /` and `Sitemap: <home URL>/sitemap.xml` |
| llms.txt | Every subsite | `custom_llms_txt` | Empty |
| llms-full.txt | Every subsite | `custom_llms_full_txt` | Empty |

- **Saving** writes all the fields shown at once. The robots.txt field is saved only where its editor appears.
- **An empty robots.txt field** means WordPress's own `robots.txt` is served, not the plugin's starting content.
- **The Disallow guard** can refuse a save until a confirmation box is ticked: [Served files](Served-files.md#the-disallow-guard).
- **The page also shows** the URLs it serves, whether GA4 tracking is Enabled or Disabled, and, where it applies, the physical-file warning.

## Site Files Tracking

**Where:** Network Admin, Settings, then Site Files Tracking (`settings.php?page=site-txt-files-tracking`).

**Who:** users with the `manage_network_options` capability. The form is protected by a nonce (`save_site_txt_tracking`).

| Field | Saves to (network option) | Format |
| --- | --- | --- |
| GA4 Measurement ID | `ptxt_ga4_measurement_id` | `G-XXXXXXXXXX` |
| Measurement Protocol API Secret | `ptxt_ga4_api_secret` | The secret's value from GA4 |

- Both values are trimmed and passed through `sanitize_text_field()` before they are saved.
- Tracking runs only when both are set. Empty both to turn it off.
- One configuration covers every subsite in the network.

## Sitemap Block Generator

**Where:** Network Admin, Settings, then Sitemap Block Generator (`settings.php?page=site-txt-files-sitemaps`).

**Who:** users with the `manage_network_options` capability. It saves nothing.

| Field | Default | Effect |
| --- | --- | --- |
| Sitemap path | `/sitemaps.xml` | The path added to each subsite's home URL. Sent as the `path` query parameter; an empty value falls back to the default, and a missing leading slash is added. |

The generated block:

- starts with `# Sitemaps`, then one `Sitemap: <home URL><path>` line per subsite;
- lists the network root site first and the rest in alphabetical order of their line, ignoring case;
- includes a subsite only when it is public on the network and its search engine visibility setting is on;
- reads at most 500 public subsites;
- shows **No public subsites found on this network.** when there is nothing to list.

The page exists only in Network Admin, so a single site that is not a network has no Sitemap Block Generator.

## Options at a glance

| Option | Scope | Written by |
| --- | --- | --- |
| `custom_robots_txt` | Each subsite | Site Files |
| `custom_llms_txt` | Each subsite | Site Files |
| `custom_llms_full_txt` | Each subsite | Site Files |
| `ptxt_ga4_measurement_id` | Network | Site Files Tracking |
| `ptxt_ga4_api_secret` | Network | Site Files Tracking |
