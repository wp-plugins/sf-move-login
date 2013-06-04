<?php
/*
 * Plugin Name: SF Move Login
 * Plugin URI: http://www.screenfeed.fr
 * Description: Change your login url to http://example.com/login
 * Version: 0.1.1
 * Author: Grégory Viguier
 * Author URI: http:www.screenfeed.fr/greg/
 * License: GPLv3
 * Require: WordPress 3.0
 * Text Domain: sfml
 * Domain Path: /languages/
 */

/* ----------------------------------------------------------------------------- */
/*																				 */
/*							Activation / Deactivation							 */
/*																				 */
/* ----------------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'sfml_activate' );
function sfml_activate() {
	$dies = array();
	$notices = array();
	$home_path = get_home_path();
	load_plugin_textdomain( 'sfml', false, basename( dirname( __FILE__ ) ) . '/languages/' );	// wp_die() will need i18n

	if ( iis7_supports_permalinks() ) {
		if ( !( ( !file_exists($home_path . 'web.config') && win_is_writable($home_path) ) || win_is_writable($home_path . 'web.config') ) )
			$notices[] = 'htaccess_not_writable';
	} else {
		if ( !( ( !file_exists($home_path . '.htaccess') && is_writable($home_path) ) || is_writable($home_path . '.htaccess') ) )
			$notices[] = 'htaccess_not_writable';
	}
	if ( !get_option('permalink_structure') )
		$dies[] = sprintf(__('Please Make sure to enable %s.', 'sfml'), '<a href="options-permalink.php">'.__('Permalinks').'</a>');

	if ( empty($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']) && empty($_SERVER['REQUEST_URI']) )
		$dies[] = __('It seems your server configuration prevent the plugin to work properly. <i>SF Move Login</i> will not be activated.', 'sfml');

	if ( count($dies) ) {
		wp_die( __('<strong>SF Move Login</strong> has not been activated.', 'sfml').'<br/>'.implode('<br/>', $dies), __('Error'), array('back_link' => true) );
	} else {
		if ( count($notices) )
			set_transient('sfml_notices-'.get_current_user_id(), $notices);
		sfml_rewrite();
		flush_rewrite_rules();
	}
}


register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );


// !Admin notices

add_action('admin_init', 'sfml_notices');
function sfml_notices() {
	$user_id = get_current_user_id();
	$notices = get_transient('sfml_notices-'.$user_id);

	if ( $notices && is_array($notices) && count($notices) ) {
		foreach ( $notices as $notice ) {
			add_action('admin_notices', 'sfml_'.$notice.'_notice');
		}
		delete_transient('sfml_notices-'.$user_id);
	}
}


function sfml_htaccess_not_writable_notice() {
	$file = iis7_supports_permalinks() ? '<code>web.config</code>' : '<code>.htaccess</code>';
	echo '<div class="error"><p>'
			.sprintf(
				__('<i>SF Move Login</i> needs access to the %1$s file. Please visit the %2$s settings page and copy/paste the given code into the %1$s file.', 'sfml'),
				$file,
				'<a href="options-permalink.php">'.__('Permalinks').'</a>'
			)
		.'</p></div>';
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								i18n support									 */
/*																				 */
/* ----------------------------------------------------------------------------- */

add_action( 'init', 'sfml_lang_init' );
function sfml_lang_init() {
	load_plugin_textdomain( 'sfml', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								Rewrite rules									 */
/*																				 */
/* ----------------------------------------------------------------------------- */

add_action( 'setup_theme', 'sfml_rewrite' );
function sfml_rewrite() {
	add_rewrite_rule( 'login/?([\?&].*)?$', 'wp-login.php', 'top' );
	$actions = array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register' );
	foreach ( $actions as $action ) {
		add_rewrite_rule( $action.'/?([\?&].*)?$', 'wp-login.php?action='.$action, 'top' );
	}
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*									Bypass										 */
/*																				 */
/* ----------------------------------------------------------------------------- */

if ( defined('SFML_ALLOW_LOGIN_ACCESS') && SFML_ALLOW_LOGIN_ACCESS )
	return;


/* ----------------------------------------------------------------------------- */
/*																				 */
/*									Filter urls									 */
/*																				 */
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


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								Redirections									 */
/*																				 */
/* ----------------------------------------------------------------------------- */

// !Redirections are hard-coded
add_filter('wp_redirect', 'sfml_redirect', 10, 2);
function sfml_redirect( $location, $status ) {
	if ( site_url( reset( explode( '?', $location ) ) ) == site_url( 'wp-login.php' ) )
		return sfml_site_url( $location, $location, 'login', get_current_blog_id() );

	return $location;
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*						Block access to wp-login.php							 */
/*																				 */
/* ----------------------------------------------------------------------------- */

// !No, you won't use wp-login.php
add_action( 'login_init', 'sfml_login_init', 0 );
function sfml_login_init() {
	$uri = !empty($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']) ? $GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'] : (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
	$uri = parse_url( $uri );
	$uri = !empty($uri['path']) ? str_replace( '/', '', basename($uri['path']) ) : '';

	if ( $uri === 'wp-login.php' )
		wp_die(__('No no no, the login form is not here.', 'sfml'));
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								Utilities										 */
/*																				 */
/* ----------------------------------------------------------------------------- */

function sfml_set_path( $path ) {
	// Action
	$parsed_path = parse_url( $path );
	if ( !empty( $parsed_path['query'] ) ) {
		wp_parse_str( $parsed_path['query'], $params );
		$action = !empty( $params['action'] ) ? $params['action'] : 'login';

		if ( isset( $params['key'] ) )
			$action = 'resetpass';

		if ( !in_array( $action, array( 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'login' ), true ) && false === has_filter( 'login_form_' . $action ) )
			$action = 'login';
	} else
		$action = 'login';

	// Path
	$path = str_replace('wp-login.php', $action, $path);
	$path = remove_query_arg('action', $path);

	return '/' . ltrim( $path, '/' );
}


// !login?action=logout -> /logout
function sfml_login_to_action( $link, $action ) {
	if ( $link && strpos($link, '/'.$action) === false )
		return str_replace(array('/login', '&amp;', '?amp;', '&'), array('/'.$action, '&', '?', '&amp;'), remove_query_arg('action', $link));
	return $link;
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								For WP < 3.4									 */
/*																				 */
/* ----------------------------------------------------------------------------- */

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

	if ( 'relative' == $scheme )
		$url = preg_replace( '#^.+://[^/]*#', '', $url );
	else
		$url = preg_replace( '#^.+://#', $scheme . '://', $url );

	return apply_filters( 'set_url_scheme', $url, $scheme, $orig_scheme );
}
endif;