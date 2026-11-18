Written against the unreleased `main` branch, after v1.3.3.

## Where it is developed

The plugin is developed in [UAMS-Web/uamswp-per-site-wayfinding](https://github.com/UAMS-Web/uamswp-per-site-wayfinding) by UAMS Web Services. All of it is one file, `uamswp-per-site-wayfinding.php`; the GitHub download holds that file and the license.

Changes go through a pull request to `main`, as [CONTRIBUTING.md](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/blob/main/CONTRIBUTING.md) describes. Releases are listed on the [Releases](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/releases) page, and the changelog is in the [README](https://github.com/UAMS-Web/uamswp-per-site-wayfinding#changelog).

## How it is tested

The unit suite runs on Pest with Brain Monkey standing in for WordPress, so it needs no database and no WordPress install. [Tests (README)](https://github.com/UAMS-Web/uamswp-per-site-wayfinding#tests) gives the commands. On every pull request, CI lints the PHP on the supported floor, runs the suite, PHPStan, Rector and Pint, and checks that the download holds only the plugin.

## Reporting a problem

Open an issue on the [issue tracker](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/issues). Say which page or URL you were using, what you expected and what happened, and which kind of network it is (subdomain or subdirectory). Report a security problem privately through the [security advisory form](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/security/advisories/new), not in an issue.

## These pages

The wiki is published from the [`docs/wiki/`](https://github.com/UAMS-Web/uamswp-per-site-wayfinding/tree/main/docs/wiki) folder each time a release is published. Edit the pages there through a pull request; an edit made in the wiki itself is overwritten by the next publish.
