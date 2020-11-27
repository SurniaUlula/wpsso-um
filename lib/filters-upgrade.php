<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2014-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoUmFiltersUpgrade' ) ) {

	class WpssoUmFiltersUpgrade {

		private $p;	// Wpsso class object.

		/**
		 * Instantiated by WpssoUmFilters->__construct().
		 */
		public function __construct( &$plugin ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array(
				'rename_options_keys'    => 1,
			) );
		}

		public function filter_rename_options_keys( $options_keys ) {

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			$options_keys[ 'wpssoum' ] = array(
				6 => array(
					'update_check_hours' => '',
				),
			);

			return $options_keys;
		}
	}
}
