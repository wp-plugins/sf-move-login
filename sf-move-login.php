<?php
/*
 * Plugin Name: SF Move Login
 * Plugin URI: http://www.screenfeed.fr/caravan-1-1/
 * Description: Change your login url
 * Version: 1.1.4
 * Author: Grégory Viguier
 * Author URI: http://www.screenfeed.fr/greg/
 * License: GPLv3
 * License URI: http://www.screenfeed.fr/gpl-v3.txt
 * Network: true
 * Text Domain: sf-move-login
 * Domain Path: /languages/
 */

if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

if ( version_compare( $GLOBALS['wp_version'], '3.1', '<' ) )
	return;


/* !---------------------------------------------------------------------------- */
/* !	INIT																	 */
/* ----------------------------------------------------------------------------- */

define( 'SFML_VERSION',			'1.1.4' );
define( 'SFML_NOOP_VERSION',	'1.0' );
define( 'SFML_FILE',			__FILE__ );
define( 'SFML_PLUGIN_BASEDIR',	basename( dirname( SFML_FILE ) ) );
define( 'SFML_PLUGIN_BASENAME',	plugin_basename( SFML_FILE ) );
define( 'SFML_PLUGIN_URL',		plugin_dir_url( SFML_FILE ) );
define( 'SFML_PLUGIN_DIR',		plugin_dir_path( SFML_FILE ) );


/* !---------------------------------------------------------------------------- */
/* !	INCLUDES																 */
/* ----------------------------------------------------------------------------- */

add_action( 'plugins_loaded', 'sfml_init' );

function sfml_init() {
	// Stuff for Noop
	if ( sf_can_use_noop( SFML_NOOP_VERSION ) && !function_exists('sfml_noop_params') )
		include( SFML_PLUGIN_DIR . 'inc/noop.inc.php' );

	// Administration
	if ( sfml_is_admin() && !function_exists('sfml_write_rules') )
		include( SFML_PLUGIN_DIR . 'inc/admin.inc.php' );

	// Plugins List: activation, deactivation, uninstall, admin notices, plugin/Noop links, Noop download and infos.
	global $pagenow;
	if ( is_admin() && ( $pagenow == 'plugins.php' || $pagenow == 'update-core.php' || $pagenow == 'update.php' || $pagenow == 'plugin-install.php' ) )
		include( SFML_PLUGIN_DIR . 'inc/plugins-list.inc.php' );
}


/* !---------------------------------------------------------------------------- */
/* !	I18N SUPPORT															 */
/* ----------------------------------------------------------------------------- */

add_action( 'init', 'sfml_lang_init' );

function sfml_lang_init() {
	load_plugin_textdomain( 'sf-move-login', false, SFML_PLUGIN_BASEDIR . '/languages/' );
}


/* !---------------------------------------------------------------------------- */
/* !	OPTIONS																	 */
/* ----------------------------------------------------------------------------- */

// !Get the slugs

function sfml_get_slugs() {
	if ( function_exists('sfml_noop_params') && class_exists('Noop_Options') )
		return Noop_Options::get_instance( sfml_noop_params() )->get_option( 'slugs' );
	else {
		static $slugs = array();	// Keep the same slugs all along.
		if ( empty($slugs) ) {
			$slugs = array(
				'postpass'			=> 'postpass',
				'logout'			=> 'logout',
				'lostpassword'		=> 'lostpassword',
				'retrievepassword'	=> 'retrievepassword',
				'resetpass'			=> 'resetpass',
				'rp'				=> 'rp',
				'register'			=> 'register',
				'login'				=> 'login',
			);

			// Plugins can add their own action
			$additional_slugs = apply_filters( 'sfml_additional_slugs', array() );
			if ( !empty( $additional_slugs ) ) {
				$additional_slugs = array_keys( $additional_slugs );
				$additional_slugs = array_combine( $additional_slugs, $additional_slugs );
				$additional_slugs = array_diff_key( $additional_slugs, $slugs );	// Don't screw the default ones
				$slugs = array_merge( $slugs, $additional_slugs );
			}

			// Generic filter, change the values
			$slugs = apply_filters( 'sfml_slugs', $slugs );
		}
		return $slugs;
	}
}


// !Access to wp-login.php

function sfml_deny_wp_login_access() {
	if ( function_exists('sfml_noop_params') && class_exists('Noop_Options') )
		return Noop_Options::get_instance( sfml_noop_params() )->get_option( 'deny_wp_login_access' );
	else
		return apply_filters( 'sfml_deny_wp_login_access', 1 );	// 1: error message, 2: 404, 3: home
}


// !Access to the administration area

function sfml_deny_admin_access() {
	if ( function_exists('sfml_noop_params') && class_exists('Noop_Options') )
		return Noop_Options::get_instance( sfml_noop_params() )->get_option( 'deny_admin_access' );
	else
		return apply_filters( 'sfml_deny_admin_access', 0 );	// 0: nothing, 1: error message, 2: 404, 3: home
}


/* --------------------------------------------------------------------------------- */
/* !TOOLS																			 */
/* --------------------------------------------------------------------------------- */

function sfml_is_admin() {
	global $pagenow;
	return is_admin() && !( (defined('DOING_AJAX') && DOING_AJAX) || ($pagenow == 'admin-post.php' && !empty($_REQUEST['action'])) );
}


if ( !function_exists('sf_can_use_noop') ):
function sf_can_use_noop( $version = '100' ) {
	return defined('NOOP_DIR') && defined('NOOP_VERSION') && version_compare(NOOP_VERSION, $version, '>=');
}
endif;


/* !---------------------------------------------------------------------------- */
/* !	EMERGENCY BYPASS														 */
/* ----------------------------------------------------------------------------- */

if ( defined('SFML_ALLOW_LOGIN_ACCESS') && SFML_ALLOW_LOGIN_ACCESS )
	return;


/* !---------------------------------------------------------------------------- */
/* !	FILTER URLS																 */
/* ----------------------------------------------------------------------------- */

// !Site URL

add_filter( 'site_url', 'sfml_site_url', 10, 4);

function sfml_site_url( $url, $path, $scheme, $blog_id = null ) {
	if ( ($scheme === 'login' || $scheme === 'login_post') && !empty($path) && is_string($path) && strpos($path, '..') === false && strpos($path, 'wp-login.php') !== false ) {
		// Base url
		if ( empty( $blog_id ) || !is_multisite() ) {
			$url = get_option( 'siteurl' );
		} else {
			switch_to_blog( $blog_id );
			$url = get_option( 'siteurl' );
			restore_current_blog();
		}

		$url = set_url_scheme( $url, $scheme );
		return $url . sfml_set_path( $path );
	}
	return $url;
}


// !Network site URL

add_filter( 'network_site_url', 'sfml_network_site_url', 10, 3);

function sfml_network_site_url( $url, $path, $scheme ) {
	if ( ($scheme === 'login' || $scheme === 'login_post') && !empty($path) && is_string($path) && strpos($path, '..') === false && strpos($path, 'wp-login.php') !== false ) {
		global $current_site;

		$url = set_url_scheme( 'http://' . $current_site->domain . $current_site->path, $scheme );
		return $url . sfml_set_path( $path );
	}
	return $url;
}


// !Logout url: wp_logout_url() add the action param after using site_url()

add_filter( 'logout_url', 'sfml_logout_url' );

function sfml_logout_url( $link ) {
	return sfml_login_to_action( $link, 'logout' );
}


// !Forgot password url: lostpassword_url() add the action param after using site_url()

add_filter( 'lostpassword_url', 'sfml_lostpass_url' );

function sfml_lostpass_url( $link ) {
	return sfml_login_to_action( $link, 'lostpassword' );
}


// !Redirections are hard-coded

add_filter('wp_redirect', 'sfml_redirect', 10, 2);

function sfml_redirect( $location, $status ) {
	if ( site_url( reset( (explode( '?', $location )) ) ) == site_url( 'wp-login.php' ) )
		return sfml_site_url( $location, $location, 'login', get_current_blog_id() );

	return $location;
}


/* !---------------------------------------------------------------------------- */
/* !	IF NOT CONNECTED, DENY ACCESS TO WP-LOGIN.PHP							 */
/* ----------------------------------------------------------------------------- */

add_action( 'login_init', 'sfml_login_init', 0 );

function sfml_login_init() {
	// If the user is logged in, do nothing, lets WP redirect this user to the administration area.
	if ( is_user_logged_in() )
		return;

	$uri = !empty($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']) ? $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] : (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
	$uri = parse_url( $uri );
	$uri = !empty($uri['path']) ? str_replace( '/', '', basename($uri['path']) ) : '';

	if ( $uri === 'wp-login.php' ) {
		do_action( 'sfml_wp_login_error' );

		// To make sure something happen.
		if ( false === has_action( 'sfml_wp_login_error' ) ) {
			sfml_wp_login_error();
		}
	}
}


add_action( 'sfml_wp_login_error', 'sfml_wp_login_error' );

function sfml_wp_login_error() {
	$do = sfml_deny_wp_login_access();
	switch( $do ) {
		case 2:
			$redirect = $GLOBALS['wp_rewrite']->using_permalinks() ? home_url('404') : add_query_arg( 'p', '404', home_url() );
			wp_safe_redirect( esc_url( apply_filters( 'sfml_404_error_page', $redirect ) ) );
			exit;
		case 3:
			wp_safe_redirect( home_url() );
			exit;
		default:
			wp_die( __('No no no, the login form is not here.', 'sf-move-login') );
	}
}


/* !---------------------------------------------------------------------------- */
/* !	IF NOT CONNECTED, DO NOT REDIRECT FROM ADMIN AREA TO WP-LOGIN.PHP		 */
/* ----------------------------------------------------------------------------- */

add_action( 'after_setup_theme', 'sfml_maybe_die_before_admin_redirect' );

function sfml_maybe_die_before_admin_redirect() {
	// If it's not the administration area, or if it's an ajax call, no need to go further.
	if ( !sfml_is_admin() )
		return;

	$scheme = is_user_admin() ? 'logged_in' : apply_filters( 'auth_redirect_scheme', '' );

	if ( !wp_validate_auth_cookie( '', $scheme) && sfml_deny_admin_access() ) {
		do_action( 'sfml_wp_admin_error' );

		// To make sure something happen.
		if ( false === has_action( 'sfml_wp_admin_error' ) ) {
			sfml_wp_admin_error();
		}
	}
}


add_action( 'sfml_wp_admin_error', 'sfml_wp_admin_error' );

function sfml_wp_admin_error() {
	$do = sfml_deny_admin_access();
	switch( $do ) {
		case 1:
			wp_die( __('Cheatin&#8217; uh?') );
		case 2:
			$redirect = $GLOBALS['wp_rewrite']->using_permalinks() ? home_url('404') : add_query_arg( 'p', '404', home_url() );
			wp_safe_redirect( esc_url( apply_filters( 'sfml_404_error_page', $redirect ) ) );
			exit;
		case 3:
			wp_safe_redirect( home_url() );
			exit;
	}
}


/* !---------------------------------------------------------------------------- */
/* !	UTILITIES																 */
/* ----------------------------------------------------------------------------- */

// !Construct the url

function sfml_set_path( $path ) {
	$slugs = sfml_get_slugs();
	// Action
	$parsed_path = parse_url( $path );
	if ( !empty( $parsed_path['query'] ) ) {
		wp_parse_str( $parsed_path['query'], $params );
		$action = !empty( $params['action'] ) ? $params['action'] : 'login';

		if ( isset( $params['key'] ) )
			$action = 'resetpass';

		if ( !isset($slugs[$action]) && false === has_filter( 'login_form_' . $action ) )
			$action = 'login';
	} else
		$action = 'login';

	// Path
	if ( isset($slugs[$action]) ) {
		$path = str_replace('wp-login.php', $slugs[$action], $path);
		$path = remove_query_arg('action', $path);
	}
	else {	// In case of a custom action
		$path = str_replace('wp-login.php', $slugs['login'], $path);
		$path = remove_query_arg('action', $path);
		$path = add_query_arg('action', $action, $path);
	}

	return '/' . ltrim( $path, '/' );
}


// !login?action=logout -> /logout

function sfml_login_to_action( $link, $action ) {
	$slugs = sfml_get_slugs();
	$need_action_param = false;

	if ( isset($slugs[$action]) ) {
		$slug = $slugs[$action];
	}
	else {	// Shouldn't happen, because this function is not used in this case.
		$slug = $slugs['login'];

		if ( false === has_filter( 'login_form_' . $action ) )
			$action = 'login';
		else		// In case of a custom action
			$need_action_param = true;
	}

	if ( $link && strpos($link, '/'.$slug) === false ) {
		$link = str_replace(array('/'.$slugs['login'], '&amp;', '?amp;', '&'), array('/'.$slug, '&', '?', '&amp;'), remove_query_arg('action', $link));
		if ( $need_action_param )		// In case of a custom action, shouldn't happen.
			$link = add_query_arg('action', $action, $link);
	}
	return $link;
}


// !For WP < 3.4

if ( !function_exists('set_url_scheme') ):
function set_url_scheme( $url, $scheme = null ) {
	$orig_scheme = $scheme;
	if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {
		if ( ( 'login_post' == $scheme || 'rpc' == $scheme ) && ( force_ssl_login() || force_ssl_admin() ) )
			$scheme = 'https';
		elseif ( ( 'login' == $scheme ) && force_ssl_admin() )
			$scheme = 'https';
		elseif ( ( 'admin' == $scheme ) && force_ssl_admin() )
			$scheme = 'https';
		else
			$scheme = ( is_ssl() ? 'https' : 'http' );
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

	return apply_filters( 'set_url_scheme', $url, $scheme, $orig_scheme );
}
endif;
/**/