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

			if ( $network ) {
				return $opts;	// Nothing to do.
			}

			$wpssoum =& WpssoUm::get_instance();

			/**
			 * Refresh the config with new version information and return.
			 */
			if ( $doing_upgrade ) {
			
				$wpssoum->update->refresh_upd_config();

				return $opts;
			}

			/**
			 * Check settings for authentication ID or update version filter changes.
			 */
			$check_for_updates = false;

			$current_opts =& $network ? $this->p->site_options : $this->p->options;

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				foreach ( array(
					'plugin_' . $ext . '_' . $info[ 'update_auth' ],
					'update_filter_for_' . $ext,
				) as $opt_key ) {

					if ( isset( $opts[ $opt_key ] ) ) {

						if ( ! isset( $current_opts[ $opt_key ] ) || $current_opts[ $opt_key ] !== $opts[ $opt_key ] ) {

							$current_opts[ $opt_key ] = $opts[ $opt_key ];

							$check_for_updates = true;
						}
					}
				}
			}

			/**
			 * Refresh the config.
			 */
			$wpssoum->update->refresh_upd_config();

			/**
			 * We have one or more authentication ID or version filter changes.
			 */
			if ( $check_for_updates ) {

				/**
				 * Note that check_ext_for_updates() does not throttle like check_all_for_updates() does.
				 */
				$wpssoum->update->check_ext_for_updates( $check_ext = null, $quiet = true );
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
