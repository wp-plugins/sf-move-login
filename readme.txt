=== Move Login ===

Contributors: GregLone, SecuPress, juliobox
Tags: login, logout, url, security
Requires at least: 3.1
Tested up to: 4.1.1
Stable tag: trunk
License: GPLv3
License URI: http://www.screenfeed.fr/gpl-v3.txt

Change your login URL for something like <code>http://example.com/login</code> and stop login brute force attempts.


== Description ==

This plugin forbids access to **http://example.com/wp-login.php** and creates new urls, like **http://example.com/login** or **http://example.com/logout**.

This is a great way to limit bots trying to brute force your login (trying to guess your login and password). Of course, the new urls are easier to remember too.

Also remember: the use of this plugin does NOT exempt you to use a strong password. Moreover, never use "admin" as login, this is the first attempt for bots.

= Translations =

* US English
* French
* Serbo-Croatian (partial, thank you Borisa)
* Hebrew (partial, thank you Ahrale)

= Multisite =

Yep!
The plugin must be activated from your network.

= Requirements =

* See some important informations in the "Installation" tab (I mean it).
* Should work on IIS7+ servers but not tested (I guess you should probably save a copy of your `web.config` file before the plugin activation).
* For nginx servers, the rewrite rules are not written automatically of course, but provided as information.


== Installation ==

1. Extract the plugin folder from the downloaded ZIP file.
1. Upload the `sf-move-login` folder to your `/wp-content/plugins/` directory.
1. If you have another plugin that makes redirections to **http://example.com/wp-login.php** (a short-links plugin for example), disable it or remove the redirection, otherwise they will conflict and you'll be locked out. See the faq in case you're not able to reach the login page (make sure to have a ftp access to your site).
1. Activate the plugin from the "Plugins" page.
1. If the plugin can't write your `.htaccess` file or `web.config` file, you'll need to edit it yourself with a ftp access, the rules are provided in the plugin settings page.


== Frequently Asked Questions ==

= Can I set my own urls? =

Since the version 1.1, yes. And since the version 2.0, you don't need any additional plugin for that.

= I'm locked out! I can't access the login page! =

You're screwed! No, I'm kidding, but you need a ftp access to your site. When logged in with your ftp software, open the file wp-config.php located at the root of your installation. Simply add this in the file: `define( 'SFML_ALLOW_LOGIN_ACCESS', true );` and save the file. This will bypass the plugin and you'll be able to access **http://example.com/wp-login.php**. Another plugin may conflict, you'll need to find which one before removing this new line of code.

= Does it really work for Multisite? =

Yes. Each blog has its own login page (but the customized slugs are the same for each blog though). The plugin must be activated from the network.

Eventually, try the [WordPress support forum](http://wordpress.org/support/plugin/sf-move-login) (best), or check out [my blog](http://www.screenfeed.fr/plugin-wp/move-login/ "Move Login") for more infos, help, or bug reports (sorry guys, it's in French, but feel free to leave a comment in English).


== Screenshots ==

1. The settings page.


== Changelog ==

= 2.0.2 =

* 2015/02/24
* Same as below... Fingers crossed. >_>

= 2.0.1 =

* 2015/02/24
* Fixes a fatal error for multisites.

= 2.0 =

* 2015/02/22
* Most of the plugin has been rewritten.
* New: you don't need my framework Noop to have a settings page anymore (yes, you can uninstall it if it's not used elsewhere). ᕙ(⇀‸↼‶)ᕗ The bad news is there are no settings import/export/history anymore (and it won't come back). Make sure your settings are ok after upgrading.
* New: the plugin disable some WordPress native redirections to administration area and login page. For example, **http://example.com/dashboard/** was leading to **http://example.com/wp-admin/**. This should solve a bunch of bugs.
* New: the rewrite rules for nginx servers are now provided in the plugin settings page as information. Thank you [Willy Bahuaud](https://profiles.wordpress.org/willybahuaud/).
* Improvement: bugfix for IIS servers.
* Improvement: better French translations.
* Bugfix: fix double slash in network site url (used for lostpassword).

= 1.1.4 =

* 2014/04/28
* Plugins can now add their own action to Move Login more easily with the filter `sfml_additional_slugs`. Even without doing anything, Move Login handle custom actions added by other plugins, but the url can't be customisable. Now, these plugins can add a new input field to let users change this new url, and it's very simple.
* Side note: I've just released a new version for my framework Noop (1.0.6). Now you can import and export your settings via a file, see the new tab in the "Help" area.

= 1.1.3 =

* 2014/04/01
* Bugfix for php 5.4.

= 1.1.2 =

* 2014/03/29
* Bugfix: don't block users accessing the script `admin-post.php`.
* Changed i18n domain.
* If Noop is not installed, add a link in the "settings" page.
* Added a direct link to download Noop, some users may not be able to install plugins directly.
* Code improvements and small bugfixes.

= 1.1.1 =

* 2013/12/17
* Bugfix.

= 1.1 =

* 2013/12/16
* Code refactoring.
* Requires WordPress 3.1 at least.
* New: the URLs can be customized, with a filter or a settings page. The settings page needs another plugin to be installed, it's a framework I made (Noop). See the Move Login row in your plugins list, there's a new link.
* New: support for custom actions in the login form (added by other plugins).
* New: choose what to do when someone attempts to access the old login page.
* New: choose what to do when someone attempts to access the administration area.
* New: enabling permalinks is not required anymore.
* Todo: provide rewrite rules for Nginx systems.

= 1.0.1 =

* 2013/09/30
* Very minor bug fix: messed the author link -_-'

= 1.0 =

* 2013/09/20
* First stable version.
* New: 1 new action called `sfml_wp_login_error` is now available for the `wp-login.php` error message, you can use your own `wp_die()` or redirect to another error page for example.

= 1.0-RC2 =

* 2013/09/12
* Bugfix: activation for multisite with not writable .htaccess file, a wrong message was shown, preventing activation (was I drunk?).
* tested on multisite with subdomain.
* SecuPress is joining the project :)

= 1.0-RC1 =

* 2013/09/11
* New: Multisite support (must be "network" activated).
* Enhancement: updated the set_url_scheme() function to the one in WP 3.6.1 (used for WP < 3.4).
* Enhancement: better rewrite rules.
* Bugfix: The plugin rewrite rules are now really removed from the .htaccess file on deactivation.

= 0.1.1 =

* 2013/06/04
* Bugfix: php notice due to a missing parameter.
* Bugfix: incorrect network_site_url filter.

= 0.1 =

* 2013/06/03
* First public beta release
* Thanks to juliobox, who's joining the project :)


== Upgrade Notice ==

= 2.0 =
The framework Noop is not needed anymore: settings are included in the plugin. Make sure your settings are ok after upgrading.

= 1.0 =
This is the first stable version of the plugin.