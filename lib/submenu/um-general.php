<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmSubmenuUmGeneral' ) && class_exists( 'WpssoAdmin' ) ) {

	class WpssoUmSubmenuUmGeneral extends WpssoAdmin {

		public function __construct( &$plugin, $id, $name, $lib, $ext ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->menu_id   = $id;
			$this->menu_name = $name;
			$this->menu_lib  = $lib;
			$this->menu_ext  = $ext;

			$this->p->util->add_plugin_filters( $this, array(
				'form_button_rows' => 2,	// Filter form buttons for all settings pages.
			) );
		}

		public function filter_form_button_rows( $form_button_rows, $menu_id ) {

			switch ( $menu_id ) {

				case 'um-general':

					/**
					 * Remove the Change to "All Options" View button.
					 */
					if ( isset( $form_button_rows[ 0 ] ) ) {

						$form_button_rows[ 0 ] = SucomUtil::preg_grep_keys( '/^change_show_options/', $form_button_rows[ 0 ], $invert = true );
					}

					$form_button_rows[ 0 ][ 'check_for_updates' ] = _x( 'Check for Plugin Updates', 'submit button', 'wpsso-um' );

					break;

				case 'tools':

					$form_button_rows[ 0 ][ 'check_for_updates' ] = _x( 'Check for Plugin Updates', 'submit button', 'wpsso-um' );
					$form_button_rows[ 0 ][ 'create_offers' ]     = _x( 'Re-Offer Plugin Updates', 'submit button', 'wpsso-um' );

					break;
			}

			return $form_button_rows;
		}

		/**
		 * Called by the extended WpssoAdmin class.
		 */
		protected function add_meta_boxes() {

			$metabox_id      = 'general';
			$metabox_title   = _x( 'Update Manager Settings', 'metabox title', 'wpsso-um' );
			$metabox_screen  = $this->pagehook;
			$metabox_context = 'normal';
			$metabox_prio    = 'default';
			$callback_args   = array(	// Second argument passed to the callback function / method.
			);

			add_meta_box( $this->pagehook . '_' . $metabox_id, $metabox_title,
				array( $this, 'show_metabox_' . $metabox_id ), $metabox_screen,
					$metabox_context, $metabox_prio, $callback_args );
		}

		public function show_metabox_general() {

			$metabox_id = 'um-general';

			$filter_name = SucomUtil::sanitize_hookname( $this->p->lca . '_' . $metabox_id . '_tabs' );

			$tabs = apply_filters( $filter_name, array(
				'filters' => _x( 'Version Filters', 'metabox tab', 'wpsso-um' ),
			) );

			$this->form->set_text_domain( 'wpsso' );	// Translate option values using wpsso text_domain.

			$table_rows = array();

			foreach ( $tabs as $tab_key => $title ) {

				$filter_name = SucomUtil::sanitize_hookname( $this->p->lca . '_' . $metabox_id . '_' . $tab_key . '_rows' );

				$table_rows[ $tab_key ] = array_merge(
					$this->get_table_rows( $metabox_id, $tab_key ), 
					(array) apply_filters( $filter_name, array(), $this->form )
				);
			}

			$this->p->util->metabox->do_tabbed( $metabox_id, $tabs, $table_rows );
		}

		protected function get_table_rows( $metabox_id, $tab_key ) {

			$table_rows = array();

			switch ( $metabox_id . '-' . $tab_key ) {

				case 'um-general-filters':

					$version_filters = $this->p->cf[ 'um' ][ 'version_filter' ];
					$ext_sorted      = WpssoConfig::get_ext_sorted();	// Since WPSSO Core v3.38.3.

					foreach ( $ext_sorted as $ext => $info ) {

						if ( ! SucomUpdate::is_installed( $ext ) ) {

							continue;
						}

						$opt_key = 'update_filter_for_' . $ext;

						$table_rows[ $opt_key ] = '' .
						$this->form->get_th_html( $info[ 'name' ], $css_class = '', $css_id = 'update_version_filter' ) . 
						'<td>' . $this->form->get_select( $opt_key, $version_filters, $css_class = 'update_filter' ) . '</td>';
					}

					break;
			}

			return $table_rows;
		}
	}
}
