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
Stable Tag: 1.5.18-1

WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.

== Description ==

<img src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png" style="width:33%;min-width:128px;max-width:256px;height:auto;float:left;margin:10px 40px 40px 0;" />

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

= Version Numbering Scheme =

Version components: `{major}.{minor}.{bugfix}-{stage}{level}`

* {major} = Major code changes / re-writes or significant feature changes.
* {minor} = New features / options were added or improved.
* {bugfix} = Bugfixes or minor improvements.
* {stage}{level} = dev &lt; a (alpha) &lt; b (beta) &lt; rc (release candidate) &lt; # (production).

Note that the production stage level can be incremented on occasion for simple text revisions and/or translation updates. See [PHP's version_compare()](http://php.net/manual/en/function.version-compare.php) documentation for additional information on "PHP-standardized" version numbering.

= Changelog / Release Notes =

**Version 1.5.19-dev2 (2017/04/03)**

* *New Features*
	* None
* *Improvements*
	* Updated the plugin documentation and FAQ URLs.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

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

= 1.5.19-dev2 =

(2017/04/03) Updated the plugin documentation and FAQ URLs.

= 1.5.18-1 =

(2017/03/25) Added an empty HTTP Expect: header to avoid "cURL error 52: Empty reply from server".

= 1.5.17-1 =

(2017/02/26) Improved the layout of options in the site and network Update Manager settings page.

= 1.5.16-1 =

(2017/02/26) Improved loading sequence of the plugin textdomain for WPSSO v3.40.0-1.

= 1.5.15-1 =

(2017/01/22) Improved clearing of error and information messages on successful update checks.

