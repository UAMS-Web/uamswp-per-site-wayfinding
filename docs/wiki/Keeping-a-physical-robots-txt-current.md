Written against the unreleased `main` branch, after v1.3.3.

Some hosts serve `robots.txt` as a file on disk at the web root, maintained by hand. The web server answers with that file before WordPress runs, so nothing saved in the plugin's robots.txt editor reaches crawlers. What the plugin can do for that file is generate its sitemap section: one `Sitemap:` line per public subsite, in a stable order, ready to paste.

1. **Go to Network Admin, then Settings, then Sitemap Block Generator.**
2. **Check the Sitemap path.** The default is `/sitemaps.xml`, which matches SEOPress. If the network's sitemap plugin serves `/sitemap.xml`, enter that and select **Regenerate with this path**.
3. **Copy the Generated block.** Click inside the box to select all of it. It looks like this:

   ```text
   # Sitemaps
   Sitemap: https://example.com/sitemaps.xml
   Sitemap: https://example.com/site-a/sitemaps.xml
   Sitemap: https://example.com/site-b/sitemaps.xml
   ```

   The network root site is always first; the other subsites follow in alphabetical order of their URL, ignoring case. Only subsites that are public and have search engine visibility turned on are listed.
4. **Replace the `# Sitemaps` section of the physical `robots.txt`** with the copied block, and deploy the file the way the host's other web-root files are deployed.
5. **Repeat whenever a subsite is added, removed, or has its search engine visibility changed.** The block is not updated on its own.

**Note:** while the physical file exists, the Site Files page shows a warning naming the file's path on every site where the robots.txt editor appears. That warning is expected for this setup.

## Reference

- [Admin pages and settings](Admin-pages-and-settings.md#sitemap-block-generator): the generator's field and what it reads.
- [Where robots.txt can be edited](Where-robots-txt-can-be-edited.md#a-physical-robotstxt-at-the-web-root): how the physical file shadows the editor.
- [Troubleshooting](Troubleshooting.md#a-subsite-is-missing-from-the-generated-block): a subsite missing from the block.
