Written against the unreleased `main` branch, after v1.3.3.

One entry per symptom, as you meet it: what you see, its cause, and the fix.

- [llms.txt answers 404](#llmstxt-answers-404)
- [llms.txt shows "not yet configured"](#llmstxt-shows-not-yet-configured)
- [Saved robots.txt content is not what crawlers get](#saved-robotstxt-content-is-not-what-crawlers-get)
- [The save is refused with a Disallow warning](#the-save-is-refused-with-a-disallow-warning)
- [There is no Site Files menu item](#there-is-no-site-files-menu-item)
- [No wayfinding_request events in GA4](#no-wayfinding_request-events-in-ga4)
- [A subsite is missing from the generated block](#a-subsite-is-missing-from-the-generated-block)
- [Fixed](#fixed)

## llms.txt answers 404

`https://site-a.example.com/llms.txt` (or `/llms-full.txt`) answers `404`, or shows the theme's not-found page.

**Cause:** that subsite's stored rewrite rules do not include the plugin's two rules. The plugin flushes rewrite rules when it is activated, and WordPress runs that step once, on the site where the plugin is activated, so other subsites keep the rules they had until they are flushed.

**Fix:** on the affected subsite, go to Settings, then Permalinks, and select Save Changes without changing anything. That flushes the subsite's rewrite rules.

## llms.txt shows "not yet configured"

The file's whole content is:

```text
# llms.txt not yet configured for https://site-a.example.com
```

**Cause:** nothing has been saved on that subsite's Site Files page.

**Fix:** write the content on Settings, then Site Files, and save: [Setting up the files for a subsite](Setting-up-the-files-for-a-subsite.md).

## Saved robots.txt content is not what crawlers get

`/robots.txt` does not show what was saved on the Site Files page.

**Causes and fixes:**

- **A physical `robots.txt` exists at the web root.** The Site Files page says so in a yellow notice. The web server serves the file; keep it current with [Keeping a physical robots.txt current](Keeping-a-physical-robots-txt-current.md), or remove it so the editor's content is served.
- **The subsite is a child of a subdirectory network.** It has no `robots.txt` of its own; edit the network root site's: [Where robots.txt can be edited](Where-robots-txt-can-be-edited.md).
- **The field was saved empty.** An empty field means WordPress's own `robots.txt` is served. Write the content you want and save.
- **A cache answered.** A page cache or CDN in front of the site can keep serving the previous file. Purge it for `/robots.txt`.

## The save is refused with a Disallow warning

Saving shows:

```text
Warning: the robots.txt content you submitted contains Disallow: /, which blocks all crawlers from the entire site.
```

**Cause:** a `User-agent: *` group contains `Disallow: /` and no `Allow: /`. The guard asks before blocking every crawler.

**Fix:** if blocking the whole subsite is intended, tick **Yes, I intend to block all crawlers on** the subsite, and save again. Otherwise change the rule; to block one crawler, put `Disallow: /` under that crawler's own `User-agent:` line. [Served files](Served-files.md#the-disallow-guard) lists what the guard flags.

## There is no Site Files menu item

A subsite's Settings menu has no Site Files entry.

**Cause:** the page is for super admins only.

**Fix:** sign in as a super admin, or ask one to make the change.

## No wayfinding_request events in GA4

Tracking is set up, but GA4 shows no `wayfinding_request` events.

**Causes and fixes:**

- **A value is missing.** The Site Files page reads **GA4 tracking: Disabled** unless both the Measurement ID and the API secret are saved. Fill both on Network Admin, Settings, then Site Files Tracking.
- **The secret's name was pasted instead of its value.** Copy the value from GA4's Measurement Protocol API secrets list and save again.
- **The wrong report was checked.** Look at Realtime first; Explorations show the parameters only after they are registered as custom dimensions.
- **The request never reached WordPress.** A cached copy of `/llms.txt` served by a page cache or CDN does not run the plugin, so it sends no event.

The plugin does not wait for GA4's answer, so a rejected event leaves no error in WordPress.

## A subsite is missing from the generated block

The Sitemap Block Generator's output has no line for a subsite you expected.

**Causes and fixes:**

- **The subsite's search engine visibility is off** (Settings, then Reading, *Discourage search engines from indexing this site*), or the subsite is not public on the network. Only public, visible subsites are listed.
- **The network has more than 500 public subsites.** The generator reads at most 500.

## Fixed

Problems fixed in a release stay here, with the release that fixed them, instead of being deleted.

- **The trailing-slash filter did not handle a request with no path.** Fixed in v1.3.2: for such a request it now returns WordPress's redirect unchanged.
