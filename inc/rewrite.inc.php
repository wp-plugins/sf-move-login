<?php
if( !defined( 'ABSPATH' ) )
	die( 'Cheatin\' uh?' );

/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES															 */
/* ----------------------------------------------------------------------------- */

// !Return an array of action => url

if ( !function_exists('sfml_rules') ):
function sfml_rules( $actions = null ) {
	$actions = is_null($actions) ? sfml_get_slugs() : $actions;
	$rules = array(
		$actions['login'] => 'wp-login.php',
	);
	unset($actions['login']);

	foreach ( $actions as $action => $slug ) {
		$rules[$slug] = 'wp-login.php?action='.$action;
	}
	return $rules;
}
endif;


// !Write rules in file.
// @return (bool) false if no rewrite module or not IIS7/Apache

if ( !function_exists('sfml_write_rules') ):
function sfml_write_rules( $rules = null ) {
	global $is_apache, $is_iis7, $is_nginx;
	$is_nginx = is_null($is_nginx) ? (strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) : $is_nginx;
	$rules = is_null($rules) ? sfml_rules() : $rules;

	// Noop
	if ( sf_can_use_noop( SFML_NOOP_VERSION ) ) {
		// Make sure we have the filters for Noop
		if ( !function_exists('sfml_noop_params') )
			include( SFML_PLUGIN_DIR . 'inc/noop.inc.php' );
		// Make sure we have Noop
		if ( !function_exists('noop_includes') )
			include( NOOP_DIR . 'includes.php' );
		// Make sure Noop is instanciated
		$a = noop_includes( sfml_noop_params() );
	}

	// IIS
	if ( $is_iis7 && iis7_supports_permalinks() )
		return sf_insert_iis7_rewrite_rules( 'SF Move Login', sf_iis7_rewrite_rules( $rules, 'SF Move Login' ) );
	// Apache
	elseif ( $is_apache && got_mod_rewrite() )
		return sf_insert_htaccess_rewrite_rules( 'SF Move Login', sf_htaccess_rewrite_rules( $rules ) );
	// Nginx
	elseif ( $is_nginx )
		return true;

	return false;
}
endif;


// !Is WP a MultiSite and a subfolder install?

if ( !function_exists('sf_is_subfolder_install') ):
function sf_is_subfolder_install() {
	static $subfolder_install;
	if ( is_null($subfolder_install) ) {
		global $wpdb;
		if ( is_multisite() )
			$subfolder_install = ! (bool) is_subdomain_install();
		elseif ( !is_null($wpdb->sitemeta) )
			$subfolder_install = ! (bool) $wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = 1 AND meta_key = 'subdomain_install'" );
		else
			$subfolder_install = false;
	}
	return $subfolder_install;
}
endif;


/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES: APACHE													 */
/* ----------------------------------------------------------------------------- */

// !Return the multisite rewrite rules (as an array)

if ( !function_exists('sf_htaccess_rewrite_rules') ):
function sf_htaccess_rewrite_rules( $rules = array() ) {
	if ( !is_array($rules) || empty($rules) )
		return '';

	global $wpdb;
	$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subfolder_install	= sf_is_subfolder_install();
	$subdir_match		= $subfolder_install ? '([_0-9a-zA-Z-]+/)?' : '';

	$out				= array(
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
		'RewriteBase '.$base,
	);
	foreach ( $rules as $slug => $rule ) {
		$out[] = 'RewriteRule ^'.$subdir_match.$slug.'/?$ $1'.$rule.' [QSA,L]';
	}
	$out[] = '</IfModule>';

	return $out;
}
endif;


// !Insert content in htaccess file, before the WP block
// @param $marker string
// @param $rules array|string
// @param $before string

if ( !function_exists('sf_insert_htaccess_rewrite_rules') ):
function sf_insert_htaccess_rewrite_rules( $marker, $rules = '', $before = '# BEGIN WordPress' ) {
	if ( !$marker )
		return false;

	$home_path		= get_home_path();
	$htaccess_file	= $home_path.'.htaccess';

	$has_htaccess			= file_exists( $htaccess_file );
	$htaccess_is_writable	= $has_htaccess && is_writeable( $htaccess_file );
	$got_mod_rewrite		= got_mod_rewrite();

	if (
		( $htaccess_is_writable && !$rules ) ||		// Remove rules
		( $htaccess_is_writable && $rules && $got_mod_rewrite ) ||		// Add rules
		( !$has_htaccess && is_writeable( $home_path ) && $rules && $got_mod_rewrite )		// Create htaccess + add rules
	) {
		// Current htaccess content
		$htaccess_content = $has_htaccess ? file_get_contents( $htaccess_file ) : '';

		// No WordPress rules or no "before tag"?
		if ( ( !$before || false === strpos($htaccess_content, $before) ) && $rules )
			return insert_with_markers( $htaccess_file, $marker, $rules );

		// Remove the SF Move Login marker
		$htaccess_content = preg_replace( "/# BEGIN $marker.*# END $marker\n*/is", '', $htaccess_content );

		// New content
		if ( $before && $rules ) {
			$rules = is_array($rules) ? implode("\n", $rules) : $rules;
			$rules = trim($rules, "\r\n ");
			if ( $rules ) {
				// The new content need to be inserted before the WordPress rules
				$rules = "# BEGIN $marker\n".$rules."\n# END $marker\n\n\n$before";
				$htaccess_content = str_replace($before, $rules, $htaccess_content);
			}
		}

		// Update the .htacces file
		return (bool) file_put_contents( $htaccess_file , $htaccess_content );
	}
	return false;
}
endif;


/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES: IIS														 */
/* ----------------------------------------------------------------------------- */

// !Return the multisite rewrite rules for IIS systems (as a part of a xml system)

if ( !function_exists('sf_iis7_rewrite_rules') ):
function sf_iis7_rewrite_rules( $rules = array(), $marker = null ) {
	if ( !is_array($rules) || empty($rules) || empty($marker) )
		return '';

	global $wpdb;
	$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subfolder_install	= sf_is_subfolder_install();
	$subdir_match		= $subfolder_install ? '([_0-9a-zA-Z-]+/)?' : '';
	$iis_subdir_match	= ltrim( $base, '/' ) . $subdir_match;
	$iis_subdir_replacement = $subfolder_install ? '{R:1}' : '';

	$rule_i				= 1;
	$space				= str_repeat(' ', 16);
	$out				= array();
	foreach ( $rules as $slug => $rule ) {
		$out[]	= $space . '<rule name="' . $marker . ' Rule ' . $rule_i . '" stopProcessing="true">'."\n"
				. $space . '    <match url="^' . $iis_subdir_match . $slug . '/?$" ignoreCase="false" />'."\n"
				. $space . '    <action type="Redirect" url="' . $iis_subdir_replacement . $rule . '" redirectType="Permanent" />'."\n"
				. $space . '</rule>'."\n";
		$rule_i++;
	}

	return $out;
}
endif;


// !Insert content in web.config file, before the WP block
// @var $rules array|string

if ( !function_exists('sf_insert_iis7_rewrite_rules') ):
function sf_insert_iis7_rewrite_rules( $marker, $rules = '', $before = 'wordpress' ) {
	if ( !$marker || !class_exists('DOMDocument') )
		return false;

	$home_path = get_home_path();
	$web_config_file = $home_path.'web.config';

	$has_web_config			= file_exists( $web_config_file );
	$web_config_is_writable	= $has_web_config && win_is_writeable( $web_config_file );
	$supports_permalinks	= iis7_supports_permalinks();

	// New content
	$rules = is_array($rules) ? implode("\n", $rules) : $rules;
	$rules = trim($rules, "\r\n");

	if (
		( $web_config_is_writable && !$rules ) ||		// Remove rules
		( $web_config_is_writable && $rules && $supports_permalinks ) ||		// Add rules
		( !$has_web_config && win_is_writeable( $home_path ) && $rules && $supports_permalinks )		// Create web.config + add rules
	) {
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
		$old_rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\''.$marker.'\')]');
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
		if ( $before )
			$wordpress_rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\''.$before.'\')]');
		if ( $before && $wordpress_rules->length > 0 ) {
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
endif;
/**/