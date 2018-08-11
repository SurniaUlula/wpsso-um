<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoUmConfig' ) ) {

	class WpssoUmConfig {

		public static $cf = array(
			'plugin' => array(
				'wpssoum' => array(			// Plugin acronym.
					'version'     => '1.11.0',	// Plugin version.
					'opt_version' => '3',		// Increment when changing default option values.
					'short'       => 'WPSSO UM',	// Short plugin name.
					'name'        => 'WPSSO Update Manager',
					'desc'        => 'WPSSO Core add-on to provide updates for the WPSSO Core Pro plugin and its Pro add-ons.',
					'slug'        => 'wpsso-um',
					'base'        => 'wpsso-um/wpsso-um.php',
					'update_auth' => '',
					'text_domain' => 'wpsso-um',
					'domain_path' => '/languages',
					'req' => array(
						'short'       => 'WPSSO Core',
						'name'        => 'WPSSO Core',
						'min_version' => '3.50.0',	// 2018/01/27
						'rec_version' => '4.10.0',
					),
					'img' => array(
						'icons' => array(
							'low'  => 'images/icon-128x128.png',
							'high' => 'images/icon-256x256.png',
						),
					),
					'lib' => array(
						'submenu' => array(	// Note that submenu elements must have unique keys.
							'um-general' => 'Update Manager',
						),
						'sitesubmenu' => array(
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

		public static function get_version( $add_slug = false ) {
			$ext = 'wpssoum';
			$info =& self::$cf['plugin'][$ext];
			return $add_slug ? $info['slug'].'-'.$info['version'] : $info['version'];
		}

		public static function set_constants( $plugin_filepath ) { 

			if ( defined( 'WPSSOUM_VERSION' ) ) {	// Define constants only once.
				return;
			}

			define( 'WPSSOUM_VERSION', self::$cf['plugin']['wpssoum']['version'] );						
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
			if ( false === $ret && ! empty( $filespec ) ) {
				$filepath = WPSSOUM_PLUGINDIR.'lib/'.$filespec.'.php';
				if ( file_exists( $filepath ) ) {
					require_once $filepath;
					if ( empty( $classname ) ) {
						return SucomUtil::sanitize_classname( 'wpssoum'.$filespec, false );	// $underscore = false
					} else {
						return $classname;
					}
				}
			}
			return $ret;
		}
	}
}
