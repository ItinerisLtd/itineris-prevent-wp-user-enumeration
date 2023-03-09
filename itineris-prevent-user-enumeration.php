<?php
/**
 * Plugin Name:     Itineris Prevent WP User Enumeration
 * Plugin URI:      https://github.com/ItinerisLtd/itineris-prevent-wp-user-enumeration
 * Description:     Disable WordPress XML-RPC via actions and filters.
 * Version:         0.1.0
 * Author:          Itineris Limited
 * Author URI:      https://itineris.co.uk
 * License:         GPL-2.0-or-later
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Make login errors generic.
add_filter('login_errors', function (string $error): string {
    $errors = $GLOBALS['errors'];
    $error_codes = $errors->get_error_codes();
    if (! in_array('invalid_username', $error_codes, true) && ! in_array('incorrect_password', $error_codes, true)) {
        return $error;
    }

    return __('Something was wrong.', 'itineris-prevent-wp-user-enumeration');
});

// Disable /?author=ID.
add_action('wp', function (): void {
    /** @var WP_Query */
    $wp_query = $GLOBALS['wp_query'];
    $query_vars = $wp_query->query_vars;
    if (empty($query_vars) || empty($query_vars['author'])) {
        return;
    }

    $wp_query->set_404();
    status_header(404);
    nocache_headers();
});

// Remove user-related REST endpoints.
add_filter('rest_endpoints', function (array $endpoints): array {
    return array_filter(
        $endpoints,
        fn(string $endpoint): bool => (0 === preg_match('/^\/wp\/v2\/users/', $endpoint)),
        ARRAY_FILTER_USE_KEY
    );
});

// Remove user info from oEmbed data.
add_filter('oembed_response_data', function (array $data): array {
    unset($data['author_name']);
    unset($data['author_url']);
    return $data;
});
