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
Requires At Least: 3.7
Tested Up To: 4.8.2
Requires PHP: 5.3
Stable Tag: 1.6.6

WPSSO extension to provide updates for the WPSSO Pro plugin and its Pro extensions.

== Description ==

<img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png">

<p>The WPSSO Update Manager extension plugin is required to enable and update the <a href="https://wpsso.com/extend/plugins/wpsso/">WPSSO Pro</a> version plugin, including all its licensed Pro extensions.</p>

Simply *download*, *install*, and *activate*.

= WPSSO (Core Plugin) Prerequisite =

WPSSO Update Manager is an extension for the WPSSO (Core Plugin) &mdash; which provides complete and accurate meta tags and Schema markup from your content for social sharing, social media, search / SEO and rich cards.

== Installation ==

= Install and Uninstall =

* [Install the WPSSO UM Plugin](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/)
* [Uninstall the WPSSO UM Plugin](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/)

== Frequently Asked Questions ==

= Frequently Asked Questions =

* None

== Other Notes ==

= Additional Documentation =

* None

== Screenshots ==

01. Update Manager settings &mdash; customize the update check frequency (once a day by default) and/or choose to install one of the development versions (development and up, alpha and up, beta and up, release-candidate and up, or stable).

== Changelog ==

= Free / Basic Version Repository =

* [GitHub](https://surniaulula.github.io/wpsso-um/)

= Version Numbering =

Version components: `{major}.{minor}.{bugfix}[-{stage}.{level}]`

* {major} = Major structural code changes / re-writes or incompatible API changes.
* {minor} = New functionality was added or improved in a backwards-compatible manner.
* {bugfix} = Backwards-compatible bug fixes or small improvements.
* {stage}.{level} = Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).

= Changelog / Release Notes =

**Version 1.6.6 (2017/09/10)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Minor code refactoring for WPSSO v3.46.0.

**Version 1.6.5 (2017/08/31)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Added a new 'wpsso_um_sslverify' filter for wp_remote_get() (returns true by default).

**Version 1.6.4 (2017/05/30)**

* *New Features*
	* None
* *Improvements*
	* Added saving of the license expiration date from the update API.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

**Version 1.6.3 (2017/04/30)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Code refactoring to rename the $is_avail array to $avail for WPSSO v3.42.0.

**Version 1.6.2 (2017/04/22)**

* *New Features*
	* None
* *Improvements*
	* Added a warning message if one or more non-stable / development update version filters is selected (the notice can be dismissed for three months).
* *Bugfixes*
	* Removed the side metaboxes for WPSSO v3.41.0, which includes a new dashboard settings page.
* *Developer Notes*
	* None

**Version 1.6.1 (2017/04/17)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* Fixed update notices for plugins that are not installed. ;-)
* *Developer Notes*
	* None

**Version 1.6.0 (2017/04/16)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Moved the 'http_request_host_is_external' filter hook to the WPSSO v3.40.13 plugin.
	* Refactored the plugin init filters and moved/renamed the registration boolean from `is_avail[$name]` to `is_avail['p_ext'][$name]`.

== Upgrade Notice ==

= 1.6.6 =

(2017/09/10) Minor code refactoring for WPSSO v3.46.0.

= 1.6.5 =

(2017/08/31) Added a new 'wpsso_um_sslverify' filter for wp_remote_get() (returns true by default).

= 1.6.4 =

(2017/05/30) Added saving of the license expiration date from the update API.

= 1.6.3 =

(2017/04/30) Code refactoring to rename the $is_avail array to $avail for WPSSO v3.42.0.

= 1.6.2 =

(2017/04/22) Removed the side metaboxes for WPSSO v3.41.0, which includes a new dashboard settings page. Added a warning message if one or more non-stable / development update version filters is selected.

= 1.6.1 =

(2017/04/17) Fixed update notices for plugins that are not installed. ;-)

= 1.6.0 =

(2017/04/16) Moved the 'http_request_host_is_external' filter hook to the WPSSO v3.40.13 plugin. Refactored the plugin init filters and moved/renamed the registration boolean.

