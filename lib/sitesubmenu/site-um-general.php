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
			$this->menu_id = $id;
			$this->menu_name = $name;
			$this->menu_lib = $lib;
			$this->menu_ext = $ext;

			if ( $this->p->debug->enabled )
				$this->p->debug->mark();
		}

		protected function set_form_property( $menu_ext ) {
			$def_site_opts = $this->p->opt->get_site_defaults();
			$this->form = new SucomForm( $this->p, WPSSO_SITE_OPTIONS_NAME, $this->p->site_options, $def_site_opts, $menu_ext );
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
			$short = $this->p->cf['plugin'][$lca]['short'];

			// add_meta_box( $id, $title, $callback, $post_type, $context, $priority, $callback_args );
			add_meta_box( $this->pagehook.'_general', 
				_x( 'Network '.$short.' Pro Update Manager', 'metabox title', 'wpsso-um' ),
				array( &$this, 'show_metabox_general' ), $this->pagehook, 'normal' );

			// add a class to set a minimum width for the network postboxes
			add_filter( 'postbox_classes_'.$this->pagehook.'_'.$this->pagehook.'_general', 
				array( &$this, 'add_class_postbox_network' ) );
		}

		public function add_class_postbox_network( $classes ) {
			$classes[] = 'postbox-network';
			return $classes;
		}

		public function show_metabox_general() {
			$metabox = 'um';
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
						$this->p->cf['update']['check_hours'], 'update_filter', '', true ).'</td>'.
					$this->p->admin->get_site_use( $this->form, true, 'update_check_hours', true );	// $network = true

					$row_number = 1;
					$version_filter = $this->p->cf['update']['version_filter'];

					foreach ( $this->p->cf['plugin'] as $ext => $info ) {

						if ( ! SucomUpdate::is_configured( $ext ) )
							continue;

						if ( $row_number === 1 )
							$th_cell = $this->form->get_th_html( _x( 'Pro Update Version Filter',
								'option label', 'wpsso-um' ), '', 'update_version_filter' );
						else $th_cell = '<th></th>';

						$table_rows[] = $th_cell.'<td colspan="2" align="right">'.$info['name'].'</td>'.
						'<td>'.$this->form->get_select( 'update_filter_for_'.$ext, $version_filter, 'update_filter', '', true ).'</td>';

						$table_rows[] = '<th></th><td></td>'.
						$this->p->admin->get_site_use( $this->form, true, 'update_filter_for_'.$ext, true );	// $network = true

						$row_number++;
					}

					break;
			}
			return $table_rows;
		}
	}
}

?>
