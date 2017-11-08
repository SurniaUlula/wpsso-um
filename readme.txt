=== WPSSO Update Manager ===
Plugin Name: WPSSO Update Manager
Plugin Slug: wpsso-um
Text Domain: wpsso-um
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.txt
Assets URI: https://surniaulula.github.io/wpsso-um/assets/
Tags: wpsso, update, manager, schedule, update check, extension, pro version, development version, pre-release
Contributors: jsmoriss
Requires PHP: 5.3
Requires At Least: 3.7
Tested Up To: 4.8.3
Stable Tag: 1.7.1

WPSSO Core extension to provide updates for the WPSSO Core Pro plugin and its Pro extensions.

== Description ==

<img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png">

<p>The WPSSO Update Manager extension is required to enable / update the <a href="https://wpsso.com/?utm_source=wpssoum-readme-pro-req">WPSSO Core Pro plugin</a> and any licensed Pro extensions.</p>

<p>The WPSSO Update Manager supports WordPress Network / Multisite installations and WordPress MU Domain Mapping.</p>

<p>Simply <em>download</em>, <em>install</em>, and <em>activate</em>.</p>

<h3>WPSSO Core Plugin Prerequisite</h3>

WPSSO Update Manager is an extension for the WPSSO Core plugin &mdash; which creates complete &amp; accurate meta tags and Schema markup from your content for social sharing, social media / SMO, search / SEO / rich cards, and more.

== Installation ==

<h3>Install and Uninstall</h3>

* [Install the WPSSO UM Plugin](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/?utm_source=wpssoum-readme-install)
* [Uninstall the WPSSO UM Plugin](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/?utm_source=wpssoum-readme-uninstall)

== Frequently Asked Questions ==

<h3>Frequently Asked Questions</h3>

* None

== Other Notes ==

<h3>Additional Documentation</h3>

* None

== Screenshots ==

01. Update Manager settings &mdash; customize the update check frequency (once a day by default) and/or choose to install one of the development versions.

== Changelog ==

<h3>Free / Basic Version Repositories</h3>

* [GitHub](https://surniaulula.github.io/wpsso-um/)

<h3>Version Numbering</h3>

Version components: `{major}.{minor}.{bugfix}[-{stage}.{level}]`

* {major} = Major structural code changes / re-writes or incompatible API changes.
* {minor} = New functionality was added or improved in a backwards-compatible manner.
* {bugfix} = Backwards-compatible bug fixes or small improvements.
* {stage}.{level} = Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).

<h3>Changelog / Release Notes</h3>

**Version 1.7.1 (2017/10/31)**

* *New Features*
	* None
* *Improvements*
	* Added a retry on cURL error 52: Empty reply from server.
* *Bugfixes*
	* Added a missing check for the SucomUtil protect_filter_value() method for older versions of WPSSO.
* *Developer Notes*
	* None

**Version 1.7.0 (2017/10/28)**

* *New Features*
	* None
* *Improvements*
	* Added WordPress Site Address value protection for improved compatibility with the WordPress MU Domain Mapping plugin.
* *Bugfixes*
	* None
* *Developer Notes*
	* Added new SucomUpdate check_all_for_updates() and get_config_keys() methods.
	* Added action hooks for 'wpsso_column_metabox_version_info_table_rows' and 'wpsso_load_setting_page_check_for_updates'.
	* Added an action hook for 'update_option_home' to check for updates when the WordPress home option value changes.

**Version 1.6.8 (2017/10/15)**

* *New Features*
	* None
* *Improvements*
	* Minor speed improvement when getting information for installed / non-active extensions. 
* *Bugfixes*
	* None
* *Developer Notes*
	* Added a check for SucomUtil get_wp_plugins() to use it instead of the (slower) WordPress get_plugins() function.

== Upgrade Notice ==

= 1.7.1 =

(2017/10/31) Added a retry on cURL error 52. Added a missing check for the SucomUtil protect_filter_value() method for older versions of WPSSO.

= 1.7.0 =

(2017/10/28) Added WordPress Site Address value protection for improved compatibility with the WordPress MU Domain Mapping plugin.

= 1.6.8 =

(2017/10/15) Minor speed improvement when getting information for installed / non-active extensions. 

= 1.6.7 =

(2017/10/02) Minor code refactoring / standardizing for WPSSO v3.46.3.

= 1.6.6 =

(2017/09/10) Minor code refactoring for WPSSO v3.46.0.

