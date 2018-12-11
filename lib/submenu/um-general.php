<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
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
				'form_button_rows' => 2,
			), -100000 );
		}

		public function filter_form_button_rows( $form_button_rows, $menu_id ) {

			switch ( $menu_id ) {
				
				case 'um-general':
				case 'tools':

					$form_button_rows[ 0 ][ 'check_for_updates' ] = _x( 'Check for Updates', 'submit button', 'wpsso-um' );

					break;
			}

			return $form_button_rows;
		}

		/**
		 * Called by the extended WpssoAdmin class.
		 */
		protected function add_meta_boxes() {

			$metabox_id      = 'general';
			$metabox_title   = _x( 'Update Manager', 'metabox title', 'wpsso-um' );
			$metabox_screen  = $this->pagehook;
			$metabox_context = 'normal';
			$metabox_prio    = 'default';
			$callback_args   = array(	// Second argument passed to the callback function / method.
			);

			add_meta_box( $this->pagehook . '_' . $metabox_id, $metabox_title,
				array( $this, 'show_metabox_general' ), $metabox_screen,
					$metabox_context, $metabox_prio, $callback_args );
		}

		public function show_metabox_general() {

			$metabox_id = 'um';

			$this->form->set_text_domain( 'wpsso' );	// translate option values using wpsso text_domain

			$this->p->util->do_metabox_table( apply_filters( $this->p->lca.'_'.$metabox_id.'_general_rows', 
				$this->get_table_rows( $metabox_id, 'general' ), $this->form ), 'metabox-'.$metabox_id.'-general' );
		}

		protected function get_table_rows( $metabox_id, $tab_key ) {

			$table_rows = array();

			switch ( $metabox_id.'-'.$tab_key ) {

				case 'um-general':

					$table_rows['update_check_hours'] = $this->form->get_th_html( _x( 'Refresh Update Information',
						'option label', 'wpsso-um' ), '', 'update_check_hours' ).
					'<td>'.$this->form->get_select( 'update_check_hours',
						$this->p->cf['um']['check_hours'], 'update_filter', '', true ).'</td>';

					$table_rows['subsection_version_filters'] = '<td colspan="2" class="subsection"><h4>'.
						_x( 'Update Version Filters', 'metabox title', 'wpsso-um' ).'</h4></td>';

					$version_filter = $this->p->cf['um']['version_filter'];

					foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

						if ( ! SucomUpdate::is_installed( $ext ) ) {
							continue;
						}

						/**
						 * Remove the short name if possible (all upper case acronym, with an optional space).
						 */
						$ext_name = preg_replace( '/ \([A-Z ]+\)$/', '', $info[ 'name' ] );

						$table_rows[] = $this->form->get_th_html( $ext_name, '', 'update_version_filter' ).
						'<td>'.$this->form->get_select( 'update_filter_for_'.$ext, $version_filter, 'update_filter', '', true ).'</td>';
					}

					break;
			}

			return $table_rows;
		}
	}
}
