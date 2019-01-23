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
Tested Up To: 5.0
Stable Tag: 1.17.0

WPSSO Core add-on provides updates for the WPSSO Core Pro plugin and its Pro add-ons.

== Description ==

<p style="margin:0;"><img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png"></p>

<p>The WPSSO Update Manager add-on is required to enable and update the <a href="https://wpsso.com/">WPSSO Core Pro plugin</a> and its Pro add-ons.</p>

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

**Version 1.17.0 (2019/01/23)**

* *New Features*
	* None.
* *Improvements*
	* Updated the minimum WPSSO Core supported version to v4.0.0 (2018/05/02).
* *Bugfixes*
	* None.
* *Developer Notes*
	* Added a call to the new SucomPlugin get_plugins() method when available.

**Version 1.16.0 (2019/01/15)**

* *New Features*
	* None.
* *Improvements*
	* None.
* *Bugfixes*
	* Fixed an incorrect method call when saving an error message for invalid URLs.
* *Developer Notes*
	* Optimized the check for a non-functioning WP cron schedule.

**Version 1.15.0 (2019/01/13)**

* *New Features*
	* None.
* *Improvements*
	* None.
* *Bugfixes*
	* None.
* *Developer Notes*
	* Further improvements in removing non-essential filter and action hooks.

== Upgrade Notice ==

= 1.17.0 =

(2019/01/23) Updated the minimum WPSSO Core supported version to v4.0.0 (2018/05/02). Added a call to the new SucomPlugin get_plugins() method when available.

