<?php
/*
 * Plugin Name: WPSSO Pro Update Manager (WPSSO UM)
 * Plugin Slug: wpsso-um
 * Text Domain: wpsso-um
 * Domain Path: /languages
 * Plugin URI: http://surniaulula.com/extend/plugins/wpsso-um/
 * Author: JS Morisset
 * Author URI: http://surniaulula.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Description: WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.
 * Requires At Least: 3.1
 * Tested Up To: 4.5
 * Version: 1.4.0
 * 
 * Copyright 2015-2016 Jean-Sebastien Morisset (http://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm {

		public $p;			// Wpsso
		public $reg;			// WpssoUmRegister
		public $filters;		// WpssoUmFilters
		public $update;			// SucomUpdate

		protected static $instance = null;

		private static $wpsso_short = 'WPSSO';
		private static $wpsso_name = 'WordPress Social Sharing Optimization (WPSSO)';
		private static $wpsso_min_version = '3.28.5';
		private static $wpsso_has_min_ver = true;

		public static function &get_instance() {
			if ( self::$instance === null )
				self::$instance = new self;
			return self::$instance;
		}

		public function __construct() {

			require_once ( dirname( __FILE__ ).'/lib/config.php' );
			WpssoUmConfig::set_constants( __FILE__ );
			WpssoUmConfig::require_libs( __FILE__ );		// includes the register.php class library
			$this->reg = new WpssoUmRegister();			// activate, deactivate, uninstall hooks

			if ( is_admin() ) {
				load_plugin_textdomain( 'wpsso-um', false, 'wpsso-um/languages/' );
				add_action( 'admin_init', array( &$this, 'check_for_wpsso' ) );
			}

			add_filter( 'wpsso_get_config', array( &$this, 'wpsso_get_config' ), 10, 1 );
			add_action( 'wpsso_init_plugin', array( &$this, 'wpsso_init_plugin' ), 10 );
		}

		public function check_for_wpsso() {
			if ( ! class_exists( 'Wpsso' ) )
				add_action( 'all_admin_notices', array( &$this, 'wpsso_missing_notice' ) );
		}

		public static function wpsso_missing_notice( $deactivate = false ) {
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];

			if ( $deactivate === true ) {
				require_once( ABSPATH.'wp-admin/includes/plugin.php' );
				deactivate_plugins( $info['base'] );

				wp_die( '<p>'.sprintf( __( 'The %1$s extension requires the %2$s plugin &mdash; please install and activate the %3$s plugin before trying to re-activate the %4$s extension.', 'wpsso-um' ), $info['name'], self::$wpsso_name, self::$wpsso_short, $info['short'] ).'</p>' );

			} else echo '<div class="error"><p>'.sprintf( __( 'The %1$s extension requires the %2$s plugin &mdash; please install and activate the %3$s plugin.', 'wpsso-um' ), $info['name'], self::$wpsso_name, self::$wpsso_short ).'</p></div>';
		}

		public function wpsso_get_config( $cf ) {
			if ( version_compare( $cf['plugin']['wpsso']['version'], self::$wpsso_min_version, '<' ) ) {
				self::$wpsso_has_min_ver = false;
				return $cf;
			}
			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
		}

		public function wpsso_init_plugin() {

			if ( method_exists( 'Wpsso', 'get_instance' ) )
				$this->p =& Wpsso::get_instance();
			else $this->p =& $GLOBALS['wpsso'];

			if ( self::$wpsso_has_min_ver === false )
				return $this->warning_wpsso_version();

			$this->filters = new WpssoUmFilters( $this->p );
			$this->update = new SucomUpdate( $this->p, $this->p->cf['plugin'], 'wpsso-um' );

			/*
			 * Force immediate check if no update check for past 2 days
			 */
			if ( is_admin() ) {
				foreach ( $this->p->cf['plugin'] as $lca => $info ) {
					// skip plugins that have an auth type but no auth string
					if ( ! empty( $info['update_auth'] ) &&
						empty( $this->p->options['plugin_'.$lca.'_'.$info['update_auth']] ) )
							continue;

					// force check if no update in update_check_hours of 24 hours * 7200 = 2 days
					$last_utime = $this->update->get_umsg( $lca, 'time' );
					if ( empty( $last_utime ) || 
						$last_utime + ( $this->p->cf['update_check_hours'] * 7200 ) < time() ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'requesting update check for '.$lca );
							$this->p->notice->inf( 'Performing an update check for the '.$info['name'].' plugin.' );
						}
						$this->update->check_for_updates( $lca, false, false );	// $use_cache = false
					}
				}
			}
		}

		private function warning_wpsso_version() {
			$info = WpssoUmConfig::$cf['plugin']['wpssoum'];
			$wpsso_version = $this->p->cf['plugin']['wpsso']['version'];

			if ( $this->p->debug->enabled )
				$this->p->debug->log( $info['name'].' requires '.self::$wpsso_short.' version '.
					self::$wpsso_min_version.' or newer ('.$wpsso_version.' installed)' );

			if ( is_admin() )
				$this->p->notice->err( sprintf( __( 'The %1$s extension version %2$s requires the use of %3$s version %4$s or newer (version %5$s is currently installed).', 'wpsso-um' ), $info['name'], $info['version'], self::$wpsso_short, self::$wpsso_min_version, $wpsso_version ), true );
		}
	}

        global $wpssoum;
	$wpssoum = WpssoUm::get_instance();
}

?>
