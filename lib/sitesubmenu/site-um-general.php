<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
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

			$this->p->util->add_plugin_filters( $this, array(
				'form_button_rows' => 2,	// Filter form buttons for all settings pages.
			) );
		}

		public function filter_form_button_rows( $form_button_rows, $menu_id ) {

			switch ( $menu_id ) {

				case 'site-um-general':

					/**
					 * Remove the Change to "All Options" View button.
					 */
					if ( isset( $form_button_rows[ 0 ] ) ) {
						$form_button_rows[ 0 ] = SucomUtil::preg_grep_keys( '/^change_show_options/', $form_button_rows[ 0 ], $invert = true );
					}

					$form_button_rows[ 0 ][ 'check_for_updates' ] = _x( 'Check for Plugin Updates', 'submit button', 'wpsso-um' );

					break;

				case 'site-tools':

					$form_button_rows[ 0 ][ 'check_for_updates' ] = _x( 'Check for Plugin Updates', 'submit button', 'wpsso-um' );

					$form_button_rows[ 0 ][ 'create_offers' ] = _x( 'Re-Offer Plugin Updates', 'submit button', 'wpsso-um' );

					break;
			}

			return $form_button_rows;
		}

		protected function set_form_object( $menu_ext ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'setting site form object for ' . $menu_ext );
			}

			$def_site_opts = $this->p->opt->get_site_defaults();

			$this->form = new SucomForm( $this->p, WPSSO_SITE_OPTIONS_NAME, $this->p->site_options, $def_site_opts, $menu_ext );
		}

		/**
		 * Called by the extended WpssoAdmin class.
		 */
		protected function add_meta_boxes() {

			$metabox_id      = 'general';
			$metabox_title   = _x( 'Network Update Manager', 'metabox title', 'wpsso-um' );
			$metabox_screen  = $this->pagehook;
			$metabox_context = 'normal';
			$metabox_prio    = 'default';
			$callback_args   = array(	// Second argument passed to the callback function / method.
			);

			add_meta_box( $this->pagehook . '_' . $metabox_id, $metabox_title,
				array( $this, 'show_metabox_' . $metabox_id ), $metabox_screen,
					$metabox_context, $metabox_prio, $callback_args );

			/**
			 * Add a class to set a minimum width for the network postboxes.
			 */
			add_filter( 'postbox_classes_' . $this->pagehook . '_' . $this->pagehook . '_general', 
				array( $this, 'add_class_postbox_network' ) );
		}

		public function show_metabox_general() {

			$metabox_id = 'um-general';

			$filter_name = SucomUtil::sanitize_hookname( $this->p->lca . '_' . $metabox_id . '_tabs' );

			$tabs = apply_filters( $filter_name, array(
				'schedule' => _x( 'Cron Schedule', 'metabox tab', 'wpsso-um' ),
				'filters'  => _x( 'Version Filters', 'metabox tab', 'wpsso-um' ),
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

				case 'um-general-schedule':

					$table_rows[ 'update_check_hours' ] = '' . 
					$this->form->get_th_html( _x( 'Refresh Update Information', 'option label', 'wpsso-um' ), '', 'update_check_hours' ) . 
					'<td>' . $this->form->get_select( 'update_check_hours', $this->p->cf[ 'um' ][ 'check_hours' ], 'update_filter', '', true ) . '</td>' . 
					WpssoAdmin::get_option_site_use( 'update_check_hours', $this->form, $network = true, $enabled = true );

					break;

				case 'um-general-filters':

					$version_filter = $this->p->cf[ 'um' ][ 'version_filter' ];

					$ext_sorted = WpssoConfig::get_ext_sorted();	// Since WPSSO Core v3.38.3.

					foreach ( $ext_sorted as $ext => $info ) {

						if ( ! SucomUpdate::is_installed( $ext ) ) {
							continue;
						}

						$table_rows[ 'update_filter_for_' . $ext ] = '' .
						$this->form->get_th_html( $info[ 'name' ], '', 'update_version_filter' ) . 
						'<td>' . $this->form->get_select( 'update_filter_for_' . $ext, $version_filter, 'update_filter', '', true ) . '</td>' . 
						WpssoAdmin::get_option_site_use( 'update_filter_for_' . $ext, $this->form, $network = true, $enabled = true );
					}

					break;
			}

			return $table_rows;
		}
	}
}
