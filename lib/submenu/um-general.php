<?php
/*
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2016 Jean-Sebastien Morisset (http://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoUmSubmenuUmgeneral' ) && class_exists( 'WpssoAdmin' ) ) {

	class WpssoUmSubmenuUmgeneral extends WpssoAdmin {

		public function __construct( &$plugin, $id, $name, $lib, $ext ) {
			$this->p =& $plugin;
			$this->menu_id = $id;
			$this->menu_name = $name;
			$this->menu_lib = $lib;
			$this->menu_ext = $ext;

			if ( $this->p->debug->enabled )
				$this->p->debug->mark();
		}

		protected function add_meta_boxes() {
			// add_meta_box( $id, $title, $callback, $post_type, $context, $priority, $callback_args );
			add_meta_box( $this->pagehook.'_general', 
				_x( 'Pro Update Manager', 'metabox title', 'wpsso-um' ),
				array( &$this, 'show_metabox_general' ), $this->pagehook, 'normal' );
		}

		public function show_metabox_general() {
			$metabox = 'um';
			echo '<table class="sucom-setting">';
			foreach ( apply_filters( $this->p->cf['lca'].'_'.$metabox.'_general_rows', 
				$this->get_table_rows( $metabox, 'general' ), $this->form ) as $row )
					echo '<tr>'.$row.'</tr>';
			echo '</table>';
		}

		protected function get_table_rows( $metabox, $key ) {
			$table_rows = array();
			switch ( $metabox.'-'.$key ) {
				case 'um-general':

					$table_rows['update_check_hours'] = $this->form->get_th_html( _x( 'Pro Update Check Schedule',
						'option label', 'wpsso-um' ), '', 'update_check_hours' ).
					'<td>'.$this->form->get_select( 'update_check_hours',
						$this->p->cf['update']['check_hours'], 'update_filter', '', true ).'</td>';

					$update_filter_select = '';
					foreach ( $this->p->cf['plugin'] as $ext => $info )
						$update_filter_select .= '<p>'.$this->form->get_select( 'update_filter_for_'.$ext,
							$this->p->cf['update']['version_filter'], 'update_filter', '', true ).
								' for '.$info['name'].'</p>'."\n";

					$table_rows['update_version_filter'] = $this->form->get_th_html( _x( 'Pro Update Version Filter',
						'option label', 'wpsso-um' ), '', 'update_version_filter' ).
					'<td>'.$update_filter_select.'</td>';

					break;
			}
			return $table_rows;
		}
	}
}

?>
