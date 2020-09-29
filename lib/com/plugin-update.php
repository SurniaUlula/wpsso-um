<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

$lib_dir = dirname( __FILE__ ) . '/';

require_once $lib_dir . 'plugin-data.php';

if ( ! class_exists( 'SucomPluginUpdate' ) ) {

	class SucomPluginUpdate {

		public $id = 0;
		public $slug;
		public $plugin;
		public $version = 0;
		public $tested;
		public $homepage;		// Plugin homepage URL.
		public $download_url;		// Update download URL.
		public $upgrade_notice;
		public $banners;
		public $icons;
		public $exp_date;		// Example: 0000-00-00 00:00:00
		public $qty_total = 0;		// Example: 10	(since v1.10.0)
		public $qty_reg   = 0;		// Example: 1	(since v1.10.0)
		public $qty_used  = '0/0';	// Example: 1/10

		public static function update_from_json( $json_encoded ) {

			$plugin_data = SucomPluginData::data_from_json( $json_encoded );

			if ( $plugin_data !== null )  {

				return self::update_from_data( $plugin_data );

			} else {

				return null;
			}
		}

		public static function update_from_data( $plugin_data ){

			$plugin_update = new SucomPluginUpdate();

			foreach ( array(
				'id', 
				'slug', 
				'plugin', 
				'version', 
				'tested', 
				'homepage', 
				'download_url', 
				'upgrade_notice',
				'banners',
				'icons',
				'exp_date',
				'qty_reg', 
				'qty_total', 
				'qty_used', 
			) as $prop_name ) {

				if ( isset( $plugin_data->$prop_name ) ) {

					$plugin_update->$prop_name = $plugin_data->$prop_name;
				}
			}

			return $plugin_update;
		}

		public function json_to_wp() {

			$plugin_update = new StdClass;

			foreach ( array(
				'id'             => 'id',
				'slug'           => 'slug',
				'plugin'         => 'plugin',
				'version'        => 'new_version',
				'tested'         => 'tested',
				'homepage'       => 'url',		// Plugin homepage URL.
				'download_url'   => 'package',		// Update download URL.
				'upgrade_notice' => 'upgrade_notice',
				'banners'        => 'banners',
				'icons'          => 'icons',
				'exp_date'       => 'exp_date',
				'qty_reg'        => 'qty_reg',
				'qty_total'      => 'qty_total',
				'qty_used'       => 'qty_used',
			) as $json_prop_name => $wp_prop_name ) {

				if ( isset( $this->$json_prop_name ) ) {

					if ( is_object( $this->$json_prop_name ) ) {

						$plugin_update->$wp_prop_name = get_object_vars( $this->$json_prop_name );

					} else {

						$plugin_update->$wp_prop_name = $this->$json_prop_name;
					}
				}
			}

			return $plugin_update;
		}
	}
}
