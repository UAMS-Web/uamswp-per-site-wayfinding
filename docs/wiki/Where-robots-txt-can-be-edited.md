Written against the unreleased `main` branch, after v1.3.3.

`robots.txt` belongs to a host, not to a path: a crawler reads exactly one, at the root of the host (`https://example.com/robots.txt`). Whether a subsite can have its own therefore depends on whether it has its own host. `llms.txt` and `llms-full.txt` have no such rule, so every subsite gets those two editors.

## By kind of network

| Network | Example subsite | robots.txt editor | llms.txt and llms-full.txt editors |
| --- | --- | --- | --- |
| Subdomain | `https://site-a.example.com/` | On every subsite | On every subsite |
| Subdirectory, network root site | `https://example.com/` | Yes, and it serves the whole host | Yes |
| Subdirectory, child subsite | `https://example.com/site-a/` | No: a **Host-scoped.** notice links to the root site's editor | Yes |
| Single site (not a network) | `https://example.com/` | Yes | Yes |

On a child subsite of a subdirectory network the plugin also leaves WordPress's own `robots_txt` output alone, and ignores any robots.txt content submitted from that subsite's form.

## A physical robots.txt at the web root

When a file named `robots.txt` exists in the WordPress installation's root folder, the web server serves it directly and WordPress never runs for that URL. Content saved in the robots.txt editor is then stored but never served.

The plugin checks for the file each time the Site Files page is shown, and on every site where the robots.txt editor appears it shows a warning: **A physical `robots.txt` file exists at the web root.**, with the file's path. It makes no HTTP request to check.

Two ways forward:

- **Keep the file**, and keep its sitemap section current with the [Sitemap Block Generator](Keeping-a-physical-robots-txt-current.md). The editor is then informational only.
- **Delete the file**, so the editor's content is served. Deleting a file on the server is outside the plugin.

## Example

On a subdirectory network at `https://example.com/` whose subsites are `/site-a/` and `/site-b/`:

- `https://example.com/robots.txt` is the network root site's editor content (or the physical file, if one exists).
- `https://example.com/site-a/robots.txt` is not a `robots.txt` any crawler reads.
- `https://example.com/site-a/llms.txt` and `https://example.com/site-b/llms.txt` each serve their own subsite's content.
