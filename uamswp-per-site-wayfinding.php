<?php
/**
 * Plugin Name: UAMSWP Per-Site robots.txt, llms.txt, llms-full.txt
 * Plugin URI:  https://github.com/UAMS-Web/uamswp-per-site-wayfinding
 * Description: Serves unique robots.txt, llms.txt, and llms-full.txt per multisite subsite. Multisite-type-aware: per-subsite robots.txt editor appears on all subsites of subdomain multisites, but only on the network root site of subdirectory multisites (since HTTP only honors one robots.txt per host). Includes a multisite sitemap-block generator for copy-paste into physical robots.txt files, and optional GA4 Measurement Protocol tracking for llms.txt / llms-full.txt requests.
 * Version:     1.3.3
 * Requires PHP: 8.2
 * Author:      UAMS Web Services
 * Author URI:  https://communications.uams.edu/web/
 * Network:     true
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Copyright:   2026 UAMS Web Services
 *
 * Design notes:
 *   - robots.txt is host-scoped per HTTP spec. On subdirectory multisites,
 *     the network root site owns robots.txt for the whole host; child
 *     subsites cannot have their own robots.txt that crawlers will honor.
 *   - llms.txt and llms-full.txt are not host-scoped by any spec. The
 *     plugin serves per-subsite versions via rewrite rules, which work
 *     correctly on both subdomain and subdirectory multisites once
 *     rewrites are flushed.
 *   - If a physical robots.txt file exists at the web root, the web
 *     server serves it in preference to anything WordPress produces.
 *     The plugin detects this and warns super admins on the root-site
 *     editor.
 *   - GA4 tracking covers llms.txt and llms-full.txt only. Physical
 *     robots.txt hits bypass WordPress entirely and must be observed
 *     through server access logs.
 */
if (! defined('ABSPATH')) {
    // Ends the process, so only a separate process can reach it (tests/Unit/ProcessExitTest.php).
    exit; // @codeCoverageIgnore
}

/**
 * Default robots.txt content for a newly configured site.
 */
function ptxt_default_robots(): string
{
    return "User-agent: *\nAllow: /\n\nSitemap: ".home_url('/sitemap.xml')."\n";
}

/**
 * Returns true if the current subsite should expose a per-site robots.txt editor.
 *
 * - Subdomain multisite: every subsite is its own host, so every subsite gets the editor.
 * - Subdirectory multisite: only the network root site owns the host-level robots.txt.
 *   Child subsites share the host and cannot have their own robots.txt per HTTP spec.
 *
 * @return bool
 */
function ptxt_robots_editor_applies()
{
    if (! is_multisite()) {
        return true;
    }
    if (is_subdomain_install()) {
        return true;
    }

    return is_main_site();
}

/**
 * Returns true if a physical robots.txt file exists at the web root.
 *
 * When true, the web server serves that file in preference to any virtual
 * content WordPress produces. The plugin's robots.txt editor on the root
 * site is effectively shadowed until the physical file is removed.
 */
function ptxt_physical_robots_exists(): bool
{
    return defined('ABSPATH') && file_exists(ABSPATH.'robots.txt');
}

/**
 * A WordPress value as a string.
 *
 * Options, request values and server values come back as mixed. A scalar is
 * read as its string form (an integer option reads as "1"); anything else, an
 * array or null, reads as the default.
 *
 * @param  mixed  $value  The value WordPress returned.
 * @param  string  $default  What a value that is not a scalar reads as.
 */
function ptxt_scalar_string(mixed $value, string $default): string
{
    return is_scalar($value) ? (string) $value : $default;
}

/**
 * A site option as a string, falling back to the default when it is unset or not a scalar.
 */
function ptxt_option_string(string $name, string $default): string
{
    return ptxt_scalar_string(get_option($name, $default), $default);
}

/**
 * A network option as a string, empty when it is unset or not a scalar.
 */
function ptxt_site_option_string(string $name): string
{
    return ptxt_scalar_string(get_site_option($name, ''), '');
}

/**
 * A request value with WordPress' added slashes removed, empty when it is not a string.
 *
 * @param  mixed  $value  An element of $_POST or $_GET, or null when it is not set.
 */
function ptxt_unslashed_string(mixed $value): string
{
    return is_string($value) ? wp_unslash($value) : '';
}

/**
 * Returns true only if the User-agent: * group contains a Disallow: / rule
 * that is NOT overridden by a subsequent Allow: / in the same group.
 *
 * Parses robots.txt by User-agent block so that Disallow: / rules targeted
 * at specific bots (SemrushBot, AhrefsBot, etc.) do not trigger the guard.
 * Only a blanket block of all crawlers does.
 *
 * @param  mixed  $content  The saved robots.txt; anything that is not a non-empty string does not block.
 */
function ptxt_wildcard_disallows_root($content): bool
{
    if (! is_string($content) || $content === '') {
        return false;
    }

    // Split on CRLF, CR or LF; explode() always returns a list, where preg_split() can return false.
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));

    $in_wildcard_group = false;
    $wildcard_disallow_root = false;
    $wildcard_allow_root = false;

    foreach ($lines as $line) {
        // Drop a comment; strpos() cannot fail the way preg_replace() can, which returns null on error.
        $comment_start = strpos($line, '#');
        $line = trim($comment_start === false ? $line : substr($line, 0, $comment_start));
        if ($line === '') {
            continue;
        }

        if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
            $agent = strtolower(trim($m[1]));
            $in_wildcard_group = ($agent === '*');

            continue;
        }

        if (! $in_wildcard_group) {
            continue;
        }

        if (preg_match('/^Disallow:\s*\/\s*$/i', $line)) {
            $wildcard_disallow_root = true;
        } elseif (preg_match('/^Allow:\s*\/\s*$/i', $line)) {
            $wildcard_allow_root = true;
        }
    }

    return $wildcard_disallow_root && ! $wildcard_allow_root;
}

/**
 * Build a "# Sitemaps" block listing one Sitemap: directive per public subsite.
 *
 * Used by the network-admin sitemap generator page. Super admins copy the
 * output and paste it into the physical robots.txt for the host.
 *
 * @param  string  $sitemap_path  Path appended to each subsite's home URL. Default '/sitemaps.xml'.
 */
function ptxt_build_multisite_sitemap_block($sitemap_path = '/sitemaps.xml'): string
{
    if (! function_exists('get_sites') || ! is_multisite()) {
        return '';
    }

    $sites = get_sites([
        'public' => 1,
        'number' => 500,
    ]);

    if (empty($sites)) {
        return '';
    }

    // Normalize path to guarantee exactly one leading slash.
    $sitemap_path = '/'.ltrim($sitemap_path, '/');

    // Collect the root site's sitemap line separately so it always sorts
    // to the top of the block, then collect child subsite lines into a
    // list that will be sorted alphabetically by full URL.
    $root_line = null;
    $child_lines = [];
    $main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 1;

    foreach ($sites as $site) {
        // WP_Site::$blog_id is a numeric string; the blog functions take an integer.
        $blog_id = (int) $site->blog_id;
        switch_to_blog($blog_id);
        if (ptxt_option_string('blog_public', '') === '1') {
            $home = rtrim(get_home_url($blog_id), '/');
            $line = "Sitemap: {$home}{$sitemap_path}";

            if ($blog_id === (int) $main_site_id) {
                $root_line = $line;
            } else {
                $child_lines[] = $line;
            }
        }
        restore_current_blog();
    }

    // Case-insensitive alphabetical sort for stable, human-friendly output.
    sort($child_lines, SORT_STRING | SORT_FLAG_CASE);

    $lines = ['# Sitemaps'];
    if ($root_line !== null) {
        $lines[] = $root_line;
    }
    foreach ($child_lines as $line) {
        $lines[] = $line;
    }

    return implode("\n", $lines)."\n";
}

/**
 * Fire a GA4 Measurement Protocol event for a wayfinding file request.
 *
 * Fire-and-forget: short timeout, non-blocking. Silently no-ops when
 * credentials are not configured.
 *
 * @param  string  $file_name  One of 'llms.txt', 'llms-full.txt'. robots.txt is not
 *                             tracked because physical robots.txt requests bypass
 *                             WordPress entirely.
 */
function ptxt_track_request($file_name): void
{
    $measurement_id = ptxt_site_option_string('ptxt_ga4_measurement_id');
    $api_secret = ptxt_site_option_string('ptxt_ga4_api_secret');

    if (empty($measurement_id) || empty($api_secret)) {
        return;
    }

    $user_agent = substr(ptxt_scalar_string($_SERVER['HTTP_USER_AGENT'] ?? null, 'unknown'), 0, 500);
    $ip = ptxt_scalar_string($_SERVER['REMOTE_ADDR'] ?? null, '0.0.0.0');
    $site_host = parse_url(home_url(), PHP_URL_HOST);

    $client_id = 'bot.'.substr(md5($user_agent), 0, 16);

    $payload = [
        'client_id' => $client_id,
        'events' => [[
            'name' => 'wayfinding_request',
            'params' => [
                'file_name' => $file_name,
                'user_agent' => $user_agent,
                'requester_ip' => $ip,
                'site_host' => $site_host,
                'engagement_time_msec' => 1,
            ],
        ]],
    ];

    $endpoint = add_query_arg(
        [
            'measurement_id' => $measurement_id,
            'api_secret' => $api_secret,
        ],
        'https://www.google-analytics.com/mp/collect'
    );

    // wp_json_encode() returns false when the payload cannot be encoded; send nothing then.
    $body = wp_json_encode($payload);
    if ($body === false) {
        return;
    }

    wp_remote_post($endpoint, [
        'timeout' => 1,
        'blocking' => false,
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $body,
    ]);
}

/**
 * 1. Override the virtual robots.txt per subsite (where the editor applies).
 *
 * Only fires when the subsite is one where the robots.txt editor is meaningful
 * (subdomain subsites always, subdirectory root site only). On shadowed setups
 * where a physical robots.txt exists at the web root, this filter never reaches
 * the browser: Apache / Nginx serves the physical file first.
 */
add_filter('robots_txt', function ($output, $public) {
    if (! ptxt_robots_editor_applies()) {
        return $output;
    }
    $custom = ptxt_option_string('custom_robots_txt', '');

    return $custom !== '' ? $custom : $output;
}, 10, 2);

/**
 * 2. Register virtual routes for llms.txt and llms-full.txt.
 */
add_action('init', function (): void {
    add_rewrite_rule('^llms\.txt$', 'index.php?llms_txt=1', 'top');
    add_rewrite_rule('^llms-full\.txt$', 'index.php?llms_full_txt=1', 'top');
});

add_filter('query_vars', function (array $vars): array {
    $vars[] = 'llms_txt';
    $vars[] = 'llms_full_txt';

    return $vars;
});

add_action('template_redirect', function (): void {
    if (get_query_var('llms_txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo ptxt_option_string('custom_llms_txt', '# llms.txt not yet configured for '.home_url()."\n");
        ptxt_track_request('llms.txt');
        exit; // @codeCoverageIgnore
    }
    if (get_query_var('llms_full_txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo ptxt_option_string('custom_llms_full_txt', '# llms-full.txt not yet configured for '.home_url()."\n");
        ptxt_track_request('llms-full.txt');
        exit; // @codeCoverageIgnore
    }
});

/**
 * Prevent WordPress canonical redirect from appending a trailing slash to
 * /llms.txt and /llms-full.txt.
 */
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    $path = parse_url(ptxt_scalar_string($requested_url, ''), PHP_URL_PATH);
    // parse_url() returns false for a malformed URL and null for one with no path.
    if (! is_string($path) || $path === '') {
        return $redirect_url;
    }

    // On subdirectory multisites, the request path includes the subsite's
    // path prefix (e.g. /pharmacy-services/llms.txt). Strip the current
    // subsite's home-URL path before checking so the filter matches on
    // both root-site and child-subsite requests.
    $home_path = parse_url(home_url('/'), PHP_URL_PATH);
    if (is_string($home_path) && $home_path !== '/' && $home_path !== '') {
        $home_path = '/'.trim($home_path, '/').'/';
        if (str_starts_with($path, $home_path)) {
            $path = '/'.substr($path, strlen($home_path));
        }
    }

    if ($path === '/llms.txt' || $path === '/llms-full.txt') {
        return false;
    }

    return $redirect_url;
}, 10, 2);

/**
 * 3. Flush rewrites on activation so llms.txt and llms-full.txt resolve
 * immediately without requiring a manual Save Permalinks step.
 *
 * register_activation_hook only fires on the site where the plugin is
 * activated. On Network Activation, WordPress fires it once per subsite
 * during activation, which is what we want.
 */
register_activation_hook(__FILE__, function (): void {
    add_rewrite_rule('^llms\.txt$', 'index.php?llms_txt=1', 'top');
    add_rewrite_rule('^llms-full\.txt$', 'index.php?llms_full_txt=1', 'top');
    flush_rewrite_rules(false);
});

/**
 * 4. Per-site admin UI under Settings, super-admin only, with a Disallow: / confirmation guard.
 */
add_action('admin_menu', function (): void {
    if (! is_super_admin()) {
        return;
    }

    add_options_page(
        'Site Files',
        'Site Files',
        'manage_network_options',
        'site-txt-files',
        'ptxt_render_settings_page'
    );
});

function ptxt_render_settings_page(): void
{
    if (! is_super_admin()) {
        wp_die('You do not have permission to access this page.');
    }

    $saved = false;
    $needs_confirm = false;
    $robots_applies = ptxt_robots_editor_applies();

    $nonce = ptxt_unslashed_string($_POST['site_txt_files_nonce'] ?? null);

    if ($nonce !== '' && wp_verify_nonce($nonce, 'save_site_txt_files')) {

        $robots_input = ptxt_unslashed_string($_POST['custom_robots_txt'] ?? null);
        $llms_input = ptxt_unslashed_string($_POST['custom_llms_txt'] ?? null);
        $llms_full_input = ptxt_unslashed_string($_POST['custom_llms_full_txt'] ?? null);

        // Only validate/save robots.txt on subsites where the editor applies.
        $has_disallow_all = $robots_applies && ptxt_wildcard_disallows_root($robots_input);
        $confirmed = ! empty($_POST['confirm_disallow_all']);

        if ($has_disallow_all && ! $confirmed) {
            $needs_confirm = true;
            $robots = esc_textarea($robots_input);
            $llms = esc_textarea($llms_input);
            $llms_full = esc_textarea($llms_full_input);
        } else {
            if ($robots_applies) {
                update_option('custom_robots_txt', $robots_input);
            }
            update_option('custom_llms_txt', $llms_input);
            update_option('custom_llms_full_txt', $llms_full_input);
            $saved = true;
        }
    }

    if (! $needs_confirm) {
        $robots = esc_textarea(ptxt_option_string('custom_robots_txt', ptxt_default_robots()));
        $llms = esc_textarea(ptxt_option_string('custom_llms_txt', ''));
        $llms_full = esc_textarea(ptxt_option_string('custom_llms_full_txt', ''));
    }

    $current_site_url = home_url();

    $tracking_id = ptxt_site_option_string('ptxt_ga4_measurement_id');
    $tracking_secret = ptxt_site_option_string('ptxt_ga4_api_secret');
    $tracking_on = ! empty($tracking_id) && ! empty($tracking_secret);

    $physical_robots = $robots_applies && ptxt_physical_robots_exists();
    $network_root_admin_url = is_multisite()
        ? get_admin_url(get_main_site_id(), 'options-general.php?page=site-txt-files')
        : admin_url('options-general.php?page=site-txt-files');
    ?>
    <div class="wrap">
        <h1>Site Files</h1>
        <p>
            These files are served at
            <?php if ($robots_applies) { ?>
                <code><?php echo esc_html($current_site_url); ?>/robots.txt</code>,
            <?php } ?>
            <code><?php echo esc_html($current_site_url); ?>/llms.txt</code>, and
            <code><?php echo esc_html($current_site_url); ?>/llms-full.txt</code>.
            Each subsite has its own llms.txt and llms-full.txt content. Super admins only.
        </p>

        <p>
            <strong>GA4 tracking:</strong>
            <?php if ($tracking_on) { ?>
                <span style="color:#1A6B3A;">Enabled</span> (configured in Network Admin &rarr; Settings &rarr; Site Files Tracking).
                Covers <code>llms.txt</code> and <code>llms-full.txt</code>.
            <?php } else { ?>
                <span style="color:#B91C1C;">Disabled</span>. Configure the GA4 measurement ID and API secret in
                Network Admin &rarr; Settings &rarr; Site Files Tracking to begin logging requests.
            <?php } ?>
        </p>

        <?php if ($saved) { ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
        <?php } ?>

        <?php if ($needs_confirm) { ?>
            <div class="notice notice-error">
                <p>
                    <strong>Warning:</strong> the robots.txt content you submitted contains
                    <code>Disallow: /</code>, which blocks all crawlers from the entire site.
                    If this is intentional, check the confirmation box below and save again.
                    Otherwise, edit the content before saving.
                </p>
            </div>
        <?php } ?>

        <form method="post">
            <?php wp_nonce_field('save_site_txt_files', 'site_txt_files_nonce'); ?>

            <?php if ($robots_applies) { ?>
                <h2>robots.txt</h2>
                <p class="description">
                    Leave blank to fall back to WordPress defaults. A sitemap directive is included in the default content.
                </p>

                <?php if ($physical_robots) { ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong>A physical <code>robots.txt</code> file exists at the web root.</strong>
                            The web server serves that file in preference to any content saved here, so edits to this
                            field will not reach crawlers until the physical file is removed. To make this editor
                            authoritative, delete the physical file at <code><?php echo esc_html(ABSPATH); ?>robots.txt</code>.
                        </p>
                    </div>
                <?php } ?>

                <textarea
                    name="custom_robots_txt"
                    rows="12"
                    cols="100"
                    class="large-text code"
                    spellcheck="false"><?php echo $robots; ?></textarea>

                <?php if ($needs_confirm) { ?>
                    <p>
                        <label>
                            <input type="checkbox" name="confirm_disallow_all" value="1">
                            Yes, I intend to block all crawlers on <?php echo esc_html($current_site_url); ?>.
                        </label>
                    </p>
                <?php } ?>
            <?php } else { ?>
                <h2>robots.txt</h2>
                <div class="notice notice-info inline">
                    <p>
                        <strong>Host-scoped.</strong>
                        Per the HTTP spec, a host has exactly one <code>robots.txt</code>, served at the host root.
                        This subsite shares its host with the rest of the network, so there is no per-subsite
                        <code>robots.txt</code> editor here. To edit <code>robots.txt</code> for the whole host,
                        use the editor on the network root site:
                        <a href="<?php echo esc_url($network_root_admin_url); ?>">Site Files (network root)</a>.
                        Note that if a physical <code>robots.txt</code> exists at the web root it will shadow
                        any content WordPress produces.
                    </p>
                </div>
            <?php } ?>

            <h2>llms.txt</h2>
            <p class="description">
                Short index describing this site's content for AI agents. Served at <code>/llms.txt</code>.
            </p>
            <textarea
                name="custom_llms_txt"
                rows="12"
                cols="100"
                class="large-text code"
                spellcheck="false"><?php echo $llms; ?></textarea>

            <h2>llms-full.txt</h2>
            <p class="description">
                Full-content version, typically longer. Served at <code>/llms-full.txt</code>.
            </p>
            <textarea
                name="custom_llms_full_txt"
                rows="24"
                cols="100"
                class="large-text code"
                spellcheck="false"><?php echo $llms_full; ?></textarea>

            <p><?php submit_button('Save Site Files'); ?></p>
        </form>
    </div>
    <?php
}

/**
 * 5. Network-level admin UI: GA4 tracking credentials and multisite sitemap block generator.
 */
add_action('network_admin_menu', function (): void {
    add_submenu_page(
        'settings.php',
        'Site Files Tracking',
        'Site Files Tracking',
        'manage_network_options',
        'site-txt-files-tracking',
        'ptxt_render_network_tracking_page'
    );

    add_submenu_page(
        'settings.php',
        'Sitemap Block Generator',
        'Sitemap Block Generator',
        'manage_network_options',
        'site-txt-files-sitemaps',
        'ptxt_render_sitemap_generator_page'
    );
});

function ptxt_render_network_tracking_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $saved = false;

    $nonce = ptxt_unslashed_string($_POST['site_txt_tracking_nonce'] ?? null);

    if ($nonce !== '' && wp_verify_nonce($nonce, 'save_site_txt_tracking')) {
        $measurement_id = trim(ptxt_unslashed_string($_POST['ptxt_ga4_measurement_id'] ?? null));
        $api_secret = trim(ptxt_unslashed_string($_POST['ptxt_ga4_api_secret'] ?? null));

        update_site_option('ptxt_ga4_measurement_id', sanitize_text_field($measurement_id));
        update_site_option('ptxt_ga4_api_secret', sanitize_text_field($api_secret));
        $saved = true;
    }

    $measurement_id = esc_attr(ptxt_site_option_string('ptxt_ga4_measurement_id'));
    $api_secret = esc_attr(ptxt_site_option_string('ptxt_ga4_api_secret'));
    ?>
    <div class="wrap">
        <h1>Site Files Tracking</h1>
        <p>
            Optional GA4 Measurement Protocol logging for requests to
            <code>llms.txt</code> and <code>llms-full.txt</code>
            across every subsite in this network. Leave both fields empty to disable tracking.
        </p>
        <p class="description">
            <strong>robots.txt is not tracked by this plugin.</strong> Physical <code>robots.txt</code>
            files served by the web server bypass WordPress entirely and cannot be observed here.
            Use server access logs for robots.txt analytics.
        </p>
        <p class="description">
            Events are sent as <code>wayfinding_request</code> with parameters
            <code>file_name</code>, <code>user_agent</code>, <code>requester_ip</code>, and <code>site_host</code>.
            To report on these in Explorations, register them as custom dimensions in GA4
            (Admin &rarr; Custom definitions). Realtime reports work without that step.
        </p>

        <?php if ($saved) { ?>
            <div class="notice notice-success is-dismissible"><p>Tracking settings saved.</p></div>
        <?php } ?>

        <form method="post">
            <?php wp_nonce_field('save_site_txt_tracking', 'site_txt_tracking_nonce'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="ptxt_ga4_measurement_id">GA4 Measurement ID</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="ptxt_ga4_measurement_id"
                                name="ptxt_ga4_measurement_id"
                                value="<?php echo $measurement_id; ?>"
                                class="regular-text code"
                                placeholder="G-XXXXXXXXXX"
                                autocomplete="off">
                            <p class="description">
                                Format: <code>G-XXXXXXXXXX</code>. Find it in GA4 under
                                Admin &rarr; Data Streams &rarr; (your web stream).
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ptxt_ga4_api_secret">Measurement Protocol API Secret</label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="ptxt_ga4_api_secret"
                                name="ptxt_ga4_api_secret"
                                value="<?php echo $api_secret; ?>"
                                class="regular-text code"
                                autocomplete="off">
                            <p class="description">
                                Create one in GA4 under
                                Admin &rarr; Data Streams &rarr; (your web stream) &rarr;
                                Measurement Protocol API secrets &rarr; Create.
                                Paste the secret <em>value</em>, not the secret name.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p><?php submit_button('Save Tracking Settings'); ?></p>
        </form>
    </div>
    <?php
}

function ptxt_render_sitemap_generator_page(): void
{
    if (! current_user_can('manage_network_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $default_path = '/sitemaps.xml';
    $requested_path = isset($_GET['path']) ? sanitize_text_field(ptxt_unslashed_string($_GET['path'])) : $default_path;
    if ($requested_path === '') {
        $requested_path = $default_path;
    }

    $block = ptxt_build_multisite_sitemap_block($requested_path);
    ?>
    <div class="wrap">
        <h1>Sitemap Block Generator</h1>
        <p>
            Generates a <code># Sitemaps</code> block containing one <code>Sitemap:</code>
            directive per public subsite on this network. Copy the output below and paste it
            into the physical <code>robots.txt</code> at the host's web root.
        </p>
        <p class="description">
            This is for networks that serve <code>robots.txt</code> as a static file on disk.
            Only subsites with Search Engine visibility enabled are included.
        </p>

        <form method="get">
            <input type="hidden" name="page" value="site-txt-files-sitemaps">
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="path">Sitemap path</label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="path"
                                name="path"
                                value="<?php echo esc_attr($requested_path); ?>"
                                class="regular-text code">
                            <p class="description">
                                Path appended to each subsite's home URL. Default is
                                <code>/sitemaps.xml</code> (matches SEOPress output).
                                Use <code>/sitemap.xml</code> if your sitemap plugin produces singular.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p>
                <button type="submit" class="button button-secondary">Regenerate with this path</button>
            </p>
        </form>

        <h2>Generated block</h2>
        <?php if ($block === '') { ?>
            <p><em>No public subsites found on this network.</em></p>
        <?php } else { ?>
            <textarea
                readonly
                rows="<?php echo max(6, substr_count($block, "\n") + 2); ?>"
                cols="100"
                class="large-text code"
                spellcheck="false"
                onclick="this.select();"><?php echo esc_textarea($block); ?></textarea>
            <p class="description">Click inside the box to select all, then copy.</p>
        <?php } ?>
    </div>
    <?php
}
