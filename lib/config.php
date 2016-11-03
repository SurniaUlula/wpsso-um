<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2016 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmConfig' ) ) {

	class WpssoUmConfig {

		public static $cf = array(
			'plugin' => array(
				'wpssoum' => array(
					'version' => '1.5.11-1',		// plugin version
					'opt_version' => '3',		// increment when changing default options
					'short' => 'WPSSO UM',
					'name' => 'WPSSO Pro Update Manager (WPSSO UM)',
					'desc' => 'WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its Pro extensions.',
					'slug' => 'wpsso-um',
					'base' => 'wpsso-um/wpsso-um.php',
					'update_auth' => '',
					'text_domain' => 'wpsso-um',
					'domain_path' => '/languages',
					'img' => array(
						'icon_small' => 'images/icon-128x128.png',
						'icon_medium' => 'images/icon-256x256.png',
					),
					'url' => array(
						// github
						'readme_txt' => 'https://raw.githubusercontent.com/SurniaUlula/wpsso-um/master/readme.txt',
						// wpsso
						'download' => 'https://wpsso.com/extend/plugins/wpsso-um/',
						'latest' => 'https://wpsso.com/extend/plugins/wpsso-um/latest/',
						'update' => 'https://wpsso.com/extend/plugins/wpsso-um/update/',
						'changelog' => 'https://wpsso.com/extend/plugins/wpsso-um/changelog/',
						'codex' => 'https://wpsso.com/codex/plugins/wpsso-um/',
						'faq' => '',
						'notes' => '',
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
					),
				),
			),
			'update' => array(
				'check_hours' => array(
					24 => 'Every day',
					48 => 'Every two days',
					72 => 'Every three days',
					96 => 'Every four days',
					120 => 'Every five days',
					144 => 'Every six days',
					168 => 'Every week',
					336 => 'Every two weeks',
					504 => 'Every three weeks',
					720 => 'Every month',
				),
				'version_filter' => array(
					'dev' => 'Development and Up',
					'alpha' => 'Alpha and Up',
					'beta' => 'Beta and Up',
					'rc' => 'Release Candidate and Up',
					'stable' => 'Stable / Production',
				),
				'version_regex' => array(
					'dev' => '/[\.\-](dev|a|alpha|b|beta|rc)?[0-9]+$/',
					'alpha' => '/[\.\-](a|alpha|b|beta|rc)?[0-9]+$/',
					'beta' => '/[\.\-](b|beta|rc)?[0-9]+$/',
					'rc' => '/[\.\-](rc)?[0-9]+$/',
					'stable' => '/[\.\-][0-9]+$/',
					'stable_one' => '/-1$/',
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
			require_once( WPSSOUM_PLUGINDIR.'lib/com/update.php' );
			require_once( WPSSOUM_PLUGINDIR.'lib/register.php' );
			require_once( WPSSOUM_PLUGINDIR.'lib/filters.php' );

			add_filter( 'wpssoum_load_lib', array( 'WpssoUmConfig', 'load_lib' ), 10, 3 );
		}

		public static function load_lib( $ret = false, $filespec = '', $classname = '' ) {
			if ( $ret === false && ! empty( $filespec ) ) {
				$filepath = WPSSOUM_PLUGINDIR.'lib/'.$filespec.'.php';
				if ( file_exists( $filepath ) ) {
					require_once( $filepath );
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
