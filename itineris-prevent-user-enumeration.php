<?php
/**
 * Plugin Name:     Itineris Prevent WP User Enumeration
 * Plugin URI:      https://github.com/ItinerisLtd/itineris-prevent-wp-user-enumeration
 * Description:     Disable WordPress XML-RPC via actions and filters.
 * Version:         0.2.2
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
add_filter('login_errors', function (string $errors): string {
    if (isset($GLOBALS['errors']) && $GLOBALS['errors'] instanceof WP_Error) {
        $error_codes = $GLOBALS['errors']->get_error_codes();
        if (! in_array('invalid_username', $error_codes, true) && ! in_array('incorrect_password', $error_codes, true)) {
            return $errors;
        }
    } else {
        $errors_to_check = [
            'The username or password you entered is incorrect',
            'lostpassword',
        ];
        $has_valid_error = (bool) array_filter(
            $errors_to_check,
            fn (string $error): bool => ! str_contains($errors, $error),
        );
        if ($has_valid_error) {
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

    if (! $GLOBALS['authordata'] instanceof WP_User) {
        return $author;
    }

    // Check lowercase in case it matches anything.
    $author = strtolower($author);
    $user = $GLOBALS['authordata'];

    // If not using "username", all is fine.
    if (strtolower($user->user_login) !== $author) {
        return $author;
    }

    // If nicename is not the username, use it.
    if (! empty($user->user_nicename) && strtolower($user->user_nicename) !== $author) {
        return $user->user_nicename;
    }

    // If nickname is not the username, use it.
    if (! empty($user->nickname) && strtolower($user->nickname) !== $author) {
        return $user->nickname;
    }

    $maybe_new_author = '';
    if (! empty($user->first_name)) {
        $maybe_new_author = $user->first_name;
    }
    if (! empty($user->last_name)) {
        $maybe_new_author = trim("{$maybe_new_author} {$user->last_name}");
    }

    // If first/last names are not the username, use it.
    if (strtolower($maybe_new_author) !== $author) {
        return $maybe_new_author;
    }

    // Finally, if all options are same as the username then give up.
    return 'REDACTED';
});
