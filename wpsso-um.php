<?php
/**
 * Plugin Name: WPSSO Update Manager
 * Plugin Slug: wpsso-um
 * Text Domain: wpsso-um
 * Domain Path: /languages
 * Plugin URI: https://wpsso.com/extend/plugins/wpsso-um/
 * Assets URI: https://surniaulula.github.io/wpsso-um/assets/
 * Author: JS Morisset
 * Author URI: https://wpsso.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Description: Update Manager for the WPSSO Core Premium plugin and its Premium complementary add-ons.
 * Requires PHP: 5.6
 * Requires At Least: 4.4
 * Tested Up To: 5.5.1
 * Version: 3.4.0
 * 
 * Version Numbering: {major}.{minor}.{bugfix}[-{stage}.{level}]
 *
 *      {major}         Major structural code changes / re-writes or incompatible API changes.
 *      {minor}         New functionality was added or improved in a backwards-compatible manner.
 *      {bugfix}        Backwards-compatible bug fixes or small improvements.
 *      {stage}.{level} Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).
 * 
 * Copyright 2015-2020 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoAddOn' ) ) {

	require_once dirname( __FILE__ ) . '/lib/abstracts/add-on.php';	// WpssoAddOn class.
}

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm extends WpssoAddOn {

		public $actions;	// WpssoUmActions class.
		public $filters;	// WpssoUmFilters class.
		public $update;		// SucomUpdate class.

		protected $p;

		private static $instance = null;

		public function __construct() {

			parent::__construct( __FILE__, __CLASS__ );
		}

		public static function &get_instance() {

			if ( null === self::$instance ) {

				self::$instance = new self;
			}

			return self::$instance;
		}

		public function init_textdomain() {

			load_plugin_textdomain( 'wpsso-um', false, 'wpsso-um/languages/' );
		}

		public function init_objects( $is_admin, $doing_ajax, $doing_cron ) {

			$this->p =& Wpsso::get_instance();

			if ( $this->p->debug->enabled ) {

				$this->p->debug->mark();
			}

			if ( $this->get_missing_requirements() ) {	// Returns false or an array of missing requirements.

				return;	// Stop here.
			}

			$info = $this->cf[ 'plugin' ][ $this->ext ];

			$this->actions = new WpssoUmActions( $this->p );
			$this->filters = new WpssoUmFilters( $this->p );
			$this->update  = new SucomUpdate( $this->p, $info[ 'text_domain' ] );
		}
	}

        global $wpssoum;

	$wpssoum =& WpssoUm::get_instance();
}
