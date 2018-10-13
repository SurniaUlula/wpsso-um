=== WPSSO Update Manager ===
Plugin Name: WPSSO Update Manager
Plugin Slug: wpsso-um
Text Domain: wpsso-um
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.txt
Assets URI: https://surniaulula.github.io/wpsso-um/assets/
Tags: wpsso, update, manager, schedule, add-on, pro version
Contributors: jsmoriss
Requires PHP: 5.4
Requires At Least: 3.8
Tested Up To: 4.9.8
Stable Tag: 1.12.1

WPSSO Core add-on to provide updates for the WPSSO Core Pro plugin and its Pro add-ons.

== Description ==

<p style="margin:0;"><img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png"></p>

<p>The WPSSO Update Manager add-on is required to enable / update the <a href="https://wpsso.com/">WPSSO Core Pro plugin</a> and any licensed Pro add-ons.</p>

<p>The WPSSO Update Manager supports WordPress Network / Multisite installations and WordPress MU Domain Mapping.</p>

<p>Simply <em>download</em>, <em>install</em> and <em>activate</em>.</p>

<h3>WPSSO Core Plugin Prerequisite</h3>

WPSSO Update Manager (aka WPSSO UM) is an add-on for the [WPSSO Core plugin](https://wordpress.org/plugins/wpsso/) (Free or Pro version).

== Installation ==

<h3 class="top">Install and Uninstall</h3>

* [Install the WPSSO UM Add-on](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/)
* [Uninstall the WPSSO UM Add-on](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/)

== Frequently Asked Questions ==

== Screenshots ==

01. Update Manager settings &mdash; customize the update check frequency (once a day by default) and/or choose to install one of the development versions.

== Changelog ==

<h3 class="top">Version Numbering</h3>

Version components: `{major}.{minor}.{bugfix}[-{stage}.{level}]`

* {major} = Major structural code changes / re-writes or incompatible API changes.
* {minor} = New functionality was added or improved in a backwards-compatible manner.
* {bugfix} = Backwards-compatible bug fixes or small improvements.
* {stage}.{level} = Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).

<h3>Free / Standard Version Repositories</h3>

* [GitHub](https://surniaulula.github.io/wpsso-um/)

<h3>Changelog / Release Notes</h3>

**Version 1.13.0-dev.2 (2018/10/11)**

* *New Features*
	* None.
* *Improvements*
	* Replaced calls to the WordPress add_query_arg() function by the SucomUpdateUtil decode_url_add_query() static method.
* *Bugfixes*
	* None.
* *Developer Notes*
	* Added a new SucomUpdateUtil class with decode_url_add_query(), unparse_url{}, and get_wp_plugins() static methods.
	* Moved the SucomPluginData class to a new lib/com/plugin-data.php library file.
	* Moved the SucomPluginUpdate class to a new lib/com/plugin-update.php library file.
	* Moved the SucomUpdateUtil class to a new lib/com/update-util.php library file.
	* Moved the SucomUpdateUtilWP class to a new lib/com/update-util-wp.php library file.

**Version 1.12.1 (2018/10/09)**

* *New Features*
	* None.
* *Improvements*
	* Added a URL consistency check after calling the WordPress add_query_arg() function.
* *Bugfixes*
	* None.
* *Developer Notes*
	* None.

== Upgrade Notice ==

= 1.13.0-dev.2 =

(2018/10/11) Replaced calls to the WordPress add_query_arg() function by the SucomUpdateUtil decode_url_add_query() static method.

= 1.12.1 =

(2018/10/09) Added a URL consistency check after calling the WordPress add_query_arg() function.

