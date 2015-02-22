<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

/* !---------------------------------------------------------------------------- */
/* !	ACTIVATION																 */
/* ----------------------------------------------------------------------------- */

// !Activate

// Trigger wp_die() on plugin activation, set a transient for admin notices
function sfml_activate() {
	global $is_apache, $is_iis7, $is_nginx;
	$is_nginx = is_null($is_nginx) ? (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) : $is_nginx;
	$dies = array();
	$notices = array();

	// The plugin needs the request uri
	if ( empty($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']) && empty($_SERVER['REQUEST_URI']) )
		$dies[] = 'error_no_request_uri';

	// IIS7
	if ( $is_iis7 && !iis7_supports_permalinks() )
		$dies[] = 'error_no_mod_rewrite';

	// Apache
	elseif ( $is_apache && !got_mod_rewrite() )
		$dies[] = 'error_no_mod_rewrite';

	// None
	elseif ( !$is_iis7 && !$is_apache && !$is_nginx )
		$dies[] = 'error_no_apache_nor_ii7';

	// Do not trigger the die()s if it isn't the plugin activation (also triggered for Noop activation/deactivation and the plugin upgrade)
	if ( strpos( current_filter(), 'activate_'.SFML_PLUGIN_BASENAME ) === false ) {
		$notices = array_merge($notices, $dies);
		$dies = array();
	}

	// die()s
	if ( count( $dies ) ) {

		load_plugin_textdomain( 'sf-move-login', false, SFML_PLUGIN_BASEDIR . '/languages/' );

		$dies = array_filter( array_map( 'sfml_notice_message', $dies ) );
		$die_msg = __('<strong>Move Login</strong> has not been activated.', 'sf-move-login').'<br/>';
		wp_die( $die_msg.implode('<br/>', $dies), __('Error'), array('back_link' => true) );

	}
	// Notices + rewrite rules
	// The new notices and the rules rewriting are way easier to deal with after redirection. See sfml_notices().
	else {

		set_transient('sfml_notices-'.get_current_user_id(), $notices);

	}
}

register_activation_hook( SFML_FILE, 'sfml_activate' );


// !Update rewrite rules on Noop activation/deactivation

function sfml_noop_activate() {
	if ( sf_can_use_noop( SFML_NOOP_VERSION ) )
		sfml_activate();
}

register_activation_hook( dirname(SFML_PLUGIN_DIR).'/noop/noop.php', 'sfml_noop_activate' );
register_deactivation_hook( dirname(SFML_PLUGIN_DIR).'/noop/noop.php', 'sfml_noop_activate' );


/* !---------------------------------------------------------------------------- */
/* !	DEACTIVATION															 */
/* ----------------------------------------------------------------------------- */

function sfml_deactivate() {
	global $is_apache, $is_iis7;
	// IIS
	if ( $is_iis7 )
		sf_insert_iis7_rewrite_rules( 'SF Move Login' );		// Empty content
	// Apache
	elseif ( $is_apache )
		sf_insert_htaccess_rewrite_rules( 'SF Move Login' );	// Empty content
}

register_deactivation_hook( SFML_FILE, 'sfml_deactivate' );


/* !---------------------------------------------------------------------------- */
/* !	UNINSTALL																 */
/* ----------------------------------------------------------------------------- */

function sfml_uninstall() {
	if ( sf_can_use_noop( SFML_NOOP_VERSION ) ) {
		if ( !class_exists('Noop_Options') )
			include( NOOP_DIR . 'libs/class-noop-options.php' );

		// Delete options and users preferences
		Noop_Options::uninstall( 'sfml', 'settings', 'move-login' );
	}
	delete_option( 'sfml_version' );
}

register_uninstall_hook( SFML_FILE, 'sfml_uninstall' );


/* !---------------------------------------------------------------------------- */
/* !	UPGRADE																	 */
/* ----------------------------------------------------------------------------- */

add_action( 'load-plugins.php', 'sfml_upgrade' );

function sfml_upgrade() {

	$db_version = get_option( 'sfml_version' );
	if ( $db_version && !version_compare( $db_version, SFML_VERSION ) )
		return;

	sfml_activate();

	// Old version compat (1.0.1)
	if ( !$db_version )
		flush_rewrite_rules();

	update_option( 'sfml_version', SFML_VERSION );
}


/* !---------------------------------------------------------------------------- */
/* !	ADMIN NOTICES + UPDATE REWRITE RULES									 */
/* ----------------------------------------------------------------------------- */

// !Admin notices

add_action( 'all_admin_notices', 'sfml_notices' );

function sfml_notices() {
	global $pagenow;
	if ( $pagenow != 'plugins.php' )
		return;

	// Get previous notices
	$user_id = get_current_user_id();
	$notices = get_transient('sfml_notices-'.$user_id);

	// If it's an array (even empty), that means it's a Move Login activation or a Noop (de)activation
	if ( is_array($notices) ) {
		global $is_apache, $is_iis7, $is_nginx;
		$is_nginx = is_null($is_nginx) ? (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) : $is_nginx;
		$home_path = get_home_path();

		// IIS7
		if ( $is_iis7 && iis7_supports_permalinks() && !( ( !file_exists($home_path . 'web.config') && win_is_writable($home_path) ) || win_is_writable($home_path . 'web.config') ) )
			$notices[] = 'error_file_not_writable';

		// Apache
		elseif ( $is_apache && got_mod_rewrite() && !( ( !file_exists($home_path . '.htaccess') && is_writable($home_path) ) || is_writable($home_path . '.htaccess') ) )
			$notices[] = 'error_file_not_writable';

		// Nginx
		elseif ( $is_nginx )
			$notices[] = 'updated_is_nginx';

		// Noop
		if ( get_noop_status( SFML_NOOP_VERSION ) != 'ok' )
			$notices[] = 'updated_no_noop';

		// Display notices
		if ( count($notices) ) {

			$messages = array();
			foreach ( $notices as $notice ) {
				$messages[substr($notice, 0, strpos($notice, '_'))][] = sfml_notice_message( $notice );
			}

			$messages = array_map( 'array_filter', $messages );
			$messages = array_filter( $messages );
			foreach ( $messages as $class => $message ) {
				if ( !empty($message) )
					echo '<div class="'.$class.'"><p>'.implode('<br/>', $message).'</p></div>';
			}

		}
		delete_transient('sfml_notices-'.$user_id);

		// Update rewrite rules. Will instanciate Noop too.
		sfml_write_rules();
	}
}


// !Messages used for notices and die()s

function sfml_notice_message( $k ) {
	static $messages;

	if ( is_null( $messages ) ) {
		global $is_iis7;
		$file	= $is_iis7 ? '<code>web.config</code>' : '<code>.htaccess</code>';
		$link	= '<a href="' . ( is_multisite() ? network_admin_url( 'settings.php?page=move-login' ) : admin_url( 'options-general.php?page=move-login' ) ) . '">Move Login</a>';
		$status	= sfml_get_noop_status_text();	// Message if Noop is not running

		$messages = array(
			'error_file_not_writable'	=> sprintf( __('<strong>Move Login</strong> needs access to the %1$s file. Please visit the %2$s settings page and copy/paste the given code into the %1$s file.', 'sf-move-login'), $file, $link ),
			'error_no_request_uri'		=> __('It seems your server configuration prevent the plugin to work properly. <strong>Move Login</strong> won\'t work.', 'sf-move-login'),
			'error_no_mod_rewrite'		=> __('It seems the url rewrite module is not activated on your server. <strong>Move Login</strong> won\'t work.', 'sf-move-login'),
			'error_no_apache_nor_ii7'	=> __('It seems your server does not use <i>Apache</i>, <i>Nginx</i>, nor <i>IIS7</i>. <strong>Move Login</strong> won\'t work.', 'sf-move-login'),
			'updated_no_noop'			=> $status,
			'updated_is_nginx'			=> sprintf( __('It seems your server uses a <i>Nginx</i> system, that I don\'t know at all. So I have to let you deal with the rewrite rules by yourself. Please visit the %2$s settings page and take a look at the rewrite rules used for a %1$s file. <strong>Move Login</strong> is running but won\'t work correctly until you deal with the rewrite rules.', 'sf-move-login'), $file, $link ),
		);
	}

	return isset( $messages[$k] ) ? $messages[$k] : '';
}


/* !---------------------------------------------------------------------------- */
/* !	LINK IN THE PLUGINS LIST PAGE + INSTALL NOOP							 */
/* ----------------------------------------------------------------------------- */

// !Link to the plugin "settings" page if Noop is not installed

add_filter( 'plugin_action_links_'.SFML_PLUGIN_BASENAME, 'sfml_settings_action_links', 10, 2 );
add_filter( 'network_admin_plugin_action_links_'.SFML_PLUGIN_BASENAME, 'sfml_settings_action_links', 10, 2 );

function sfml_settings_action_links( $links, $file ) {
	if ( !sf_can_use_noop( SFML_NOOP_VERSION ) )
		$links['settings'] = '<a href="' . ( is_multisite() ? network_admin_url( 'settings.php?page=move-login' ) : admin_url( 'options-general.php?page=move-login' ) ) . '">' . __("Settings") . '</a>';
	return $links;
}


// !Link to download Noop if not installed

add_filter( 'plugin_row_meta', 'sfml_plugin_row_meta', PHP_INT_MAX, 4 );

function sfml_plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data = false, $status = false ) {
	if ( $plugin_file === SFML_PLUGIN_BASENAME ) {

		if ( $status_text = sfml_get_noop_status_text() ) {
			$plugin_meta[] = $status_text;
		}

	}
	return $plugin_meta;
}


// !The link for Noop will need the Noop infos

add_action( 'setup_theme', 'sfml_noop_infos' );

function sfml_noop_infos() {
	if ( function_exists('sf_pull_noop_info') ) {
		return;
	}

	$status = get_noop_status( SFML_NOOP_VERSION );
	if ( $status == 'required' || $status == 'corrupted' ) {
		include( SFML_PLUGIN_DIR . 'inc/noop-infos.inc.php' );
	}
}


/* !---------------------------------------------------------------------------- */
/* !	SHOW EVERY AUTHORS ON THE PLUGIN PAGE									 */
/* ----------------------------------------------------------------------------- */

add_filter( 'plugin_row_meta', 'sfml_authors_plugin_row_meta', 10, 2 );

function sfml_authors_plugin_row_meta( $plugin_meta, $plugin_file ) {
	if ( SFML_PLUGIN_BASENAME !== $plugin_file )
		return $plugin_meta;

	$links			= '<a href="http://www.screenfeed.fr/greg/" title="' . esc_attr__( 'Visit author homepage' ) . '">Grégory Viguier</a>';
	$link_pos		= array_search( sprintf( __( 'By %s' ), $links ), $plugin_meta );
	if ( $link_pos === false )
		return $plugin_meta;

	$links			= (array) $links;
	$authors		= array(
		array( 'name' => 'Julio Potier',	'url' => 'http://www.boiteaweb.fr' ),
		array( 'name' => 'SecuPress',		'url' => 'http://blog.secupress.fr' ),
	);

	foreach( $authors as $author ) {
		$links[] = '<a href="' . $author['url'] . '" title="' . esc_attr__( 'Visit author homepage' ) . '">' . $author['name'] . '</a>';
	}

	$links = sprintf( __( 'By %s' ), wp_sprintf( '%l', $links ) );
	$plugin_meta[$link_pos] = $links;

	return $plugin_meta;
}


/* !---------------------------------------------------------------------------- */
/* !	UTILITIES																 */
/* ----------------------------------------------------------------------------- */

// !Get Noop status (installed, inactive, etc)

if ( !function_exists('get_noop_status') ) :
function get_noop_status( $required_version ) {
	if ( !defined('NOOP_DIR') ) {
		global $plugins;

		// Noop is installed and active, there's a problem
		if ( isset($plugins['active']['noop/noop.php']) || isset($plugins['mustuse']['noop.php']) )
			return 'corrupted';
		// Noop is inactive
		elseif ( isset($plugins['inactive']['noop/noop.php']) )
			return 'inactive';
		// Noop is not installed
		else
			return 'required';
	}
	// Check Noop version
	elseif ( !defined('NOOP_VERSION') || version_compare( NOOP_VERSION, $required_version, '<' ) )
		return 'upgrade';

	return 'ok';
}
endif;


function sfml_get_noop_status_text() {
	$status_text	= '';
	$noop_status	= get_noop_status( SFML_NOOP_VERSION );
	$messages		= array(
		'ok'		=> false,
		'required'	=> __( 'To enable the real settings page for Move Login, please install the plugin %1$s (can also be %4$s).', 'sf-move-login' ),
		'inactive'	=> __( 'To enable the real settings page for Move Login, please activate the plugin %2$s.', 'sf-move-login' ),
		'upgrade'	=> __( 'To enable the real settings page for Move Login, please upgrade the plugin %2$s to the version %3$s (can also be %4$s).', 'sf-move-login' ),
		'corrupted'	=> __( 'It seems the plugin Noop is installed but doesn\'t work properly. To enable the real settings page for Move Login, please reinstall %1$s (can also be %4$s).', 'sf-move-login' ),
	);

	// Add the message
	if ( !empty($messages[$noop_status]) ) {

		$multi		= is_multisite() ? 'multi' : 'mono';	// On multisite, Noop must be "network activated" because SF Move Login is. Since the link can be displayed anywhere, we have to force it to "network".
		$link		= sf_plugin_install_link( 'noop', 'Noop', $multi );
		$noop_link	= sf_plugin_activation_link( 'noop/noop.php', 'Noop', $multi );

		$status_text = sprintf(
			$messages[$noop_status],
			$link,
			$noop_link,
			SFML_NOOP_VERSION,
			'<a href="' . sfml_noop_download_url() . '">' . __( 'downloaded separately', 'sf-move-login' ) . '</a>'
		);
	}

	return $status_text;
}
/**/