<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cheatin\' uh?' );
}

/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES															 */
/* ----------------------------------------------------------------------------- */

// !Return an array of action => url

function sfml_rules( $actions = null ) {
	if ( is_null( $actions ) ) {
		if ( ! class_exists( 'SFML_Options' ) ) {
			include( SFML_PLUGIN_DIR . 'inc/class-sfml-options.php' );
		}
		$actions = SFML_Options::get_slugs();
	}

	$rules = array(
		$actions['login'] => 'wp-login.php',
	);
	unset( $actions['login'] );

	foreach ( $actions as $action => $slug ) {
		$rules[ $slug ] = 'wp-login.php?action=' . $action;
	}
	return $rules;
}


// !Write rules in file.
// @return (bool) false if no rewrite module or not IIS7/Apache

function sfml_write_rules( $rules = null ) {
	global $is_apache, $is_iis7;

	$rules = is_null( $rules ) ? sfml_rules() : $rules;

	// Nginx
	if ( sfml_is_nginx() ) {
		return true;
	}

	if ( ! sfml_can_write_file() ) {
		return false;
	}

	// IIS
	if ( $is_iis7 ) {
		return sfml_insert_iis7_rewrite_rules( 'SF Move Login', sfml_iis7_rewrite_rules( $rules, 'SF Move Login' ) );
	}
	// Apache
	elseif ( $is_apache ) {
		return sfml_insert_apache_rewrite_rules( 'SF Move Login', sfml_apache_rewrite_rules( $rules ) );
	}

	return false;
}


// !Is it a nginx server?

function sfml_is_nginx() {
	global $is_nginx;
	if ( is_null( $is_nginx ) ) {
		$is_nginx = ! empty( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false;
	}
	return $is_nginx;
}


/*
 * !Tell if the .htaccess/web.config file is writable.
 * If the file does not exists (uh?), check if the parent folder is writable.
 */

function sfml_can_write_file() {
	global $is_apache, $is_iis7;

	$home_path = sfml_get_home_path();

	// IIS7
	if ( $is_iis7 && sfml_iis7_supports_permalinks() && ( wp_is_writable( $home_path . 'web.config' ) || ( ! file_exists( $home_path . 'web.config' ) && wp_is_writable( $home_path ) ) ) ) {
		return true;
	}
	// Apache
	elseif ( $is_apache && sfml_got_mod_rewrite() && ( wp_is_writable( $home_path . '.htaccess' ) || ( ! file_exists( $home_path . '.htaccess' ) && wp_is_writable( $home_path ) ) ) ) {
		return true;
	}

	return false;
}


// !Is WP a MultiSite and a subfolder install?

function sfml_is_subfolder_install() {
	global $wpdb;
	static $subfolder_install;

	if ( ! isset( $subfolder_install ) ) {
		if ( is_multisite() ) {
			$subfolder_install = ! (bool) is_subdomain_install();
		}
		elseif ( ! is_null( $wpdb->sitemeta ) ) {
			$subfolder_install = ! (bool) $wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = 1 AND meta_key = 'subdomain_install'" );
		}
		else {
			$subfolder_install = false;
		}
	}

	return $subfolder_install;
}


/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES: NGINX													 */
/* ----------------------------------------------------------------------------- */

// !Return the multisite rewrite rules (as an array).

function sfml_nginx_rewrite_rules( $rules = array() ) {
	global $wpdb;

	if ( ! is_array( $rules ) || empty( $rules ) ) {
		return '';
	}

	$base			= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subdir_match	= sfml_is_subfolder_install() ? '([_0-9a-zA-Z-]+/)?' : '(/)?';
	$out			= array();

	foreach ( $rules as $slug => $rule ) {
		$out[] = 'rewrite ^' . $subdir_match . $slug . '/?$ $1' . $rule . ' break';
	}

	return $out;
}


/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES: APACHE													 */
/* ----------------------------------------------------------------------------- */

// !got_mod_rewrite() like.

function sfml_got_mod_rewrite() {
	if ( function_exists( 'got_mod_rewrite' ) ) {
		return got_mod_rewrite();
	}

	$got_rewrite = apache_mod_loaded( 'mod_rewrite', true );

	/**
	 * Filter whether Apache and mod_rewrite are present.
	 *
	 * This filter was previously used to force URL rewriting for other servers,
	 * like nginx. Use the got_url_rewrite filter in got_url_rewrite() instead.
	 *
	 * @since 2.5.0
	 *
	 * @see got_url_rewrite()
	 *
	 * @param bool $got_rewrite Whether Apache and mod_rewrite are present.
	 */
	return apply_filters( 'got_rewrite', $got_rewrite );
}


// !Return the multisite rewrite rules (as an array).

function sfml_apache_rewrite_rules( $rules = array() ) {
	global $wpdb;

	if ( ! is_array( $rules ) || empty( $rules ) ) {
		return '';
	}

	$base			= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subdir_match	= sfml_is_subfolder_install() ? '([_0-9a-zA-Z-]+/)?' : '';

	$out			= array(
		'<IfModule mod_rewrite.c>',
		'RewriteEngine On',
		'RewriteBase ' . $base,
	);
	foreach ( $rules as $slug => $rule ) {
		$out[] = 'RewriteRule ^' . $subdir_match . $slug . '/?$ $1' . $rule . ' [QSA,L]';
	}
	$out[] = '</IfModule>';

	return $out;
}


// !Insert content in htaccess file, before the WP block.
// @param $marker string
// @param $rules array|string
// @param $before string

function sfml_insert_apache_rewrite_rules( $marker, $rules = '', $before = '# BEGIN WordPress' ) {
	if ( ! $marker ) {
		return false;
	}

	$home_path		= sfml_get_home_path();
	$htaccess_file	= $home_path . '.htaccess';

	$has_htaccess			= file_exists( $htaccess_file );
	$htaccess_is_writable	= $has_htaccess && is_writeable( $htaccess_file );
	$got_mod_rewrite		= sfml_got_mod_rewrite();

	if (
		( $htaccess_is_writable && !$rules ) ||		// Remove rules
		( $htaccess_is_writable && $rules && $got_mod_rewrite ) ||		// Add rules
		( !$has_htaccess && is_writeable( $home_path ) && $rules && $got_mod_rewrite )		// Create htaccess + add rules
	) {
		// Current htaccess content
		$htaccess_content = $has_htaccess ? file_get_contents( $htaccess_file ) : '';

		// No WordPress rules or no "before tag"?
		if ( ( !$before || false === strpos( $htaccess_content, $before ) ) && $rules ) {
			return insert_with_markers( $htaccess_file, $marker, $rules );
		}

		// Remove the SF Move Login marker
		$htaccess_content = preg_replace( "/# BEGIN $marker.*# END $marker\n*/is", '', $htaccess_content );

		// New content
		if ( $before && $rules ) {
			$rules = is_array( $rules ) ? implode( "\n", $rules ) : $rules;
			$rules = trim( $rules, "\r\n " );
			if ( $rules ) {
				// The new content need to be inserted before the WordPress rules
				$rules = "# BEGIN $marker\n$rules\n# END $marker\n\n\n$before";
				$htaccess_content = str_replace( $before, $rules, $htaccess_content );
			}
		}

		// Update the .htacces file
		return (bool) file_put_contents( $htaccess_file , $htaccess_content );
	}
	return false;
}


/* !---------------------------------------------------------------------------- */
/* !	REWRITE RULES: IIS														 */
/* ----------------------------------------------------------------------------- */

// !iis7_supports_permalinks() like.

function sfml_iis7_supports_permalinks() {
	global $is_iis7;

	if ( function_exists( 'iis7_supports_permalinks' ) ) {
		return iis7_supports_permalinks();
	}

	$supports_permalinks = false;
	if ( $is_iis7 ) {
		/* First we check if the DOMDocument class exists. If it does not exist, then we cannot
		 * easily update the xml configuration file, hence we just bail out and tell user that
		 * pretty permalinks cannot be used.
		 *
		 * Next we check if the URL Rewrite Module 1.1 is loaded and enabled for the web site. When
		 * URL Rewrite 1.1 is loaded it always sets a server variable called 'IIS_UrlRewriteModule'.
		 * Lastly we make sure that PHP is running via FastCGI. This is important because if it runs
		 * via ISAPI then pretty permalinks will not work.
		 */
		$supports_permalinks = class_exists('DOMDocument') && isset($_SERVER['IIS_UrlRewriteModule']) && ( PHP_SAPI == 'cgi-fcgi' );
	}

	/**
	 * Filter whether IIS 7+ supports pretty permalinks.
	 *
	 * @since 2.8.0
	 *
	 * @param bool $supports_permalinks Whether IIS7 supports permalinks. Default false.
	 */
	return apply_filters( 'iis7_supports_permalinks', $supports_permalinks );
}


// !Return the multisite rewrite rules for IIS systems (as a part of a xml system).

function sfml_iis7_rewrite_rules( $rules = array(), $marker = null ) {
	global $wpdb;

	if ( ! is_array( $rules ) || empty( $rules ) || empty( $marker ) ) {
		return '';
	}

	$base				= parse_url( trailingslashit( get_option( 'home' ) ), PHP_URL_PATH );
	$subfolder_install	= sfml_is_subfolder_install();
	$subdir_match		= $subfolder_install ? '([_0-9a-zA-Z-]+/)?' : '';
	$iis_subdir_match	= ltrim( $base, '/' ) . $subdir_match;
	$iis_subdir_repl	= $subfolder_install ? '{R:1}' : '';

	$rule_i				= 1;
	$space				= str_repeat( ' ', 16 );

	$out				= array();
	foreach ( $rules as $slug => $rule ) {
		$out[]	= $space . '<rule name="' . $marker . ' Rule ' . $rule_i . '" stopProcessing="true">' . "\n"
				. $space . '    <match url="^' . $iis_subdir_match . $slug . '/?$" ignoreCase="false" />' . "\n"
				. $space . '    <action type="Redirect" url="' . $iis_subdir_repl . $rule . '" redirectType="Permanent" />' . "\n"
				. $space . "</rule>\n";
		$rule_i++;
	}

	return $out;
}


// !Insert content in web.config file, before the WP block.
// @var $rules array|string

function sfml_insert_iis7_rewrite_rules( $marker, $rules = '', $before = 'wordpress' ) {
	if ( ! $marker || ! class_exists( 'DOMDocument' ) ) {
		return false;
	}

	$home_path       = sfml_get_home_path();
	$web_config_file = $home_path . 'web.config';

	$has_web_config			= file_exists( $web_config_file );
	$web_config_is_writable	= $has_web_config && wp_is_writable( $web_config_file );
	$supports_permalinks	= sfml_iis7_supports_permalinks();

	// New content
	$rules = is_array( $rules ) ? implode( "\n", $rules ) : $rules;
	$rules = trim( $rules, "\r\n" );

	if (
		( $web_config_is_writable && !$rules ) ||		// Remove rules
		( $web_config_is_writable && $rules && $supports_permalinks ) ||		// Add rules
		( !$has_web_config && wp_is_writable( $home_path ) && $rules && $supports_permalinks )		// Create web.config + add rules
	) {
		// If configuration file does not exist then we create one.
		if ( ! $has_web_config ) {
			$fp = fopen( $web_config_file, 'w' );
			fwrite( $fp, '<configuration/>' );
			fclose( $fp );
		}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;

		if ( $doc->load( $web_config_file ) === false ) {
			return false;
		}

		$xpath = new DOMXPath( $doc );

		// Remove old rules
		$old_rules = $xpath->query( '/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'' . $marker . '\')]' );
		if ( $old_rules->length > 0 ) {
			$child = $old_rules->item(0);
			$parent = $child->parentNode;
			$parent->removeChild( $child );
		}

		// No new rules?
		if ( ! $rules ) {
			$doc->formatOutput = true;
			saveDomDocument( $doc, $web_config_file );
			return true;
		}

		// Check the XPath to the rewrite rule and create XML nodes if they do not exist
		$xmlnodes = $xpath->query( '/configuration/system.webServer/rewrite/rules' );
		if ( $xmlnodes->length > 0 ) {
			$rules_node = $xmlnodes->item(0);
		}
		else {
			$rules_node = $doc->createElement('rules');

			$xmlnodes   = $xpath->query('/configuration/system.webServer/rewrite');
			if ( $xmlnodes->length > 0 ) {
				$rewrite_node = $xmlnodes->item(0);
				$rewrite_node->appendChild( $rules_node );
			}
			else {
				$rewrite_node = $doc->createElement('rewrite');
				$rewrite_node->appendChild( $rules_node );

				$xmlnodes = $xpath->query( '/configuration/system.webServer' );
				if ( $xmlnodes->length > 0 ) {
					$system_webServer_node = $xmlnodes->item(0);
					$system_webServer_node->appendChild( $rewrite_node );
				}
				else {
					$system_webServer_node = $doc->createElement('system.webServer');
					$system_webServer_node->appendChild( $rewrite_node );

					$xmlnodes = $xpath->query( '/configuration' );
					if ( $xmlnodes->length > 0 ) {
						$config_node = $xmlnodes->item(0);
						$config_node->appendChild( $system_webServer_node );
					}
					else {
						$config_node = $doc->createElement('configuration');
						$doc->appendChild( $config_node );
						$config_node->appendChild( $system_webServer_node );
					}
				}
			}
		}

		$rule_fragment = $doc->createDocumentFragment();
		$rule_fragment->appendXML( $rules );

		// Insert before the WP rules
		if ( $before ) {
			$wordpress_rules = $xpath->query( '/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'' . $before . '\')]' );
		}
		if ( $before && $wordpress_rules->length > 0 ) {
			$child  = $wordpress_rules->item(0);
			$parent = $child->parentNode;
			$parent->insertBefore( $element, $child );
		}
		else {
			$rules_node->appendChild( $rule_fragment );
		}

		$doc->encoding     = 'UTF-8';
		$doc->formatOutput = true;
		saveDomDocument( $doc, $web_config_file );

		return true;
	}

	return false;
}

/**/