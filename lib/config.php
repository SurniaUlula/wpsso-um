<?php
/*
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.txt
Copyright 2015 - Jean-Sebastien Morisset - http://surniaulula.com/
*/

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmConfig' ) ) {

	class WpssoUmConfig {

		public static $cf = array(
			'plugin' => array(
				'wpssoum' => array(
					'version' => '1.0',	// plugin version
					'short' => 'WPSSO UM',
					'name' => 'WPSSO Pro Update Manager (WPSSO UM)',
					'desc' => 'WPSSO extension to provide updates for the WordPress Social Sharing Optimization (WPSSO) Pro plugin and its extensions.',
					'slug' => 'wpsso-um',
					'base' => 'wpsso-ssb/wpsso-um.php',
					'img' => array(
						'icon-small' => 'images/icon-128x128.png',
						'icon-medium' => 'images/icon-256x256.png',
					),
					'url' => array(
						'download' => 'http://surniaulula.com/extend/plugins/wpsso-um/',
						'review' => '',
						'readme' => 'https://raw.githubusercontent.com/SurniaUlula/wpsso-um/master/readme.txt',
						'wp_support' => '',
						'update' => 'http://surniaulula.com/extend/plugins/wpsso-um/update/',
						'purchase' => '',
						'changelog' => 'http://surniaulula.com/extend/plugins/wpsso-um/changelog/',
						'codex' => '',
						'faq' => '',
						'notes' => '',
						'feed' => '',
						'pro_support' => '',
						'pro_ticket' => '',
					),
				),
			),
		);

		public static function set_constants( $plugin_filepath ) { 
			$lca = 'wpssoum';
			$slug = self::$cf['plugin'][$lca]['slug'];

			define( 'WPSSOUM_FILEPATH', $plugin_filepath );						
			define( 'WPSSOUM_PLUGINDIR', trailingslashit( plugin_dir_path( $plugin_filepath ) ) );
			define( 'WPSSOUM_PLUGINBASE', plugin_basename( $plugin_filepath ) );
			define( 'WPSSOUM_TEXTDOM', $slug );
			define( 'WPSSOUM_URLPATH', trailingslashit( plugins_url( '', $plugin_filepath ) ) );
		}

		public static function require_libs( $plugin_filepath ) {
			require_once( WPSSOUM_PLUGINDIR.'lib/com/update.php' );
		}
	}
}

?>
