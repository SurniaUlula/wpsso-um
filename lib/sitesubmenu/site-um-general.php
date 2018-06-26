<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoUmSitesubmenuSiteumgeneral' ) && class_exists( 'WpssoAdmin' ) ) {

	class WpssoUmSitesubmenuSiteumgeneral extends WpssoAdmin {

		public function __construct( &$plugin, $id, $name, $lib, $ext ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->menu_id   = $id;
			$this->menu_name = $name;
			$this->menu_lib  = $lib;
			$this->menu_ext  = $ext;
		}

		protected function set_form_object( $menu_ext ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'setting site form object for '.$menu_ext );
			}

			$def_site_opts = $this->p->opt->get_site_defaults();

			$this->form = new SucomForm( $this->p, WPSSO_SITE_OPTIONS_NAME, $this->p->site_options, $def_site_opts, $menu_ext );
		}

		protected function add_plugin_hooks() {
			$this->p->util->add_plugin_filters( $this, array(
				'action_buttons' => 1,
			) );
		}

		/**
		 * Called by the extended WpssoAdmin class.
		 */
		protected function add_meta_boxes() {

			add_meta_box( $this->pagehook.'_general', 
				_x( 'Network Update Manager', 'metabox title', 'wpsso-um' ),
					array( $this, 'show_metabox_general' ), $this->pagehook, 'normal' );

			/**
			 * Add a class to set a minimum width for the network postboxes.
			 */
			if ( class_exists( __CLASS__, 'add_class_postbox_network' ) ) {	// Since wpsso v4.6.0
				add_filter( 'postbox_classes_'.$this->pagehook.'_'.$this->pagehook.'_general', 
					array( $this, 'add_class_postbox_network' ) );
			}
		}

		public function filter_action_buttons( $action_buttons ) {

			$action_buttons[0]['check_for_updates'] = _x( 'Check for Updates', 'submit button', 'wpsso-um' );

			return $action_buttons;
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
						$this->p->cf['um']['check_hours'], 'update_filter', '', true ).'</td>'.
					WpssoAdmin::get_option_site_use( 'update_check_hours', $this->form, true, true );

					$table_rows['subsection_version_filters'] = '<td></td><td class="subsection" colspan="3"><h4>'.
						_x( 'Update Version Filters', 'metabox title', 'wpsso-um' ).'</h4></td>';

					$version_filter = $this->p->cf['um']['version_filter'];

					foreach ( $this->p->cf['plugin'] as $ext => $info ) {

						if ( ! SucomUpdate::is_installed( $ext ) ) {
							continue;
						}

						/**
						 * Remove the short name if possible (all upper case acronym, with an optional space).
						 */
						$ext_name = preg_replace( '/ \([A-Z ]+\)$/', '', $info['name'] );

						$table_rows[] = $this->form->get_th_html( $ext_name, '', 'update_version_filter' ).
						'<td>'.$this->form->get_select( 'update_filter_for_'.$ext,
							$version_filter, 'update_filter', '', true ).'</td>'.
						WpssoAdmin::get_option_site_use( 'update_filter_for_'.$ext, $this->form, true, true );
					}

					break;
			}

			return $table_rows;
		}
	}
}
