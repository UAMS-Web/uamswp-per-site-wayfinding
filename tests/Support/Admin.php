<?php

declare(strict_types=1);

namespace Tests\Support;

use function Brain\Monkey\Functions\when;

/**
 * Stand-ins for the WordPress output helpers an admin page echoes through.
 *
 * Each helper marks what it was given, so a test can see which helper wrapped
 * which value: an escape that is dropped, or applied to the wrong value, shows
 * in the output.
 */
final class Admin
{
    public static function stubOutput(): void
    {
        when('esc_html')->alias(fn (string $value): string => '[html:'.$value.']');
        when('esc_attr')->alias(fn (string $value): string => '[attr:'.$value.']');
        when('esc_textarea')->alias(fn (string $value): string => '[textarea:'.$value.']');
        when('esc_url')->alias(fn (string $value): string => '[url:'.$value.']');
        when('wp_unslash')->alias(fn (string $value): string => stripslashes($value));
        when('sanitize_text_field')->alias(fn (string $value): string => '[clean:'.$value.']');
        when('wp_nonce_field')->alias(function (string $action, string $name): void {
            echo '<nonce:'.$action.':'.$name.'>';
        });
        when('submit_button')->alias(function (string $text): void {
            echo '<submit:'.$text.'>';
        });
    }

    /**
     * Make this a child site of a subdirectory network, where the robots.txt editor does not apply.
     */
    public static function asChildSite(): void
    {
        when('is_multisite')->justReturn(true);
        when('is_subdomain_install')->justReturn(false);
        when('is_main_site')->justReturn(false);
        when('get_main_site_id')->justReturn(1);
        when('get_admin_url')->alias(fn (int $id, string $path): string => 'https://root.test/'.$id.'/wp-admin/'.$path);
    }
}
