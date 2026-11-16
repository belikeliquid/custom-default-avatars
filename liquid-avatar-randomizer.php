<?php
/**
 * Plugin Name:       Liquid Avatar Randomizer
 * Plugin URI:        https://github.com/belikeliquid/liquid-avatar-randomizer
 * Description:       Gives every commenter, user and subscriber without an avatar a fun image picked from your own set. Add or remove images any time under Settings > Avatar Randomizer.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Liquid Media
 * Author URI:        https://belikeliquid.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       liquid-avatar-randomizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LMAR_VERSION', '1.0.0' );
define( 'LMAR_FILE', __FILE__ );
define( 'LMAR_URL', plugin_dir_url( __FILE__ ) );
define( 'LMAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'LMAR_KEY', 'liquid_avatar_randomizer' );     // Value stored in the avatar_default option.
define( 'LMAR_OPT_IMAGES', 'lmar_images' );           // List of image entries.
define( 'LMAR_OPT_MODE', 'lmar_mode' );               // 'gravatar' or 'local'.
define( 'LMAR_OPT_PREV_DEFAULT', 'lmar_prev_avatar_default' );
define( 'LMAR_CREDIT_URL', 'https://belikeliquid.com' );

/**
 * Bundled starter images (file names inside assets/avatars).
 */
function lmar_bundled_files() {
	return array( 'palm.png', 'sunset.png', 'wave.png', 'turtle.png', 'hibiscus.png' );
}

/**
 * Default image list: every bundled file, stored as "bundled:<file>".
 */
function lmar_default_images() {
	return array_map(
		function ( $f ) {
			return 'bundled:' . $f;
		},
		lmar_bundled_files()
	);
}

/**
 * Current image entries. Each entry is "bundled:<file>" or an attachment ID (as a string).
 */
function lmar_get_images() {
	$images = get_option( LMAR_OPT_IMAGES, null );
	if ( ! is_array( $images ) ) {
		$images = lmar_default_images();
	}
	return array_values( array_filter( array_map( 'lmar_clean_entry', $images ) ) );
}

/**
 * Validate one entry. Returns the cleaned entry or '' if invalid.
 */
function lmar_clean_entry( $entry ) {
	$entry = trim( (string) $entry );
	if ( 0 === strpos( $entry, 'bundled:' ) ) {
		$file = substr( $entry, 8 );
		return in_array( $file, lmar_bundled_files(), true ) ? 'bundled:' . $file : '';
	}
	$id = absint( $entry );
	if ( $id && wp_attachment_is_image( $id ) ) {
		return (string) $id;
	}
	return '';
}

/**
 * Resolve an entry to an image URL.
 */
function lmar_entry_url( $entry, $size = 'medium' ) {
	if ( 0 === strpos( $entry, 'bundled:' ) ) {
		return LMAR_URL . 'assets/avatars/' . substr( $entry, 8 );
	}
	$url = wp_get_attachment_image_url( absint( $entry ), $size );
	return $url ? $url : '';
}

/**
 * All usable image URLs.
 */
function lmar_get_urls() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$urls = array();
	foreach ( lmar_get_images() as $entry ) {
		$url = lmar_entry_url( $entry );
		if ( $url ) {
			$urls[] = $url;
		}
	}
	$cache = $urls;
	return $urls;
}

/**
 * Build a stable key for whoever the avatar is for, so the same person
 * always gets the same image (no flicker between page loads).
 */
function lmar_identity_key( $id_or_email ) {
	$email = '';
	$user  = false;

	if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
		$comment = get_comment( $id_or_email );
		if ( $comment ) {
			if ( ! empty( $comment->user_id ) ) {
				$user = get_user_by( 'id', (int) $comment->user_id );
			}
			if ( ! $user ) {
				$email = $comment->comment_author_email;
				if ( '' === $email ) {
					$email = $comment->comment_author . '|' . $comment->comment_author_IP;
				}
			}
		}
	} elseif ( $id_or_email instanceof WP_User ) {
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user = get_user_by( 'id', (int) $id_or_email->post_author );
	} elseif ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', absint( $id_or_email ) );
	} elseif ( is_string( $id_or_email ) ) {
		$email = $id_or_email;
	}

	if ( $user ) {
		$email = $user->user_email ? $user->user_email : 'user-' . $user->ID;
	}

	return strtolower( trim( (string) $email ) );
}

/**
 * Pick an image URL for a given key.
 */
function lmar_pick_url( $key ) {
	$urls = lmar_get_urls();
	if ( empty( $urls ) ) {
		return '';
	}
	$index = (int) ( sprintf( '%u', crc32( $key ) ) % count( $urls ) );
	return $urls[ $index ];
}

/**
 * Gravatar only accepts default images it can fetch: jpg, jpeg, gif or png, with no query string.
 */
function lmar_gravatar_can_use( $url ) {
	if ( false !== strpos( $url, '?' ) ) {
		return false;
	}
	$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	return in_array( $ext, array( 'jpg', 'jpeg', 'gif', 'png' ), true );
}

/**
 * Main filter: swap in a random avatar when the site default is ours.
 * Runs late so local-avatar plugins that already set a URL win.
 */
function lmar_filter_avatar_data( $args, $id_or_email ) {
	if ( ! empty( $args['url'] ) ) {
		return $args; // Another plugin already supplied a real avatar.
	}

	$default = isset( $args['default'] ) ? $args['default'] : get_option( 'avatar_default', 'mystery' );
	if ( LMAR_KEY !== $default ) {
		return $args;
	}

	$key = lmar_identity_key( $id_or_email );
	$url = lmar_pick_url( '' === $key ? wp_rand() : $key );
	if ( '' === $url ) {
		$args['default'] = 'mystery';
		return $args;
	}

	$mode = get_option( LMAR_OPT_MODE, 'gravatar' );

	if ( 'local' === $mode || ! lmar_gravatar_can_use( $url ) || ! empty( $args['force_default'] ) ) {
		// Skip Gravatar and use the image straight from this site.
		$args['url']          = $url;
		$args['found_avatar'] = false;
		return $args;
	}

	// Gravatar mode: real Gravatars still show; everyone else falls back to our image.
	if ( 0 === strpos( home_url(), 'https:' ) ) {
		$url = set_url_scheme( $url, 'https' );
	}
	$args['default'] = $url;
	return $args;
}
add_filter( 'pre_get_avatar_data', 'lmar_filter_avatar_data', 20, 2 );

/**
 * Add our choice to Settings > Discussion > Default Avatar.
 */
function lmar_avatar_defaults( $defaults ) {
	$defaults[ LMAR_KEY ] = __( 'Liquid Avatar Randomizer (your image set)', 'liquid-avatar-randomizer' );
	return $defaults;
}
add_filter( 'avatar_defaults', 'lmar_avatar_defaults' );

/**
 * Activation: make our set the site default, remembering the old one.
 */
function lmar_activate() {
	if ( false === get_option( LMAR_OPT_IMAGES ) ) {
		add_option( LMAR_OPT_IMAGES, lmar_default_images() );
	}
	if ( false === get_option( LMAR_OPT_MODE ) ) {
		add_option( LMAR_OPT_MODE, 'gravatar' );
	}
	$current = get_option( 'avatar_default', 'mystery' );
	if ( LMAR_KEY !== $current ) {
		update_option( LMAR_OPT_PREV_DEFAULT, $current );
		update_option( 'avatar_default', LMAR_KEY );
	}
}
register_activation_hook( __FILE__, 'lmar_activate' );

/**
 * Deactivation: put the old default back so avatars never break.
 */
function lmar_deactivate() {
	if ( LMAR_KEY === get_option( 'avatar_default' ) ) {
		update_option( 'avatar_default', get_option( LMAR_OPT_PREV_DEFAULT, 'mystery' ) );
	}
}
register_deactivation_hook( __FILE__, 'lmar_deactivate' );

if ( is_admin() ) {
	require_once LMAR_DIR . 'includes/admin.php';
}
