<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'SucomPluginData' ) ) {

	class SucomPluginData {
	
		public $id = 0;
		public $name;
		public $slug;
		public $plugin;
		public $version;
		public $tested;
		public $requires;
		public $homepage;
		public $download_url;
		public $author;
		public $author_homepage;
		public $upgrade_notice;
		public $banners;
		public $icons;
		public $rating;
		public $num_ratings;
		public $last_updated;
		public $sections;
	
		public static function data_from_json( $json_encoded ) {

			$json_data = json_decode( $json_encoded, $assoc = false );

			if ( empty( $json_data ) || ! is_object( $json_data ) )  {
				return null;
			}

			if ( empty( $json_data->plugin ) || empty( $json_data->version ) ) {
				return null;
			}

			$plugin_data = new SucomPluginData();

			foreach( get_object_vars( $json_data ) as $key => $value ) {
				$plugin_data->$key = $value;
			}

			return $plugin_data;
		}
	
		public function json_to_wp(){

			$plugin_data = new StdClass;

			foreach ( array(
				'name', 
				'slug', 
				'plugin', 
				'version', 
				'tested', 
				'requires', 
				'homepage', 
				'download_url',
				'author_homepage',
				'upgrade_notice',
				'banners',
				'icons',
				'rating', 
				'num_ratings', 
				'last_updated',
				'sections',
			) as $prop_name ) {
				if ( isset( $this->$prop_name ) ) {
					if ( $prop_name === 'download_url' ) {
						$plugin_data->download_link = $this->download_url;
					} elseif ( $prop_name === 'author_homepage' ) {
						if ( strpos( $this->author, '<a href' ) === false ) {
							$plugin_data->author = sprintf( '<a href="%s">%s</a>', $this->author_homepage, $this->author );
						} else {
							$plugin_data->author = $this->author;
						}
					} elseif ( $prop_name === 'sections' && empty( $this->$prop_name ) ) {
						$plugin_data->$prop_name = array( 'description' => '' );
					} elseif ( is_object( $this->$prop_name ) ) {
						$plugin_data->$prop_name = get_object_vars( $this->$prop_name );
					} else {
						$plugin_data->$prop_name = $this->$prop_name;
					}
				} elseif ( $prop_name === 'author_homepage' ) {
					$plugin_data->author = $this->author;
				}
			}

			return $plugin_data;
		}
	}
}
