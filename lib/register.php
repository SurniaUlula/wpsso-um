<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2019 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmRegister' ) ) {

	class WpssoUmRegister {

		public function __construct() {

			register_activation_hook( WPSSOUM_FILEPATH, array( $this, 'network_activate' ) );

			register_deactivation_hook( WPSSOUM_FILEPATH, array( $this, 'network_deactivate' ) );

			if ( is_multisite() ) {

				add_action( 'wpmu_new_blog', array( $this, 'wpmu_new_blog' ), 10, 6 );

				add_action( 'wpmu_activate_blog', array( $this, 'wpmu_activate_blog' ), 10, 5 );
			}
		}

		/**
		 * Fires immediately after a new site is created.
		 */
		public function wpmu_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

			switch_to_blog( $blog_id );

			$this->activate_plugin();

			restore_current_blog();
		}

		/**
		 * Fires immediately after a site is activated (not called when users and sites are created by a Super Admin).
		 */
		public function wpmu_activate_blog( $blog_id, $user_id, $password, $signup_title, $meta ) {

			switch_to_blog( $blog_id );

			$this->activate_plugin();

			restore_current_blog();
		}

		public function network_activate( $sitewide ) {

			self::do_multisite( $sitewide, array( $this, 'activate_plugin' ) );
		}

		public function network_deactivate( $sitewide ) {

			self::do_multisite( $sitewide, array( $this, 'deactivate_plugin' ) );
		}

		/**
		 * uninstall.php defines constants before calling network_uninstall().
		 */
		public static function network_uninstall() {

			$sitewide = true;

			/**
			 * Uninstall from the individual blogs first.
			 */
			self::do_multisite( $sitewide, array( __CLASS__, 'uninstall_plugin' ) );
		}

		private static function do_multisite( $sitewide, $method, $args = array() ) {

			if ( is_multisite() && $sitewide ) {

				global $wpdb;

				$db_query = 'SELECT blog_id FROM ' . $wpdb->blogs;
				$blog_ids = $wpdb->get_col( $db_query );

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );

					call_user_func_array( $method, array( $args ) );
				}

				restore_current_blog();

			} else {
				call_user_func_array( $method, array( $args ) );
			}
		}

		private function activate_plugin() {

			if ( class_exists( 'Wpsso' ) ) {

				/**
				 * Register plugin install, activation, update times.
				 */
				if ( class_exists( 'WpssoUtilReg' ) ) {	// Since WPSSO v6.13.1.

					$version = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ][ 'version' ];

					WpssoUtilReg::update_ext_version( 'wpssoum', $version );
				}
			}

			self::delete_options();
		}

		private function deactivate_plugin() {

			if ( class_exists( 'WpssoConfig' ) ) {

				$cf = WpssoConfig::get_config();

				foreach ( $cf[ 'plugin' ] as $ext => $info ) {

					wp_clear_scheduled_hook( 'plugin_updates-' . $info[ 'slug' ] );
				}
			}
		}

		private static function uninstall_plugin() {
			self::delete_options();
		}

		private static function delete_options() {

			$api_version = SucomUpdate::get_api_version();

			if ( class_exists( 'WpssoConfig' ) ) {

				$cf = WpssoConfig::get_config();

				foreach ( $cf[ 'plugin' ] as $ext => $info ) {

					foreach ( array( 'err', 'inf', 'time' ) as $type ) {
						delete_option( md5( $ext . '_uapi' . $api_version . $type ) );
					}

					delete_option( 'external_updates-' . $info[ 'slug' ] );
				}

			} else {	// In case wpsso is deactivated.

				foreach ( array(
					'wpsso'      => 'wpsso',
					'wpssoam'    => 'wpsso-am',
					'wpssojson'  => 'wpsso-schema-json-ld',
					'wpssoorg'   => 'wpsso-organization',
					'wpssoplm'   => 'wpsso-plm',
					'wpssorrssb' => 'wpsso-rrssb',
					'wpssossb'   => 'wpsso-ssb',
					'wpssotie'   => 'wpsso-tune-image-editors',
					'wpssoum'    => 'wpsso-um',
				) as $ext => $slug ) {

					foreach ( array( 'err', 'inf', 'time' ) as $type ) {
						delete_option( md5( $ext . '_uapi' . $api_version . $type ) );
					}

					delete_option( 'external_updates-' . $slug );
				}
			}
		}
	}
}
