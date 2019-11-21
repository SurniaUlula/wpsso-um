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
 * Description: Update manager for the WPSSO Core Premium plugin and its complementary Premium add-ons.
 * Requires At Least: 3.9
 * Tested Up To: 5.3
 * Version: 2.6.0-dev.2
 * 
 * Version Numbering: {major}.{minor}.{bugfix}[-{stage}.{level}]
 *
 *      {major}         Major structural code changes / re-writes or incompatible API changes.
 *      {minor}         New functionality was added or improved in a backwards-compatible manner.
 *      {bugfix}        Backwards-compatible bug fixes or small improvements.
 *      {stage}.{level} Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).
 * 
 * Copyright 2015-2019 Jean-Sebastien Morisset (https://wpsso.com/)
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
		private $check_hours  = 24;
		private $have_req_min = true;	// Have minimum wpsso version.

		private static $instance;

		public function __construct() {

			require_once dirname( __FILE__ ) . '/lib/config.php';

			WpssoUmConfig::set_constants( __FILE__ );

			WpssoUmConfig::require_libs( __FILE__ );	// Includes the register.php class library.

			$this->reg = new WpssoUmRegister();		// Activate, deactivate, uninstall hooks.

			if ( is_admin() ) {
				add_action( 'admin_init', array( __CLASS__, 'required_check' ) );
			}

			/**
			 * Add WPSSO filter hooks.
			 */
			add_filter( 'wpsso_get_config', array( $this, 'wpsso_get_config' ), 10, 2 );	// Checks core version and merges config array.

			/**
			 * Add WPSSO action hooks.
			 */
			add_action( 'wpsso_init_textdomain', array( __CLASS__, 'wpsso_init_textdomain' ) );
			add_action( 'wpsso_init_options', array( $this, 'wpsso_init_options' ), 10 );	// Sets the $this->p reference variable.
			add_action( 'wpsso_init_objects', array( $this, 'wpsso_init_objects' ), 10 );
			add_action( 'wpsso_init_plugin', array( $this, 'wpsso_init_plugin' ), -100 );
		}

		public static function &get_instance() {

			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public static function required_check() {

			if ( ! class_exists( 'Wpsso' ) ) {
				add_action( 'all_admin_notices', array( __CLASS__, 'required_notice' ) );
			}
		}

		public static function required_notice() {

			self::wpsso_init_textdomain();

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

			$error_msg = __( 'The %1$s add-on requires the %2$s plugin &mdash; install and activate the %3$s plugin or <a href="%4$s">deactivate the %5$s add-on</a>.', 'wpsso-um' );

			$deactivate_url = html_entity_decode( wp_nonce_url( add_query_arg( array(
				'action'        => 'deactivate',
				'plugin'        => $info[ 'base' ],
				'plugin_status' => 'all',
				'paged'         => 1,
				's'             => '',
			), admin_url( 'plugins.php' ) ), 'deactivate-plugin_' . $info[ 'base' ] ) );

			echo '<div class="notice notice-error error"><p>';
			echo sprintf( $error_msg, $info[ 'name' ], $info[ 'req' ][ 'name' ], $info[ 'req' ][ 'short' ], $deactivate_url, $info[ 'short' ] );
			echo '</p></div>';
		}

		public static function wpsso_init_textdomain() {
			load_plugin_textdomain( 'wpsso-um', false, 'wpsso-um/languages/' );
		}

		/**
		 * Checks the core plugin version and merges the extension / add-on config array.
		 */
		public function wpsso_get_config( $cf, $plugin_version = 0 ) {

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

			if ( version_compare( $plugin_version, $info[ 'req' ][ 'min_version' ], '<' ) ) {

				$this->have_req_min = false;

				return $cf;
			}

			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoUmConfig::$cf );
		}

		/**
		 * Sets the $this->p reference variable for the core plugin instance.
		 */
		public function wpsso_init_options() {

			$this->p =& Wpsso::get_instance();

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_req_min ) {

				$this->p->avail[ 'p_ext' ][ 'um' ] = false;	// Signal that this extension / add-on is not available.

				return;
			}

			$this->p->avail[ 'p_ext' ][ 'um' ] = true;		// Signal that this extension / add-on is available.
		}

		public function wpsso_init_objects() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_req_min ) {
				return;	// Stop here.
			}

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

			$this->check_hours = $this->get_update_check_hours();

			$this->actions = new WpssoUmActions( $this->p );
			$this->filters = new WpssoUmFilters( $this->p );
			$this->update  = new SucomUpdate( $this->p, $this->check_hours, $info[ 'text_domain' ] );
		}

		public function wpsso_init_plugin() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_req_min ) {

				$this->min_version_notice();

				return;	// Stop here.
			}

			$cache_md5_pre  = $this->p->lca . '_';
			$cache_salt     = __METHOD__ . '_cron_check';
			$cache_exp_secs = DAY_IN_SECONDS;
			$cache_id       = $cache_md5_pre . md5( $cache_salt );

			/**
			 * Check if the WordPress cron is operating correctly. Run once per day - if the transient does not exist,
			 * or has expired, then get_transient() will return false.
			 */
			if ( ! get_transient( $cache_id ) ) {

				$check_required = false;

				foreach ( $this->p->cf[ 'plugin' ] as $ext => $info ) {

					/**
					 * The plugin must be installed to check for updates.
					 */
					if ( ! SucomUpdate::is_installed( $ext ) ) {
						continue;
					}

					$last_check_time = SucomUpdate::get_umsg( $ext, 'time' ); 	// Get the last update check timestamp.

					if ( empty( $last_check_time ) ) {

						$check_required = true;

						break;	// Stop here.

					} else {

						$current_time    = time();
						$next_sched_time = $last_check_time + ( $this->check_hours * HOUR_IN_SECONDS ); // Estimate the next scheduled check.
						$next_plus_day   = $next_sched_time + DAY_IN_SECONDS;
						$last_plus_week  = $last_check_time + WEEK_IN_SECONDS;

						/**
						 * Force an update check if more than 1 day overdue or more than 1 week ago.
						 */
						if ( $next_plus_day < $current_time || $last_plus_week < $current_time ) {
	
							$check_required = true;
	
							break;
						}
					}
				}

				if ( $check_required ) {
					$this->update->quiet_update_check();
				}

				set_transient( $cache_id, time(), $cache_exp_secs );
			}
		}

		private function min_version_notice() {

			if ( ! is_admin() ) {
				return;
			}

			$info = WpssoUmConfig::$cf[ 'plugin' ][ 'wpssoum' ];

			$error_msg = sprintf( __( 'The %1$s version %2$s add-on requires %3$s version %4$s or newer (version %5$s is currently installed).',
				'wpsso-um' ), $info[ 'name' ], $info[ 'version' ], $info[ 'req' ][ 'short' ], $info[ 'req' ][ 'min_version' ],
					$this->p->cf[ 'plugin' ][ 'wpsso' ][ 'version' ] );

			$this->p->notice->err( $error_msg );

			if ( method_exists( $this->p->admin, 'get_check_for_updates_link' ) ) {

				$update_msg = $this->p->admin->get_check_for_updates_link();

				if ( ! empty( $update_msg ) ) {
					$this->p->notice->inf( $update_msg );
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
