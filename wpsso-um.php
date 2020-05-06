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
 * Description: Update Manager for the WPSSO Core Premium Plugin and its Premium Complementary Add-ons.
 * Requires PHP: 5.6
 * Requires At Least: 4.2
 * Tested Up To: 5.4.1
 * Version: 2.10.0
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

if ( ! class_exists( 'WpssoUm' ) ) {

	class WpssoUm {

		/**
		 * Wpsso plugin class object variable.
		 */
		public $p;		// Wpsso

		/**
		 * Library class object variables.
		 */
		public $actions;	// WpssoUmActions
		public $filters;	// WpssoUmFilters
		public $reg;		// WpssoUmRegister
		public $update;		// SucomUpdate

		/**
		 * Reference Variables (config, options, modules, etc.).
		 */
		private $check_hours = 24;

		private $have_wpsso_min_version = true;	// Have WPSSO Core minimum version.

		private static $ext      = 'wpssoum';
		private static $p_ext    = 'um';
		private static $info     = array();
		private static $instance = null;

		public function __construct() {

			require_once dirname( __FILE__ ) . '/lib/config.php';

			WpssoUmConfig::set_constants( __FILE__ );

			WpssoUmConfig::require_libs( __FILE__ );	// Includes the register.php class library.

			$this->reg = new WpssoUmRegister();		// Activate, deactivate, uninstall hooks.

			/**
			 * Check for required plugins and show notices.
			 */
			add_action( 'all_admin_notices', array( __CLASS__, 'show_required_notices' ) );

			/**
			 * Add WPSSO filter hooks.
			 */
			add_filter( 'wpsso_get_config', array( $this, 'wpsso_get_config' ), 10, 2 );	// Checks core version and merges config array.
			add_filter( 'wpsso_get_avail', array( $this, 'wpsso_get_avail' ), 10, 1 );

			/**
			 * Add WPSSO action hooks.
			 */
			add_action( 'wpsso_init_textdomain', array( __CLASS__, 'wpsso_init_textdomain' ) );
			add_action( 'wpsso_init_objects', array( $this, 'wpsso_init_objects' ), 10 );
			add_action( 'wpsso_init_plugin', array( $this, 'wpsso_init_plugin' ), -100 );
		}

		public static function &get_instance() {

			if ( null === self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Check for required plugins and show notices.
		 */
		public static function show_required_notices() {

			$missing_requirements = self::get_missing_requirements();	// Returns false or an array of missing requirements.

			if ( ! $missing_requirements ) {
				return;	// Stop here.
			}

			self::wpsso_init_textdomain();	// If not already loaded, load the textdomain now.

			$info = WpssoUmConfig::$cf[ 'plugin' ][ self::$ext ];

			$notice_msg = __( 'The %1$s add-on requires the %2$s plugin &mdash; please install and activate the missing plugin.',
				'wpsso-um' );

			foreach ( $missing_requirements as $key => $req_info ) {

				echo '<div class="notice notice-error error"><p>';

				echo sprintf( $notice_msg, $info[ 'name' ], $req_info[ 'name' ] );

				echo '</p></div>';
			}
		}

		/**
		 * Returns false or an array of the missing requirements (ie. 'wpsso', 'woocommerce', etc.).
		 */
		public static function get_missing_requirements() {

			static $local_cache = null;

			if ( null !== $local_cache ) {
				return $local_cache;
			}

			$local_cache = array();

			$info = WpssoUmConfig::$cf[ 'plugin' ][ self::$ext ];

			foreach ( $info[ 'req' ] as $key => $req_info ) {

				if ( isset( $req_info[ 'class' ] ) ) {

					if ( class_exists( $req_info[ 'class' ] ) ) {
						continue;	// Requirement satisfied.
					}

				} else {
					continue;	// Nothing to check.
				}

				$local_cache[ $key ] = $req_info;
			}

			if ( empty( $local_cache ) ) {
				$local_cache = false;
			}

			return $local_cache;
		}

		/**
		 * The 'wpsso_init_textdomain' action is run after the $check, $avail, and $debug properties are defined.
		 */
		public static function wpsso_init_textdomain( $debug_enabled = false ) {

			static $loaded = null;

			if ( null !== $loaded ) {
				return;
			}

			$loaded = true;

			load_plugin_textdomain( 'wpsso-um', false, 'wpsso-um/languages/' );
		}

		/**
		 * Checks the core plugin version and merges the extension / add-on config array.
		 */
		public function wpsso_get_config( $cf, $plugin_version = 0 ) {

			$info = WpssoUmConfig::$cf[ 'plugin' ][ self::$ext ];

			$req_info = $info[ 'req' ][ 'wpsso' ];

			if ( version_compare( $plugin_version, $req_info[ 'min_version' ], '<' ) ) {

				$this->have_wpsso_min_version = false;

				return $cf;
			}

			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
		}

		/**
		 * The 'wpsso_get_avail' filter is run after the $check property is defined.
		 */
		public function wpsso_get_avail( $avail ) {

			if ( ! $this->have_wpsso_min_version ) {

				$avail[ 'p_ext' ][ self::$p_ext ] = false;	// Signal that this extension / add-on is not available.

				return $avail;
			}

			$avail[ 'p_ext' ][ self::$p_ext ] = true;		// Signal that this extension / add-on is available.

			return $avail;
		}

		public function wpsso_init_objects() {

			$this->p =& Wpsso::get_instance();

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_wpsso_min_version ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: have_wpsso_min_version is false' );
				}

				return;	// Stop here.
			}

			$info = WpssoUmConfig::$cf[ 'plugin' ][ self::$ext ];

			$this->check_hours = $this->get_update_check_hours();

			$this->actions = new WpssoUmActions( $this->p );
			$this->filters = new WpssoUmFilters( $this->p );
			$this->update  = new SucomUpdate( $this->p, $this->check_hours, $info[ 'text_domain' ] );
		}

		/**
		 * All WPSSO objects are instantiated and configured.
		 */
		public function wpsso_init_plugin() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_wpsso_min_version ) {

				$this->min_version_notice();	// Show minimum version notice.

				return;	// Stop here.
			}
		}

		private function min_version_notice() {

			$info = WpssoUmConfig::$cf[ 'plugin' ][ self::$ext ];

			$req_info = $info[ 'req' ][ 'wpsso' ];

			if ( is_admin() ) {

				$notice_msg = sprintf( __( 'The %1$s version %2$s add-on requires %3$s version %4$s or newer (version %5$s is currently installed).',
					'wpsso-um' ), $info[ 'name' ], $info[ 'version' ], $req_info[ 'name' ], $req_info[ 'min_version' ],
						$this->p->cf[ 'plugin' ][ 'wpsso' ][ 'version' ] );

				$this->p->notice->err( $notice_msg );

				if ( method_exists( $this->p->admin, 'get_check_for_updates_link' ) ) {
	
					$update_msg = $this->p->admin->get_check_for_updates_link();

					if ( ! empty( $update_msg ) ) {
						$this->p->notice->inf( $update_msg );
					}
				}

			} else {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( sprintf( '%1$s version %2$s requires %3$s version %4$s or newer',
						$info[ 'name' ], $info[ 'version' ], $req_info[ 'name' ], $req_info[ 'min_version' ] ) );
				}
			}
		}

		public function get_update_check_hours() {

			$check_hours = 24;
			$const_hours = SucomUtil::get_const( 'WPSSOUM_CHECK_HOURS', null );	// Return null if not defined.
			$opt_hours   = isset( $this->p->options[ 'update_check_hours' ] ) ? $this->p->options[ 'update_check_hours' ] : 24;

			if ( $const_hours !== null ) {
				$check_hours = $const_hours >= 12 ? WPSSOUM_CHECK_HOURS : 12;
			} elseif ( $opt_hours >= 24 ) {
				$check_hours = $opt_hours;
			}

			if ( $check_hours > 168 ) {	// Check at least once a week.
				$check_hours = 168;
			}

			return $check_hours;
		}
	}

        global $wpssoum;

	$wpssoum =& WpssoUm::get_instance();
}
