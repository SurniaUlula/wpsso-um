<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoUmConfig' ) ) {

	class WpssoUmConfig {

		public static $cf = array(
			'plugin' => array(
				'wpssoum' => array(
					'version' => '1.6.6-dev.2',		// plugin version
					'opt_version' => '3',		// increment when changing default options
					'short' => 'WPSSO UM',		// short plugin name
					'name' => 'WPSSO Update Manager',
					'desc' => 'WPSSO extension to provide updates for the WPSSO Pro plugin and its Pro extensions.',
					'slug' => 'wpsso-um',
					'base' => 'wpsso-um/wpsso-um.php',
					'update_auth' => '',
					'text_domain' => 'wpsso-um',
					'domain_path' => '/languages',
					'req' => array(
						'short' => 'WPSSO',
						'name' => 'WPSSO',
						'min_version' => '3.11.0',
						'rec_version' => '3.45.10-dev.2',
					),
					'img' => array(
						'icons' => array(
							'low' => 'images/icon-128x128.png',
							'high' => 'images/icon-256x256.png',
						),
					),
					'lib' => array(
						// submenu items must have unique keys
						'submenu' => array (
							'um-general' => 'Update Manager',
						),
						'sitesubmenu' => array (
							'site-um-general' => 'Update Manager',
						),
						'gpl' => array(
						),
						'pro' => array(
						),
					),
				),
			),
		);

		public static function get_version() { 
			return self::$cf['plugin']['wpssoum']['version'];
		}

		public static function set_constants( $plugin_filepath ) { 
			define( 'WPSSOUM_FILEPATH', $plugin_filepath );						
			define( 'WPSSOUM_PLUGINDIR', trailingslashit( realpath( dirname( $plugin_filepath ) ) ) );
			define( 'WPSSOUM_PLUGINSLUG', self::$cf['plugin']['wpssoum']['slug'] );		// wpsso-um
			define( 'WPSSOUM_PLUGINBASE', self::$cf['plugin']['wpssoum']['base'] );		// wpsso-um/wpsso-um.php
			define( 'WPSSOUM_URLPATH', trailingslashit( plugins_url( '', $plugin_filepath ) ) );
		}

		public static function require_libs( $plugin_filepath ) {

			require_once WPSSOUM_PLUGINDIR.'lib/com/update.php';

			require_once WPSSOUM_PLUGINDIR.'lib/filters.php';
			require_once WPSSOUM_PLUGINDIR.'lib/register.php';

			add_filter( 'wpssoum_load_lib', array( 'WpssoUmConfig', 'load_lib' ), 10, 3 );
		}

		public static function load_lib( $ret = false, $filespec = '', $classname = '' ) {
			if ( $ret === false && ! empty( $filespec ) ) {
				$filepath = WPSSOUM_PLUGINDIR.'lib/'.$filespec.'.php';
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
					if ( empty( $classname ) )
						return SucomUtil::sanitize_classname( 'wpssoum'.$filespec, false );	// $underscore = false
					else return $classname;
				}
			}
			return $ret;
		}
	}
}

?>
