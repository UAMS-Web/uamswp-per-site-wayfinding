Written against the unreleased `main` branch, after v1.3.3.

At the end of this walkthrough one subsite serves its own `llms.txt` and `llms-full.txt`, and, where the subsite owns its host, its own `robots.txt`. It assumes the plugin is network-activated ([Installation (README)](https://github.com/UAMS-Web/uamswp-per-site-wayfinding#installation)) and that you are a super admin.

1. **Open the subsite's dashboard and go to Settings, then Site Files.** The page lists the URLs it serves, for example `https://site-a.example.com/llms.txt`, and shows whether GA4 tracking is Enabled or Disabled.

   **Note:** the menu item appears only for super admins. A subsite administrator who is not a super admin does not see it.

2. **Read the robots.txt section first.** What it shows depends on the network:

   - On a subdomain network, and on the network root site of a subdirectory network, it is an editor. Before anything is saved it is filled with the plugin's starting content: `User-agent: *`, `Allow: /` and a `Sitemap:` line for the subsite's `/sitemap.xml`.
   - On a child subsite of a subdirectory network it is a notice, **Host-scoped.**, with a link to the editor on the network root site. That subsite cannot have a `robots.txt` of its own; [Where robots.txt can be edited](Where-robots-txt-can-be-edited.md) explains why.
   - A yellow notice, **A physical `robots.txt` file exists at the web root.**, means the web server serves a file from disk instead of what you save here. Follow [Keeping a physical robots.txt current](Keeping-a-physical-robots-txt-current.md) instead of editing this field.

3. **Write the llms.txt field:** a short index of the subsite's content for AI agents, in plain text or Markdown.

4. **Write the llms-full.txt field:** the long-form version of the same content.

5. **Select Save Site Files.** A green **Settings saved.** notice appears.

   **Warning:** if the robots.txt field blocks every crawler (`Disallow: /` under `User-agent: *`), the save is refused with a red **Warning:** notice and a confirmation box. Tick the box only if blocking the whole subsite is intended. [Served files](Served-files.md#the-disallow-guard) describes exactly what the guard checks.

6. **Open the URLs in a browser:** `https://site-a.example.com/llms.txt` and `https://site-a.example.com/llms-full.txt` show the text you saved, as `text/plain`. If either answers `404`, see [Troubleshooting](Troubleshooting.md#llmstxt-answers-404).

## Where to go next

- [Tracking llms.txt requests in GA4](Tracking-llms-txt-requests-in-GA4.md), to see which agents fetch the files.
- [Keeping a physical robots.txt current](Keeping-a-physical-robots-txt-current.md), for a network whose `robots.txt` is a file on disk.
- [Admin pages and settings](Admin-pages-and-settings.md), for every field and the option each one saves to.
