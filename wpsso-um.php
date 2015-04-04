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
 * Tested Up To: 4.1.1
 * Version: 1.0dev1
 * 
 * Copyright 2015 - Jean-Sebastien Morisset - http://surniaulula.com/
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm {

		public $p;				// class object variables

		protected static $instance = null;

		private $wpsso_min_version = '3.0dev1';
		private $wpsso_has_min_ver = true;

		public static function &get_instance() {
			if ( self::$instance === null )
				self::$instance = new self;
			return self::$instance;
		}

		public function __construct() {
			require_once ( dirname( __FILE__ ).'/lib/config.php' );
			WpssoUmConfig::set_constants( __FILE__ );
			WpssoUmConfig::require_libs( __FILE__ );

			add_filter( 'wpsso_get_config', array( &$this, 'filter_get_config' ), 10, 1 );

			if ( is_admin() )
				add_action( 'admin_init', array( &$this, 'check_for_wpsso' ) );

			add_action( 'wpsso_init_plugin', array( &$this, 'init_plugin' ), 10 );
		}

		// merge our config with the wpsso config
		// this filter is executed at wp init priority -1
		public function filter_get_config( $cf ) {
			if ( version_compare( $cf['plugin']['wpsso']['version'], $this->wpsso_min_version, '<' ) ) {
				$this->wpsso_has_min_ver = false;
				return $cf;
			}
			$cf = SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
			return $cf;
		}

		public function check_for_wpsso() {
			if ( ! class_exists( 'Wpsso' ) ) {
				require_once( ABSPATH.'wp-admin/includes/plugin.php' );
				deactivate_plugins( WPSSOUM_PLUGINBASE );
				wp_die( '<p>'. sprintf( __( 'The WPSSO Pro Update Manager (WPSSO UM) extension requires the WordPress Social Sharing Optimization (WPSSO) plugin &mdash; Please install and activate WPSSO before re-activating this extension.', WPSSOAM_TEXTDOM ) ).'</p>' );
			}
		}

		// executed once all class objects have been defined and modules have been loaded
		public function init_plugin() {
			$this->p =& Wpsso::get_instance();
			$this->update = new SucomUpdate( $this->p, $this->p->cf['plugin'], $this->p->cf['update_check_hours'] );
			if ( is_admin() ) {
				foreach ( array_keys( $this->p->cf['plugin'] ) as $lca ) {
					$last_update = get_option( $lca.'_utime' );
					if ( empty( $last_update ) || 
						( ! empty( $this->cf['update_check_hours'] ) && 
							$last_update + ( $this->cf['update_check_hours'] * 7200 ) < time() ) )
								$this->update->check_for_updates( $lca );
				}
			}
		}
	}

        global $wpssoum;
	$wpssoum = WpssoUm::get_instance();
}

?>
