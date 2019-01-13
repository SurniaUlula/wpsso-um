<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'SucomUpdateUtil' ) ) {

	class SucomUpdateUtil {

		protected static $cache_wp_plugins = null;

		/**
		 * Decode a URL and add query arguments. Returns false on error.
		 */
		public static function decode_url_add_query( $url, array $args ) {

			if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {	// Check for invalid URL.
				return false;
			}

			$parsed_url = parse_url( SucomUtil::decode_html( urldecode( $url ) ) );

			if ( empty( $parsed_url ) ) {
				return false;
			}

			if ( empty( $parsed_url[ 'query' ] ) ) {
				$parsed_url[ 'query' ] = http_build_query( $args );
			} else {
				$parsed_url[ 'query' ] .= '&' . http_build_query( $args );
			}

			$url = self::unparse_url( $parsed_url );

			return $url;
		}

		public static function unparse_url( $parsed_url ) {

			$scheme   = isset( $parsed_url[ 'scheme' ] )   ? $parsed_url[ 'scheme' ] . '://' : '';
			$user     = isset( $parsed_url[ 'user' ] )     ? $parsed_url[ 'user' ] : '';
			$pass     = isset( $parsed_url[ 'pass' ] )     ? ':' . $parsed_url[ 'pass' ]  : '';
			$host     = isset( $parsed_url[ 'host' ] )     ? $parsed_url[ 'host' ] : '';
			$port     = isset( $parsed_url[ 'port' ] )     ? ':' . $parsed_url[ 'port' ] : '';
			$path     = isset( $parsed_url[ 'path' ] )     ? $parsed_url[ 'path' ] : '';
			$query    = isset( $parsed_url[ 'query' ] )    ? '?' . $parsed_url[ 'query' ] : '';
			$fragment = isset( $parsed_url[ 'fragment' ] ) ? '#' . $parsed_url[ 'fragment' ] : '';

			return $scheme . $user . $pass . ( $user || $pass ? '@' : '' ) . $host . $port . $path . $query . $fragment;
		}

		/**
		 * The WordPress get_plugins() function is very slow, so call it only once and cache its result.
		 */
		public static function get_wp_plugins() {

			if ( self::$cache_wp_plugins !== null ) {
				return self::$cache_wp_plugins;
			}

			if ( ! function_exists( 'get_plugins' ) ) {	// Load the library if necessary.

				$plugin_lib = trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';

				if ( file_exists( $plugin_lib ) ) {	// Just in case.
					require_once $plugin_lib;
				}
			}

			if ( function_exists( 'get_plugins' ) ) {
				self::$cache_wp_plugins = get_plugins();
			} else {
				self::$cache_wp_plugins = array();
			}

			return self::$cache_wp_plugins;
		}

		public static function raw_do_option( $action, $opt_name, $val = null ) {

			global $wp_filter, $wp_actions;

			$saved_wp_filter  = $wp_filter;
			$saved_wp_actions = $wp_actions;

			foreach ( array(
				'sanitize_option_' . $opt_name,
				'default_option_' . $opt_name,
				'pre_option_' . $opt_name,
				'option_' . $opt_name,	
				'pre_update_option_' . $opt_name,
				'pre_update_option',
			) as $tag ) {
				unset( $wp_filter[ $tag ] );
			}

			$ret = null;

			switch( $action ) {

				case 'get':
				case 'get_option':

					$ret = get_option( $opt_name, $default = $val );

					break;

				case 'update':
				case 'update_option':

					foreach ( array(
						'update_option',
						'update_option_' . $opt_name,
						'updated_option',
					) as $tag ) {
						unset( $wp_actions[ $tag ] );
					}

					$ret = update_option( $opt_name, $val );

					break;

				case 'delete':
				case 'delete_option':

					foreach ( array(
						'delete_option',
						'delete_option_' . $opt_name,
						'deleted_option',
					) as $tag ) {
						unset( $wp_actions[ $tag ] );
					}

					$ret = delete_option( $opt_name );

					break;
			}

			$wp_filter  = $saved_wp_filter;
			$wp_actions = $saved_wp_actions;

			unset( $saved_wp_filter, $saved_wp_actions );

			return $ret;
		}
	}
}
