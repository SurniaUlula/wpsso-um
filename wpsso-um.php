<?php
/*
 * Plugin Name: WPSSO Pro Update Manager (WPSSO UM)
 * Plugin Slug: wpsso-um
 * Text Domain: wpsso-um
 * Domain Path: /languages
 * Plugin URI: https://surniaulula.com/extend/plugins/wpsso-um/
 * Assets URI: https://surniaulula.github.io/wpsso-um/assets/
 * Author: JS Morisset
 * Author URI: https://surniaulula.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Description: WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.
 * Requires At Least: 3.5
 * Tested Up To: 4.6.1
 * Version: 1.5.11-1
 * 
 * Version Numbering Scheme: {major}.{minor}.{bugfix}-{stage}{level}
 *
 *	{major}		Major code changes / re-writes or significant feature changes.
 *	{minor}		New features / options were added or improved.
 *	{bugfix}	Bugfixes or minor improvements.
 *	{stage}{level}	dev < a (alpha) < b (beta) < rc (release candidate) < # (production).
 *
 * See PHP's version_compare() documentation at http://php.net/manual/en/function.version-compare.php.
 * 
 * Copyright 2015-2016 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm {

		public $p;			// Wpsso
		public $reg;			// WpssoUmRegister
		public $filters;		// WpssoUmFilters
		public $update;			// SucomUpdate

		private static $instance = null;
		private static $check_hours = 24;
		private static $allow_host = 'wpsso.com';
		private static $text_domain = 'wpsso-um';
		private static $req_short = 'WPSSO';
		private static $req_name = 'WordPress Social Sharing Optimization (WPSSO)';
		private static $req_min_version = '3.36.0-1';
		private static $req_has_min_ver = true;

		public static function &get_instance() {
			if ( self::$instance === null )
				self::$instance = new self;
			return self::$instance;
		}

		public function __construct() {

			require_once ( dirname( __FILE__ ).'/lib/config.php' );
			WpssoUmConfig::set_constants( __FILE__ );
			WpssoUmConfig::require_libs( __FILE__ );	// includes the register.php class library
			$this->reg = new WpssoUmRegister();		// activate, deactivate, uninstall hooks

			if ( is_admin() ) {
				load_plugin_textdomain( 'wpsso-um', false, 'wpsso-um/languages/' );
				add_action( 'admin_init', array( &$this, 'required_check' ) );
			}

			add_filter( 'wpsso_get_config', array( &$this, 'wpsso_get_config' ), 10, 2 );
			add_action( 'wpsso_init_options', array( &$this, 'wpsso_init_options' ), 10 );
			add_action( 'wpsso_init_objects', array( &$this, 'wpsso_init_objects' ), 10 );
			add_action( 'wpsso_init_plugin', array( &$this, 'wpsso_init_plugin' ), -100 );
		}

		public function required_check() {
			if ( ! class_exists( 'Wpsso' ) )
				add_action( 'all_admin_notices', array( &$this, 'required_notice' ) );
		}

		public static function required_notice( $deactivate = false ) {
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];

			if ( $deactivate === true ) {
				require_once( ABSPATH.'wp-admin/includes/plugin.php' );
				deactivate_plugins( $info['base'] );

				wp_die( '<p>'.sprintf( __( 'The %1$s extension requires the %2$s plugin &mdash; please install and activate the %3$s plugin before trying to re-activate the %4$s extension.', 'wpsso-um' ), $info['name'], self::$req_name, self::$req_short, $info['short'] ).'</p>' );

			} else echo '<div class="error"><p>'.sprintf( __( 'The %1$s extension requires the %2$s plugin &mdash; please install and activate the %3$s plugin.', 'wpsso-um' ), $info['name'], self::$req_name, self::$req_short ).'</p></div>';
		}

		public function wpsso_get_config( $cf, $plugin_version = 0 ) {
			if ( version_compare( $plugin_version, self::$req_min_version, '<' ) ) {
				self::$req_has_min_ver = false;
				return $cf;
			}
			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
		}

		public function wpsso_init_options() {
			if ( method_exists( 'Wpsso', 'get_instance' ) )
				$this->p =& Wpsso::get_instance();
			else $this->p =& $GLOBALS['wpsso'];

			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( self::$req_has_min_ver === false )
				return;		// stop here
		}

		public function wpsso_init_objects() {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( self::$req_has_min_ver === false )
				return;		// stop here

			self::$check_hours = $this->get_update_check_hours();

			$this->filters = new WpssoUmFilters( $this->p );
			$this->update = new SucomUpdate( $this->p, $this->p->cf['plugin'],
				self::$check_hours, self::$allow_host, self::$text_domain );
		}

		public function wpsso_init_plugin() {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( self::$req_has_min_ver === false )
				return $this->min_version_notice();

			/*
			 * Force immediate check if no update check for past 2 days
			 */
			if ( is_admin() ) {
				foreach ( $this->p->cf['plugin'] as $ext => $info ) {

					if ( ! SucomUpdate::is_configured( $ext ) )
						continue;

					$last_utime = $this->update->get_umsg( $ext, 'time' );		// last update check
					$next_utime = $last_utime + ( self::$check_hours * 3600 );	// next scheduled check

					if ( empty( $last_utime ) || $next_utime + 86400 < time() ) {	// plus one day
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'requesting update check for '.$ext );
							$this->p->notice->inf( 'Performing an update check for the '.$info['name'].' plugin.',
								true, __FUNCTION__.'_'.$ext.'_update_check', true );
						}
						$this->update->check_for_updates( $ext, false, false );	// $notice = false, $use_cache = false
					}
				}
			}
		}

		private function min_version_notice() {
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];
			$have_version = $this->p->cf['plugin']['wpsso']['version'];

			if ( $this->p->debug->enabled )
				$this->p->debug->log( $info['name'].' requires '.self::$req_short.' version '.
					self::$req_min_version.' or newer ('.$have_version.' installed)' );

			if ( is_admin() )
				$this->p->notice->err( sprintf( __( 'The %1$s extension version %2$s requires the use of %3$s version %4$s or newer (version %5$s is currently installed).', 'wpsso-um' ), $info['name'], $info['version'], self::$req_short, self::$req_min_version, $have_version ) );
		}

		// minimum value is 12 hours for the constant, 24 hours otherwise
		public static function get_update_check_hours() {
			$wpsso =& Wpsso::get_instance();
			if ( SucomUtil::get_const( 'WPSSOUM_CHECK_HOURS', 0 ) >= 12 )
				return WPSSOUM_CHECK_HOURS;
			elseif ( isset( $wpsso->options['update_check_hours'] ) &&
				$wpsso->options['update_check_hours'] >= 24 )
					return $wpsso->options['update_check_hours'];
			else return 24;	// default value
		}
	}

        global $wpssoum;
	$wpssoum =& WpssoUm::get_instance();
}

?>
