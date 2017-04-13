<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmSubmenuUmGeneral' ) && class_exists( 'WpssoAdmin' ) ) {

	class WpssoUmSubmenuUmGeneral extends WpssoAdmin {

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

		protected function add_side_meta_boxes() {

			// show the help metabox on all pages
			add_meta_box( $this->pagehook.'_help',
				_x( 'Help and Support', 'metabox title (side)', 'wpsso-um' ), 
					array( &$this, 'show_metabox_help' ), $this->pagehook, 'side' );

			add_meta_box( $this->pagehook.'_version_info',
				_x( 'Version Information', 'metabox title (side)', 'wpsso-um' ), 
					array( &$this, 'show_metabox_version_info' ), $this->pagehook, 'side' );
		}

		protected function add_meta_boxes() {
			$lca = $this->p->cf['lca'];
			$short_pro = $this->p->cf['plugin'][$lca]['short'].' '._x( 'Pro', 'package type', 'wpsso-um' );

			// add_meta_box( $id, $title, $callback, $post_type, $context, $priority, $callback_args );
			add_meta_box( $this->pagehook.'_general', 
				sprintf( _x( 'Update Manager for %s', 'metabox title', 'wpsso-um' ), $short_pro ),
					array( &$this, 'show_metabox_general' ), $this->pagehook, 'normal' );
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
						$this->p->cf['um']['check_hours'], 'update_filter', '', true ).'</td>';

					$table_rows['subsection_version_filters'] = '<td></td><td class="subsection"><h4>'.
						_x( 'Update Version Filters', 'metabox title', 'wpsso-um' ).'</h4></td>';

					$version_filter = $this->p->cf['um']['version_filter'];

					foreach ( $this->p->cf['plugin'] as $ext => $info ) {
						if ( ! SucomUpdate::is_configured( $ext ) ) {
							continue;
						}
						$ext_name = preg_replace( '/\([A-Z ]+\)$/', '', $info['name'] );	// remove the short name
						$table_rows[] = $this->form->get_th_html( $ext_name, '', 'update_version_filter' ).
						'<td>'.$this->form->get_select( 'update_filter_for_'.$ext,
							$version_filter, 'update_filter', '', true ).'</td>';
					}

					break;
			}
			return $table_rows;
		}
	}
}

?>
