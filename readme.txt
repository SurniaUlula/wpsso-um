=== WPSSO Pro Update Manager ===
Plugin Name: WPSSO Pro Update Manager (WPSSO UM)
Plugin Slug: wpsso-um
Text Domain: wpsso-um
Domain Path: /languages
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.txt
Donate Link: https://wpsso.com/?utm_source=wpssoum-readme-donate
Assets URI: https://surniaulula.github.io/wpsso-um/assets/
Tags: wpsso, update, manager
Contributors: jsmoriss
Requires At Least: 3.5
Tested Up To: 4.6.1
Stable Tag: 1.5.11-1

WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.

== Description ==

<p><img src="https://surniaulula.github.io/wpsso-um/assets/icon-256x256.png" width="256" height="256" style="width:33%;min-width:128px;max-width:256px;float:left;margin:0 40px 20px 0;" />The WPSSO Pro Update Manager (WPSSO UM) extension plugin is required to enable and update the <a href="https://wpsso.com/extend/plugins/wpsso/">WordPress Social Sharing Optimization (WPSSO) Pro</a> version plugin, including all its licensed Pro extensions.</p>

Simply *download*, *install*, and *activate*.

<blockquote>
<p><strong>Prerequisite</strong> &mdash; WPSSO Pro Update Manager (WPSSO UM) is an extension for the <a href="https://wordpress.org/plugins/wpsso/">WordPress Social Sharing Optimization (WPSSO)</a> plugin, which <em>automatically</em> creates complete and accurate meta tags and Schema markup for Social Sharing Optimization (SSO) and SEO.</p>
</blockquote>

== Installation ==

= Install and Uninstall =

* [Install the Plugin](https://wpsso.com/codex/plugins/wpsso-um/installation/install-the-plugin/)
* [Uninstall the Plugin](https://wpsso.com/codex/plugins/wpsso-um/installation/uninstall-the-plugin/)

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

**Version 1.5.11-1 (2016/11/03)**

Official announcement: N/A

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* None
* *Developer Notes*
	* Refactored the SucomUpdate class to optimize caching performance.

**Version 1.5.10-2 (2016/10/23)**

Official announcement: N/A

* *New Features*
	* None
* *Improvements*
	* None
* *Bugfixes*
	* Fixed a "missing extension" error for configured extensions that are not yet installed.
* *Developer Notes*
	* None

**Version 1.5.9-1 (2016/10/15)**

Official announcement: N/A

* *New Features*
	* None
* *Improvements*
	* Added a check for plugin data in the class property cache (faster) before falling back to the WordPress transient / object cache.
* *Bugfixes*
	* None
* *Developer Notes*
	* Added a delete_transient(), wp_cache_delete(), delete_option() call before updating to force a WordPress cache refresh.

**Version 1.5.8-1 (2016/10/01)**

Official announcement: N/A

* *New Features*
	* None
* *Improvements*
	* Added notice messages for missing WordPress plugin library and/or get_plugins() function.
* *Bugfixes*
	* None
* *Developer Notes*
	* None

== Upgrade Notice ==

= 1.5.11-1 =

(2016/11/03) Refactored the SucomUpdate class to optimize caching performance.

