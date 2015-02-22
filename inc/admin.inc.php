<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

include( SFML_PLUGIN_DIR . 'inc/rewrite.inc.php' );


/* !---------------------------------------------------------------------------- */
/* !	PLUGIN SETTINGS PAGE WITH NOOP											 */
/* ----------------------------------------------------------------------------- */

// !Add fields

add_action( 'sfml_settings-sfml_add_fields', 'sfml_settings_fields', 10, 2 );

function sfml_settings_fields( $fields_args, $args ) {
	// Sections
	$is_new_admin = version_compare( $GLOBALS['wp_version'], '3.8', '>=' );
	Noop_Settings::add_section( 'slugs',	Noop_fields::section_icon('links') . __('Links') );
	Noop_Settings::add_section( 'access',	Noop_fields::section_icon($is_new_admin ? 'admin-network' : 'site') . __('Access', 'sf-move-login') );

	// Fields
	$fields = Noop_fields::get_instance( sfml_noop_params() );
	$labels = sfml_slugs_fields_labels();

	// Add a warning if the plugin is bypassed
	if ( defined('SFML_ALLOW_LOGIN_ACCESS') && SFML_ALLOW_LOGIN_ACCESS ) {
		$description = '<span class="description">' . __( 'The constant <code>SFML_ALLOW_LOGIN_ACCESS</code> is defined to <code>true</code>, the settings below won\'t take effect.', 'sf-move-login' ) . '</span>';
		Noop_fields::add_section_description( 'access', $description );
	}

	foreach ( $labels as $action => $label ) {
		Noop_Settings::add_field(
			'slugs.'.$action,
			$label,
			array( $fields, 'text_field' ),
			'slugs',
			array(
				'label_for'		=> 'slugs.'.$action,
				'attributes'	=> array( 'pattern' => '[0-9a-z_-]*' ),
			)
		);
	}

	Noop_Settings::add_field(
		'deny_wp_login_access',
		'<code>wp-login.php</code>',
		array( $fields, 'radio_field' ),
		'access',
		array(
			'label_for'		=> 'deny_wp_login_access',
			'values'		=> array(
				1 => __('Display an error message', 'sf-move-login'),
				2 => __('Redirect to a &laquo;Page not found&raquo; error page', 'sf-move-login'),
				3 => __('Redirect to the home page', 'sf-move-login'),
			),
			'next_under'	=> true,
			'label'			=> '<strong>' . __('When a not connected user attempts to access the old login page.', 'sf-move-login') . '</strong>',
		)
	);

	Noop_Settings::add_field(
		'deny_admin_access',
		__('Administration area', 'sf-move-login'),
		array( $fields, 'radio_field' ),
		'access',
		array(
			'label_for'		=> 'deny_admin_access',
			'values'		=> array(
				0 => __('Do nothing, redirect to the new login page', 'sf-move-login'),
				1 => __('Display an error message', 'sf-move-login'),
				2 => __('Redirect to a &laquo;Page not found&raquo; error page', 'sf-move-login'),
				3 => __('Redirect to the home page', 'sf-move-login'),
			),
			'next_under'	=> true,
			'label'			=> '<strong>' . __('When a not connected user attempts to access the administration area.', 'sf-move-login') . '</strong>',
		)
	);

	// Credits tab in help
	add_filter( 'move-login_contextual_credits_tab_title', '__return_false' );
	add_filter( 'move-login_contextual_credits_tab_content', 'sfml_credits' );
}


// !Add the rewrite rules in a textarea

add_action( 'move-login_after_form', 'sfml_after_form' );

function sfml_after_form( $current_tab ) {
	if ( $current_tab == 'sfml' ) {
		global $is_iis7;
		$file = $is_iis7 ? '<code>web.config</code>' : '<code>.htaccess</code>';
		?>
		<div class='noop-form'>
		<h3><?php echo Noop_fields::section_icon('tools') . sprintf( __('%s File', 'sf-move-login'), $file ); ?></h3>
		<?php sfml_rewrite_rules_textarea(); ?>
		</div>
		<?php
	}
}


// !A textarea displaying the rewrite rules. Used with or without Noop.

function sfml_rewrite_rules_textarea( $echo = true ) {
	global $is_apache, $is_iis7, $is_nginx;
	$is_nginx = is_null($is_nginx) ? (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) : $is_nginx;
	$rules				= sfml_rules();

	// IIS
	if ( $is_iis7 ) {
		$htaccess_file	= implode( "\n", sf_iis7_rewrite_rules( $rules, 'SF Move Login' ) );
		$height			= 20;
		$file			= '<code>web.config</code>';
		$above			= '<code>&lt;rule name="WordPress Rule 1" stopProcessing="true"&gt;</code>';
	}
	// Apache
	elseif( $is_apache || $is_nginx ) {						// I don't know how nginx works, so at least, display some "wrong" infos.
		$htaccess_file  = "\n# BEGIN SF Move Login\n";
		$htaccess_file .= implode( "\n", sf_htaccess_rewrite_rules( $rules ) );
		$htaccess_file .= "\n# END SF Move Login\n";
		$height			= substr_count( $htaccess_file, "\n" );
		$file			= '<code>.htaccess</code>';
		$above			= '<code># BEGIN WordPress</code>';
	}
	else
		return '';

	// Message
	$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$document_root_fix	= str_replace( '\\', '/', realpath( $_SERVER['DOCUMENT_ROOT'] ) );
	$abspath_fix		= str_replace( '\\', '/', ABSPATH );
	$home_path			= 0 === strpos( $abspath_fix, $document_root_fix ) ? $document_root_fix . $base : get_home_path();

	$content  = '<p>' . sprintf( __( 'If the plugin fails to add the new rewrite rules to your %1$s file on activation, add the following to your %1$s file in %2$s, replacing other %3$s rules if they exist, <strong>above</strong> the line reading %4$s:', 'sf-move-login' ), $file, '<code>'.$home_path.'</code>', 'SF Move Login', $above ) . "</p>\n";
	$content .= '<textarea class="code readonly auto-select" readonly="readonly" cols="120" rows="' . $height . '">' . esc_textarea( $htaccess_file ) . "</textarea>\n";

	wp_enqueue_script( 'noop-settings' );

	// Get out
	if ( !$echo )
		return $content;
	echo $content;
}


// !Credits

function sfml_credits( $credits ) {
	$credits	= array();
	$credits[]	= array( 'author' => 'Grégory Viguier (Screenfeed)',	'author_uri' => 'http://www.screenfeed.fr/' );
	$credits[]	= array( 'author' => 'Julio Potier (BoiteAWeb)',		'author_uri' => 'http://www.boiteaweb.fr/' );
	$credits[]	= array( 'author' => 'SecuPress',						'author_uri' => 'http://www.secupress.fr/' );
	return $credits;
}


// !No debug metaboxes

add_filter( 'move-login_show_debug_metaboxes', '__return_false' );


/* !---------------------------------------------------------------------------- */
/* !	PLUGIN "SETTINGS" PAGE WITHOUT NOOP										 */
/* ----------------------------------------------------------------------------- */

add_action( 'init', 'sfml_handle_admin_menu' );

function sfml_handle_admin_menu() {
	if ( sf_can_use_noop( SFML_NOOP_VERSION ) )
		return;

	$prefix = is_multisite() ? 'network_' : '';
	add_action( $prefix . 'admin_menu', 'sfml_admin_menu' );
}


function sfml_admin_menu() {
	$page = is_multisite() ? 'settings.php' : 'options-general.php';
	$cap  = is_multisite() ? 'manage_network_options' : 'manage_options';
	add_submenu_page( $page, 'Move Login', 'Move Login', $cap, 'move-login', 'sfml_settings_page' );
}


function sfml_settings_page() {
	global $title, $wpdb; ?>
	<div class="wrap">
		<?php screen_icon('tools'); ?>
		<h2><?php echo esc_html( $title ); ?></h2>

		<?php
		sfml_rewrite_rules_textarea();

		if ( !file_exists( path_join(WP_PLUGIN_DIR, 'noop/noop.php') ) ) {

			if ( !function_exists('sf_pull_noop_info') ) {
				include( SFML_PLUGIN_DIR . 'inc/noop-infos.inc.php' );
			}

			echo '<p>' . sprintf(
				__( 'To enable the real settings page for Move Login, please install the plugin %1$s (can also be %4$s).', 'sf-move-login' ),
				sf_plugin_install_link( 'noop', 'Noop', is_multisite() ? 'multi' : 'mono' ),
				null,
				null,
				'<a href="' . sfml_noop_download_url() . '">' . __( 'downloaded separately', 'sf-move-login' ) . '</a>'
			) . "</p>\n";
		}
		?>
	</div>
	<?php
}


/* --------------------------------------------------------------------------------- */
/* !	TOOLS																		 */
/* --------------------------------------------------------------------------------- */

/*
 * Return a plugin installation link.
 * @param (string) $plugin_slug: "my-plugin" (from the dirname).
 * @param (string) $plugin_name: "My Plugin".
 * @param (string) $activation_type: "multi" to force network activation, "mono" to force monosite activation. Default to false: automatic, depending on the context.
 * @return (string) The link tag.
 */
if ( !function_exists('sf_plugin_install_link') ):
function sf_plugin_install_link( $plugin_slug, $plugin_name, $activation_type = false ) {
	$url = 'update.php?action=install-plugin&plugin=' . $plugin_slug;

	if ( $activation_type !== 'multi' && $activation_type !== 'mono' ) {
		$activation_type = is_network_admin() ? 'multi' : 'mono';
	}
	if ( $activation_type === 'multi' && current_user_can('manage_network_plugins') ) {
		$url = network_admin_url( $url );
	}
	elseif ( $activation_type === 'mono' && current_user_can('install_plugins') ) {
		$url = admin_url( $url );
	}
	else {
		return '<strong>' . $plugin_name . '</strong>';
	}

	$url = wp_nonce_url( $url, 'install-plugin_' . $plugin_slug );

	return '<a href="' . $url . '" title="' . sprintf( esc_attr__('Install %s'), $plugin_name ) . '" class="install-now">' . $plugin_name . '</a>';
}
endif;


/*
 * Return a plugin activation link.
 * @param (string) $plugin_path: "my-plugin/my-plugin-file.php".
 * @param (string) $plugin_name: "My Plugin".
 * @param (string) $activation_type: "multi" to force network activation, "mono" to force monosite activation. Default to false: automatic, depending on the context.
 * @return (string) The link tag.
 */
if ( !function_exists('sf_plugin_activation_link') ):
function sf_plugin_activation_link( $plugin_path, $plugin_name, $activation_type = false ) {
	if ( $activation_type !== 'multi' && $activation_type !== 'mono' ) {
		$activation_type = is_network_admin() ? 'multi' : 'mono';
	}
	if ( $activation_type === 'multi' && current_user_can('manage_network_plugins') ) {
		$title_tag = esc_attr__('Activate this plugin for all sites in this network');
	}
	elseif ( $activation_type === 'mono' && current_user_can('activate_plugins') ) {
		$title_tag = esc_attr__('Activate this plugin');
	}
	else {
		return '<strong>' . $plugin_name . '</strong>';
	}

	$plugin_path = trim( $plugin_path, '/' );
	$url = 'plugins.php?action=activate&plugin=' . $plugin_path;
	$url = $activation_type === 'multi' ? network_admin_url( $url ) : admin_url( $url );
	$url = wp_nonce_url( $url, 'activate-plugin_' . $plugin_path );

	return '<a href="' . $url . '" title="' . $title_tag . '" class="edit">' . $plugin_name . '</a>';
}
endif;


function sfml_noop_download_url() {
	static $url;
	if ( empty( $url ) ) {
		$url = 'http://www.screenfeed.fr/downloads/noop/?ver=' . time();
	}
	return $url;
}
/**/