UAMSWP Per-Site Wayfinding is a WordPress Multisite plugin that serves its own `robots.txt`, `llms.txt` and `llms-full.txt` for each subsite, edited by super admins in the dashboard. It also builds a sitemap block for a `robots.txt` kept as a file on disk, and can log requests for the two `llms` files to Google Analytics 4.

This wiki holds the documentation: a walkthrough, task guides, the reference and troubleshooting. The [README](https://github.com/UAMS-Web/uamswp-per-site-wayfinding#readme) keeps the overview, the requirements, installation, the tests and the changelog.

## Getting started

- [Installation (README)](https://github.com/UAMS-Web/uamswp-per-site-wayfinding#installation): network-activate the plugin.
- [Setting up the files for a subsite](Setting-up-the-files-for-a-subsite.md): write a subsite's `llms.txt`, `llms-full.txt` and `robots.txt`, and check what is served.

## Guides

- [Keeping a physical robots.txt current](Keeping-a-physical-robots-txt-current.md): generate the sitemap block for a `robots.txt` the web server serves from disk.
- [Tracking llms.txt requests in GA4](Tracking-llms-txt-requests-in-GA4.md): send an event to Google Analytics 4 for each request.

## Reference

- [Where robots.txt can be edited](Where-robots-txt-can-be-edited.md): subdomain and subdirectory networks, and a physical file at the web root.
- [Admin pages and settings](Admin-pages-and-settings.md): every page, field, option and capability.
- [Served files](Served-files.md): the URLs, what each one returns, the Disallow guard and the tracking event.

## Troubleshooting

- [Troubleshooting](Troubleshooting.md): one entry per symptom, with its cause and fix.

## Project

- [Project](Project.md): where the plugin is developed, how it is tested and how to report a problem.
