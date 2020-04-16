<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmConfig' ) ) {

	class WpssoUmConfig {

		public static $cf = array(
			'plugin' => array(
				'wpssoum' => array(			// Plugin acronym.
					'version'     => '2.9.0-rc.1',	// Plugin version.
					'opt_version' => '4',		// Increment when changing default option values.
					'short'       => 'WPSSO UM',	// Short plugin name.
					'name'        => 'WPSSO Update Manager',
					'desc'        => 'Update Manager for the WPSSO Core Premium Plugin and its Premium Complementary Add-ons.',
					'slug'        => 'wpsso-um',
					'base'        => 'wpsso-um/wpsso-um.php',
					'update_auth' => '',		// No premium version.
					'text_domain' => 'wpsso-um',
					'domain_path' => '/languages',

					/**
					 * Required plugin and its version.
					 */
					'req' => array(
						'wpsso' => array(
							'class'       => 'Wpsso',
							'name'        => 'WPSSO Core',
							'min_version' => '4.26.0',	// Required minimum version (released on 2018/04/06).
						),
					),

					/**
					 * URLs or relative paths to plugin banners and icons.
					 */
					'assets' => array(
						'icons' => array(
							'low'  => 'images/icon-128x128.png',
							'high' => 'images/icon-256x256.png',
						),
					),

					/**
					 * Library files loaded and instantiated by WPSSO.
					 */
					'lib' => array(
						'pro' => array(
						),
						'sitesubmenu' => array(
							'site-um-general' => 'Update Manager',
						),
						'std' => array(
						),
						'submenu' => array(
							'um-general' => 'Update Manager',
						),
					),
				),
			),

			/**
			 * Additional add-on setting options.
			 */
			'opt' => array(
				'defaults' => array(
					'update_check_hours' => 24,
				),
				'site_defaults' => array(
					'update_check_hours'     => 24,
					'update_check_hours:use' => 'default',
				),
			),
		);

		public static function get_version( $add_slug = false ) {

			$info =& self::$cf[ 'plugin' ][ 'wpssoum' ];

			return $add_slug ? $info[ 'slug' ] . '-' . $info[ 'version' ] : $info[ 'version' ];
		}

		public static function set_constants( $plugin_file_path ) { 

			if ( defined( 'WPSSOUM_VERSION' ) ) {	// Define constants only once.
				return;
			}

			$info =& self::$cf[ 'plugin' ][ 'wpssoum' ];

			/**
			 * Define fixed constants.
			 */
			define( 'WPSSOUM_FILEPATH', $plugin_file_path );						
			define( 'WPSSOUM_PLUGINBASE', $info[ 'base' ] );	// Example: wpsso-um/wpsso-um.php.
			define( 'WPSSOUM_PLUGINDIR', trailingslashit( realpath( dirname( $plugin_file_path ) ) ) );
			define( 'WPSSOUM_PLUGINSLUG', $info[ 'slug' ] );	// Example: wpsso-um.
			define( 'WPSSOUM_URLPATH', trailingslashit( plugins_url( '', $plugin_file_path ) ) );
			define( 'WPSSOUM_VERSION', $info[ 'version' ] );						
		}

		public static function require_libs( $plugin_file_path ) {

			require_once WPSSOUM_PLUGINDIR . 'lib/com/update.php';

			require_once WPSSOUM_PLUGINDIR . 'lib/actions.php';
			require_once WPSSOUM_PLUGINDIR . 'lib/filters.php';
			require_once WPSSOUM_PLUGINDIR . 'lib/register.php';

			add_filter( 'wpssoum_load_lib', array( 'WpssoUmConfig', 'load_lib' ), 10, 3 );
		}

		public static function load_lib( $ret = false, $filespec = '', $classname = '' ) {

			if ( false === $ret && ! empty( $filespec ) ) {

				$file_path = WPSSOUM_PLUGINDIR . 'lib/' . $filespec . '.php';

				if ( file_exists( $file_path ) ) {

					require_once $file_path;

					if ( empty( $classname ) ) {
						return SucomUtil::sanitize_classname( 'wpssoum' . $filespec, $allow_underscore = false );
					} else {
						return $classname;
					}
				}
			}

			return $ret;
		}
	}
}
