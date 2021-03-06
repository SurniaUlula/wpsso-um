<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmFilters' ) ) {

	class WpssoUmFilters {

		private $p;	// Wpsso class object.
		private $a;	// WpssoUm class object.
		private $upg;	// WpssoUmFiltersUpgrade class object.

		/**
		 * Instantiated by WpssoUm->init_objects().
		 */
		public function __construct( &$plugin, &$addon ) {

			static $do_once = null;

			if ( true === $do_once ) {

				return;	// Stop here.
			}

			$do_once = true;

			$this->p =& $plugin;
			$this->a =& $addon;

			require_once WPSSOUM_PLUGINDIR . 'lib/filters-upgrade.php';

			$this->upg = new WpssoUmFiltersUpgrade( $plugin, $addon );

			$this->p->util->add_plugin_filters( $this, array( 
				'option_type'          => 2,
				'save_options'         => 4,	// Deprecated since 2020/06/20.
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

			if ( ! empty( $type ) ) {

				return $type;

			} elseif ( strpos( $base_key, 'update_' ) !== 0 ) {

				return $type;
			}

			switch ( $base_key ) {

				case ( strpos( $base_key, 'update_filter_for_' ) === 0 ? true : false ):

					return 'not_blank';
			}

			return $type;
		}

		/**
		 * Deprecated since 2020/06/20.
		 */
		public function filter_save_options( array $opts, $options_name, $network, $upgrading ) {

			_deprecated_function( __METHOD__ . '()', '2020/06/20', __CLASS__ . '::filter_save_setting_options()' );	// Deprecation message.

			return $this->filter_save_setting_options( $opts, $network, $upgrading );
		}

		/**
		 * Check for Authentication ID and version filter changes, and if the submitted values are different, force an
		 * update check. $network is true when saving multisite settings.
		 */
		public function filter_save_setting_options( array $opts, $network, $upgrading ) {

			if ( $network ) {

				return $opts;	// Nothing to do.
			}

			$check_ext_for_updates = array();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$update_auth = isset( $info[ 'update_auth' ] ) ? $info[ 'update_auth' ] : '';

				foreach ( array(
					'plugin_' . $ext . '_' . $update_auth,	// Authentication ID.
					'update_filter_for_' . $ext,		// Version filter.
				) as $opt_key ) {

					/**
					 * The option key will exist only if the option was changed or received focus.
					 */
					if ( isset( $opts[ $opt_key ] ) ) {

						/**
						 * Check if the current option value is different than the submitted value.
						 */
						if ( ! isset( $this->p->options[ $opt_key ] ) || $this->p->options[ $opt_key ] !== $opts[ $opt_key ] ) {

							/**
							 * Update the current value (so we can refresh the config) and signal that
							 * an update check is required for that plugin / add-on.
							 */
							$this->p->options[ $opt_key ] = $opts[ $opt_key ];

							$check_ext_for_updates[] = $ext;
						}
					}
				}
			}

			/**
			 * Check for updates if we have one or more Auth ID or version filter changes.
			 */
			if ( ! empty( $check_ext_for_updates ) ) {

				$this->a->update->refresh_upd_config();

				/**
				 * SucomUpdate->check_ext_for_updates() does not throttle like SucomUpdate->check_all_for_updates().
				 */
				$this->a->update->check_ext_for_updates( $check_ext_for_updates, $quiet = true );
			}

			return $opts;
		}

		public function filter_get_defaults( $def_opts ) {

			$def_filter_name = $this->a->update->get_default_filter_name();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = $def_filter_name;
			}

			return $def_opts;
		}

		public function filter_get_site_defaults( $def_opts ) {

			$def_filter_name = $this->a->update->get_default_filter_name();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$def_opts[ 'update_filter_for_' . $ext ] = $def_filter_name;

				$def_opts[ 'update_filter_for_' . $ext . ':use' ] = 'default';
			}

			return $def_opts;
		}

		public function filter_readme_upgrade_notices( $upgrade_notices, $ext ) {

			$filter_regex = $this->a->update->get_ext_filter_regex( $ext );

			foreach ( $upgrade_notices as $version => $info ) {

				if ( preg_match( $filter_regex, $version ) === 0 ) {

					unset ( $upgrade_notices[ $version ] );
				}
			}

			return $upgrade_notices;
		}

		public function filter_newer_version_available( $newer_avail, $ext, $plugin_version, $stable_version, $latest_version ) {

			if ( $newer_avail ) {	// Already true.

				return $newer_avail;
			}

			$filter_name = $this->a->update->get_ext_filter_name( $ext );

			if ( 'stable' !== $filter_name && version_compare( $plugin_version, $latest_version, '<' ) ) {

				return true;
			}

			return $newer_avail;
		}

		/**
		 * Filter for 'wpssoum_status_std_features'.
		 */
		public function filter_status_std_features( $features, $ext, $info ) {

			$features[ '(api) Update Check Schedule' ] = array( 
				'label_transl' => _x( '(api) Update Check Schedule', 'lib file description', 'wpsso-um' ),
				'status'       => SucomUpdate::is_enabled() ? 'on' : 'off'
			);

			return $features;
		}
	}
}
