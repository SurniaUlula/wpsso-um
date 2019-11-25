<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2019 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for.' );
}

$lib_dir = dirname( __FILE__ ) . '/';

require_once $lib_dir . 'plugin-data.php';
require_once $lib_dir . 'plugin-update.php';
require_once $lib_dir . 'update-util.php';
require_once $lib_dir . 'update-util-wp.php';

if ( ! class_exists( 'SucomUpdate' ) ) {

	class SucomUpdate {
	
		private $p           = null;
		private $plugin_lca  = '';
		private $plugin_slug = '';
		private $text_domain = '';
		private $cron_hook   = '';
		private $sched_hours = 24;
		private $sched_name  = 'every24hours';

		private static $api_version  = 2.2;
		private static $upd_config   = array();

		private static $http_error_codes = array(
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
		);
	
		public function __construct( &$plugin, $check_hours = 24, $text_domain = '' ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// Begin timer.
			}

			$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX ? true : false;

			if ( isset( $this->p->lca ) ) {
				$this->plugin_lca = $this->p->lca;
			} elseif ( isset( $this->p->cf[ 'lca' ] ) ) {
				$this->plugin_lca = $this->p->cf[ 'lca' ];
			}

			if ( ! empty( $this->plugin_lca ) && ! empty( $this->p->cf[ 'plugin' ] ) ) {

				$this->plugin_slug = $this->p->cf[ 'plugin' ][ $this->plugin_lca ][ 'slug' ];	// Example: wpsso.
				$this->text_domain = $text_domain;						// Example: wpsso-um.
				$this->cron_hook   = $this->plugin_lca . '_update_manager_check';		// Example: wpsso_update_manager_check.
				$this->sched_hours = $check_hours < 12 ? 12 : $check_hours;			// Example: 12 (12 hours minimum).
				$this->sched_name  = 'every' . $this->sched_hours . 'hours';			// Example: every24hours.

				/**
				 * Optimize performance and do not load if this is an ajax call (ie. DOING_AJAX is true).
				 */
				if ( ! $doing_ajax ) {

					/**
					 * Check for the "Check Again" feature on the WordPress Dashboard > Updates page.
					 */
					if ( strpos( $_SERVER[ 'REQUEST_URI' ], '/update-core.php?force-check=1' ) ) {
						$this->manual_update_check();
					} else {
						$this->set_upd_config();
					}

					$this->add_wp_hooks();	// Private method.
				}
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// End timer.
			}
		}

		/**
		 * Since WPSSO UM v2.5.1.
		 */
		public function refresh_upd_config() {

			return $this->set_upd_config( $quiet = false, $read_cache = false );
		}

		/**
		 * When $quiet is false, the following notices may be shown:
		 *
		 *	- Please note that one or more non-stable / development Update Version Filters have been selected.
		 */
		private function set_upd_config( $quiet = false, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$cf_plugins = $this->p->cf[ 'plugin' ];

			$cache_md5_pre  = $this->p->lca . '_!_';
			$cache_exp_secs = 3 * DAY_IN_SECONDS;
			$cache_salt     = __CLASS__ . '::upd_config';
			$cache_id       = $cache_md5_pre . md5( $cache_salt );

			if ( $read_cache ) {

				self::$upd_config = get_transient( $cache_id );

				if ( is_array( self::$upd_config ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'config retrieved from transient cache' );
					}

					return;
				}

			} else {
				delete_transient( $cache_id );
			}

			self::$upd_config = array();	// Init a new config array.

			$has_dev_filter = false;	// Assume we're using the production version filter by default.

			foreach ( $cf_plugins as $ext => $info ) {

				$ext_auth_type = $this->get_ext_auth_type( $ext );
				$ext_auth_id   = $this->get_ext_auth_id( $ext );

				/**
				 * Prefer a 'urls' array key instead of 'url'.
				 */
				if ( ! empty( $info[ 'url' ] ) ) {

					if ( empty( $info[ 'urls' ] ) ) {
						$info[ 'urls' ] = $info[ 'url' ];
					}

					unset( $info[ 'url' ] );
				}

				if ( $ext_auth_type !== 'none' && empty( $ext_auth_id ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - auth type without id' );
					}

					continue;

				} elseif ( empty( $info[ 'slug' ] ) || empty( $info[ 'base' ] ) || empty( $info[ 'urls' ][ 'update' ] ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - incomplete config' );
					}

					continue;
				}

				/**
				 * Saved as the 'installed_version' value.
				 */
				$ext_version = $this->get_ext_version( $ext );	// Uses a local cache.

				if ( false === $ext_version ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - version is false' );
					}

					continue;
				}

				/**
				 * Saved as the 'version_filter' value.
				 */
				if ( false !== strpos( $ext_version, 'not-installed' ) ) {	// Anywhere in string.
					$filter_name = 'stable';
				} else {
					$filter_name = $this->get_ext_filter_name( $ext );
				}

				if ( $filter_name !== 'stable' ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: non-stable filter found' );
					}

					$has_dev_filter = true;
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: installed version is ' . $ext_version . ' with ' . $filter_name . ' version filter' );
				}

				/**
				 * get_user_locale() is available since WP v4.7.0, so make sure it exists before calling it. :)
				 */
				$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();

				/**
			 	 * Define some standard error messages for consistency checks.
				 */
				$inconsistency_msg = sprintf( __( 'An inconsistency was found in the %1$s update server information &mdash;',
					$this->text_domain ), $info[ 'name' ] );

				$update_disabled_msg = sprintf( __( 'Update checks for %1$s are disabled while this inconsistency persists.',
					$this->text_domain ), $info[ 'short' ] );
					
				$update_disabled_msg .= empty( $info[ 'urls' ][ 'support' ] ) ? '' : ' ' .
					sprintf( __( 'You may <a href="%1$s">open a new support ticket</a> if you believe this error message is incorrect.',
						$this->text_domain ), $info[ 'urls' ][ 'support' ] );

				/**
				 * Add query arguments to the update URL.
				 */
				$json_url  = $info[ 'urls' ][ 'update' ];
				$json_args = array();

				if ( ! empty( $ext_auth_type ) && $ext_auth_type !== 'none' ) {
					$json_args[ $ext_auth_type ] = $ext_auth_id;
				}

				$json_args[ 'api_version' ]       = self::$api_version;
				$json_args[ 'installed_version' ] = $ext_version;
				$json_args[ 'version_filter' ]    = $filter_name;
				$json_args[ 'sched_hours' ]       = $this->sched_hours;
				$json_args[ 'locale' ]            = $locale;

				$json_url = SucomUpdateUtil::decode_url_add_query( $json_url, $json_args );

				if ( filter_var( $json_url, FILTER_VALIDATE_URL ) === false ) {	// Check for invalid URL.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: invalid authentication URL (' . $json_url . ')' );
					}

					$error_msg = $inconsistency_msg . ' ' . sprintf( __( 'invalid authentication URL (%1$s).',
						$this->text_domain ), $json_url ) . ' ' . $update_disabled_msg;

					self::set_umsg( $ext, 'err', $error_msg );

					continue;
				}

				self::$upd_config[ $ext ] = array(
					'name'              => $info[ 'name' ],
					'short'             => $info[ 'short' ],
					'slug'              => $info[ 'slug' ],				// Example: wpsso.
					'base'              => $info[ 'base' ],				// Example: wpsso/wpsso.php.
					'api_version'       => self::$api_version,
					'auth_type'         => $ext_auth_type,
					'auth_id'           => $ext_auth_id,
					'installed_version' => $ext_version,
					'version_filter'    => $filter_name,
					'hosts'             => empty( $info[ 'hosts' ] ) ? array() : $info[ 'hosts' ],
					'urls'              => empty( $info[ 'urls' ] ) ? array() : $info[ 'urls' ],
					'data_json_url'     => $json_url,
					'data_expire'       => 86100,					// Plugin data expiration (almost 24 hours).
					'option_name'       => 'external_update-' . $info[ 'slug' ],	// Example: external_update-wpsso.
				);

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: update info configured (auth_type is ' . $ext_auth_type . ')' );
				}
			}

			if ( $has_dev_filter ) {

				$user_id = get_current_user_id();

				if ( ! $quiet && $user_id ) {

					$notice_key   = 'non-stable-update-version-filters-selected';
					$dismiss_time = MONTH_IN_SECONDS;

					$this->p->notice->warn( sprintf( __( 'Please note that one or more non-stable / development %s have been selected.',
						$this->text_domain ), $this->p->util->get_admin_url( 'um-general', _x( 'Update Version Filters',
							'metabox title', $this->text_domain ) ) ), $user_id, $notice_key, $dismiss_time );
				}
			}

			set_transient( $cache_id, self::$upd_config, $cache_exp_secs );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'config saved to transient cache for ' . $cache_exp_secs . ' seconds' );
			}
		}

		/**
		 * Called by self::__construct() after self::set_upd_config() is run.
		 */
		private function add_wp_hooks() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( empty( self::$upd_config ) ) {	// Just in case.

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'skipping all update checks - update config array is empty' );
				}

				return;
			}

			/**
			 * Refresh the config and plugin update data if/when the WordPress home URL is changed.
			 */
			add_action( 'update_option_home', array( $this, 'quiet_update_check' ), PHP_INT_MAX );

			add_filter( 'http_request_host_is_external', array( $this, 'allow_update_package' ), PHP_INT_MAX, 3 );
			add_filter( 'http_headers_useragent', array( $this, 'maybe_update_wpua' ), PHP_INT_MAX, 1 );

			/**
			 * If the WordPress update system has been disabled and/or manipulated, then re-enable updates by including
			 * our update data (if a new plugin version is available).
			 */
			add_filter( 'pre_transient_update_plugins', array( $this, 'reenable_plugin_updates' ), PHP_INT_MAX, 1 );
			add_filter( 'pre_site_transient_update_plugins', array( $this, 'reenable_plugin_updates' ), PHP_INT_MAX, 1 );

			/**
			 * Provide plugin data from the json api for add-ons not hosted on wordpress.org.
			 */
			add_filter( 'plugins_api_result', array( $this, 'external_plugin_data' ), PHP_INT_MAX, 3 );

			add_filter( 'transient_update_plugins', array( $this, 'maybe_add_plugin_update' ), PHP_INT_MAX, 1 );
			add_filter( 'site_transient_update_plugins', array( $this, 'maybe_add_plugin_update' ), PHP_INT_MAX, 1 );

			/**
			 * Maybe remove the old plugin update hook.
			 */
			if ( wp_get_schedule( 'plugin_update-' . $this->plugin_slug ) ) {
				wp_clear_scheduled_hook( 'plugin_update-' . $this->plugin_slug );
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'adding ' . $this->cron_hook . ' schedule for ' . $this->sched_name );
			}

			add_action( $this->cron_hook, array( $this, 'quiet_update_check' ) );

			add_filter( 'cron_schedules', array( $this, 'add_custom_schedule' ) );

			$schedule = wp_get_schedule( $this->cron_hook );

			$is_scheduled = false;

			if ( ! empty( $schedule ) ) {

				if ( $schedule !== $this->sched_name ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'changing ' . $this->cron_hook . ' schedule from ' . $schedule . ' to ' . $this->sched_name );
					}

					wp_clear_scheduled_hook( $this->cron_hook );

				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $this->cron_hook . ' already registered for schedule ' . $this->sched_name );
					}

					$is_scheduled = true;
				}
			}

			if ( ! $is_scheduled && ! defined( 'WP_INSTALLING' ) && ! wp_next_scheduled( $this->cron_hook ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'registering ' . $this->cron_hook . ' for schedule ' . $this->sched_name );
				}

				wp_schedule_event( time(), $this->sched_name, $this->cron_hook );
			}
		}

		/**
		 * Since WPSSO UM v2.5.0.
		 */
		public function manual_update_check() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			return $this->check_all_for_updates( $quiet = false );
		}

		/**
		 * Since WPSSO UM v2.5.0.
		 */
		public function quiet_update_check() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			return $this->check_all_for_updates( $quiet = true );
		}

		/**
		 * Since WPSSO UM v1.7.0.
		 *
		 * When $quiet is false, the following notices may be shown:
		 *
		 *	- Update manager cache refresh denied. Please wait a few minutes before trying to force another update cache refresh.
		 */
		public function check_all_for_updates( $quiet = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			/**
			 * Throttle non-caching executions to one per minute.
			 */
			$throttle_mins = 3;

			$cache_md5_pre  = $this->p->lca . '_';
			$cache_exp_secs = $throttle_mins * 60;
			$cache_salt     = __METHOD__;
			$cache_id       = $cache_md5_pre . md5( $cache_salt );

			if ( false !== get_transient( $cache_id ) ) {

				$user_id = get_current_user_id();

				if ( ! $quiet && $user_id ) {

					$notice_key = __FUNCTION__ . '_throttling';

					$this->p->notice->warn( __( 'Update manager cache refresh denied.', $this->text_domain ) . ' ' .
						__( 'Please wait a few minutes before trying to force another update cache refresh.', $this->text_domain ),
							$user_id, $notice_key );
				}

				return;
			}

			set_transient( $cache_id, time(), $cache_exp_secs );

			$this->set_upd_config( $quiet, $read_cache = false );

			$this->check_ext_for_updates( $check_ext = null, $quiet );
		}

		/**
		 * When $quiet is false, the following notices may be shown:
		 *
		 *	- No plugins defined for updates.
		 *	- Update information for %s has been retrieved and saved.
		 *	- An error was returned while getting update information for %s.
		 *	- Failed saving retrieved update information for %s.
		 */
		public function check_ext_for_updates( $check_ext = null, $quiet = true ) {

			$ext_upd_config = array();

			if ( empty( $check_ext ) ) {

				$ext_upd_config = self::$upd_config;	// Check all plugins defined.

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'checking all known plugins for updates' );
				}

			} elseif ( is_array( $check_ext ) ) {

				foreach ( $check_ext as $ext ) {
					if ( isset( self::$upd_config[ $ext ] ) ) {
						$ext_upd_config[ $ext ] = self::$upd_config[ $ext ];
					}
				}

			} elseif ( is_string( $check_ext ) ) {

				if ( isset( self::$upd_config[ $check_ext ] ) ) {
					$ext_upd_config[ $check_ext ] = self::$upd_config[ $check_ext ];
				}
			}

			$user_id = get_current_user_id();

			if ( empty( $ext_upd_config ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: no plugins to check for updates' );
				}

				if ( ! $quiet && $user_id ) {

					$notice_key = __FUNCTION__ . '_no_plugins_defined';

					$this->p->notice->err( __( 'No plugins defined for updates.', $this->text_domain ), $user_id, $notice_key );
				}

				return;
			}

			foreach ( $ext_upd_config as $ext => $upd_info ) {

				if ( ! self::is_installed( $ext ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: not installed' );
					}

					continue;
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: checking for update' );
				}

				$update_data                 = new StdClass;
				$update_data->lastCheck      = time();
				$update_data->checkedVersion = $upd_info[ 'installed_version' ];
				$update_data->update         = $this->get_update_data( $ext, $read_cache = false );

				if ( self::update_option_data( $ext, $update_data ) ) {

					if ( empty( self::$upd_config[ $ext ][ 'uerr' ] ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: update information saved in ' . $upd_info[ 'option_name' ] );
						}

						if ( ! empty( self::$upd_config[ $ext ][ 'plugin_data' ] ) ) {

							if ( ! $quiet && $user_id ) {

								$notice_key = __FUNCTION__ . '_' . $ext . '_' . $upd_info[ 'option_name' ] . '_success';

								$this->p->notice->inf( sprintf( __( 'Update information for %s has been retrieved and saved.',
									$this->text_domain ), $upd_info[ 'name' ] ), $user_id, $notice_key );
							}
						}

					} else {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: error returned getting update information' );
						}

						if ( ! $quiet && $user_id ) {

							$notice_key = __FUNCTION__ . '_' . $ext . '_' . $upd_info[ 'option_name' ] . '_error_returned';

							$this->p->notice->warn( sprintf( __( 'An error was returned while getting update information for %s.',
								$this->text_domain ), $upd_info[ 'name' ] ), $user_id, $notice_key );
						}
					}

				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: failed saving update information in ' . $upd_info[ 'option_name' ] );
					}

					if ( ! $quiet && $user_id ) {

						$notice_key = __FUNCTION__ . '_' . $ext . '_' . $upd_info[ 'option_name' ] . '_failed_saving';

						$this->p->notice->err( sprintf( __( 'Failed saving retrieved update information for %s.',
							$this->text_domain ), $upd_info[ 'name' ] ), $user_id, $notice_key );
					}
				}
			}
		}
	
		private function check_pp_compat( $ext = '', $li = true, $rv = true, $rc = true ) {

			if ( method_exists( $this->p->check, 'pp' ) ) {
				return $this->p->check->pp( $ext, $li, $rv, $rc );
			} else {
				return $this->p->check->aop( $ext, $li, $rv, $rc );	// Deprecated on 2018/08/27.
			}
		}

		public function allow_update_package( $is_allowed, $ip, $url ) {

			if ( $is_allowed ) {	// Already allowed.
				return $is_allowed;
			}

			foreach ( self::$upd_config as $ext => $upd_info ) {
				if ( ! empty( $upd_info[ 'response' ]->package ) && $upd_info[ 'response' ]->package === $url ) {
					return true;
				}
			}

			return $is_allowed;
		}

		/**
		 * A filter for 'http_headers_useragent' makes sure we have a standard WordPress useragent string. The
		 * 'http_headers_useragent' filter hook offers two arguments, but only since WP v5.1.0, so require one argument to
		 * stay backwards compatible with older WP versions.
		 */
		public function maybe_update_wpua( $wpua ) {

			global $wp_version;

			$correct_wpua = 'WordPress/' . $wp_version . '; ' . SucomUpdateUtilWP::raw_home_url();

			if ( $correct_wpua !== $wpua ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'incorrect wordpress id: ' . $wpua );
				}

				return $correct_wpua;
			}

			return $wpua;
		}

		/**
		 * Provide plugin data from the json api for add-ons not hosted on wordpress.org.
		 */
		public function external_plugin_data( $result, $action = null, $args = null ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( $action !== 'plugin_information' ) {				// This filter only provides plugin data.
				return $result;
			} elseif ( empty( $args->slug ) ) {					// Make sure we have a slug in the request.
				return $result;
			} elseif ( ! empty( $args->unfiltered ) ) {				// Flag for the update manager filter.
				return $result;
			} elseif ( empty( $this->p->cf[ '*' ][ 'slug' ][ $args->slug ] ) ) {	// Make sure the plugin slug is one of ours.
				return $result;
			}

			$ext = $this->p->cf[ '*' ][ 'slug' ][ $args->slug ];			// Get the plugin acronym to read its config.

			if ( empty( self::$upd_config[ $ext ][ 'slug' ] ) ) {			// Make sure we have an update config for acronym.
				return $result;
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'getting plugin data for ' . $ext );
			}

			$plugin_data = $this->get_plugin_data( $ext, $read_cache = true );	// Get plugin data from the json api.

			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {
				return $result;
			}

			return $plugin_data->json_to_wp();
		}

		/**
		 * If the WordPress update system has been disabled and/or manipulated (ie. $updates is not false), then re-enable
		 * updates by including our update data (if a new plugin version is available).
		 */
		public function reenable_plugin_updates( $updates = false ) {

			if ( false !== $updates ) {
				$updates = $this->maybe_add_plugin_update( $updates );
			}

			return $updates;
		}

		/**
		 * $updates can be false or stdClass object.
		 */
		public function maybe_add_plugin_update( $updates = false ) {

			foreach ( self::$upd_config as $ext => $upd_info ) {

				/**
				 * Check the static cache first.
				 */
				if ( isset( self::$upd_config[ $ext ][ 'response' ] ) ) {

					/**
					 * Remove existing update information to make sure it is correct (not from wordpress.org).
					 */
					if ( isset( $updates->response[ $upd_info[ 'base' ] ] ) ) {	// Avoid a "modify non-object" error.
						unset( $updates->response[ $upd_info[ 'base' ] ] );	// Example: wpsso/wpsso.php.
					}

					/**
					 * only provide update information when an update is required.
					 */
					if ( false !== self::$upd_config[ $ext ][ 'response' ] ) {	// False when installed version is current.

						$updates = $this->update_response_data( $updates, $ext );
					}

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: using static cache update response data' );
						$this->p->debug->log( $ext . ' plugin: calling method/function backtrace 4', 4 );
						$this->p->debug->log( $ext . ' plugin: calling method/function backtrace 5', 5 );
					}

					continue;	// Get the next plugin from the config.
				}

				self::$upd_config[ $ext ][ 'response' ] = false;	// Default value.

				if ( self::prefer_wp_org_update( $ext ) ) {

					/**
					 * Seed the static cache.
					 */
					if ( isset( $updates->response[ $upd_info[ 'base' ] ] ) ) {
						self::$upd_config[ $ext ][ 'response' ] = $updates->response[ $upd_info[ 'base' ] ];
					}

					continue;
				}

				if ( ! self::is_installed( $ext ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: not installed' );
					}

					continue;
				}

				$update_data = self::get_option_data( $ext );

				if ( empty( $update_data ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: update option is empty' );
					}

				} elseif ( empty( $update_data->update ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: no update information' );
					}

				} elseif ( ! is_object( $update_data->update ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: update property is not an object' );
					}

				} elseif ( version_compare( self::$upd_config[ $ext ][ 'installed_version' ], $update_data->update->version, '<' ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: installed version is older than update version (' .
							self::$upd_config[ $ext ][ 'installed_version' ] . ' vs ' . $update_data->update->version . ')' );
						$this->p->debug->log( $ext . ' plugin: calling method/function backtrace 4', 4 );
						$this->p->debug->log( $ext . ' plugin: calling method/function backtrace 5', 5 );
					}

					/**
					 * Update the static cache.
					 */
					self::$upd_config[ $ext ][ 'response' ] = $update_data->update->json_to_wp();

					$updates = $this->update_response_data( $updates, $ext );

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log_arr( 'option_data', $updates->response[ $upd_info[ 'base' ] ], 5 );
					}

				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: installed version is current or newer than update version' );
						$this->p->debug->log( $ext . ' plugin: calling method/function backtrace 4', 4 );
						$this->p->debug->log( $ext . ' plugin: calling method/function backtrace 5', 5 );
					}
				}
			}

			return $updates;
		}

		private function update_response_data( $updates, $ext ) {

			if ( isset( self::$upd_config[ $ext ][ 'response' ] ) &&
				false !== self::$upd_config[ $ext ][ 'response' ] ) {	// False when installed version is current.

				$update_data =& self::$upd_config[ $ext ][ 'response' ];	// Shortcut.

				if ( isset( $update_data->plugin ) ) {				// Example: wpsso/wpsso.php

					if ( ! is_object( $updates ) ) {

						$updates = new stdClass;

						$updates->last_checked = time();

						$updates->checked = array();
					}

					$updates->checked[ $update_data->plugin ] = self::$upd_config[ $ext ][ 'installed_version' ];

					$updates->response[ $update_data->plugin ] = $update_data;
				}
			}

			return $updates;
		}

		public function add_custom_schedule( $schedules ) {

			if ( $this->sched_hours > 0 ) {
				$schedules[ $this->sched_name ] = array(
					'interval' => $this->sched_hours * HOUR_IN_SECONDS,
					'display'  => sprintf( 'Every %d hours', $this->sched_hours )
				);
			}

			return $schedules;
		}
	
		public function get_update_data( $ext, $read_cache = true ) {

			/**
			 * Get plugin data from the json api.
			 */
			$plugin_data = $this->get_plugin_data( $ext, $read_cache );

			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: returned update data is invalid' );
				}

				return null;
			}

			return SucomPluginUpdate::update_from_data( $plugin_data );
		}

		/**
		 * Get plugin data from the json api.
		 */
		public function get_plugin_data( $ext, $read_cache = true ) {

			if ( empty( self::$upd_config[ $ext ][ 'slug' ] ) ) { // Make sure we have a config for that slug.

				return $plugin_data = null;
			}

			if ( empty( self::$upd_config[ $ext ][ 'data_json_url' ] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: exiting early - update json_url is empty' );
				}

				return $plugin_data = null;
			}

			$home_url    = SucomUpdateUtilWP::raw_home_url();
			$json_url    = self::$upd_config[ $ext ][ 'data_json_url' ];

			$cache_md5_pre = $this->plugin_lca . '_';
			$cache_salt    = 'SucomUpdate::plugin_data(json_url:' . $json_url . '_home_url:' . $home_url . ')';
			$cache_id      = $cache_md5_pre . md5( $cache_salt );

			if ( self::prefer_wp_org_update( $ext ) ) {

				delete_transient( $cache_id );

				return $plugin_data = null;
			}

			global $wp_version;

			$ext_pdir    = $this->check_pp_compat( $ext, $li = false );
			$ext_pp      = self::$upd_config[ $ext ][ 'auth_id' ] && $this->check_pp_compat( $ext, $li = true, WPSSO_UNDEF ) === WPSSO_UNDEF ? true : false;
			$ext_stat    = ( $ext_pp ? 'L' : ( $ext_pdir ? 'U' : 'S' ) ) . ( self::$upd_config[ $ext ][ 'auth_id' ] ? '*' : '' );

			if ( $read_cache ) {

				/**
				 * Check static cache first, then check the transient cache.
				 */
				if ( isset( self::$upd_config[ $ext ][ 'plugin_data' ]->plugin ) ) {

					$plugin_data = self::$upd_config[ $ext ][ 'plugin_data' ];
				} else {
					$plugin_data = self::$upd_config[ $ext ][ 'plugin_data' ] = get_transient( $cache_id );
				}

				if ( false !== $plugin_data ) { // False if transient is expired or not found.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: returning plugin data from cache' );
					}

					return $plugin_data;
				}

			} else {
				delete_transient( $cache_id );
			}

			$plugin_data = null;
			
			/**
			 * Define some standard error messages for consistency checks.
			 */
			$inconsistency_msg = sprintf( __( 'An inconsistency was found in the %1$s update server information &mdash;',
				$this->text_domain ), self::$upd_config[ $ext ][ 'name' ] );

			$update_disabled_msg = sprintf( __( 'Update checks for %1$s are disabled while this inconsistency persists.',
				$this->text_domain ), self::$upd_config[ $ext ][ 'short' ] );
				
			$update_disabled_msg .= empty( self::$upd_config[ $ext ][ 'urls' ][ 'support' ] ) ? '' : ' ' .
				sprintf( __( 'You may <a href="%1$s">open a new support ticket</a> if you believe this error message is incorrect.',
					$this->text_domain ), self::$upd_config[ $ext ][ 'urls' ][ 'support' ] );

			/**
			 * Check the local resolver and DNS IPv4 values for inconsistencies.
			 */
			$json_host = parse_url( $json_url, PHP_URL_HOST );

			if ( empty( $json_host ) || $json_host === $json_url ) {	// Check for false or original URL.

				$error_msg = $inconsistency_msg . ' ' . sprintf( __( 'the update server URL (%1$s) does not appear to be a valid URL.',
					$this->text_domain ), $json_url ) . ' ' . $update_disabled_msg;

				self::set_umsg( $ext, 'err', $error_msg );

				self::$upd_config[ $ext ][ 'plugin_data' ] = $plugin_data;

				set_transient( $cache_id, new stdClass, self::$upd_config[ $ext ][ 'data_expire' ] );

				return $plugin_data;	// Returns null.
			}

			static $host_cache = array(); // Local cache to lookup the host ip only once.

			if ( ! isset( $host_cache[ $json_host ][ 'ip' ] ) ) {

				$host_cache[ $json_host ][ 'ip' ] = gethostbyname( $json_host ); // Returns an IPv4 address, or the hostname on failure.

				if ( $host_cache[ $json_host ][ 'ip' ] === $json_host ) {
					$host_cache[ $json_host ][ 'ip' ] = 'FAILURE';
				}
			}

			if ( ! isset( $host_cache[ $json_host ][ 'a' ] ) ) {

				$dns_rec = dns_get_record( $json_host . '.', DNS_A ); // Returns an array of associative arrays.

				$host_cache[ $json_host ][ 'a' ] = empty( $dns_rec[ 0 ][ 'ip' ] ) ? false : $dns_rec[ 0 ][ 'ip' ];
			}

			if ( $host_cache[ $json_host ][ 'ip' ] !== $host_cache[ $json_host ][ 'a' ] ) {

				$error_msg = $inconsistency_msg . ' ' .
					sprintf( __( 'the IPv4 address (%1$s) from the local host does not match the DNS IPv4 address (%2$s).',
						$this->text_domain ), $host_cache[ $json_host ][ 'ip' ], $host_cache[ $json_host ][ 'a' ] ) . ' ' .
						$update_disabled_msg;

				self::set_umsg( $ext, 'err', $error_msg );

				self::$upd_config[ $ext ][ 'plugin_data' ] = $plugin_data;

				set_transient( $cache_id, new stdClass, self::$upd_config[ $ext ][ 'data_expire' ] );

				return $plugin_data;	// Returns null.
			}

			/**
			 * Set wp_remote_get() options.
			 */
			$ua_wpid = 'WordPress/' . $wp_version . ' (' . self::$upd_config[ $ext ][ 'slug' ] . '/' . 
				self::$upd_config[ $ext ][ 'installed_version' ] . '/' . $ext_stat . '); ' . $home_url;

			$ssl_verify = apply_filters( $this->plugin_lca . '_um_sslverify', true );

			$get_options = array(
				'timeout'     => 15, // Default timeout is 5 seconds.
				'redirection' => 5,  // Default redirection is 5.
				'sslverify'   => $ssl_verify,
				'user-agent'  => $ua_wpid,
				'headers'     => array(
					'Accept'         => 'application/json',
					'X-WordPress-Id' => $ua_wpid
				)
			);

			/**
			 * Call wp_remote_get().
			 */
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( $ext . ' plugin: sslverify is ' . ( $ssl_verify ? 'true' : 'false' ) );
				$this->p->debug->log( $ext . ' plugin: calling wp_remote_get() for ' . $json_url );
			}

			global $wp_filter;

			$saved_wp_filter = $wp_filter;

			foreach ( array(
				'http_headers_useragent',	// Clear pre-existing filters and re-hook.
				'http_request_timeout',
				'http_request_redirection_count',
				'http_request_version',
				'http_request_reject_unsafe_urls',
				'http_request_args',
				'pre_http_request',
				'https_ssl_verify',
				'http_response',
			) as $tag ) {
				unset( $wp_filter[ $tag ] );
			}

			add_filter( 'http_headers_useragent', array( $this, 'maybe_update_wpua' ), PHP_INT_MAX, 1 );

			$request = wp_remote_get( $json_url, $get_options );

			/**
			 * Check for "cURL error 52: Empty reply from server" and retry wp_remote_get() after pausing for 1 second.
			 */
			if ( is_wp_error( $request ) && strpos( $request->get_error_message(), 'cURL error 52:' ) === 0 ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: wp error code ' . $request->get_error_code() . ' - ' . $request->get_error_message() );
					$this->p->debug->log( $ext . ' plugin: (retry) calling wp_remote_get() for ' . $json_url );
				}

				sleep( 1 ); // Pause 1 second before retrying.

				$request = wp_remote_get( $json_url, $get_options );
			}

			remove_filter( 'http_headers_useragent', array( $this, 'maybe_update_wpua' ), PHP_INT_MAX );

			$wp_filter = $saved_wp_filter;

			unset( $saved_wp_filter );

			if ( is_wp_error( $request ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: wp error code ' . $request->get_error_code() . ' - ' . $request->get_error_message() );
				}

				$this->p->notice->err( sprintf( __( 'Update error from the WordPress wp_remote_get() function: %s',
					$this->text_domain ), $request->get_error_message() ) );

			} elseif ( isset( $request[ 'response' ][ 'code' ] ) ) {
			
				$http_code = (int) $request[ 'response' ][ 'code' ];

				if ( 200 === $http_code ) {
				
					if ( ! empty( $request[ 'body' ] ) ) {

						$payload = json_decode( $request[ 'body' ], $assoc = true, 32 ); // Create an associative array.

						/**
						 * Add or remove existing response messages.
						 */
						foreach ( array( 'err', 'inf' ) as $type ) {
							self::set_umsg( $ext, $type, ( empty( $payload[ 'api_response' ][ $type ] ) ?
								null : $payload[ 'api_response' ][ $type ] ) );
						}
	
						if ( empty( $request[ 'headers' ][ 'x-error-msg' ] ) && 
							empty( $request[ 'headers' ][ 'x-update-error' ] ) && 
								empty( $request[ 'headers' ][ 'x-smp-error' ] ) ) {	// Deprecated on 2018/06/03.
		
							self::$upd_config[ $ext ][ 'uerr' ] = false;
	
							$plugin_data = SucomPluginData::data_from_json( $request[ 'body' ] ); // Returns null on json error.
	
							if ( empty( $plugin_data->plugin ) ) {
		
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( $ext . ' plugin: returned plugin data is incomplete' );
								}
		
								$plugin_data = null;
		
							} elseif ( $plugin_data->plugin !== self::$upd_config[ $ext ][ 'base' ] ) {
		
								if ( $this->p->debug->enabled ) {
									$this->p->debug->log( $ext . ' plugin: property ' . $plugin_data->plugin . 
										' does not match ' . self::$upd_config[ $ext ][ 'base' ] );
								}
		
								$plugin_data = null;
							}
						}
					}

				} elseif ( isset( self::$http_error_codes[ $http_code ] ) ) {

					self::$upd_config[ $ext ][ 'uerr' ] = sprintf( __( 'The WordPress %1$s function returned HTTP %2$d (%3$s) for %4$s.',
						$this->text_domain ), 'wp_remote_get()', $http_code, self::$http_error_codes[ $http_code ], $json_url );
				}
			}

			self::set_umsg( $ext, 'time', time() );

			self::$upd_config[ $ext ][ 'plugin_data' ] = $plugin_data; // Save to static cache.

			if ( null === $plugin_data ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: saving empty stdClass to transient ' . $cache_id );
				}

				set_transient( $cache_id, new stdClass, self::$upd_config[ $ext ][ 'data_expire' ] );

			} else {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: saving plugin data to transient ' . $cache_id );
				}

				set_transient( $cache_id, $plugin_data, self::$upd_config[ $ext ][ 'data_expire' ] );
			}

			return $plugin_data;
		}

		public function get_ext_version( $ext, $read_cache = true ) {

			static $local_cache = array();

			if ( $read_cache ) {
				if ( isset( $local_cache[ $ext ] ) ) {
					return $local_cache[ $ext ];	// Return from cache.
				}
			}

			$info = array();

			$local_cache[ $ext ] = 0;

			if ( isset( $this->p->cf[ 'plugin' ][ $ext ] ) ) {
				$info = $this->p->cf[ 'plugin' ][ $ext ];
			}

			/**
			 * Plugin is active - get the plugin version from the config array.
			 */
			if ( isset( $info[ 'version' ] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: version from plugin config' );
				}

				$local_cache[ $ext ] = $info[ 'version' ];

			/**
			 * Plugin is not active (or not installed) - use get_plugins() to get the plugin version.
			 */
			} elseif ( isset( $info[ 'base' ] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: not active / installed' );
				}

				$wp_plugins = SucomUpdateUtil::get_plugins();

				/**
				 * The plugin is installed.
				 */
				if ( isset( $wp_plugins[ $info[ 'base' ] ] ) ) {

					/**
					 * Use the version found in the plugins array.
					 */
					if ( isset( $wp_plugins[ $info[ 'base' ]][ 'Version' ] ) ) {

						$local_cache[ $ext ] = $wp_plugins[ $info[ 'base' ] ][ 'Version' ];

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: installed version is ' . $local_cache[ $ext ] . ' according to WordPress' );
						}

					} else {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: ' . $info[ 'base' ] . ' version key missing from plugins array' );
						}

						$this->p->notice->err( sprintf( __( 'The %1$s plugin (%2$s) version number is missing from the WordPress plugins array.',
							$this->text_domain ), $info[ 'name' ], $info[ 'base' ] ) );

						/**
						 * Save to cache and stop here.
						 */
						return $local_cache[ $ext ] = '0-no-version';
					}

				/**
				 * Plugin is not installed.
				 */
				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: ' . $info[ 'base' ] . ' plugin not installed' );
					}

					/**
					 * Save to cache and stop here.
					 */
					return $local_cache[ $ext ] = 'not-installed';
				}

			/**
			 * Plugin missing version and/or slug.
			 */
			} else {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: config is missing version and plugin base keys' );
				}

				/**
				 * Save to cache and stop here.
				 */
				return $local_cache[ $ext ] = false;
			}

			$filter_regex = $this->get_ext_filter_regex( $ext );

			if ( ! preg_match( $filter_regex, $local_cache[ $ext ] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: ' . $local_cache[ $ext ] . ' does not match version filter' );
				}

				/**
				 * Save to cache and stop here.
				 */
				return $local_cache[ $ext ] = '0.' . $local_cache[ $ext ];

			} else {

				$ext_auth_type = $this->get_ext_auth_type( $ext );
				$ext_auth_id   = $this->get_ext_auth_id( $ext );

				if ( $ext_auth_type !== 'none' ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: auth type is defined' );
					}

					if ( $this->check_pp_compat( $ext, $li = false ) ) {

						if ( empty( $ext_auth_id ) ) {	// pdir without an auth_id.

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext . ' plugin: have pdir but no auth_id' );
							}

							/**
							 * Save to cache and stop here.
							 */
							return $local_cache[ $ext ] = '0.' . $local_cache[ $ext ];

						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: have pdir with an auth_id' );
						}

					} elseif ( ! empty( $ext_auth_id ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: free with an auth_id' );
						}

						/**
						 * Save to cache and stop here.
						 */
						return $local_cache[ $ext ] = '0.' . $local_cache[ $ext ];
					}

				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: no auth type' );
				}
			}

			return $local_cache[ $ext ];
		}

		public function get_ext_auth_type( $ext ) {

			static $local_cache = array();

			if ( isset( $local_cache[ $ext ] ) ) {
				return $local_cache[ $ext ];	// Return from cache.
			}

			return $local_cache[ $ext ] = empty( $this->p->cf[ 'plugin' ][ $ext ][ 'update_auth' ] ) ?
				'none' : $this->p->cf[ 'plugin' ][ $ext ][ 'update_auth' ];
		}

		public function get_ext_auth_id( $ext ) {

			static $local_cache = array();

			if ( isset( $local_cache[ $ext ] ) ) {
				return $local_cache[ $ext ];	// Return from cache.
			}

			$ext_auth_type = $this->get_ext_auth_type( $ext );
			$ext_auth_key  = 'plugin_' . $ext . '_' . $ext_auth_type;

			return $local_cache[ $ext ] = empty( $this->p->options[ $ext_auth_key ] ) ? '' : $this->p->options[ $ext_auth_key ];
		}

		public function get_ext_filter_name( $ext ) {

			if ( ! empty( $this->p->options[ 'update_filter_for_' . $ext] ) ) {

				$filter_name = $this->p->options[ 'update_filter_for_' . $ext ];

				if ( ! empty( $this->p->cf[ 'um' ][ 'version_regex' ][ $filter_name ] ) ) {	// Make sure the name is valid.
					return $filter_name;
				}
			}

			return 'stable';
		}

		/**
		 * Include extra checks to make sure we have fallback values.
		 */
		public function get_ext_filter_regex( $ext ) {

			$filter_name  = $this->get_ext_filter_name( $ext );	// Returns a valid filter name or 'stable'.
			$filter_regex = '/^[0-9][0-9\.\-]+$/';			// Default stable regex.

			if ( ! empty( $this->p->cf[ 'um' ][ 'version_regex' ][ $filter_name ] ) ) {
				$filter_regex = $this->p->cf[ 'um' ][ 'version_regex' ][ $filter_name ];
			}

			return $filter_regex;
		}

		public static function prefer_wp_org_update( $ext ) {

			static $local_cache = array();

			if ( isset( $local_cache[ $ext ] ) ) {
				return $local_cache[ $ext ];	// Return from cache.
			}

			if ( ! isset( self::$upd_config[ $ext ] ) ) {
				return $local_cache[ $ext ] = false;
			}

			$upd_info = self::$upd_config[ $ext ];

			/**
			 * Make sure the plugin is available on wordpress.org.
			 */
			if ( ! empty( $upd_info[ 'hosts' ][ 'wp_org' ] ) ) {	// Since WPSSO v6.12.0.

				/**
				 * Possibly switching from a development to a stable version filter, or from a Premium to a
				 * Standard version.
				 */
				if ( 0 === strpos( $upd_info[ 'installed_version' ], '0.' ) ) {
					return $local_cache[ $ext ] = false;
				}

				/**
				 * Make sure the authentication type is 'none' (ie. no Pro / Premium version exists).
				 */
				if ( ! empty( $upd_info[ 'auth_type' ] ) && $upd_info[ 'auth_type' ] === 'none' ) {

					/**
					 * Make sure we are using only the stable versions.
					 */
					if ( ! empty( $upd_info[ 'version_filter' ] ) && $upd_info[ 'version_filter' ] === 'stable' ) {

						return $local_cache[ $ext ] = true;
					}
				}
			}

			return $local_cache[ $ext ] = false;
		}

		public static function is_enabled() {

			return empty( self::$upd_config ) ? false : true;
		}

		public static function is_configured( $ext = null ) {

			if ( empty( $ext ) ) {
				return count( self::$upd_config );
			}

			if ( isset( self::$upd_config[ $ext ] ) ) {
				return true;
			}

			return false;
		}

		public static function is_installed( $ext ) {

			if ( empty( $ext ) ) {
				return false;
			}

			if ( ! isset( self::$upd_config[ $ext ] ) ) {
				return false;
			}

			$upd_info = self::$upd_config[ $ext ];	// Shortcut.

			if ( ! isset( $upd_info[ 'installed_version' ] ) ) {
				return false;
			}

			if ( false !== strpos( $upd_info[ 'installed_version' ], 'not-installed' ) ) {	// Anywhere in string.
				return false;
			}

			return true;
		}

		/**
		 * Called by delete_options() in the register class.
		 */
		public static function get_api_version() {

			return self::$api_version;
		}

		/**
		 * Called by get_plugin_data() when the transient / object cache is empty and/or not used.
		 */
		private static function set_umsg( $ext, $type, $val ) {

			$opt_name = md5( $ext . '_uapi' . self::$api_version . $type );

			if ( empty( $val ) ) {

				$val = null;

				SucomUpdateUtilWP::raw_do_option( 'delete', $opt_name );
			} else {
				$val_string = base64_encode( $val );	// Convert object or array to string.

				SucomUpdateUtilWP::raw_do_option( 'update', $opt_name, $val_string );
			}

			if ( isset( self::$upd_config[ $ext ] ) ) {
				self::$upd_config[ $ext ][ 'u' . $type ] = $val;
			}

			return $val;
		}

		public static function get_umsg( $ext, $type = 'err', $def = null ) {

			$opt_name = md5( $ext . '_uapi' . self::$api_version . $type );

			if ( isset( self::$upd_config[ $ext ][ 'u' . $type ] ) ) {

				$val = self::$upd_config[ $ext ][ 'u' . $type ];
			} else {
				$val = SucomUpdateUtilWP::raw_do_option( 'get', $opt_name, $def );

				if ( is_string( $val ) ) {
					$val = base64_decode( $val );	// Convert string back to object or array.
				}

				if ( empty( $val ) ) {
					$val = null;
				}

				if ( isset( self::$upd_config[ $ext ] ) ) {
					self::$upd_config[ $ext ][ 'u' . $type ] = $val;
				}
			}

			return $val;
		}

		/**
		 * Returns null if $prop does not exist (since v1.10.0).
		 */
		public static function get_option( $ext, $prop = false ) {

			$not_found = false !== $prop ? null : false;	// Return null if $prop does not exist.

			if ( ! empty( self::$upd_config[ $ext ][ 'option_name' ] ) ) {

				$option_data = self::get_option_data( $ext );

				if ( false !== $prop ) {
					if ( is_object( $option_data->update ) && isset( $option_data->update->$prop ) ) {
						return $option_data->update->$prop;
					} else {
						return $not_found;
					}
				} else {
					return $option_data;
				}
			}

			return $not_found;
		}

		private static function get_option_data( $ext, $def = false ) {

			if ( ! isset( self::$upd_config[ $ext ][ 'option_data' ] ) ) {

				if ( ! empty( self::$upd_config[ $ext ][ 'option_name' ] ) ) {

					$opt_name = self::$upd_config[ $ext ][ 'option_name' ];

					self::$upd_config[ $ext ][ 'option_data' ] = SucomUpdateUtilWP::raw_do_option( 'get', $opt_name, $def );
				} else {
					self::$upd_config[ $ext ][ 'option_data' ] = $def;
				}
			}

			return self::$upd_config[ $ext ][ 'option_data' ];
		}

		private static function update_option_data( $ext, $option_data ) {

			self::$upd_config[ $ext ][ 'option_data' ] = $option_data;

			if ( ! empty( self::$upd_config[ $ext ][ 'option_name' ] ) ) {
				return update_option( self::$upd_config[ $ext ][ 'option_name' ], $option_data );
			}

			return false;
		}

		private static function clear_option_data( $ext ) {

			unset( self::$upd_config[ $ext ][ 'option_data' ] );

			if ( ! empty( self::$upd_config[ $ext ][ 'option_name' ] ) ) {
				return delete_option( self::$upd_config[ $ext ][ 'option_name' ] );
			}

			return false;
		}
	}
}

if ( ! class_exists( 'SucomPluginData' ) ) {

	class SucomPluginData {
	
		public $id = 0;
		public $name;
		public $slug;
		public $plugin;
		public $version;
		public $tested;
		public $requires;
		public $homepage;
		public $download_url;
		public $author;
		public $author_homepage;
		public $upgrade_notice;
		public $banners;
		public $icons;
		public $rating;
		public $num_ratings;
		public $last_updated;
		public $sections;
	
		public function __construct() {
		}

		public static function data_from_json( $json_encoded ) {

			$json_data = json_decode( $json_encoded, $assoc = false );

			if ( empty( $json_data ) || ! is_object( $json_data ) )  {
				return null;
			}

			if ( empty( $json_data->plugin ) || empty( $json_data->version ) ) {
				return null;
			}

			$plugin_data = new SucomPluginData();

			foreach( get_object_vars( $json_data ) as $key => $value ) {
				$plugin_data->$key = $value;
			}

			return $plugin_data;
		}
	
		public function json_to_wp(){

			$plugin_data = new StdClass;

			foreach ( array(
				'name', 
				'slug', 
				'plugin', 
				'version', 
				'tested', 
				'requires', 
				'homepage', 
				'download_url',
				'author_homepage',
				'upgrade_notice',
				'banners',
				'icons',
				'rating', 
				'num_ratings', 
				'last_updated',
				'sections',
			) as $prop_name ) {

				if ( isset( $this->$prop_name ) ) {

					if ( $prop_name === 'download_url' ) {

						$plugin_data->download_link = $this->download_url;

					} elseif ( $prop_name === 'author_homepage' ) {

						if ( strpos( $this->author, '<a href' ) === false ) {
							$plugin_data->author = sprintf( '<a href="%s">%s</a>', $this->author_homepage, $this->author );
						} else {
							$plugin_data->author = $this->author;
						}

					} elseif ( $prop_name === 'sections' && empty( $this->$prop_name ) ) {

						$plugin_data->$prop_name = array( 'description' => '' );

					} elseif ( is_object( $this->$prop_name ) ) {

						$plugin_data->$prop_name = get_object_vars( $this->$prop_name );
					} else {
						$plugin_data->$prop_name = $this->$prop_name;
					}

				} elseif ( $prop_name === 'author_homepage' ) {
					$plugin_data->author = $this->author;
				}
			}

			return $plugin_data;
		}
	}
}
	
if ( ! class_exists( 'SucomPluginUpdate' ) ) {

	class SucomPluginUpdate {
	
		public $id = 0;
		public $slug;
		public $plugin;
		public $version = 0;
		public $tested;
		public $homepage;	// Plugin homepage URL.
		public $download_url;	// Update download URL.
		public $upgrade_notice;
		public $icons;
		public $exp_date;	// Example: 0000-00-00 00:00:00
		public $qty_total = 0;	// Example: 10	(since v1.10.0)
		public $qty_reg   = 0;	// Example: 1	(since v1.10.0)
		public $qty_used  = '';	// Example: 1/10

		public function __construct() {
		}

		public static function update_from_json( $json_encoded ) {

			$plugin_data = SucomPluginData::data_from_json( $json_encoded );

			if ( $plugin_data !== null )  {
				return self::update_from_data( $plugin_data );
			} else {
				return null;
			}
		}
	
		public static function update_from_data( $plugin_data ){

			$plugin_update = new SucomPluginUpdate();

			foreach ( array(
				'id', 
				'slug', 
				'plugin', 
				'version', 
				'tested', 
				'homepage', 
				'download_url', 
				'upgrade_notice',
				'icons',
				'exp_date',
				'qty_total', 
				'qty_reg', 
				'qty_used', 
			) as $prop_name ) {
				if ( isset( $plugin_data->$prop_name ) ) {
					$plugin_update->$prop_name = $plugin_data->$prop_name;
				}
			}

			return $plugin_update;
		}
	
		public function json_to_wp() {

			$plugin_update = new StdClass;

			foreach ( array(
				'id'             => 'id',
				'slug'           => 'slug',
				'plugin'         => 'plugin',
				'version'        => 'new_version',
				'tested'         => 'tested',
				'homepage'       => 'url',		// Plugin homepage URL.
				'download_url'   => 'package',		// Update download URL.
				'upgrade_notice' => 'upgrade_notice',
				'icons'          => 'icons',
				'exp_date'       => 'exp_date',
				'qty_total'      => 'qty_total',
				'qty_reg'        => 'qty_reg',
				'qty_used'       => 'qty_used',
			) as $json_prop_name => $wp_prop_name ) {
				if ( isset( $this->$json_prop_name ) ) {
					if ( is_object( $this->$json_prop_name ) ) {
						$plugin_update->$wp_prop_name = get_object_vars( $this->$json_prop_name );
					} else {
						$plugin_update->$wp_prop_name = $this->$json_prop_name;
					}
				}
			}

			return $plugin_update;
		}
	}
}
