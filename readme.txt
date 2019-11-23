=== Update Manager | WPSSO Add-on ===
Plugin Name: WPSSO Update Manager
Plugin Slug: wpsso-um
Text Domain: wpsso-um
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.txt
Assets URI: https://surniaulula.github.io/wpsso-um/assets/
Tags: wpsso, update, manager, schedule, add-on, pro version
Contributors: jsmoriss
Requires At Least: 3.9
Tested Up To: 5.3
Stable Tag: 2.5.0

Update manager for the WPSSO Core Premium plugin and its complementary Premium add-ons.

== Description ==

<p style="margin:0;"><img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png"></p>

<p>The WPSSO Update Manager add-on is required to enable and update the <a href="https://wpsso.com/">WPSSO Core Premium plugin</a> and its complementary Premium add-ons.</p>

<p>The WPSSO Update Manager supports WordPress Network / Multisite installations and WordPress MU Domain Mapping.</p>

<p>Simply <em>download</em>, <em>install</em> and <em>activate</em>.</p>

<h3>WPSSO Core Plugin Required</h3>

WPSSO Update Manager (aka WPSSO UM) is an add-on for the [WPSSO Core plugin](https://wordpress.org/plugins/wpsso/).

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

<h3>Standard Version Repositories</h3>

* [GitHub](https://surniaulula.github.io/wpsso-um/)

<h3>Changelog / Release Notes</h3>

**Version 2.6.0 (2019/11/23)**

* **New Features**
	* None.
* **Improvements**
	* Added a check for settings changes (when add-ons are activated / deactivated) to refresh the cached update configuration.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Moved the detection of Authentication ID changes (and subsequent update check) from the WPSSO Core plugin.
	* Updated `WpssoUmRegister->activate_plugin()` for the new WpssoUtilReg class in WPSSO Core v6.13.0.

**Version 2.5.0 (2019/11/19)**

* **New Features**
	* None.
* **Improvements**
	* Added support for the 'force-check' argument in the '/wp-admin/update-core.php' page.
	* Added a three minute throttling feature when manually forcing a plugin update cache refresh.
	* Added a method to handle the possible creation of an $updates class object (when $updates is false) and/or the addition of plugin update response data.
* **Bugfixes**
	* Fixed a possible PHP "Attempt to modify property 'response' of non-object" warning.
* **Developer Notes**
	* Optimized caching true/false method arguments for manual and automated (ie. cron) update checks.
	* Optimized the plugin installed version checks to re-use existing plugin data instead of calling a function.

**Version 2.4.0 (2019/11/17)**

* **New Features**
	* None.
* **Improvements**
	* Added a check to skip fetching update information for stable non-Premium plugins hosted on wordpress.org.
* **Bugfixes**
	* None.
* **Developer Notes**
	* None.

**Version 2.3.0 (2019/10/14)**

* **New Features**
	* None.
* **Improvements**
	* Added a notice message for possible HTTP errors codes (400 to 417) returned by `wp_remote_get()`.
* **Bugfixes**
	* None.
* **Developer Notes**
	* None.

== Upgrade Notice ==
 
= 2.6.0 =

(2019/11/23) Added a check for settings changes (when add-ons are activated / deactivated) to refresh the cached update configuration.

= 2.5.0 =

(2019/11/19) Added support for the 'force-check' argument in the '/wp-admin/update-core.php' page.

