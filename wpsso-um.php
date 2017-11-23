<?php
/*
 * Plugin Name: WPSSO Update Manager
 * Plugin Slug: wpsso-um
 * Text Domain: wpsso-um
 * Domain Path: /languages
 * Plugin URI: https://wpsso.com/extend/plugins/wpsso-um/
 * Assets URI: https://surniaulula.github.io/wpsso-um/assets/
 * Author: JS Morisset
 * Author URI: https://wpsso.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Description: WPSSO Core extension to provide updates for the WPSSO Core Pro plugin and its Pro extensions.
 * Requires PHP: 5.4
 * Requires At Least: 3.8
 * Tested Up To: 4.9
 * Version: 1.8.0-b.2
 * 
 * Version Numbering: {major}.{minor}.{bugfix}[-{stage}.{level}]
 *
 *	{major}		Major structural code changes / re-writes or incompatible API changes.
 *	{minor}		New functionality was added or improved in a backwards-compatible manner.
 *	{bugfix}	Backwards-compatible bug fixes or small improvements.
 *	{stage}.{level}	Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).
 * 
 * Copyright 2015-2017 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm {

		public $p;			// Wpsso
		public $reg;			// WpssoUmRegister
		public $filters;		// WpssoUmFilters
		public $update;			// SucomUpdate

		private $check_hours = 24;
		private static $instance;
		private static $have_min = true;	// have minimum wpsso version

		public function __construct() {

			require_once ( dirname( __FILE__ ).'/lib/config.php' );
			WpssoUmConfig::set_constants( __FILE__ );
			WpssoUmConfig::require_libs( __FILE__ );	// includes the register.php class library
			$this->reg = new WpssoUmRegister();		// activate, deactivate, uninstall hooks

			if ( is_admin() ) {
				add_action( 'admin_init', array( __CLASS__, 'required_check' ) );
				add_action( 'wpsso_init_textdomain', array( __CLASS__, 'wpsso_init_textdomain' ) );
			}

			add_filter( 'wpsso_get_config', array( &$this, 'wpsso_get_config' ), 10, 2 );
			add_action( 'wpsso_init_options', array( &$this, 'wpsso_init_options' ), 10 );
			add_action( 'wpsso_init_objects', array( &$this, 'wpsso_init_objects' ), 10 );
			add_action( 'wpsso_init_plugin', array( &$this, 'wpsso_init_plugin' ), -100 );
		}

		public static function &get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		public static function required_check() {
			if ( ! class_exists( 'Wpsso' ) ) {
				add_action( 'all_admin_notices', array( __CLASS__, 'required_notice' ) );
			}
		}

		// also called from the activate_plugin method with $deactivate = true
		public static function required_notice( $deactivate = false ) {
			self::wpsso_init_textdomain();
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];
			$die_msg = __( '%1$s is an extension for the %2$s plugin &mdash; please install and activate the %3$s plugin before activating %4$s.', 'wpsso-um' );
			$err_msg = __( 'The %1$s extension requires the %2$s plugin &mdash; install and activate the %3$s plugin or <a href="%4$s">deactivate the %5$s extension</a>.', 'wpsso-um' );
			if ( $deactivate === true ) {
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once trailingslashit( ABSPATH ).'wp-admin/includes/plugin.php';
				}
				deactivate_plugins( $info['base'], true );	// $silent = true
				wp_die( '<p>'.sprintf( $die_msg, $info['name'], $info['req']['name'],
					$info['req']['short'], $info['short'] ).'</p>' );
			} else {
				$deactivate_url = wp_nonce_url( 'plugins.php?action=deactivate&amp;'.
					'plugin='.$info['base'].'&amp;plugin_status=active&amp;paged=1&amp;s=',
						'deactivate-plugin_'.$info['base'] );
				echo '<div class="notice notice-error error"><p>'.
					sprintf( $err_msg, $info['name'], $info['req']['name'],
						$info['req']['short'], $deactivate_url, $info['short'] ).'</p></div>';
			}
		}

		public static function wpsso_init_textdomain() {
			load_plugin_textdomain( 'wpsso-um', false, 'wpsso-um/languages/' );
		}

		public function wpsso_get_config( $cf, $plugin_version = 0 ) {
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];

			if ( version_compare( $plugin_version, $info['req']['min_version'], '<' ) ) {
				self::$have_min = false;
				return $cf;
			}

			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
		}

		public function wpsso_init_options() {
			if ( method_exists( 'Wpsso', 'get_instance' ) ) {
				$this->p =& Wpsso::get_instance();
			} else {
				$this->p =& $GLOBALS['wpsso'];
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( self::$have_min ) {
				$this->p->avail['p_ext']['um'] = true;
			} else {
				$this->p->avail['p_ext']['um'] = false;	// just in case
			}
		}

		public function wpsso_init_objects() {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( self::$have_min ) {
				$info = WpssoUmConfig::$cf['plugin']['wpssoum'];
				$this->check_hours = $this->get_update_check_hours();
				$this->filters = new WpssoUmFilters( $this->p );
				$this->update = new SucomUpdate( $this->p, $this->check_hours, $info['text_domain'] );
			}
		}

		public function wpsso_init_plugin() {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! self::$have_min ) {
				return $this->min_version_notice();	// stop here
			}

			if ( is_admin() ) {
				foreach ( $this->p->cf['plugin'] as $ext => $info ) {
					if ( ! SucomUpdate::is_installed( $ext ) ) {				// plugin must be installed for updates
						continue;
					}
					$last_utime = $this->update->get_umsg( $ext, 'time' );			// get the last update check
					$next_utime = $last_utime + ( $this->check_hours * HOUR_IN_SECONDS );	// get the next scheduled check
					if ( empty( $last_utime ) || $next_utime + DAY_IN_SECONDS < time() ) {	// check if more than 1 day overdue
						if ( $this->p->debug->enabled ) {
							$dismiss_key = __FUNCTION__.'_'.$ext.'_update_check';
							$this->p->debug->log( 'requesting update check for '.$ext );
							$this->p->notice->inf( sprintf( __( 'Performing an update check for the %s plugin.',
								'wpsso-um' ), $info['name'] ), true, $dismiss_key, true );
						}
						$this->update->check_ext_for_updates( $ext, true, false );	// $quiet = true, $use_cache = false
					}
				}
			}
		}

		private function min_version_notice() {
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];
			$wpsso_version = $this->p->cf['plugin']['wpsso']['version'];

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( $info['name'].' requires '.$info['req']['short'].' v'.
					$info['req']['min_version'].' or newer ('.$wpsso_version.' installed)' );
			}

			if ( is_admin() ) {
				$this->p->notice->err( sprintf( __( 'The %1$s extension v%2$s requires %3$s v%4$s or newer (v%5$s currently installed).',
					'wpsso-um' ), $info['name'], $info['version'], $info['req']['short'],
						$info['req']['min_version'], $wpsso_version ) );
			}
		}

		// minimum value is 12 hours for the constant, 24 hours otherwise
		public function get_update_check_hours() {
			if ( SucomUtil::get_const( 'WPSSOUM_CHECK_HOURS', 0 ) >= 12 ) {
				return WPSSOUM_CHECK_HOURS;
			} elseif ( isset( $this->p->options['update_check_hours'] ) &&
				$this->p->options['update_check_hours'] >= 24 ) {
				return $this->p->options['update_check_hours'];
			} else {
				return 24;	// default value
			}
		}
	}

        global $wpssoum;
	$wpssoum =& WpssoUm::get_instance();
}

?>
