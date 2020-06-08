<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmFilters' ) ) {

	class WpssoUmFilters {

		private $p;

		public function __construct( &$plugin ) {

			/**
			 * Just in case - prevent filters from being hooked and executed more than once.
			 */
			static $do_once = null;

			if ( true === $do_once ) {
				return;	// Stop here.
			}

			$do_once = true;

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array( 
				'option_type'       => 2,
				'save_options'      => 4,
				'get_defaults'      => 1,	// Option defaults.
				'get_site_defaults' => 1,	// Site option defaults.
			) );

			if ( is_admin() ) {

				$this->p->util->add_plugin_filters( $this, array( 
					'readme_upgrade_notices'  => 2, 
					'newer_version_available' => 5, 
				) );

				$this->p->util->add_plugin_filters( $this, array( 
					'status_std_features' => 3,
				), $prio = 10, $ext = 'wpssoum' );
			}
		}

		public function filter_option_type( $type, $base_key ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! empty( $type ) ) {
				return $type;
			} elseif ( strpos( $base_key, 'update_' ) !== 0 ) {
				return $type;
			}

			switch ( $base_key ) {

				case 'update_check_hours':

					return 'pos_int';

					break;

				case ( strpos( $base_key, 'update_filter_for_' ) === 0 ? true : false ):

					return 'not_blank';

					break;
			}

			return $type;
		}

		public function filter_save_options( $opts, $options_name, $network, $doing_upgrade ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$current_opts =& $network ? $this->p->site_options : $this->p->options;

			/**
			 * Check settings for authentication ID or update version filter changes.
			 */
			$check_for_updates = false;

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				/**
				 * An 'update_auth' value is typically 'tid' or an empty string.
				 */
				$update_auth = isset( $info[ 'update_auth' ] ) ? $info[ 'update_auth' ] : '';

				foreach ( array(
					'plugin_' . $ext . '_' . $update_auth,
					'update_filter_for_' . $ext,
				) as $opt_key ) {

					/**
					 * The option name will exist in the submitted options array only if the option was changed
					 * or received focus.
					 */
					if ( isset( $opts[ $opt_key ] ) ) {

						/**
						 * Check if the current option value is different than the submitted value.
						 */
						if ( ! isset( $current_opts[ $opt_key ] ) || $current_opts[ $opt_key ] !== $opts[ $opt_key ] ) {

							/**
							 * Update the current value (so we can refresh the config) and signal that
							 * an update check is required.
							 */
							$current_opts[ $opt_key ] = $opts[ $opt_key ];

							$check_for_updates = true;
						}
					}
				}
			}

			/**
			 * Refresh the config to use new plugin version strings ($doing_upgrade is true) or to use the latest
			 * authentication and/or version filters.
			 */
			if ( $doing_upgrade || $check_for_updates ) {

				$wpssoum =& WpssoUm::get_instance();

				$wpssoum->update->refresh_upd_config();

				/**
				 * Check for updates if we have one or more authentication or version filter changes.
				 */
				if ( $check_for_updates ) {

					/**
					 * Note that SucomUpdate->check_ext_for_updates() does not throttle like
					 * SucomUpdate->check_all_for_updates() does.
					 */
					$wpssoum->update->check_ext_for_updates( $check_ext = null, $quiet = true );
				}
			}

			return $opts;
		}

		public function filter_get_defaults( $def_opts ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {
				$def_opts[ 'update_filter_for_' . $ext ] = 'stable';
			}

			return $def_opts;
		}

		public function filter_get_site_defaults( $def_opts ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = 'stable';

				$def_opts[ 'update_filter_for_' . $ext . ':use' ] = 'default';
			}

			return $def_opts;
		}

		public function filter_readme_upgrade_notices( $upgrade_notices, $ext ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$wpssoum =& WpssoUm::get_instance();

			$filter_regex = $wpssoum->update->get_ext_filter_regex( $ext );

			foreach ( $upgrade_notices as $version => $info ) {

				if ( preg_match( $filter_regex, $version ) === 0 ) {

					unset ( $upgrade_notices[ $version ] );
				}
			}

			return $upgrade_notices;
		}

		public function filter_newer_version_available( $newer_avail, $ext, $installed_version, $stable_version, $latest_version ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( $newer_avail ) {
				return $newer_avail;
			}

			$wpssoum =& WpssoUm::get_instance();

			$filter_name = $wpssoum->update->get_ext_filter_name( $ext );

			if ( $filter_name !== 'stable' && version_compare( $installed_version, $latest_version, '<' ) ) {
				return true;
			}

			return $newer_avail;
		}

		public function filter_status_std_features( $features, $ext, $info ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$features[ '(api) Update Check Schedule' ] = array( 
				'status' => SucomUpdate::is_enabled() ? 'on' : 'off'
			);

			return $features;
		}
	}
}
