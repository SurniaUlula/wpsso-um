<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmFilters' ) ) {

	class WpssoUmFilters {

		protected $p;

		public static $cf = array(
			'opt' => array(				// options
				'defaults' => array(
					'update_check_hours' => 24,
				),
				'site_defaults' => array(
					'update_check_hours' => 24,
					'update_check_hours:use' => 'default',
				),
			),
		);

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			$this->p->util->add_plugin_filters( $this, array( 
				'get_defaults' => 1,			// option defaults
				'get_site_defaults' => 1,		// site option defaults
			) );

			if ( is_admin() ) {
				$this->p->util->add_plugin_filters( $this, array( 
					'readme_upgrade_notices' => 2, 
					'newer_version_available' => 5, 
					'option_type' => 2,		// define the value type for each option
				) );
				$this->p->util->add_plugin_filters( $this, array( 
					'status_gpl_features' => 3,
				), 10, 'wpssoum' );
			}
		}

		public function filter_get_defaults( $def_opts ) {
			$def_opts = array_merge( $def_opts, self::$cf['opt']['defaults'] );
			foreach ( $this->p->cf['plugin'] as $ext => $info ) {
				$def_opts['update_filter_for_'.$ext] = 'stable';
			}
			return $def_opts;
		}

		public function filter_get_site_defaults( $def_opts ) {
			$def_opts = array_merge( $def_opts, self::$cf['opt']['site_defaults'] );
			foreach ( $this->p->cf['plugin'] as $ext => $info ) {
				$def_opts['update_filter_for_'.$ext] = 'stable';
				$def_opts['update_filter_for_'.$ext.':use'] = 'default';
			}
			return $def_opts;
		}

		public function filter_readme_upgrade_notices( $upgrade_notices, $ext ) {
			$wpssoum =& WpssoUm::get_instance();
			$filter_regex = $wpssoum->update->get_filter_regex( $ext );
			foreach ( $upgrade_notices as $version => $info ) {
				if ( ! preg_match( $filter_regex, $version ) ) {
					unset ( $upgrade_notices[$version] );
				}
			}
			return $upgrade_notices;
		}

		public function filter_newer_version_available( $is_older, $ext, $installed_version, $stable_version, $latest_version ) {
			if ( ! $is_older ) {
				$wpssoum =& WpssoUm::get_instance();
				$filter_name = $wpssoum->update->get_filter_name( $ext );
				if ( $filter_name !== 'stable' && 
					version_compare( $installed_version, $latest_version, '<' ) ) {
					return true;
				}
			}
			return $is_older;
		}

		public function filter_option_type( $type, $key ) {
			if ( ! empty( $type ) ) {
				return $type;
			} elseif ( strpos( $key, 'update_' ) !== 0 ) {
				return $type;
			}
			switch ( $key ) {
				case 'update_check_hours':
					return 'pos_int';
					break;
				case ( strpos( $key, 'update_filter_for_' ) === 0 ? true : false ):
					return 'not_blank';
					break;
			}
			return $type;
		}

		public function filter_status_gpl_features( $features, $lca, $info ) {
			$features['(api) Update Check Schedule'] = array( 
				'status' => SucomUpdate::is_enabled() ? 'on' : 'off'
			);
			return $features;
		}
	}
}

?>
