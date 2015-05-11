<?php
/**
 * Plugin Name: WPSSO Pro Update Manager (WPSSO UM)
 * Plugin URI: http://surniaulula.com/extend/plugins/wpsso-um/
 * Author: Jean-Sebastien Morisset
 * Author URI: http://surniaulula.com/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Description: Update Manager for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its extensions
 * Requires At Least: 3.0
 * Tested Up To: 4.2.2
 * Version: 1.1.1
 * 
 * Copyright 2015 - Jean-Sebastien Morisset - http://surniaulula.com/
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm {

		public $p;			// Wpsso
		public $filters;		// WpssoUmFilters
		public $update;			// SucomUpdate

		protected static $instance = null;

		private $wpsso_min_version = '3.0.5';
		private $wpsso_has_min_ver = true;

		public static function &get_instance() {
			if ( self::$instance === null )
				self::$instance = new self;
			return self::$instance;
		}

		public function __construct() {

			require_once ( dirname( __FILE__ ).'/lib/config.php' );
			WpssoUmConfig::set_constants( __FILE__ );
			WpssoUmConfig::require_libs( __FILE__ );		// includes the register.php class library

			$this->reg = new WpssoUmRegister( $this );		// activate, deactivate, uninstall hooks

			if ( is_admin() )
				add_action( 'admin_init', array( &$this, 'wp_check_for_wpsso' ) );

			add_filter( 'wpsso_get_config', array( &$this, 'wpsso_get_config' ), 10, 1 );
			add_action( 'wpsso_init_plugin', array( &$this, 'wpsso_init_plugin' ), 10 );
		}

		// merge our config with the wpsso config
		// this filter is executed at wp init priority -1
		public function wpsso_get_config( $cf ) {
			if ( version_compare( $cf['plugin']['wpsso']['version'], $this->wpsso_min_version, '<' ) ) {
				$this->wpsso_has_min_ver = false;
				return $cf;
			}
			$cf = SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
			return $cf;
		}

		public function wp_check_for_wpsso() {
			if ( ! class_exists( 'Wpsso' ) )
				add_action( 'all_admin_notices', array( &$this, 'wp_notice_missing_wpsso' ) );
		}

		public function wp_notice_missing_wpsso() {
			$ext_name = WpssoUmConfig::$cf['plugin']['wpssoum']['name'];
			$req_name = 'WordPress Social Sharing Optimization (WPSSO)';
			$req_uca = 'WPSSO';
			echo '<div class="error"><p>';
			echo sprintf( __( 'The %s extension requires the %s plugin &mdash; '.
				'Please install and activate the %s plugin.', WPSSOUM_TEXTDOM ),
					$ext_name, $req_name, $req_uca );
			echo '</p></div>';
		}

		// executed once all class objects have been defined and modules have been loaded
		public function wpsso_init_plugin() {

			// fallback to global variable for older versions
			if ( method_exists( 'Wpsso', 'get_instance' ) )
				$this->p =& Wpsso::get_instance();
			else $this->p =& $GLOBALS['wpsso'];

			if ( $this->wpsso_has_min_ver === false )
				return $this->warning_wpsso_version( WpssoUmConfig::$cf['plugin']['wpssoum'] );

			require_once( WPSSOUM_PLUGINDIR.'lib/filters.php' );
			$this->filters = new WpssoUmFilters( $this->p, __FILE__ );

			$check_hours = empty( $this->p->cf['update_check_hours'] ) ? 
				24 : $this->p->cf['update_check_hours'];

			$this->update = new SucomUpdate( $this->p, $this->p->cf['plugin'], $check_hours );

			if ( is_admin() ) {
				foreach ( $this->p->cf['plugin'] as $lca => $info ) {

					// skip plugins that have an auth type, but no auth string
					if ( ! empty( $info['update_auth'] ) &&
						empty( $this->p->options['plugin_'.$lca.'_'.$info['update_auth']] ) )
							continue;

					$last_update = get_option( $lca.'_utime' );

					// check_hours of 24 * 7200 = 2 days
					if ( empty( $last_update ) || $last_update + ( $check_hours * 7200 ) < time() ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'requesting update check for '.$lca );
							$this->p->notice->inf( 'Performing an update check for the '.$info['name'].' plugin.' );
						}
						$this->update->check_for_updates( $lca );
					}
				}
			}
		}

		private function warning_wpsso_version( $info ) {
			$wpsso_version = $this->p->cf['plugin']['wpsso']['version'];
			if ( ! empty( $this->p->debug->enabled ) )
				$this->p->debug->log( $info['name'].' requires WPSSO version '.$this->wpsso_min_version.
					' or newer ('.$wpsso_version.' installed)' );
			if ( is_admin() )
				$this->p->notice->err( 'The '.$info['name'].' version '.$info['version'].
					' extension requires WPSSO version '.$this->wpsso_min_version.
					' or newer (version '.$wpsso_version.' is currently installed).', true );
		}
	}

        global $wpssoum;
	$wpssoum = WpssoUm::get_instance();
}

?>
