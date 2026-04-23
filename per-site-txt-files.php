<?php
/**
 * Plugin Name: Per-Site robots.txt, llms.txt, llms-full.txt
 * Description: Serves unique robots.txt, llms.txt, and llms-full.txt per multisite subsite. Production build for uamsdev.com. Includes optional GA4 Measurement Protocol tracking.
 * Version:     1.1.0
 * Network:     true
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default robots.txt content for a newly configured site.
 * Includes the sitemap directive pointing at the subsite's own sitemap.
 */
function ptxt_default_robots() {
    return "User-agent: *\nAllow: /\n\nSitemap: " . home_url('/sitemap.xml') . "\n";
}

/**
 * Returns true only if the User-agent: * group contains a Disallow: / rule
 * that is NOT overridden by a subsequent Allow: / in the same group.
 *
 * This parses robots.txt by User-agent block so that Disallow: / rules
 * targeted at specific bots (SemrushBot, AhrefsBot, etc.) do not trigger
 * the guard. Only a blanket block of all crawlers does.
 */
function ptxt_wildcard_disallows_root($content) {
    if (!is_string($content) || $content === '') {
        return false;
    }

    // Strip comments and normalize whitespace line by line.
    $lines = preg_split('/\r\n|\r|\n/', $content);

    $in_wildcard_group = false;
    $wildcard_disallow_root = false;
    $wildcard_allow_root    = false;

    foreach ($lines as $line) {
        // Drop inline comments and trim.
        $line = trim(preg_replace('/#.*$/', '', $line));
        if ($line === '') {
            continue;
        }

        if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
            $agent = strtolower(trim($m[1]));
            $in_wildcard_group = ($agent === '*');
            continue;
        }

        if (!$in_wildcard_group) {
            continue;
        }

        if (preg_match('/^Disallow:\s*\/\s*$/i', $line)) {
            $wildcard_disallow_root = true;
        } elseif (preg_match('/^Allow:\s*\/\s*$/i', $line)) {
            $wildcard_allow_root = true;
        }
    }

    // Disallow: / under * is only dangerous if not neutralized by Allow: /.
    return $wildcard_disallow_root && !$wildcard_allow_root;
}

/**
 * Fire a GA4 Measurement Protocol event for a wayfinding file request.
 *
 * Fire-and-forget: short timeout, non-blocking, never blocks the response.
 * Silently no-ops if the GA4 measurement ID or API secret are not configured,
 * so the plugin continues to serve files normally even without tracking set up.
 *
 * @param string $file_name One of 'robots.txt', 'llms.txt', 'llms-full.txt'.
 */
function ptxt_track_request($file_name) {
    $measurement_id = get_site_option('ptxt_ga4_measurement_id', '');
    $api_secret     = get_site_option('ptxt_ga4_api_secret', '');

    if (empty($measurement_id) || empty($api_secret)) {
        return;
    }

    $user_agent = isset($_SERVER['HTTP_USER_AGENT'])
        ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500)
        : 'unknown';
    $ip         = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $site_host  = parse_url(home_url(), PHP_URL_HOST);

    // Synthesize a stable client_id per user agent so GA4 groups crawler hits sensibly.
    $client_id = 'bot.' . substr(md5($user_agent), 0, 16);

    $payload = [
        'client_id' => $client_id,
        'events'    => [[
            'name'   => 'wayfinding_request',
            'params' => [
                'file_name'            => $file_name,
                'user_agent'           => $user_agent,
                'requester_ip'         => $ip,
                'site_host'            => $site_host,
                'engagement_time_msec' => 1,
            ],
        ]],
    ];

    $endpoint = add_query_arg(
        [
            'measurement_id' => $measurement_id,
            'api_secret'     => $api_secret,
        ],
        'https://www.google-analytics.com/mp/collect'
    );

    wp_remote_post($endpoint, [
        'timeout'  => 1,
        'blocking' => false,
        'headers'  => ['Content-Type' => 'application/json'],
        'body'     => wp_json_encode($payload),
    ]);
}

/**
 * 1. Override the virtual robots.txt per subsite, and track the request.
 */
add_filter('robots_txt', function ($output, $public) {
    $custom = get_option('custom_robots_txt');
    ptxt_track_request('robots.txt');
    return $custom !== false && $custom !== '' ? $custom : $output;
}, 10, 2);

/**
 * 2. Register virtual routes for llms.txt and llms-full.txt.
 */
add_action('init', function () {
    add_rewrite_rule('^llms\.txt$',      'index.php?llms_txt=1',      'top');
    add_rewrite_rule('^llms-full\.txt$', 'index.php?llms_full_txt=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'llms_txt';
    $vars[] = 'llms_full_txt';
    return $vars;
});

add_action('template_redirect', function () {
    if (get_query_var('llms_txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo get_option('custom_llms_txt', "# llms.txt not yet configured for " . home_url() . "\n");
        ptxt_track_request('llms.txt');
        exit;
    }
    if (get_query_var('llms_full_txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo get_option('custom_llms_full_txt', "# llms-full.txt not yet configured for " . home_url() . "\n");
        ptxt_track_request('llms-full.txt');
        exit;
    }
});

/**
 * Prevent WordPress canonical redirect from appending a trailing slash to
 * /llms.txt and /llms-full.txt. Without this, requests 301 to /llms.txt/.
 */
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    $path = parse_url($requested_url, PHP_URL_PATH);
    if ($path === '/llms.txt' || $path === '/llms-full.txt') {
        return false;
    }
    return $redirect_url;
}, 10, 2);

/**
 * 3. Per-site admin UI under Settings, super-admin only, with a Disallow: / confirmation guard.
 */
add_action('admin_menu', function () {
    if (!is_super_admin()) {
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

function ptxt_render_settings_page() {
    if (!is_super_admin()) {
        wp_die('You do not have permission to access this page.');
    }

    $saved         = false;
    $needs_confirm = false;

    if (isset($_POST['site_txt_files_nonce']) && wp_verify_nonce($_POST['site_txt_files_nonce'], 'save_site_txt_files')) {

        $robots_input    = isset($_POST['custom_robots_txt'])    ? wp_unslash($_POST['custom_robots_txt'])    : '';
        $llms_input      = isset($_POST['custom_llms_txt'])      ? wp_unslash($_POST['custom_llms_txt'])      : '';
        $llms_full_input = isset($_POST['custom_llms_full_txt']) ? wp_unslash($_POST['custom_llms_full_txt']) : '';

        // Guard: Disallow: / under User-agent: * blocks all crawlers. Require confirmation.
        // Block-scoped check: only fires when the wildcard user-agent group disallows root.
        // Rules for specific bots (e.g. "User-agent: ahrefsbot" + "Disallow: /") are not flagged.
        $has_disallow_all = ptxt_wildcard_disallows_root($robots_input);
        $confirmed        = !empty($_POST['confirm_disallow_all']);

        if ($has_disallow_all && !$confirmed) {
            $needs_confirm = true;
            // Hold the submitted values so the admin does not lose their work.
            $robots    = esc_textarea($robots_input);
            $llms      = esc_textarea($llms_input);
            $llms_full = esc_textarea($llms_full_input);
        } else {
            update_option('custom_robots_txt',    $robots_input);
            update_option('custom_llms_txt',      $llms_input);
            update_option('custom_llms_full_txt', $llms_full_input);
            $saved = true;
        }
    }

    if (!$needs_confirm) {
        $robots    = esc_textarea(get_option('custom_robots_txt',    ptxt_default_robots()));
        $llms      = esc_textarea(get_option('custom_llms_txt',      ''));
        $llms_full = esc_textarea(get_option('custom_llms_full_txt', ''));
    }

    $current_site_url = home_url();

    // Surface tracking status so super admins know whether GA4 events are firing.
    $tracking_id     = get_site_option('ptxt_ga4_measurement_id', '');
    $tracking_secret = get_site_option('ptxt_ga4_api_secret', '');
    $tracking_on     = !empty($tracking_id) && !empty($tracking_secret);
    ?>
    <div class="wrap">
        <h1>Site Files</h1>
        <p>
            These files are served at
            <code><?php echo esc_html($current_site_url); ?>/robots.txt</code>,
            <code><?php echo esc_html($current_site_url); ?>/llms.txt</code>, and
            <code><?php echo esc_html($current_site_url); ?>/llms-full.txt</code>.
            Each subsite has its own content. Super admins only.
        </p>

        <p>
            <strong>GA4 tracking:</strong>
            <?php if ($tracking_on) : ?>
                <span style="color:#1A6B3A;">Enabled</span> (configured in Network Admin &rarr; Settings &rarr; Site Files Tracking).
            <?php else : ?>
                <span style="color:#B91C1C;">Disabled</span>. Configure the GA4 measurement ID and API secret in
                Network Admin &rarr; Settings &rarr; Site Files Tracking to begin logging requests.
            <?php endif; ?>
        </p>

        <?php if ($saved) : ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
        <?php endif; ?>

        <?php if ($needs_confirm) : ?>
            <div class="notice notice-error">
                <p>
                    <strong>Warning:</strong> the robots.txt content you submitted contains
                    <code>Disallow: /</code>, which blocks all crawlers from the entire site.
                    If this is intentional, check the confirmation box below and save again.
                    Otherwise, edit the content before saving.
                </p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('save_site_txt_files', 'site_txt_files_nonce'); ?>

            <h2>robots.txt</h2>
            <p class="description">
                Leave blank to fall back to WordPress defaults. A sitemap directive is included in the default content.
            </p>
            <textarea
                name="custom_robots_txt"
                rows="12"
                cols="100"
                class="large-text code"
                spellcheck="false"><?php echo $robots; ?></textarea>

            <?php if ($needs_confirm) : ?>
                <p>
                    <label>
                        <input type="checkbox" name="confirm_disallow_all" value="1">
                        Yes, I intend to block all crawlers on <?php echo esc_html($current_site_url); ?>.
                    </label>
                </p>
            <?php endif; ?>

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
 * 4. Network-level admin UI for GA4 Measurement Protocol credentials.
 *
 * Tracking credentials are stored as site-wide options (get_site_option /
 * update_site_option) so one configuration drives telemetry for every subsite.
 */
add_action('network_admin_menu', function () {
    add_submenu_page(
        'settings.php',
        'Site Files Tracking',
        'Site Files Tracking',
        'manage_network_options',
        'site-txt-files-tracking',
        'ptxt_render_network_tracking_page'
    );
});

function ptxt_render_network_tracking_page() {
    if (!current_user_can('manage_network_options')) {
        wp_die('You do not have permission to access this page.');
    }

    $saved = false;

    if (isset($_POST['site_txt_tracking_nonce']) && wp_verify_nonce($_POST['site_txt_tracking_nonce'], 'save_site_txt_tracking')) {
        $measurement_id = isset($_POST['ptxt_ga4_measurement_id'])
            ? trim(wp_unslash($_POST['ptxt_ga4_measurement_id']))
            : '';
        $api_secret = isset($_POST['ptxt_ga4_api_secret'])
            ? trim(wp_unslash($_POST['ptxt_ga4_api_secret']))
            : '';

        update_site_option('ptxt_ga4_measurement_id', sanitize_text_field($measurement_id));
        update_site_option('ptxt_ga4_api_secret',     sanitize_text_field($api_secret));
        $saved = true;
    }

    $measurement_id = esc_attr(get_site_option('ptxt_ga4_measurement_id', ''));
    $api_secret     = esc_attr(get_site_option('ptxt_ga4_api_secret', ''));
    ?>
    <div class="wrap">
        <h1>Site Files Tracking</h1>
        <p>
            Optional GA4 Measurement Protocol logging for requests to
            <code>robots.txt</code>, <code>llms.txt</code>, and <code>llms-full.txt</code>
            across every subsite in this network. Leave both fields empty to disable tracking.
        </p>
        <p class="description">
            Events are sent as <code>wayfinding_request</code> with parameters
            <code>file_name</code>, <code>user_agent</code>, <code>requester_ip</code>, and <code>site_host</code>.
            To report on these in Explorations, register them as custom dimensions in GA4
            (Admin &rarr; Custom definitions). Realtime reports work without that step.
        </p>

        <?php if ($saved) : ?>
            <div class="notice notice-success is-dismissible"><p>Tracking settings saved.</p></div>
        <?php endif; ?>

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
