<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

/* !---------------------------------------------------------------------------- */
/* !	NOOP PARAMS																 */
/* ----------------------------------------------------------------------------- */

// !Return all settings for Noop.

function sfml_noop_params() {
	$sets = array(
		'option_name'		=> 'sfml',
		'option_group'		=> 'sfml_settings',

		'page_name'			=> 'move-login',
		'page_parent_name'	=> 'settings',
		'page_parent'		=> 'options-general.php',
		'capability'		=> 'manage_options',

		'plugin_page_title'	=> 'Move Login',
		'plugin_menu_name'	=> 'Move Login',
		'plugin_file'		=> SFML_FILE,
		'plugin_logo_url'	=> SFML_PLUGIN_URL . 'res/icon.png',

		'support_image'		=> SFML_PLUGIN_URL . 'res/support.jpg',
		'support_url'		=> 'https://wordpress.org/support/plugin/sf-move-login',
	);

	if ( is_multisite() ) {
		$sets['page_parent']	= 'settings.php';
		$sets['capability']		= 'manage_network_options';
		$sets['network_menu']	= true;
	}

	return $sets;
}


/* !---------------------------------------------------------------------------- */
/* !	PLUGIN OPTIONS															 */
/* ----------------------------------------------------------------------------- */

// !Default options

add_filter( 'sfml_default_options', 'sfml_default_options' );

function sfml_default_options( $options = array() ) {
	$options = array_merge( $options, array(
		'slugs.postpass'			=> 'postpass',
		'slugs.logout'				=> 'logout',
		'slugs.lostpassword'		=> 'lostpassword',
		'slugs.retrievepassword'	=> 'retrievepassword',
		'slugs.resetpass'			=> 'resetpass',
		'slugs.rp'					=> 'rp',
		'slugs.register'			=> 'register',
		'slugs.login'				=> 'login',
		'deny_wp_login_access'		=> 1,
		'deny_admin_access'			=> 0,
	) );

	// Plugins can add their own action
	$additional_slugs = apply_filters( 'sfml_additional_slugs', array() );
	if ( !empty( $additional_slugs ) ) {
		foreach ( $additional_slugs as $slug => $label ) {
			if ( empty( $options['slugs.' . $slug] ) ) {
				$options['slugs.' . $slug] = $slug;
			}
		}
	}
	return $options;
}


// !Escape functions (display)

add_filter( 'sfml_escape_functions', 'sfml_escape_functions' );

function sfml_escape_functions( $functions = array() ) {
	$func_sanitize_title	= array( 'function'  => 'sanitize_title', 'params' => array( '', 'display' ) );
	$func_intval			= array( 'function'  => 'intval' );

	$functions = array_merge( $functions, array(
		'slugs.postpass'			=> $func_sanitize_title,
		'slugs.logout'				=> $func_sanitize_title,
		'slugs.lostpassword'		=> $func_sanitize_title,
		'slugs.retrievepassword'	=> $func_sanitize_title,
		'slugs.resetpass'			=> $func_sanitize_title,
		'slugs.rp'					=> $func_sanitize_title,
		'slugs.register'			=> $func_sanitize_title,
		'slugs.login'				=> $func_sanitize_title,
		'deny_wp_login_access'		=> $func_intval,
		'deny_admin_access'			=> $func_intval,
	) );

	// Plugins can add their own action
	$additional_slugs = apply_filters( 'sfml_additional_slugs', array() );
	if ( !empty( $additional_slugs ) ) {
		foreach ( $additional_slugs as $slug => $label ) {
			if ( empty( $functions['slugs.' . $slug] ) ) {
				$functions['slugs.' . $slug] = $func_sanitize_title;
			}
		}
	}
	return $functions;
}


// !Sanitization functions (save)

add_filter( 'sfml_sanitization_functions', 'sfml_sanitization_functions' );

function sfml_sanitization_functions( $functions = array() ) {
	$func_sanitize_title	= array( 'function'  => 'sanitize_title' );
	$func_intval			= array( 'function'  => 'intval' );

	$functions = array_merge( $functions, array(
		'slugs.postpass'			=> $func_sanitize_title,
		'slugs.logout'				=> $func_sanitize_title,
		'slugs.lostpassword'		=> $func_sanitize_title,
		'slugs.retrievepassword'	=> $func_sanitize_title,
		'slugs.resetpass'			=> $func_sanitize_title,
		'slugs.rp'					=> $func_sanitize_title,
		'slugs.register'			=> $func_sanitize_title,
		'slugs.login'				=> $func_sanitize_title,
		'deny_wp_login_access'		=> $func_intval,
		'deny_admin_access'			=> $func_intval,
	) );

	// Plugins can add their own action
	$additional_slugs = apply_filters( 'sfml_additional_slugs', array() );
	if ( !empty( $additional_slugs ) ) {
		foreach ( $additional_slugs as $slug => $label ) {
			if ( empty( $functions['slugs.' . $slug] ) ) {
				$functions['slugs.' . $slug] = $func_sanitize_title;
			}
		}
	}
	return $functions;
}


// !Validate Settings on update

add_filter( 'sfml_validate_settings', 'sfml_validate_settings', 10, 3 );

function sfml_validate_settings( $opts, $default_options, $context ) {
	if ( strpos($context, 'save-') === 0 ) {
		$slugs = Noop_Options::get_sub_options( 'slugs', $opts );
		if ( count( $slugs ) ) {
			$error		= false;
			$singles	= array();
			$forbidden	= array();
			$exclude	= array_diff_key( $slugs, sfml_slugs_fields_labels() );	// postpass, retrievepassword, rp

			foreach ( $slugs as $action => $slug ) {
				if ( isset($exclude[$action]) )
					continue;
				// Forbidden slugs
				if ( isset($exclude[$slug]) ) {
					$opts['slugs.'.$action] = $default_options['slugs.'.$action];
					$forbidden[] = $slug;
					continue;
				}
				// Duplicate slugs
				if ( isset($singles[$slug]) ) {
					$opts['slugs.'.$action] = $default_options['slugs.'.$action];
					$error = true;
				} else
					$singles[$slug] = 1;
			}

			// Trigger errors
			if ( $context == 'save-form' ) {
				if ( $nbr_forbidden = count($forbidden) )
					add_settings_error( 'sfml_settings', 'forbidden-slugs', sprintf( _n("The slug %s is forbidden.", "The slugs %s are forbidden.", $nbr_forbidden, 'sf-move-login'), wp_sprintf('<code>%l</code>', $forbidden) ) );
				if ( $error )
					add_settings_error( 'sfml_settings', 'duplicates-slugs', __("The links can't have the same slugs.", 'sf-move-login') );
			}

			// Write the new rules (they're not saved in the db yet)
			if ( !function_exists('sfml_write_rules') )
				include( SFML_PLUGIN_DIR . 'inc/rewrite.inc.php' );

			sfml_write_rules( sfml_rules( Noop_Options::get_sub_options( 'slugs', $opts ) ) );
		}
	}
	return $opts;
}


/* !---------------------------------------------------------------------------- */
/* !	UTILITIES																 */
/* ----------------------------------------------------------------------------- */

// !Fields labels (for the slugs)

function sfml_slugs_fields_labels() {
	$labels = array(
		'login'				=> __('Log in'),
		'logout'			=> __('Log out'),
		'register'			=> __('Register'),
		'lostpassword'		=> __('Lost Password'),
		'resetpass'			=> __('Password Reset'),
	);

	// Plugins can add their own action
	$additional_slugs = apply_filters( 'sfml_additional_slugs', array() );
	if ( !empty( $additional_slugs ) ) {
		$additional_slugs = array_diff_key( $additional_slugs, $labels );
		$labels = array_merge( $labels, $additional_slugs );
	}
	return $labels;
}


/* !---------------------------------------------------------------------------- */
/* !	NOOP INIT																 */
/* ----------------------------------------------------------------------------- */

if ( !function_exists('noop_includes') )
	include( NOOP_DIR . 'includes.php' );


noop_includes( sfml_noop_params() );
/**/