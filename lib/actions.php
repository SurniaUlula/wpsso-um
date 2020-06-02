<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmActions' ) ) {

	class WpssoUmActions {

		private $p;

		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( is_admin() ) {

				$this->p->util->add_plugin_actions( $this, array( 
					'load_setting_page_check_for_updates' => 4,
					'load_setting_page_create_offers'     => 4,
				) );
			}
		}

		public function action_load_setting_page_check_for_updates( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			$wpssoum =& WpssoUm::get_instance();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$this->p->admin->get_readme_info( $ext, $use_cache = false );
			}

			$wpssoum->update->manual_update_check();

			$this->p->notice->upd( __( 'Plugin update information has been refreshed.', 'wpsso-um' ) );
		}

		public function action_load_setting_page_create_offers( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			$wpssoum =& WpssoUm::get_instance();

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$wpssoum->update->create_offer( $ext );
			}

			$wpssoum->update->refresh_upd_config();

			$this->p->notice->upd( __( 'Plugin update offers have been re-enabled.', 'wpsso-um' ) );
		}
	}
}
