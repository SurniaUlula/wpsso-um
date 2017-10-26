<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2017 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

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

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array( 
				'get_defaults' => 1,			// option defaults
				'get_site_defaults' => 1,		// site option defaults
			) );

			if ( is_admin() ) {

				add_action( 'update_option_home', array( &$this, 'wp_home_option_updated' ), 100, 2 );

				$this->p->util->add_plugin_actions( $this, array( 
					'column_metabox_version_info_table_rows' => 2,
					'load_setting_page_check_for_updates' => 4,
				) );

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

		// executed by the wordpress 'update_option_home' action.
		public function wp_home_option_updated( $old_value, $new_value ) {
			$wpssoum =& WpssoUm::get_instance();
			$wpssoum->update->check_all_for_updates( true, false );		// $quiet = true, $use_cache = false
		}

		public function action_column_metabox_version_info_table_rows( $table_cols, $form ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$lca = $this->p->cf['lca'];
			$admin_url = $this->p->util->get_admin_url( '?'.$lca.'-action=check_for_updates' );
			$admin_url = wp_nonce_url( $admin_url, WpssoAdmin::get_nonce_action(), WPSSO_NONCE_NAME );
			$label_transl = _x( 'Check for Updates', 'submit button', 'wpsso-um' );

			echo '<tr><td colspan="'.$table_cols.'">';
			echo $form->get_button( $label_transl, 'button-secondary', 'column-check-for-updates', $admin_url );
			echo '</td></tr>';
		}

		public function action_load_setting_page_check_for_updates( $pagehook, $menu_id, $menu_name, $menu_lib ) {
			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}
			$lca = $this->p->cf['lca'];
			foreach ( $this->p->cf['plugin'] as $ext => $info ) {
				$this->p->admin->get_readme_info( $ext, false );	// $use_cache = false
			}
			$wpssoum =& WpssoUm::get_instance();
			$wpssoum->update->check_all_for_updates( false, false );	// $quiet = false, $use_cache = false
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

		public function filter_newer_version_available( $newer_avail, $ext, $installed_version, $stable_version, $latest_version ) {
			if ( $newer_avail ) {
				return $newer_avail;
			}
			$wpssoum =& WpssoUm::get_instance();
			$filter_name = $wpssoum->update->get_filter_name( $ext );
			if ( $filter_name !== 'stable' && version_compare( $installed_version, $latest_version, '<' ) ) {
				return true;
			}
			return $newer_avail;
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
