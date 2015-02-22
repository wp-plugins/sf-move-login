<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}


/* !---------------------------------------------------------------------------- */
/* !	FILTER URLS																 */
/* ----------------------------------------------------------------------------- */

// !Site URL

add_filter( 'site_url', 'sfml_site_url', 10, 4 );

function sfml_site_url( $url, $path, $scheme, $blog_id = null ) {
	if ( ( $scheme === 'login' || $scheme === 'login_post' ) && ! empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false && strpos( $path, 'wp-login.php' ) !== false ) {
		// Base url
		if ( empty( $blog_id ) || ! is_multisite() ) {
			$url = get_option( 'siteurl' );
		}
		else {
			switch_to_blog( $blog_id );
			$url = get_option( 'siteurl' );
			restore_current_blog();
		}

		$url = set_url_scheme( $url, $scheme );
		return rtrim( $url, '/' ) . '/' . ltrim( sfml_set_path( $path ), '/' );
	}
	return $url;
}


// !Network site URL

add_filter( 'network_site_url', 'sfml_network_site_url', 10, 3 );

function sfml_network_site_url( $url, $path, $scheme ) {
	global $current_site;

	if ( ( $scheme === 'login' || $scheme === 'login_post' ) && ! empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false && strpos( $path, 'wp-login.php' ) !== false ) {
		$url = set_url_scheme( 'http://' . rtrim( $current_site->domain, '/' ) . '/' . ltrim( $current_site->path, '/' ), $scheme );
		return rtrim( $url, '/' ) . '/' . ltrim( sfml_set_path( $path ), '/' );
	}

	return $url;
}


// !Logout url: wp_logout_url() add the action param after using site_url()

add_filter( 'logout_url', 'sfml_logout_url' );

function sfml_logout_url( $link ) {
	return sfml_login_to_action( $link, 'logout' );
}


// !Forgot password url: wp_lostpassword_url() add the action param after using site_url()

add_filter( 'lostpassword_url', 'sfml_lostpass_url' );

function sfml_lostpass_url( $link ) {
	return sfml_login_to_action( $link, 'lostpassword' );
}


// !Redirections are hard-coded

add_filter( 'wp_redirect', 'sfml_redirect', 10, 2 );

function sfml_redirect( $location, $status ) {
	if ( site_url( reset( (explode( '?', $location )) ) ) === site_url( 'wp-login.php' ) ) {
		return sfml_site_url( $location, $location, 'login', get_current_blog_id() );
	}

	return $location;
}

/**/