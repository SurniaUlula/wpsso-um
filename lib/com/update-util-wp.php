<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'SucomUpdateUtilWP' ) ) {

	class SucomUpdateUtilWP {

		/**
		 * Unfiltered version of home_url() from wordpress/wp-includes/link-template.php
		 * Last synchronized with WordPress v5.0 on 2018/12/12.
		 */
		public static function raw_home_url( $path = '', $scheme = null ) {

			return self::raw_get_home_url( null, $path, $scheme );
		}

		/**
		 * Unfiltered version of get_home_url() from wordpress/wp-includes/link-template.php
		 * Last synchronized with WordPress v5.0 on 2018/12/12.
		 */
		public static function raw_get_home_url( $blog_id = null, $path = '', $scheme = null ) {

			global $pagenow;

			if ( empty( $blog_id ) || ! is_multisite() ) {

				$url = self::raw_do_option( 'get', 'home' );
			} else {
				switch_to_blog( $blog_id );

				$url = self::raw_do_option( 'get', 'home' );

				restore_current_blog();
			}

			if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {

				if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $pagenow ) {

					$scheme = 'https';
				} else {
					$scheme = parse_url( $url, PHP_URL_SCHEME );
				}
			}

			$url = self::raw_set_url_scheme( $url, $scheme );

			if ( $path && is_string( $path ) ) {
				$url .= '/' . ltrim( $path, '/' );
			}

			return $url;
		}

		/**
		 * Unfiltered version of set_url_scheme() from wordpress/wp-includes/link-template.php
		 * Last synchronized with WordPress v5.0 on 2018/12/12.
		 */
		private static function raw_set_url_scheme( $url, $scheme = null ) {

			if ( ! $scheme ) {
				$scheme = is_ssl() ? 'https' : 'http';
			} elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
				$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
			} elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
				$scheme = is_ssl() ? 'https' : 'http';
			}

			$url = trim( $url );

			if ( substr( $url, 0, 2 ) === '//' ) {
				$url = 'http:' . $url;
			}

			if ( 'relative' === $scheme ) {

				$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );

				if ( $url !== '' && $url[0] === '/' ) {
					$url = '/' . ltrim( $url, "/ \t\n\r\0\x0B" );
				}

			} else {
				$url = preg_replace( '#^\w+://#', $scheme . '://', $url );
			}

			return $url;
		}

		/**
		 * Temporarily disable filter and action hooks before calling
		 * get_option(), update_option, and delete_option().
		 */
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
