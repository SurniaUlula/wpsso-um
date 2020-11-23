<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'SucomUpdateUtil' ) ) {

	class SucomUpdateUtil {

		private static $get_plugins_cache = null;	// Common cache for get_plugins().

		public function __construct() {}

		/**
		 * Returns an imploded string of active modules.
		 */
		public static function encode_avail( array $avail ) {

			$avail = self::clean_avail( $avail );

			$avail_enc = array();

			foreach ( $avail as $sub => $libs ) {

				foreach ( $libs as $lib => $active ) {

					if ( $active ) {

						$avail_enc[] = $sub . ':' . $lib;
					}
				}
			}

			$avail_enc = implode( $glue = ',', $avail_enc );	// Convert to comma delimited string.

			return $avail_enc;
		}

		public static function clean_avail( array $avail ) {

			foreach ( $avail as $sub => $libs ) {

				if ( empty( $libs ) || ! is_array( $libs ) ) {	// Just in case.

					unset( $avail[ $sub ] );

					continue;
				}
					
				switch ( $sub ) {

					case '*':	// Skip deprecated plugin features.
					case 'admin':	// Skip available admin settings.
					case 'p':	// Skip available plugin features.
					case 'wp':	// Skip available WP features.

						unset( $avail[ $sub ] );

						continue 2;
				}

				foreach ( $libs as $lib => $active ) {

					switch ( $lib ) {

						case 'any':	// Skip generic module.

							unset( $avail[ $sub ][ $lib ] );

							continue 2;
					}
				}
			}

			return $avail;
		}

		/**
		 * Decode a URL and add query arguments. Returns false on error.
		 */
		public static function decode_url_add_query( $url, array $args ) {

			if ( method_exists( 'SucomUtil', 'decode_url_add_query' ) ) {

				return SucomUtil::decode_url_add_query( $url, $args );
			}

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

			if ( method_exists( 'SucomUtil', 'unparse_url' ) ) {

				return SucomUtil::unparse_url( $parsed_url );
			}

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

			if ( null !== SucomUpdateUtil::$get_plugins_cache ) {	// Use SucomUpdateUtil (not self) for single variable reference.

				return SucomUpdateUtil::$get_plugins_cache;	// Use SucomUpdateUtil (not self) for single variable reference.
			}

			SucomUpdateUtil::$get_plugins_cache = array();	// Use SucomUpdateUtil (not self) for single variable reference.

			if ( ! function_exists( 'get_plugins' ) ) {	// Load the library if necessary.

				$plugin_lib = trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';

				if ( file_exists( $plugin_lib ) ) {	// Just in case.

					require_once $plugin_lib;
				}
			}

			if ( function_exists( 'get_plugins' ) ) {

				SucomUpdateUtil::$get_plugins_cache = get_plugins();	// Use SucomUpdateUtil (not self) for single variable reference.
			}

			return SucomUpdateUtil::$get_plugins_cache;	// Use SucomUpdateUtil (not self) for single variable reference.
		}

		/**
		 * Clear both caches.
		 */
		public static function clear_plugins_cache() {

			if ( method_exists( 'SucomPlugin', 'clear_plugins_cache' ) ) {	// Since WPSSO Core v4.21.0.

				SucomPlugin::clear_plugins_cache();
			}

			SucomUpdateUtil::$get_plugins_cache = null;	// Use SucomUpdateUtil (not self) for single variable reference.
		}
	}
}
