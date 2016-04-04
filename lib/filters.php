<?php
/*
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2016 Jean-Sebastien Morisset (http://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmFilters' ) ) {

	class WpssoUmFilters {

		protected $p;
		protected $plugin_filepath;

		public static $cf = array(
			'opt' => array(				// options
				'defaults' => array(
					'update_check_hours' => 24,
				),
			),
		);

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			$this->p->util->add_plugin_filters( $this, array( 
				'get_defaults' => 1,			// option defaults
			) );

			if ( is_admin() ) {
				$this->p->util->add_plugin_filters( $this, array( 
					'readme_upgrade_notices' => 2, 
					'newer_version_available' => 5, 
					'option_type' => 2,		// define the value type for each option
					'messages_tooltip_side' => 2,	// tooltip messages for side boxes
					'messages_tooltip' => 2,	// tooltip messages filter
				) );
				$this->p->util->add_plugin_filters( $this, array( 
					'status_gpl_features' => 3,
				), 10, 'wpssoum' );
			}
		}

		public function filter_get_defaults( $def_opts ) {
			$def_opts = array_merge( $def_opts, self::$cf['opt']['defaults'] );
			foreach ( $this->p->cf['plugin'] as $ext => $info )
				$def_opts['update_filter_for_'.$ext] = 'stable';
			return $def_opts;
		}

		public function filter_readme_upgrade_notices( $upgrade_notices, $ext ) {
			$wpssoum =& WpssoUm::get_instance();
			$filter_regex = $wpssoum->update->get_version_filter_regex( $ext );
			foreach ( $upgrade_notices as $version => $info ) {
				if ( ! preg_match( $filter_regex, $version ) )
					unset ( $upgrade_notices[$version] );
			}
			return $upgrade_notices;
		}

		public function filter_newer_version_available( $is_older, $ext, $installed_version, $stable_version, $latest_version ) {
			if ( ! $is_older ) {
				if ( isset( $this->p->options['update_filter_for_'.$ext] ) &&
					$this->p->options['update_filter_for_'.$ext] !== 'stable' &&
						version_compare( $installed_version, $latest_version, '<' ) )
							return true;
			} else return $is_older;
		}

		public function filter_option_type( $type, $key ) {
			if ( ! empty( $type ) )
				return $type;

			// remove localization for more generic match
			if ( strpos( $key, '#' ) !== false )
				$key = preg_replace( '/#.*$/', '', $key );

			switch ( $key ) {
				case 'update_check_hours':
					return 'pos_num';
					break;
				case ( strpos( $key, 'update_filter_for_' ) === 0 ? true : false ):
					return 'not_blank';
					break;
			}
			return $type;
		}

		public function filter_messages_tooltip_side( $text, $idx ) {
			switch ( $idx ) {
				case 'tooltip-side-update-check-schedule':
					$text = sprintf( __( 'When the %1$s extension is active, an update check is scheduled every %2$d hours to retrieve update information for <em>installed and licensed</em> %3$s extensions.', 'wpsso-um' ), $this->p->cf['plugin']['wpssoum']['name'], WpssoUm::get_update_check_hours(), $this->p->cf['plugin']['wpsso']['short'] );
					break;
			}
			return $text;
		}

		public function filter_messages_tooltip( $text, $idx ) {
			if ( strpos( $idx, 'tooltip-update_' ) !== 0 )
				return $text;
			switch ( $idx ) {
				case 'tooltip-update_check_hours':
					$text = sprintf( __( 'How often would you like the %1$s to check for new updates? You can also use the "%2$s" button to manually check for new updates at any time.', 'wpsso-um' ), $this->p->cf['plugin']['wpssoum']['name'], _x( 'Check for Pro Update(s)', 'submit button', 'wpsso-um' ) );
					break;
				case 'tooltip-update_version_filter':
					$text = __( 'For each licensed Pro version plugin / extension, select the type of version releases you would like to receive and install.', 'wpsso-um' );
					break;
			}
			return $text;
		}

		public function filter_status_gpl_features( $features, $lca, $info ) {
			$features['Update Check Schedule'] = array( 
				'status' => SucomUpdate::is_enabled() ? 'on' : 'off'
			);
			return $features;
		}
	}
}

?>
