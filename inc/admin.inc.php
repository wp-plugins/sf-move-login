<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

/* ----------------------------------------------------------------------------- */
/*																				 */
/*							Activation / Deactivation							 */
/*																				 */
/* ----------------------------------------------------------------------------- */

register_activation_hook( SFML_FILE, 'sfml_activate' );
function sfml_activate() {
	$dies = array();
	$notices = array();
	$is_IIS = iis7_supports_permalinks();
	$home_path = get_home_path();
	load_plugin_textdomain( 'sfml', false, basename( dirname( SFML_FILE ) ) . '/languages/' );	// wp_die() will need i18n
	$die_msg = __('<strong>SF Move Login</strong> has not been activated.', 'sfml').'<br/>';

	if ( empty($GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI']) && empty($_SERVER['REQUEST_URI']) )
		$dies[] = __('It seems your server configuration prevent the plugin to work properly. <i>SF Move Login</i> will not be activated.', 'sfml');

	if ( $is_IIS ) {
		if ( !( ( !file_exists($home_path . 'web.config') && win_is_writable($home_path) ) || win_is_writable($home_path . 'web.config') ) )
			$notices[] = 'htaccess_not_writable';
	} else {
		if ( !( ( !file_exists($home_path . '.htaccess') && is_writable($home_path) ) || is_writable($home_path . '.htaccess') ) )
			$notices[] = 'htaccess_not_writable';
	}

	if ( !is_multisite() ) {
		if ( !get_option('permalink_structure') )
			$dies[] = sprintf(__('Please make sure to enable %s.', 'sfml'), '<a href="options-permalink.php">'.__('Permalinks').'</a>');
	}

	if ( count($dies) ) {
		wp_die( $die_msg.implode('<br/>', $dies), __('Error'), array('back_link' => true) );
	} else {
		if ( !is_multisite() ) {
			sfml_rewrite();
			flush_rewrite_rules();
		}
		else {
			// IIS
			if ( $is_IIS ) {
				sfml_insert_multisite_iis7_rewrite_rules( sfml_multisite_iis7_rewrite_rules() );
			}
			// Apache
			else {
				if ( got_mod_rewrite() )
					sfml_insert_multisite_rewrite_rules( sfml_multisite_rewrite_rules() );
				else
					wp_die( $die_msg . __('It seems the url rewrite module is not activated on your server. <i>SF Move Login</i> will not be activated.', 'sfml'), __('Error'), array('back_link' => true) );
			}
		}

		if ( count($notices) )
			set_transient('sfml_notices-'.get_current_user_id(), $notices);
	}
}


register_deactivation_hook( SFML_FILE, 'sfml_deactivate' );
function sfml_deactivate() {
	if ( is_multisite() ) {
		// IIS
		if ( iis7_supports_permalinks() )
			sfml_insert_multisite_iis7_rewrite_rules();	// Empty content
		// Apache
		else
			sfml_insert_multisite_rewrite_rules();		// Empty content
	}
	else {
		// Remove the plugin rules
		$rules = sfml_rules();
		foreach ( $rules as $action => $rule ) {
			remove_rewrite_rule( $action.'/?$', $rule, 'top' );
		}
		// Flush .htaccess
		flush_rewrite_rules();
	}
}


// !On deactivation, the plugin rules must be removed before flushing them (they're still in $wp_rewrite).
if ( !function_exists('remove_rewrite_rule') ):
function remove_rewrite_rule( $regex, $redirect, $after = 'bottom' ) {
	global $wp_rewrite;
	$index = (strpos($redirect, '?') == false ? strlen($redirect) : strpos($redirect, '?'));
	$front = substr($redirect, 0, $index);
	if ( $front != $wp_rewrite->index ) { //it doesn't redirect to WP's index.php
		unset($wp_rewrite->non_wp_rules[$regex]);
	} else {
		if ( 'bottom' == $after)
			unset($wp_rewrite->extra_rules[$regex]);
		else
			unset($wp_rewrite->extra_rules_top[$regex]);
	}
}
endif;


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								Admin notices									 */
/*																				 */
/* ----------------------------------------------------------------------------- */

add_action('admin_init', 'sfml_notices');
function sfml_notices() {
	$user_id = get_current_user_id();
	$notices = get_transient('sfml_notices-'.$user_id);

	if ( $notices && is_array($notices) && count($notices) ) {
		$action = is_network_admin() ? 'network_admin_notices' : 'admin_notices';
		foreach ( $notices as $notice ) {
			add_action( $action, 'sfml_'.$notice.'_notice' );
		}
		delete_transient('sfml_notices-'.$user_id);
	}
}


function sfml_htaccess_not_writable_notice() {
	$file = iis7_supports_permalinks() ? '<code>web.config</code>' : '<code>.htaccess</code>';
	$link = is_network_admin() ? '<a href="settings.php?page=move-login">SF Move Login</a>' : '<a href="options-permalink.php">'.__('Permalinks').'</a>';
	echo '<div class="error"><p>'
			.sprintf(
				__('<i>SF Move Login</i> needs access to the %1$s file. Please visit the %2$s settings page and copy/paste the given code into the %1$s file.', 'sfml'),
				$file,
				$link
			)
		.'</p></div>';
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*							Rewrite rules: APACHE								 */
/*																				 */
/* ----------------------------------------------------------------------------- */

// !Return the multisite rewrite rules (as an array)

function sfml_multisite_rewrite_rules() {
	global $wpdb;
	$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subdomain_install	= is_multisite() ? is_subdomain_install() : (bool) $wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = 1 AND meta_key = 'subdomain_install'" );
	$subdir_match		= $subdomain_install ? '' : '([_0-9a-zA-Z-]+/)?';
	$rules				= sfml_rules();

	$out				= array(
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
		'RewriteBase '.$base,
		'',
	);
	foreach ( $rules as $action => $rule ) {
		$out[] = "RewriteRule ^".$subdir_match.$action.'/?$ $1'.$rule.' [QSA,L]';
	}
	$out[] = '</IfModule>';

	return $out;
}


// !Insert content in htaccess file, before the WP block
// @var $rules array|string

function sfml_insert_multisite_rewrite_rules( $rules = '' ) {
	$home_path = get_home_path();
	$htaccess_file = $home_path.'.htaccess';

	global $wp_rewrite;
	$has_htaccess = file_exists( $htaccess_file );

	// IF no htaccess and parent directory is writeable and we have rules to write and permalinks are being used and rewrite module is enabled
	// OR htaccess is writeable.
	if ( ( !$has_htaccess && is_writeable( $home_path ) && $rules && $wp_rewrite->using_mod_rewrite_permalinks() ) || is_writeable( $htaccess_file ) ) {
		// Current htaccess content
		$htaccess_content = $has_htaccess ? file_get_contents( $htaccess_file ) : '';

		// No WordPress rules? There's a problem somewhere.
		if ( false === strpos($htaccess_content, '# BEGIN WordPress') && $rules )
			return insert_with_markers( $htaccess_file, 'SF Move Login', $rules );

		// Remove the SF Move Login marker
		$htaccess_content = preg_replace( "/# BEGIN SF Move Login.*# END SF Move Login\n*/is", '', $htaccess_content );

		// New content
		$rules = is_array($rules) ? implode("\n", $rules) : $rules;
		$rules = trim($rules, "\r\n ");
		if ( $rules ) {
			// The new content need to be inserted before the WordPress rules
			$rules = "# BEGIN SF Move Login\n".$rules."\n# END SF Move Login\n\n\n# BEGIN WordPress";
			$htaccess_content = str_replace('# BEGIN WordPress', $rules, $htaccess_content);
		}

		// Update the .htacces file
		return (bool) file_put_contents( $htaccess_file , $htaccess_content );
	}
	return false;
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*								Rewrite rules: IIS								 */
/*																				 */
/* ----------------------------------------------------------------------------- */

// !Return the multisite rewrite rules for IIS systems (as a part of a xml system)

function sfml_multisite_iis7_rewrite_rules() {
	global $wpdb;
	$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subdomain_install	= is_multisite() ? is_subdomain_install() : (bool) $wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = 1 AND meta_key = 'subdomain_install'" );
	$subdir_match		= $subdomain_install ? '' : '([_0-9a-zA-Z-]+/)?';
	$iis_subdir_match	= ltrim( $base, '/' ) . $subdir_match;
	$iis_subdir_replacement = $subdomain_install ? '' : '{R:1}';
	$rules				= sfml_rules();

	$rule_i				= 1;
	$space				= str_repeat(' ', 16);
	$out				= array();
	foreach ( $rules as $action => $rule ) {
		$out[]	= $space . '<rule name="SF Move Login Rule ' . $rule_i . '" stopProcessing="true">'."\n"
				. $space . '    <match url="^' . $iis_subdir_match . $action . '/?$" ignoreCase="false" />'."\n"
				. $space . '    <action type="Redirect" url="' . $iis_subdir_replacement . $rule . '" redirectType="Permanent" />'."\n"
				. $space . '</rule>'."\n";
		$rule_i++;
	}

	return $out;
}


// !Insert content in web.config file, before the WP block
// @var $rules array|string

function sfml_insert_multisite_iis7_rewrite_rules( $rules = '' ) {
	$home_path = get_home_path();
	$web_config_file = $home_path.'web.config';

	global $wp_rewrite;
	$has_web_config = file_exists( $web_config_file );

	if ( !class_exists('DOMDocument') )
		return false;

	// New content
	$rules = is_array($rules) ? implode("\n", $rules) : $rules;
	$rules = trim($rules, "\r\n");

	// IF no web.config and parent directory is writeable and we have rules to write and permalinks are being used and rewrite module is enabled
	// OR web.config is writeable.
	if ( ( !$has_web_config && win_is_writeable( $home_path ) && $rules && $wp_rewrite->using_mod_rewrite_permalinks() ) || win_is_writeable( $web_config_file ) ) {

		// If configuration file does not exist then we create one.
		if ( !$has_web_config ) {
			$fp = fopen( $web_config_file, 'w');
			fwrite($fp, '<configuration/>');
			fclose($fp);
		}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;

		if ( $doc->load($web_config_file) === false )
			return false;

		$xpath = new DOMXPath($doc);

		// Remove old rules
		$old_rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'SF Move Login\')]');
		if ( $old_rules->length > 0 ) {
			$child = $old_rules->item(0);
			$parent = $child->parentNode;
			$parent->removeChild($child);
		}

		// No new rules?
		if ( !$rules ) {
			$doc->formatOutput = true;
			saveDomDocument($doc, $web_config_file);
			return true;
		}

		// Check the XPath to the rewrite rule and create XML nodes if they do not exist
		$xmlnodes = $xpath->query('/configuration/system.webServer/rewrite/rules');
		if ( $xmlnodes->length > 0 ) {
			$rules_node = $xmlnodes->item(0);
		} else {
			$rules_node = $doc->createElement('rules');

			$xmlnodes = $xpath->query('/configuration/system.webServer/rewrite');
			if ( $xmlnodes->length > 0 ) {
				$rewrite_node = $xmlnodes->item(0);
				$rewrite_node->appendChild($rules_node);
			} else {
				$rewrite_node = $doc->createElement('rewrite');
				$rewrite_node->appendChild($rules_node);

				$xmlnodes = $xpath->query('/configuration/system.webServer');
				if ( $xmlnodes->length > 0 ) {
					$system_webServer_node = $xmlnodes->item(0);
					$system_webServer_node->appendChild($rewrite_node);
				} else {
					$system_webServer_node = $doc->createElement('system.webServer');
					$system_webServer_node->appendChild($rewrite_node);

					$xmlnodes = $xpath->query('/configuration');
					if ( $xmlnodes->length > 0 ) {
						$config_node = $xmlnodes->item(0);
						$config_node->appendChild($system_webServer_node);
					} else {
						$config_node = $doc->createElement('configuration');
						$doc->appendChild($config_node);
						$config_node->appendChild($system_webServer_node);
					}
				}
			}
		}

		$rule_fragment = $doc->createDocumentFragment();
		$rule_fragment->appendXML($rules);

		// Insert before the WP rules
		$wordpress_rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')]');
		if ( $wordpress_rules->length > 0 ) {
			$child = $wordpress_rules->item(0);
			$parent = $child->parentNode;
			$parent->insertBefore($element, $child);
		}
		else {
			$rules_node->appendChild($rule_fragment);
		}

		$doc->encoding = "UTF-8";
		$doc->formatOutput = true;
		saveDomDocument($doc, $web_config_file);

		return true;
	}
	return false;
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*					Link to the plugin network "settings" page					 */
/*																				 */
/* ----------------------------------------------------------------------------- */


add_filter( 'network_admin_plugin_action_links_'.plugin_basename( SFML_FILE ), 'sfml_settings_action_links', 10, 2 );
function sfml_settings_action_links( $links, $file ) {
	$links['settings'] = '<a href="' . network_admin_url( 'settings.php?page=move-login' ) . '">' . __("Settings") . '</a>';
	return $links;
}


/* ----------------------------------------------------------------------------- */
/*																				 */
/*						Plugin network "settings" page							 */
/*																				 */
/* ----------------------------------------------------------------------------- */

add_action('network_admin_menu', 'sfml_network_admin_menu');
function sfml_network_admin_menu() {
	add_submenu_page( 'settings.php', 'SF Move Login', 'SF Move Login', 'manage_network_options', 'move-login', 'sfml_settings_page' );
}


function sfml_settings_page() {
	global $title, $wpdb; ?>
	<div class="wrap">
		<?php screen_icon('tools'); ?>
		<h2><?php echo esc_html( $title ); ?></h2>

		<?php
		// IIS
		if ( iis7_supports_permalinks() ) {
			$htaccess_file	= implode( "\n", sfml_multisite_iis7_rewrite_rules() );
			$height			= 20;
			$file			= '<code>web.config</code>';
			$above			= '<code>&lt;rule name="WordPress Rule 1" stopProcessing="true"&gt;</code>';
		}
		// Apache
		else {
			$htaccess_file  = "\n# BEGIN SF Move Login\n";
			$htaccess_file .= implode( "\n", sfml_multisite_rewrite_rules() );
			$htaccess_file .= "\n# END SF Move Login\n";
			$height			= substr_count( $htaccess_file, "\n" );
			$file			= '<code>.htaccess</code>';
			$above			= '<code># BEGIN WordPress</code>';
		}

		// Message
		$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
		$document_root_fix	= str_replace( '\\', '/', realpath( $_SERVER['DOCUMENT_ROOT'] ) );
		$abspath_fix		= str_replace( '\\', '/', ABSPATH );
		$home_path			= 0 === strpos( $abspath_fix, $document_root_fix ) ? $document_root_fix . $base : get_home_path();
		?>
		<ol>
			<li><p><?php _e( 'Make sure to enable permalinks for ALL your blogs.', 'sfml' ); ?></p></li>
			<li>
				<p><?php printf( __( 'If the plugin fails to add the new rewrite rules to your %1$s file on activation, add the following to your %1$s file in %2$s, replacing other %3$s rules if they exist, <strong>above</strong> the line reading %4$s:', 'sfml' ), $file, '<code>'.$home_path.'</code>', 'SF Move Login', $above ); ?></p>
				<textarea class="code readonly" readonly="readonly" cols="120" rows="<?php echo $height; ?>"><?php echo esc_textarea( $htaccess_file ); ?></textarea>
			</li>
		</ol>


	</div>
<?php
}
/**/