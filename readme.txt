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
Requires PHP: 5.6
Requires At Least: 4.4
Tested Up To: 5.6
Stable Tag: 4.0.0

Update Manager for the WPSSO Core Premium plugin and its Premium complementary add-ons.

== Description ==

<p style="margin:0;"><img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png"></p>

<p>The WPSSO Update Manager add-on is required to enable and update the <a href="https://wpsso.com/">WPSSO Core Premium plugin</a> and its complementary Premium add-ons.</p>

<p>The WPSSO Update Manager supports WordPress Network / Multisite installations, WordPress MU Domain Mapping, and WordPress v5.5 Automatic Updates.</p>

<p>Simply <em>download</em>, <em>install</em> and <em>activate</em>.</p>

<h3>WPSSO Core Plugin Required</h3>

WPSSO Update Manager (aka WPSSO UM) is an add-on for the [WPSSO Core plugin](https://wordpress.org/plugins/wpsso/).

WPSSO Core and its add-ons make sure your content looks great on social sites and in search results, no matter how your URLs are crawled, shared, re-shared, posted, or embedded.

== Installation ==

<h3 class="top">Install and Uninstall</h3>

* [Install the WPSSO Update Manager add-on](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/).
* [Uninstall the WPSSO Update Manager add-on](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/).

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

<h3>Standard Version Repositories</h3>

* [GitHub](https://surniaulula.github.io/wpsso-um/)

<h3>Changelog / Release Notes</h3>

**Version 4.0.0 (2020/11/16)**

* **New Features**
	* None.
* **Improvements**
	* Updated the query API to v4.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Query API v4 changes:
		* Renamed the 'locale' key to 'user_locale'.
		* Renamed the 'installed_version' key to 'plugin_version'.
		* Added a 'wp_version' and 'wc_version' keys for minimum version checks.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

**Version 3.6.1 (2020/11/04)**

* **New Features**
	* None.
* **Improvements**
	* None.
* **Bugfixes**
	* Fixed deprecated `implode()` argument order in SucomUpdateUtil.
* **Developer Notes**
	* None.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

**Version 3.6.0 (2020/10/30)**

* **New Features**
	* None.
* **Improvements**
	* Added the available modules list to the update plugin API v3 query.
* **Bugfixes**
	* None.
* **Developer Notes**
	* None.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

**Version 3.5.0 (2020/10/24)**

* **New Features**
	* None.
* **Improvements**
	* Added cache refresh action when a WPSSO plugin / add-on is activated.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Added an `$addon` object variable to the WpssoUmActions and WpssoUmFilters constructor arguments.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

== Upgrade Notice ==

= 4.0.0 =

(2020/11/16) Updated the query API to v4. Updated minimum required WPSSO Core version from v5.0.0 to v6.0.0.

= 3.6.1 =

(2020/11/04) Fixed deprecated `implode()` argument order in SucomUpdateUtil.

= 3.6.0 =

(2020/10/30) Added the availables module list to the plugin update API v3 query.

= 3.5.0 =

(2020/10/24) Added cache refresh action when a WPSSO plugin / add-on is activated.

