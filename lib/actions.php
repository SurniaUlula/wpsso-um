<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2021 Jean-Sebastien Morisset (https://wpsso.com/)
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

			add_action( 'activated_plugin', array( $this, 'activated_plugin' ), 10, 2 );
			add_action( 'upgrader_process_complete', array( $this, 'upgrader_process_complete' ), 10, 2 );

			if ( is_admin() ) {

				$this->p->util->add_plugin_actions( $this, array( 
					'load_setting_page_check_for_updates' => 4,
					'load_setting_page_create_offers'     => 4,
				) );
			}
		}

		/**
		 * This action is run by WordPress after any plugin is activated.
		 *
		 * If a plugin is silently activated (such as during an update), this action does not run. 
		 */
		public function activated_plugin( $plugin_base, $network_activation ) {

			if ( 0 === strpos( $plugin_base, 'wpsso' ) ) {	// Matches WPSSO plugin or add-on.

				$this->a->update->refresh_upd_config();
			}
		}

		/**
		 * This action is run by WordPress when the upgrader process is complete.
		 */
		public function upgrader_process_complete( $wp_upgrader_obj, $hook_extra ) {

			if ( ! empty( $hook_extra[ 'action' ] ) && ! empty( $hook_extra[ 'type' ] ) && ! empty( $hook_extra[ 'plugins' ] ) &&
				'update' === $hook_extra[ 'action' ] && 'plugin' === $hook_extra[ 'type' ] && is_array( $hook_extra[ 'plugins' ] ) ) {

				foreach ( $hook_extra[ 'plugins' ] as $plugin_base ) {

					if ( 0 === strpos( $plugin_base, 'wpsso' ) ) {	// Matches WPSSO plugin or add-on.

						$this->a->update->refresh_upd_config();

						break;	// Stop here.
					}
				}
			}
		}

		public function action_load_setting_page_check_for_updates( $pagehook, $menu_id, $menu_name, $menu_lib ) {

			foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

				$this->p->admin->get_readme_info( $ext, $use_cache = false );
			}

			$this->a->update->manual_update_check();

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
