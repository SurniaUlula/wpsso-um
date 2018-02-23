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
Requires PHP: 5.4
Requires At Least: 3.8
Tested Up To: 4.9.4
Stable Tag: 1.8.2

WPSSO Core extension to provide updates for the WPSSO Core Pro plugin and its Pro extensions.

== Description ==

<img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png">

<p>The WPSSO Update Manager extension is required to enable / update the <a href="https://wpsso.com/?utm_source=wpssoum-readme-pro-req">WPSSO Core Pro plugin</a> and any licensed Pro extensions.</p>

<p>The WPSSO Update Manager supports WordPress Network / Multisite installations and WordPress MU Domain Mapping.</p>

<p>Simply <em>download</em>, <em>install</em>, and <em>activate</em>.</p>

<h3>WPSSO Core Plugin Prerequisite</h3>

WPSSO Update Manager (aka WPSSO UM) is an extension for the WPSSO Core plugin &mdash; which creates complete &amp; accurate meta tags and Schema markup from your existing content for social sharing, Social Media Optimization (SMO), Search Engine Optimization (SEO), Google Rich Cards, Pinterest Rich Pins, etc.

== Installation ==

<h3>Install and Uninstall</h3>

* [Install the WPSSO UM Extension](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/?utm_source=wpssoum-readme-install)
* [Uninstall the WPSSO UM Extension](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/?utm_source=wpssoum-readme-uninstall)

== Frequently Asked Questions ==

<h3>Frequently Asked Questions</h3>

* None

== Other Notes ==

<h3>Additional Documentation</h3>

* None

== Screenshots ==

01. Update Manager settings &mdash; customize the update check frequency (once a day by default) and/or choose to install one of the development versions.

== Changelog ==

<h3>Version Numbering</h3>

Version components: `{major}.{minor}.{bugfix}[-{stage}.{level}]`

* {major} = Major structural code changes / re-writes or incompatible API changes.
* {minor} = New functionality was added or improved in a backwards-compatible manner.
* {bugfix} = Backwards-compatible bug fixes or small improvements.
* {stage}.{level} = Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).

<h3>Free / Basic Version Repositories</h3>

* [GitHub](https://surniaulula.github.io/wpsso-um/)

<h3>Changelog / Release Notes</h3>

**Version 1.9.0-rc.2 (2018/02/23)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Refactored the WpssoUm `min_version_notice()` method to use PHP's `trigger_error()` and include a notice to refresh plugin update information.
	* Refactored the WpssoUm `get_update_check_hours()` method to check the scheduled hours value for minimum (12 hours) and maximum (1 week) limits.
	* Refactored the WpssoUm sanity check to make sure the WordPress cron is operating correctly (and force an update check if required). 
	* Renamed the 'plugin_update-wpsso' cron hook to 'wpsso_update_manager_check'.

**Version 1.8.2 (2018/02/04)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Maintenance release.

== Upgrade Notice ==

= l.9.0-rc.2 =

(2018/02/23) Refactored the WpssoUm min_version_notice() method to use PHP's trigger_error() and include a notice to refresh plugin update information.

= 1.8.2 =

(2018/02/04) Maintenance release.

