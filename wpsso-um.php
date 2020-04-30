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
 * Version: 2.10.0-dev.6
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
		private $check_hours      = 24;
		private $have_min_version = true;	// Have minimum wpsso version.

		private static $instance;

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

			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Check for required plugins and show notices.
		 */
		public static function show_required_notices() {

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

			foreach ( $info[ 'req' ] as $ext => $req_info ) {

				if ( isset( $req_info[ 'class' ] ) ) {	// Just in case.
					if ( class_exists( $req_info[ 'class' ] ) ) {
						continue;	// Requirement satisfied.
					}
				} else continue;	// Nothing to check.

				$deactivate_url = html_entity_decode( wp_nonce_url( add_query_arg( array(
					'action'        => 'deactivate',
					'plugin'        => $info[ 'base' ],
					'plugin_status' => 'all',
					'paged'         => 1,
					's'             => '',
				), admin_url( 'plugins.php' ) ), 'deactivate-plugin_' . $info[ 'base' ] ) );

				self::wpsso_init_textdomain();	// If not already loaded, load the textdomain now.

				$notice_msg = __( 'The %1$s add-on requires the %2$s plugin &mdash; install and activate the plugin or <a href="%3$s">deactivate this add-on</a>.', 'wpsso-um' );

				echo '<div class="notice notice-error error"><p>';
				echo sprintf( $notice_msg, $info[ 'name' ], $req_info[ 'name' ], $deactivate_url );
				echo '</p></div>';
			}
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

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

			$req_info = $info[ 'req' ][ 'wpsso' ];

			if ( version_compare( $plugin_version, $req_info[ 'min_version' ], '<' ) ) {

				$this->have_min_version = false;

				return $cf;
			}

			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
		}

		/**
		 * The 'wpsso_get_avail' filter is run after the $check property is defined.
		 */
		public function wpsso_get_avail( $avail ) {

			if ( ! $this->have_min_version ) {

				$avail[ 'p_ext' ][ 'um' ] = false;	// Signal that this extension / add-on is not available.

				return $avail;
			}

			$avail[ 'p_ext' ][ 'um' ] = true;		// Signal that this extension / add-on is available.

			return $avail;
		}

		public function wpsso_init_objects() {

			$this->p =& Wpsso::get_instance();

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_min_version ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: have_min_version is false' );
				}

				return;	// Stop here.
			}

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

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

			if ( ! $this->have_min_version ) {

				$this->min_version_notice();	// Show minimum version notice.

				return;	// Stop here.
			}
		}

		private function min_version_notice() {

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

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
