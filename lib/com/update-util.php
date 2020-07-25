<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'SucomUpdateUtil' ) ) {

	class SucomUpdateUtil {

		private static $cache_plugins = null;	// Common cache for get_plugins().

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
		public static function get_plugins() {

			if ( method_exists( 'SucomPlugin', 'get_plugins' ) ) {	// Since WPSSO Core v4.21.0.

				return SucomPlugin::get_plugins();
			}

			if ( null !== self::$cache_plugins ) {

				return self::$cache_plugins;
			}

			self::$cache_plugins = array();	// Default value.

			if ( ! function_exists( 'get_plugins' ) ) {	// Load the library if necessary.

				$plugin_lib = trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';

				if ( file_exists( $plugin_lib ) ) {	// Just in case.

					require_once $plugin_lib;
				}
			}

			if ( function_exists( 'get_plugins' ) ) {

				self::$cache_plugins = get_plugins();
			}

			return self::$cache_plugins;
		}
	}
}
