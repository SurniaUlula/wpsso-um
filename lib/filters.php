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
				'option_type'          => 2,
				'save_options'         => 4,
				'save_setting_options' => 3,
				'get_defaults'         => 1,	// Option defaults.
				'get_site_defaults'    => 1,	// Site option defaults.
			) );

			if ( is_admin() ) {

				$this->p->util->add_plugin_filters( $this, array( 
					'readme_upgrade_notices'  => 2, 
					'newer_version_available' => 5, 
				) );

				$this->p->util->add_plugin_filters( $this, array( 
					'status_std_features' => 3,
				), $prio = 10, $ext = 'wpssoum' );	// Hooks the 'wpssoum' filters.
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

				case ( strpos( $base_key, 'update_filter_for_' ) === 0 ? true : false ):

					return 'not_blank';
			}

			return $type;
		}

		/**
		 * Deprecated on 2020/06/20.
		 */
		public function filter_save_options( array $opts, $options_name, $network, $upgrading ) {

			return $this->filter_save_setting_options( $opts, $network, $upgrading );
		}

		/**
		 * $network is true if saving multisite network settings.
		 */
		public function filter_save_setting_options( array $opts, $network, $upgrading ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $network ) {

				return $opts;	// Nothing to do.
			}

			/**
			 * Check settings for authentication ID or update version filter changes.
			 */
			$check_ext_for_updates = array();

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
						if ( ! isset( $this->p->options[ $opt_key ] ) || $this->p->options[ $opt_key ] !== $opts[ $opt_key ] ) {

							/**
							 * Update the current value (so we can refresh the config) and signal that
							 * an update check is required.
							 */
							$this->p->options[ $opt_key ] = $opts[ $opt_key ];

							$check_ext_for_updates[] = $ext;
						}
					}
				}
			}

			/**
			 * Check for updates if we have one or more authentication or version filter changes.
			 */
			if ( ! empty( $check_ext_for_updates ) ) {

				$wpssoum =& WpssoUm::get_instance();

				$wpssoum->update->refresh_upd_config();

				/**
				 * Note that SucomUpdate->check_ext_for_updates() does not throttle like
				 * SucomUpdate->check_all_for_updates() does.
				 */
				$wpssoum->update->check_ext_for_updates( $check_ext_for_updates, $quiet = true );
			}

			return $opts;
		}

		public function filter_get_defaults( $def_opts ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$wpssoum =& WpssoUm::get_instance();

			$def_filter_name = $wpssoum->update->get_default_filter_name();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = $def_filter_name;
			}

			return $def_opts;
		}

		public function filter_get_site_defaults( $def_opts ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$wpssoum =& WpssoUm::get_instance();

			$def_filter_name = $wpssoum->update->get_default_filter_name();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = $def_filter_name;

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

			if ( 'stable' !== $filter_name && version_compare( $installed_version, $latest_version, '<' ) ) {

				return true;
			}

			return $newer_avail;
		}

		/**
		 * Filter for 'wpssoum_status_std_features'.
		 */
		public function filter_status_std_features( $features, $ext, $info ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$features[ _x( '(api) Update Check Schedule', 'lib file description', 'wpsso-um' ) ] = array( 
				'status' => SucomUpdate::is_enabled() ? 'on' : 'off'
			);

			return $features;
		}
	}
}
