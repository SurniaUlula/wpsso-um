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

		private $p;	// Wpsso class object.
		private $a;	// WpssoUm class object.

		public function __construct( &$plugin, &$addon ) {

			$this->p =& $plugin;
			$this->a =& $addon;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			add_action( 'activated_plugin', array( $this, 'activated_plugin' ), 10, 2 );

			$this->p->util->add_plugin_actions( $this, array( 
				'version_updates' => 1,
			) );

			if ( is_admin() ) {

				$this->p->util->add_plugin_actions( $this, array( 
					'load_setting_page_check_for_updates' => 4,
					'load_setting_page_create_offers'     => 4,
				) );
			}
		}

		/**
		 * This action is run by WordPress immediately after any plugin is activated.
		 *
		 * If a plugin is silently activated (such as during an update), this action does not run. 
		 */
		public function activated_plugin( $plugin, $network_activation ) {

			if ( 0 === strpos( $plugin, $this->p->lca ) ) {	// Check for WPSSO plugin or add-on.

				$this->a->update->refresh_upd_config();
			}
		}

		/**
		 * Example $ext_updates = array( 'wpssoum' ).
		 */
		public function action_version_updates( array $ext_updates ) {

			$this->a->update->refresh_upd_config();
		}

		public function action_load_setting_page_check_for_updates( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$this->p->admin->get_readme_info( $ext, $use_cache = false );
			}

			$this->a->update->refresh_upd_config();

			$this->p->notice->upd( __( 'Plugin update information has been refreshed.', 'wpsso-um' ) );
		}

		public function action_load_setting_page_create_offers( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$this->a->update->create_offer( $ext );
			}

			$this->a->update->refresh_upd_config();

			$this->p->notice->upd( __( 'Plugin update offers have been reenabled.', 'wpsso-um' ) );
		}
	}
}
