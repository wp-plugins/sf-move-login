=== Move Login ===

Contributors: GregLone, juliobox
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=UBY2MY2J4YB7J&item_number=SF-Move-Login
Tags: login, logout, url, security
Requires at least: 3.0
Tested up to: 3.6-beta3
Stable tag: trunk
License: GPLv3

Change your login url to http://example.com/login.


== Description ==

This plugin forbids access to **http://example.com/wp-login.php** and creates new urls, like **http://example.com/login** or **http://example.com/logout**.

This is a great way to limit bots trying to brute-forcing your login (trying to guess your login and password). Of course, the new urls are easier to remember too.
The plugin is small, fast, and does not create new security vulnerabilities like some other plugins I've seen.

No settings: activate, it works.

Also remember: using this plugin does NOT exempt you to use a strong password. Moreover, never use "admin" as login, this is the first attempt for bots.

**Please note that even if the plugin works properly so far, it still is a beta.**
Please use the forum to report bugs, the plugin will be available on my site when the version 1.0 is reached. Thanks!

= Translations =

* English
* French

= Multisite =

Not ready for Multisite, yet.

= Requirements =

See some important informations in the "Installation" tab.


== Installation ==

1. Extract the plugin folder from the downloaded ZIP file.
2. Upload sf-move-login folder to your `/wp-content/plugins/` directory.
3. Enable url rewriting in the permalinks settings page.
4. if you have another plugin that redirects **http://example.com/login** to **http://example.com/wp-login.php** (a short-links plugin for example), disable it or remove the redirection, otherwise they will conflict and you'll be locked out. See the faq in case you're not able to reach the login page (make sure to have a ftp access to your site).
5. Activate the plugin from the "Plugins" page.
6. If the plugin can't write your `.htaccess` file, you'll need to edit it yourself with a ftp access.


== Frequently Asked Questions ==

= Can I set my own urls? =

Nop, sorry. I prefer keep the plugin as simple as possible.

= I'm locked out! I can't access the login page! =

You're screwed! No, I'm kidding, but you need a ftp access to your site. When logged in with your ftp software, open the file wp-config.php located at the root of your installation. Simply add this in the file: `define('SFML_ALLOW_LOGIN_ACCESS', true);` and save the file. This will bypass the plugin and you'll be able to access **http://example.com/wp-login.php**. Another plugin may conflict, you'll need to find which one before removing this new line of code.

Eventually, check out [my blog](http://www.screenfeed.fr/sfml/) for more infos, help, or bug reports (sorry guys, it's in french, but feel free to leave a comment in english).


== Screenshots ==

Nothing to show.


== Changelog ==

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