Written against the unreleased `main` branch, after v1.3.3.

What each URL returns, how `robots.txt` content is checked before it is saved, and what the plugin sends to GA4.

## The URLs

| URL | Served by | Returns |
| --- | --- | --- |
| `/robots.txt` | WordPress's `robots_txt` filter | The subsite's saved robots.txt content where its editor applies; otherwise WordPress's own output. A physical file at the web root is served by the web server instead. |
| `/llms.txt` | A rewrite rule (`^llms\.txt$`) | The saved llms.txt content as `text/plain; charset=utf-8` |
| `/llms-full.txt` | A rewrite rule (`^llms-full\.txt$`) | The saved llms-full.txt content as `text/plain; charset=utf-8` |

On a subdirectory network the two `llms` URLs sit under each subsite's path, for example `https://example.com/site-a/llms.txt`.

**Before any content is saved,** `/llms.txt` returns `# llms.txt not yet configured for <home URL>` and `/llms-full.txt` returns `# llms-full.txt not yet configured for <home URL>`. Once the form has been saved, an empty field is served as an empty file.

**No trailing slash.** WordPress would normally redirect `/llms.txt` to `/llms.txt/`. The plugin turns that redirect off for the two `llms` paths, including under a subdirectory subsite's path.

**Rewrite rules.** The two rules are registered on every request and flushed when the plugin is activated. WordPress runs the activation step once, on the site where the plugin is activated, so another subsite's stored rules can lack them until they are flushed there: [Troubleshooting](Troubleshooting.md#llmstxt-answers-404).

## The Disallow guard

Saving robots.txt content that blocks every crawler from the whole site needs a ticked confirmation box. The check:

- reads the content one `User-agent:` group at a time, and looks only at groups for `User-agent: *`;
- flags a line that is exactly `Disallow: /` (any letter case, spaces allowed);
- is cleared by a line that is exactly `Allow: /` in a `User-agent: *` group;
- ignores comments (everything after `#`) and blank lines, and accepts any line ending.

| Content | Refused until confirmed? |
| --- | --- |
| `User-agent: *` then `Disallow: /` | Yes |
| `User-agent: *` then `Disallow: / # everything` | Yes |
| `User-agent: *` then `Disallow: /` then `Allow: /` | No |
| `User-agent: BadBot` then `Disallow: /` | No |
| `User-agent: *` then `Disallow: /private/` | No |

The guard runs only where the robots.txt editor appears.

## The tracking event

When both tracking values are set ([Admin pages and settings](Admin-pages-and-settings.md#site-files-tracking)), each request for `/llms.txt` or `/llms-full.txt` sends one event to the GA4 Measurement Protocol endpoint (`https://www.google-analytics.com/mp/collect`).

| Field | Value |
| --- | --- |
| Event name | `wayfinding_request` |
| `file_name` | `llms.txt` or `llms-full.txt` |
| `user_agent` | The request's user agent, cut to 500 characters; `unknown` when there is none |
| `requester_ip` | The request's remote address; `0.0.0.0` when there is none |
| `site_host` | The host of the subsite that served the file |
| `engagement_time_msec` | `1` |
| `client_id` | `bot.` followed by the first 16 characters of the MD5 of the user agent, so requests from one agent are grouped together |

The request is sent without waiting for an answer (non-blocking, 1-second timeout), so tracking cannot slow or break the file's response. `robots.txt` requests are not tracked.
