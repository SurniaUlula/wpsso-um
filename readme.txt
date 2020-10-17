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
Requires PHP: 5.6
Requires At Least: 4.4
Tested Up To: 5.5.1
Stable Tag: 3.3.0

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

**Version 3.4.0 (2020/10/17)**

* **New Features**
	* None.
* **Improvements**
	* Refactored the add-on class to extend a new WpssoAddOn abstract class.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Added a new WpssoAddOn class in lib/abstracts/add-on.php.
	* Added a new SucomAddOn class in lib/abstracts/com/add-on.php.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

**Version 3.3.0 (2020/09/18)**

* **New Features**
	* None.
* **Improvements**
	* Added support for translated plugin names in notice messages.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Refactored the SucomUpdateUtilWP::raw_do_option() method.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.4.
	* WPSSO Core v5.0.0.

**Version 3.2.1 (2020/09/15)**

* **New Features**
	* None.
* **Improvements**
	* Updated the French plugin translations.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Moved extracted translation strings from lib/gettext-*.php files to a new gettext/ folder.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.2.
	* WPSSO Core v5.0.0.

**Version 3.2.0 (2020/09/11)**

* **New Features**
	* None.
* **Improvements**
	* Removed the Cron Schedule tab from the SSO &gt; Update Manager settings page - update checks are set to every 24 hrs.
* **Bugfixes**
	* None.
* **Developer Notes**
	* Added a new WpssoUmFiltersUpgrade class in lib/filters-upgrade.php.
* **Requires At Least**
	* PHP v5.6.
	* WordPress v4.2.
	* WPSSO Core v5.0.0.

== Upgrade Notice ==

= 3.4.0 =

(2020/10/17) Refactored the add-on class to extend a new WpssoAddOn abstract class.

= 3.3.0 =

(2020/09/18) Added support for translated plugin names in notice messages.

