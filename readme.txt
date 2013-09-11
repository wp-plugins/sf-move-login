=== Move Login ===

Contributors: GregLone, juliobox
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=UBY2MY2J4YB7J&item_number=SF-Move-Login
Tags: login, logout, url, security
Requires at least: 3.0
Tested up to: 3.7-alpha-25157
Stable tag: trunk
License: GPLv3

Change your login url to http://example.com/login.


== Description ==

This plugin forbids access to **http://example.com/wp-login.php** and creates new urls, like **http://example.com/login** or **http://example.com/logout**.

This is a great way to limit bots trying to brute-forcing your login (trying to guess your login and password). Of course, the new urls are easier to remember too.
The plugin is small, fast, and does not create new security vulnerabilities like some other plugins I've seen.

No settings: activate, it works.

Also remember: using this plugin does NOT exempt you to use a strong password. Moreover, never use "admin" as login, this is the first attempt for bots.

**Please note that even if the plugin works properly so far, it still is a beta version.**
Please use the forum to report bugs, the plugin will be available on my site when the version 1.0 is reached. Thanks!

= Translations =

* English
* French

= Multisite =

Yep!
Note: not tested on subdomain installations.

= Requirements =

See some important informations in the "Installation" tab.
Should work on IIS servers but not tested.


== Installation ==

1. Extract the plugin folder from the downloaded ZIP file.
2. Upload sf-move-login folder to your `/wp-content/plugins/` directory.
3. Enable url rewriting in the permalinks settings page.
4. If you have another plugin that redirects **http://example.com/login** to **http://example.com/wp-login.php** (a short-links plugin for example), disable it or remove the redirection, otherwise they will conflict and you'll be locked out. See the faq in case you're not able to reach the login page (make sure to have a ftp access to your site).
5. Activate the plugin from the "Plugins" page.
6. If the plugin can't write your `.htaccess` file or `web.config` file, you'll need to edit it yourself with a ftp access.


== Frequently Asked Questions ==

= Can I set my own urls? =

Nop, sorry. I prefer keep the plugin as simple as possible.

= I'm locked out! I can't access the login page! =

You're screwed! No, I'm kidding, but you need a ftp access to your site. When logged in with your ftp software, open the file wp-config.php located at the root of your installation. Simply add this in the file: `define('SFML_ALLOW_LOGIN_ACCESS', true);` and save the file. This will bypass the plugin and you'll be able to access **http://example.com/wp-login.php**. Another plugin may conflict, you'll need to find which one before removing this new line of code.

Eventually, check out [my blog](http://www.screenfeed.fr/sfml/) for more infos, help, or bug reports (sorry guys, it's in french, but feel free to leave a comment in english). Note: this page does not exists yet, I'll create it when the version 1.0 is reached.

= Does it really work for Multisite? =

Yes. Each blog has its own login page. The plugin must be network activated. Make sure permalinks are activated for ALL your blogs. In case the plugin fails to add the rewrite rules, there's a new "settings" page in your network admin area: "Settings" -> "SF Move Login". You'll be able to copy/paste the needed lines to your `.htaccess` file or `web.config` file, you'll need to edit it yourself with a ftp access.

= I've enabled the Multisite feature on my site, but the plugin does not work anymore :( =

Multisite and monosite installations does not have the same rewrite rules. Simply deactivate the plugin, and "network" activate it again. The new rules will be created.


== Screenshots ==

Nothing to show.


== Changelog ==

= 1.0-RC1 =

* 2013/09/11
* New: Multisite support (must be "network" activated).
* Enhancement: updated the set_url_scheme() function to the one in WP 3.7-alpha (used for WP < 3.4).
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

Nothing special