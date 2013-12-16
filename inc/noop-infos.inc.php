<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

/*
 * To include this file, tou can use this check:
 *  if ( is_admin() && ( $pagenow == 'plugins.php' || $pagenow == 'update.php' || $pagenow == 'plugin-install.php' ) ) {
 *  	include( 'path/to/plugins-list.inc.php' );
 *  }
 */

/* !---------------------------------------------------------------------------- */
/* !	HACK THE RETURNED OBJECT												 */
/* ----------------------------------------------------------------------------- */

add_filter( 'plugins_api', 'sfml_pull_noop_info', 10, 3 );
function sfml_pull_noop_info( $res, $action, $args ) {
	if( $action == 'plugin_information' && $args->slug == 'noop' )
		return sf_remote_retrieve_body( sf_remote_plugin_infos( $args ) );
	return $res;
}


/* !---------------------------------------------------------------------------- */
/* !	UTILITIES																 */
/* ----------------------------------------------------------------------------- */

// !Call home

if ( !function_exists('sf_remote_plugin_infos') ) :
function sf_remote_plugin_infos( $request ) {
	return wp_remote_post(
		'http://www.screenfeed.fr/downloads/',
		array( 'timeout' => 30, 'body' => array( 'action' => 'plugin_information', 'request' => serialize( (array) $request ) ) )
	);
}
endif;


// !Deal with sf_remote_plugin_infos() result

if ( !function_exists('sf_remote_retrieve_body') ) :
function sf_remote_retrieve_body( $response ) {
	$error_msg = sprintf(
		__( 'An unexpected error occurred. Something may be wrong with screenfeed.fr or this server&#8217;s configuration. If you continue to have problems, please leave a message on <a href="%s">my blog</a>.', 'sfml' ),
		'http://www.screenfeed.fr/blog/'
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'plugins_api_failed',
			$error_msg,
			$response->get_error_message()
		);
	}

	$response = wp_unslash( wp_remote_retrieve_body( $response ) );
	if ( is_serialized( $response ) )
		return (object) @unserialize( $response );

	return new WP_Error( 'plugins_api_failed', $error_msg, $response );
}
endif;


// For WP < 3.6
if ( !function_exists('wp_unslash') ) :
function wp_unslash( $value ) {
	return stripslashes_deep( $value );
}
endif;
/**/