=== WPSSO Update Manager ===
Plugin Name: WPSSO Update Manager (WPSSO UM)
Plugin Slug: wpsso-um
Text Domain: wpsso-um
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.txt
Assets URI: https://surniaulula.github.io/wpsso-um/assets/
Tags: wpsso, update, manager, schedule, update check, extension, pro version, development version, pre-release
Contributors: jsmoriss
Requires At Least: 3.7
Tested Up To: 4.7.3
Stable Tag: 1.5.20

WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.

== Description ==

<img class="readme-icon" src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png">

<p>The WPSSO Update Manager (WPSSO UM) extension plugin is required to enable and update the <a href="https://wpsso.com/extend/plugins/wpsso/">WordPress Social Sharing Optimization (WPSSO) Pro</a> version plugin, including all its licensed Pro extensions.</p>

Simply *download*, *install*, and *activate*.

<blockquote>
<p><strong>Prerequisite</strong> &mdash; WPSSO Update Manager (WPSSO UM) is an extension for the <a href="https://wordpress.org/plugins/wpsso/">WordPress Social Sharing Optimization (WPSSO)</a> plugin, which <em>automatically</em> generates complete and accurate meta tags + Schema markup from your content for Social Sharing Optimization (SSO) and Search Engine Optimization (SEO).</p>
</blockquote>

== Installation ==

= Install and Uninstall =

* [Install the Plugin](https://wpsso.com/docs/plugins/wpsso-um/installation/install-the-plugin/)
* [Uninstall the Plugin](https://wpsso.com/docs/plugins/wpsso-um/installation/uninstall-the-plugin/)

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

**Version 1.6.0-b.1 (2017/04/13)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Moved the 'http_request_host_is_external' filter hook to the WPSSO v3.40.13 plugin.
	* Refactored the plugin init filters and moved/renamed the registration boolean from `is_avail[$name]` to `is_avail['p_ext'][$name]`.

**Version 1.5.20 (2017/04/08)**

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Minor code optimization for the plugin data filters.
	* Updated the version filters to allow for both {stage}# and {stage}.#.
	* Dropped the package number from the production version string.

**Version 1.5.19-1 (2017/04/05)**

* *New Features*
	* None
* *Improvements*
	* Updated the plugin icon images and the documentation URLs.
* *Bugfixes*
	* None
* *Developer Notes*
	* Renamed WpssoAdmin get_site_use() to get_option_site_use() for WPSSO v3.40.11-1.

**Version 1.5.18-1 (2017/03/25)**

* *New Features*
	* None
* *Improvements*
	* Added an empty HTTP `Expect:` header to avoid "cURL error 52: Empty reply from server" when using the WordPress wp_remote_get() function.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

**Version 1.5.17-1 (2017/02/26)**

* *New Features*
	* None
* *Improvements*
	* Improved the layout of options in the site and network Update Manager settings page.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

**Version 1.5.16-1 (2017/02/26)**

* *New Features*
	* None
* *Improvements*
	* Improved loading sequence of the plugin textdomain for WPSSO v3.40.0-1.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

**Version 1.5.15-1 (2017/01/22)**

* *New Features*
	* None
* *Improvements*
	* Improved clearing of error and information messages on successful update checks.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

== Upgrade Notice ==

= 1.6.0-b.1 =

(2017/04/13) Moved the 'http_request_host_is_external' filter hook to the WPSSO v3.40.13 plugin. Refactored the plugin init filters and moved/renamed the registration boolean.

= 1.5.20 =

(2017/04/08) Minor code optimization for the plugin data filters. Updated the version filters to allow for both {stage}# and {stage}.#. 

= 1.5.19-1 =

(2017/04/05) Updated the plugin icon images and the documentation URLs. Renamed a WpssoAdmin class method for WPSSO v3.40.11-1.

= 1.5.18-1 =

(2017/03/25) Added an empty HTTP Expect: header to avoid "cURL error 52: Empty reply from server".

= 1.5.17-1 =

(2017/02/26) Improved the layout of options in the site and network Update Manager settings page.

= 1.5.16-1 =

(2017/02/26) Improved loading sequence of the plugin textdomain for WPSSO v3.40.0-1.

= 1.5.15-1 =

(2017/01/22) Improved clearing of error and information messages on successful update checks.

