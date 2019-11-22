<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2019 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmFilters' ) ) {

	class WpssoUmFilters {

		private $p;

		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array( 
				'save_options'      => 4,
				'get_defaults'      => 1,	// Option defaults.
				'get_site_defaults' => 1,	// Site option defaults.
			) );

			if ( is_admin() ) {

				$this->p->util->add_plugin_filters( $this, array( 
					'readme_upgrade_notices'  => 2, 
					'newer_version_available' => 5, 
					'option_type'             => 2,	// Define the value type for each option.
				) );

				$this->p->util->add_plugin_filters( $this, array( 
					'status_std_features' => 3,
				), $prio = 10, $ext = 'wpssoum' );
			}
		}

		public function filter_save_options( $opts, $options_name, $network, $doing_upgrade ) {

			$have_auth_changes = false;

			/**
			 * Check all add-ons for any Authentication ID or Update Version Filter changes.
			 */
			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				foreach ( array(
					'plugin_' . $ext . '_' . $info[ 'update_auth' ],	// Authentication ID.
					'update_filter_for_' . $ext,				// Update version filter.
				) as $opt_name ) {

					if ( isset( $opts[ $opt_name ] ) ) {

						if ( ! isset( $this->p->options[ $opt_name ] ) || $opts[ $opt_name ] !== $this->p->options[ $opt_name ] ) {

							/**
							 * Update the current options array for SucomUpdate->get_ext_auth_id() and
							 * SucomUpdate->get_ext_filter_name().
							 */
							$this->p->options[ $opt_name ] = $opts[ $opt_name ];

							$have_auth_changes = true;
						}
					}
				}
			}

			if ( $have_auth_changes ) {

				$wpssoum =& WpssoUm::get_instance();

				$wpssoum->update->check_all_for_updates( $quiet = true, $throttle = false );

			} elseif ( $doing_upgrade ) {

				$wpssoum =& WpssoUm::get_instance();

				$wpssoum->update->refresh_upd_config();
			}

			return $opts;
		}

		public function filter_get_defaults( $def_opts ) {

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = 'stable';
			}

			return $def_opts;
		}

		public function filter_get_site_defaults( $def_opts ) {

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = 'stable';

				$def_opts[ 'update_filter_for_' . $ext . ':use' ] = 'default';
			}

			return $def_opts;
		}

		public function filter_readme_upgrade_notices( $upgrade_notices, $ext ) {

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

		public function filter_option_type( $type, $base_key ) {

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

		public function filter_status_std_features( $features, $ext, $info ) {

			$features[ '(api) Update Check Schedule' ] = array( 
				'status' => SucomUpdate::is_enabled() ? 'on' : 'off'
			);

			return $features;
		}
	}
}
