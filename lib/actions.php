<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2019 Jean-Sebastien Morisset (https://wpsso.com/)
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
					'load_setting_page_check_for_updates'    => 4,
				) );
			}
		}

		public function action_load_setting_page_check_for_updates( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {
				$this->p->admin->get_readme_info( $ext, $use_cache = false );
			}

			$wpssoum =& WpssoUm::get_instance();

			$wpssoum->update->manual_update_check();

			$this->p->notice->upd( __( 'Plugin and add-on information has been refreshed.', 'wpsso' ) );
		}
	}
}
