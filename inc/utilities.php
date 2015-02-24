<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}


/* !---------------------------------------------------------------------------- */
/* !	OPTIONS																	 */
/* ----------------------------------------------------------------------------- */

// !Get all options

function sfml_get_options() {
	return SFML_Options::get_options();
}


// !Get default options

function sfml_get_default_options() {
	return SFML_Options::get_default_options();
}


// !Get the slugs

function sfml_get_slugs() {
	return SFML_Options::get_slugs();
}


// !Access to wp-login.php

function sfml_deny_wp_login_access() {
	$options = sfml_get_options();
	return $options['deny_wp_login_access'];	// 1: error message, 2: 404, 3: home
}


// !Access to the administration area

function sfml_deny_admin_access() {
	$options = sfml_get_options();
	return $options['deny_admin_access'];	// 0: nothing, 1: error message, 2: 404, 3: home
}


/* !---------------------------------------------------------------------------- */
/* !	UTILITIES																 */
/* ----------------------------------------------------------------------------- */

// !Construct the url

function sfml_set_path( $path ) {
	$slugs = sfml_get_slugs();

	// Action
	$parsed_path = parse_url( $path );
	if ( ! empty( $parsed_path['query'] ) ) {
		wp_parse_str( $parsed_path['query'], $params );
		$action = !empty( $params['action'] ) ? $params['action'] : 'login';

		if ( isset( $params['key'] ) ) {
			$action = 'resetpass';
		}

		if ( ! isset( $slugs[ $action ] ) && false === has_filter( 'login_form_' . $action ) ) {
			$action = 'login';
		}
	}
	else {
		$action = 'login';
	}

	// Path
	if ( isset($slugs[$action]) ) {
		$path = str_replace( 'wp-login.php', $slugs[ $action ], $path );
		$path = remove_query_arg( 'action', $path );
	}
	else {	// In case of a custom action
		$path = str_replace( 'wp-login.php', $slugs['login'], $path );
		$path = remove_query_arg( 'action', $path );
		$path = add_query_arg( 'action', $action, $path );
	}

	return '/' . ltrim( $path, '/' );
}


// !login?action=logout -> /logout

function sfml_login_to_action( $link, $action ) {
	$slugs = sfml_get_slugs();
	$need_action_param = false;

	if ( isset( $slugs[ $action ] ) ) {
		$slug = $slugs[ $action ];
	}
	else {	// Shouldn't happen, because this function is not used in this case.
		$slug = $slugs['login'];

		if ( false === has_filter( 'login_form_' . $action ) ) {
			$action = 'login';
		}
		else {		// In case of a custom action
			$need_action_param = true;
		}
	}

	if ( $link && strpos( $link, '/' . $slug ) === false ) {
		$link = str_replace( array( '/' . $slugs['login'], '&amp;', '?amp;', '&' ), array( '/' . $slug, '&', '?', '&amp;' ), remove_query_arg( 'action', $link ) );

		if ( $need_action_param ) {		// In case of a custom action, shouldn't happen.
			$link = add_query_arg( 'action', $action, $link );
		}
	}

	return $link;
}


/* !---------------------------------------------------------------------------- */
/* !	GENERIC TOOLS															 */
/* ----------------------------------------------------------------------------- */

// !Get current URL.

if ( ! function_exists( 'sf_get_current_url' ) ) :
function sf_get_current_url( $mode = 'base' ) {
	$url = ! empty( $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] ) ? $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] : ( ! empty( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
	$url = 'http' . ( is_ssl() ? 's' : '' ) . '://' . $_SERVER['HTTP_HOST'] . $url;
	switch( $mode ) :
		case 'raw'  :
			return $url;
			break;
		case 'base' :
			$url = reset( (explode( '?', $url )) );
			return reset( (explode( '&', $url )) );
			break;
		case 'uri'  :
			$url = reset( (explode( '?', $url )) );
			$url = reset( (explode( '&', $url )) );
			return trim( str_replace( home_url(), '', $url ), '/' );
	endswitch;
}
endif;


// !For WP < 3.4

if ( ! function_exists( 'set_url_scheme' ) ) :
function set_url_scheme( $url, $scheme = null ) {
	$orig_scheme = $scheme;

	if ( ! $scheme ) {
		$scheme = is_ssl() ? 'https' : 'http';
	} elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
		$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
	} elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
		$scheme = is_ssl() ? 'https' : 'http';
	}

	$url = trim( $url );
	if ( substr( $url, 0, 2 ) === '//' )
		$url = 'http:' . $url;

	if ( 'relative' == $scheme ) {
		$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );
		if ( $url !== '' && $url[0] === '/' )
			$url = '/' . ltrim($url , "/ \t\n\r\0\x0B" );
	} else {
		$url = preg_replace( '#^\w+://#', $scheme . '://', $url );
	}

	/**
	 * Filter the resulting URL after setting the scheme.
	 *
	 * @since 3.4.0
	 *
	 * @param string $url         The complete URL including scheme and path.
	 * @param string $scheme      Scheme applied to the URL. One of 'http', 'https', or 'relative'.
	 * @param string $orig_scheme Scheme requested for the URL. One of 'http', 'https', 'login',
	 *                            'login_post', 'admin', 'rpc', or 'relative'.
	 */
	return apply_filters( 'set_url_scheme', $url, $scheme, $orig_scheme );
}
endif;


// !get_home_path() like. But this time we don't "fallback" to the real function if it exists, because of a bug with old versions.

function sfml_get_home_path() {
	$home    = set_url_scheme( get_option( 'home' ), 'http' );
	$siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );

	if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
		$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); /* $siteurl - $home */
		$pos = strripos( str_replace( '\\', '/', $_SERVER['SCRIPT_FILENAME'] ), trailingslashit( $wp_path_rel_to_home ) );
		$home_path = substr( $_SERVER['SCRIPT_FILENAME'], 0, $pos );
		$home_path = trailingslashit( $home_path );
	}
	else {
		$home_path = ABSPATH;
	}

	return str_replace( '\\', '/', $home_path );
}

/**/