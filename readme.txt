=== WPSSO Pro Update Manager ===
Plugin Name: WPSSO Pro Update Manager (WPSSO UM)
Plugin Slug: wpsso-um
Text Domain: wpsso-um
Domain Path: /languages
Contributors: jsmoriss
Donate Link: https://wpsso.com/
Tags: wpsso, update, manager
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.txt
Requires At Least: 3.1
Tested Up To: 4.5
Stable Tag: 1.4.1-1

WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.

== Description ==

<p><img src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png" width="256" height="256" style="width:33%;min-width:128px;max-width:256px;float:left;margin:0 40px 20px 0;" />The WPSSO Pro Update Manager (WPSSO UM) extension plugin is required to enable and update the <a href="https://wpsso.com/extend/plugins/wpsso/">WordPress Social Sharing Optimization (WPSSO) Pro</a> version plugin, including all licensed Pro extensions.</p>

Simply *download*, *install*, and *activate*.

The WPSSO UM extension has only two options under the SSO &gt; Pro Update Manager settings page &mdash; one option to customize the update check frequency (once a day by default) and another to install development and pre-release versions.

= Extends the WPSSO Social Plugin =

The WordPress Social Sharing Optimization (WPSSO) plugin is required to use the WPSSO UM extension.

== Installation ==

= Install and Uninstall =

* [Install the Plugin](http://wpsso.com/codex/plugins/wpsso-um/installation/install-the-plugin/)
* [Uninstall the Plugin](http://wpsso.com/codex/plugins/wpsso-um/installation/uninstall-the-plugin/)

== Frequently Asked Questions ==

= Frequently Asked Questions =

* None

== Other Notes ==

= Additional Documentation =

* None

== Screenshots ==

01. The WPSSO Pro Update Manager settings &mdash; customize the update check frequency (once a day by default) and/or choose to install one of the development versions (development and up, alpha and up, beta and up, release-candidate and up, or stable).

== Changelog ==

= Free / Basic Version Repository =

* [GitHub](https://github.com/SurniaUlula/wpsso-um)

= Changelog / Release Notes =

**Version 1.5.0-dev1 (TBD)**

Official announcement: N/A

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* None

**Version 1.4.1-1 (2016/04/08)**

Official announcement: N/A

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Added check for installed version not covered by the chosen version filter -- allows downgrading from a development version to an earlier stable version.
	* Added a new SucomUpdate `get_version_filter_regex()` method.
	* Merged the WpssoUtil 'sucom_installed_version' and 'sucom_ua_plugin' filters into the SucomUpdate class.

**Version 1.4.0-1 (2016/03/31)**

Official announcement: N/A

* *New Features*
	* Added a new "Pro Update Manager" settings page with "Pro Update Check Schedule" and "Pro Update Version Filter" options.
	* Added a new 'WPSSOUM_CHECK_HOURS' constant. You can define this constant in your wp-config.php file to increase the default schedule of 24 hours.
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Changes to support the new plugin update API v2.
	* Tested with WordPress v4.5-RC1-37079.
	* Adopted a new version numbering system: `{major}.{minor}.{bugfix}-{stage}{level}`

== Upgrade Notice ==

= 1.4.1-1 =

(2016/04/08) Added check for installed version not covered by the chosen version filter -- allows downgrading from a development version to an earlier stable version.

= 1.4.0-1 =

(2016/03/31) Added a new "Pro Update Manager" settings page with "Pro Update Check Schedule" and "Pro Update Version Filter" options.

