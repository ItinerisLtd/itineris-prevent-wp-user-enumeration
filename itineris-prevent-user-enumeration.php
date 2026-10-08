<?php
/**
 * Plugin Name:        Itineris Prevent WP User Enumeration
 * Plugin URI:         https://github.com/ItinerisLtd/itineris-prevent-wp-user-enumeration
 * Description:        Prevent User Enumeration in WordPress to satisfy security reports.
 * Version:            0.3.1
 * Requires at least:  7.1
 * Requires PHP:       8.4
 * Author:             Itineris Limited
 * Author URI:         https://itineris.co.uk
 * License:            GPL-2.0-or-later
 * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Make login errors generic.
add_filter('login_errors', function (string $errors): string {
    $wp_error = $GLOBALS['errors'] ?? null;
    if ($wp_error instanceof WP_Error) {
        // Registration reuses some of these codes for validation errors.
        if ('login' !== ($GLOBALS['action'] ?? 'login')) {
            return $errors;
        }

        $enumerable_codes = [
            'invalid_username',
            'invalid_email',
            'incorrect_password',
        ];
        if ([] === array_intersect($enumerable_codes, $wp_error->get_error_codes())) {
            return $errors;
        }
    } else {
        $errors_to_check = [
            'The username or password you entered is incorrect',
            'lostpassword',
        ];
        if (! array_any($errors_to_check, fn (string $error): bool => str_contains($errors, $error))) {
            return $errors;
        }
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
    if (is_admin() || current_user_can('list_users')) {
        return $endpoints;
    }

    return array_filter(
        $endpoints,
        fn(string $endpoint): bool => (0 === preg_match('/^\/wp\/v2\/users/', $endpoint)),
        ARRAY_FILTER_USE_KEY,
    );
});

// Remove user info from oEmbed data.
add_filter('oembed_response_data', function (array $data): array {
    unset($data['author_name']);
    unset($data['author_url']);
    return $data;
});

/**
 * Remove references to usernames where "the_author" is called
 * An example of this can be found in WP /feed/.
 */
add_filter('the_author', function (string $author): string {
    if (is_admin()) {
        return $author;
    }

    $user = $GLOBALS['authordata'] ?? null;
    if (! $user instanceof WP_User) {
        return $author;
    }

    // Check lowercase in case it matches anything.
    $lowercase_author = strtolower($author);

    // If not using "username", all is fine.
    if (strtolower($user->user_login) !== $lowercase_author) {
        return $author;
    }

    // Nicename is skipped because it is derived from the username.
    $candidates = [
        (string) $user->nickname,
        trim("{$user->first_name} {$user->last_name}"),
    ];
    foreach ($candidates as $candidate) {
        if ('' !== $candidate && strtolower($candidate) !== $lowercase_author) {
            return $candidate;
        }
    }

    // Finally, if all options are same as the username then give up.
    return __('REDACTED', 'itineris-prevent-wp-user-enumeration');
});
