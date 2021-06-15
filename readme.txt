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
Requires PHP: 7.0
Requires At Least: 4.5
Tested Up To: 5.7.2
Stable Tag: 4.4.1

Update Manager for the WPSSO Core Premium plugin and its Premium complementary add-ons.

== Description ==

<p><img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png"> The WPSSO Update Manager add-on is required to enable and update the <a href="https://wpsso.com/">WPSSO Core Premium plugin</a> and its complementary Premium add-ons.</p>

<p>The WPSSO Update Manager supports WordPress Network / Multisite installations, WordPress MU Domain Mapping, and WordPress v5.5 Automatic Updates.</p>

<p>Simply <em>download</em>, <em>install</em> and <em>activate</em>.</p>

<h3>WPSSO Core Required</h3>

WPSSO Update Manager (WPSSO UM) is an add-on for the [WPSSO Core plugin](https://wordpress.org/plugins/wpsso/).

== Installation ==

<h3 class="top">Install and Uninstall</h3>

* [Install the WPSSO Update Manager add-on](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/).
* [Uninstall the WPSSO Update Manager add-on](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/).

== Frequently Asked Questions ==

== Screenshots ==

01. Update Manager settings - customize the update check frequency (once a day by default) and/or choose to install one of the development versions.

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

**Version 4.4.1 (2021/02/25)**

* **New Features**
	* None.
* **Improvements**
	* Updated the banners and icons of WPSSO Core and its add-ons.
* **Bugfixes**
	* None.
* **Developer Notes**
	* None.
* **Requires At Least**
	* PHP v7.0.
	* WordPress v4.5.
	* WPSSO Core v5.0.0.

**Version 4.4.0 (2021/01/27)**

* **New Features**
	* None.
* **Improvements**
	* None.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Added a 'upgrader_process_complete' action hook to refresh the update manager configuation.
	* Removed the 'wpsso_version_updates' action hook.
	* Updated the API version to 4.4.
* **Requires At Least**
	* PHP v7.0.
	* WordPress v4.5.
	* WPSSO Core v5.0.0.

**Version 4.3.0 (2021/01/11)**

* **New Features**
	* None.
* **Improvements**
	* Updated the cache refresh notice strings.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Removed support for the deprecated 'x-error-msg' header.
	* Updated the API version to 4.3.
* **Requires At Least**
	* PHP v7.0.
	* WordPress v4.5.
	* WPSSO Core v5.0.0.

**Version 4.2.0 (2020/12/14)**

* **New Features**
	* None.
* **Improvements**
	* Added 'php_version' key for API v4.1 minimum version checks.
* **Bugfixes**
	* None.
* **Developer Notes**
	* None.
* **Requires At Least**
	* PHP v7.0.
	* WordPress v4.5.
	* WPSSO Core v5.0.0.

**Version 4.1.0 (2020/12/04)**

* **New Features**
	* None.
* **Improvements**
	* None.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Included the `$addon` argument for library class constructors.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.5.
	* WPSSO Core v5.0.0.

**Version 4.0.1 (2020/11/26)**

* **New Features**
	* None.
* **Improvements**
	* None.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Added support for `Wpsso->id` in WPSSO Core v8.14.0.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

**Version 4.0.0 (2020/11/16)**

* **New Features**
	* None.
* **Improvements**
	* Updated the query API to v4.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Query API v4 updates:
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

= 4.4.1 =

(2021/02/25) Updated the banners and icons of WPSSO Core and its add-ons.

= 4.4.0 =

(2021/01/27) Added a 'upgrader_process_complete' action hook to refresh the update manager configuation.

= 4.3.0 =

(2021/01/11) Updated the cache refresh notice strings. Removed support for the deprecated 'x-error-msg' header.

= 4.2.0 =

(2020/12/14) Added 'php_version' key for API v4.1 minimum version checks.

= 4.1.0 =

(2020/12/04) Included the `$addon` argument for library class constructors.

= 4.0.1 =

(2020/11/26) Added support for `Wpsso->id` in WPSSO Core v8.14.0.

= 4.0.0 =

(2020/11/16) Updated the query API to v4.

= 3.6.1 =

(2020/11/04) Fixed deprecated `implode()` argument order in SucomUpdateUtil.

= 3.6.0 =

(2020/10/30) Added the availables module list to the plugin update API v3 query.

= 3.5.0 =

(2020/10/24) Added cache refresh action when a WPSSO plugin / add-on is activated.

