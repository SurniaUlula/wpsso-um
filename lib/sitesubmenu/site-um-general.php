<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmSitesubmenuSiteumgeneral' ) && class_exists( 'WpssoAdmin' ) ) {

	class WpssoUmSitesubmenuSiteumgeneral extends WpssoAdmin {

		public function __construct( &$plugin, $id, $name, $lib, $ext ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->menu_id = $id;
			$this->menu_name = $name;
			$this->menu_lib = $lib;
			$this->menu_ext = $ext;	// lowercase acronyn for plugin or extension
		}

		protected function set_form_object( $menu_ext ) {
			$def_site_opts = $this->p->opt->get_site_defaults();
			$this->form = new SucomForm( $this->p, WPSSO_SITE_OPTIONS_NAME,
				$this->p->site_options, $def_site_opts, $menu_ext );
		}

		protected function add_plugin_hooks() {
			$this->p->util->add_plugin_filters( $this, array(
				'action_buttons' => 1,
			) );
		}

		protected function add_meta_boxes() {
			$lca = $this->p->cf['lca'];
			$short_pro = $this->p->cf['plugin'][$lca]['short'].' '.
				_x( 'Pro', 'package type', 'wpsso-um' );

			// add_meta_box( $id, $title, $callback, $post_type, $context, $priority, $callback_args );
			add_meta_box( $this->pagehook.'_general', 
				sprintf( _x( 'Network Update Manager for %s', 'metabox title', 'wpsso-um' ), $short_pro ),
					array( &$this, 'show_metabox_general' ), $this->pagehook, 'normal' );

			// add a class to set a minimum width for the network postboxes
			add_filter( 'postbox_classes_'.$this->pagehook.'_'.$this->pagehook.'_general', 
				array( &$this, 'add_class_postbox_network' ) );
		}

		public function filter_action_buttons( $action_buttons ) {
			$action_buttons[0]['check_for_updates'] = _x( 'Check for Updates',
				'submit button', 'wpsso-um' );
			return $action_buttons;
		}

		public function add_class_postbox_network( $classes ) {
			$classes[] = 'postbox-network';
			return $classes;
		}

		public function show_metabox_general() {
			$metabox = 'um';
			$this->form->set_text_domain( 'wpsso' );	// translate option values using wpsso text_domain
			$this->p->util->do_table_rows( apply_filters( $this->p->cf['lca'].'_'.$metabox.'_general_rows', 
				$this->get_table_rows( $metabox, 'general' ), $this->form ), 'metabox-'.$metabox.'-general' );
		}

		protected function get_table_rows( $metabox, $key ) {
			$table_rows = array();
			switch ( $metabox.'-'.$key ) {
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
						$ext_name = preg_replace( '/\([A-Z ]+\)$/', '', $info['name'] );	// remove the short name
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

?>
