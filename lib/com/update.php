<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'SucomUpdate' ) ) {

	class SucomUpdate {
	
		private $p           = null;
		private $plugin_lca  = '';
		private $plugin_slug = '';
		private $text_domain = '';
		private $cron_hook   = '';
		private $sched_hours = 24;
		private $sched_name  = 'every24hours';

		private static $api_version  = 2;
		private static $upd_config   = array();
		private static $ext_versions = array();

		public function __construct( &$plugin, $check_hours = 24, $text_domain = '' ) {

			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// Begin timer.
			}

			if ( isset( $this->p->lca ) ) {
				$this->plugin_lca = $this->p->lca;
			} elseif ( isset( $this->p->cf['lca'] ) ) {
				$this->plugin_lca = $this->p->cf['lca'];
			}

			if ( ! empty( $this->plugin_lca ) ) {

				$this->plugin_slug = $this->p->cf['plugin'][$this->plugin_lca]['slug'];	// Example: wpsso.
				$this->text_domain = $text_domain;					// Example: wpsso-um.
				$this->cron_hook   = $this->plugin_lca . '_update_manager_check';		// Example: wpsso_update_manager_check.
				$this->sched_hours = $check_hours < 12 ? 12 : $check_hours;		// Example: 12 (12 hours minimum).
				$this->sched_name  = 'every' . $this->sched_hours . 'hours';		// Example: every24hours.

				$this->set_config();	// Private method.
				$this->install_hooks();	// Private method.
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// End timer.
			}
		}

		/**
		 * Called by the WordPress cron.
		 */
		public function check_all_for_updates( $quiet = true, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$check_ext = null; // Check all ext by default.

			$this->check_ext_for_updates( $this->plugin_lca, $quiet, $read_cache ); // Check the lca plugin first.

			$check_ext = $this->get_config_keys( $check_ext, $this->plugin_lca, $read_cache ); // Reset config and get ext array (exclude lca).

			$this->check_ext_for_updates( $check_ext, $quiet, $read_cache ); // Check all remaining plugins.
		}

		public function check_ext_for_updates( $check_ext = null, $quiet = true, $read_cache = true ) {

			$ext_config = array();

			if ( empty( $check_ext ) ) {

				$ext_config = self::$upd_config;	// Check all plugins defined.

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'checking all known plugins for updates' );
				}

			} elseif ( is_array( $check_ext ) ) {

				foreach ( $check_ext as $ext ) {
					if ( isset( self::$upd_config[ $ext ] ) ) {
						$ext_config[ $ext ] = self::$upd_config[ $ext ];
					}
				}

			} elseif ( is_string( $check_ext ) ) {

				if ( isset( self::$upd_config[ $check_ext ] ) ) {
					$ext_config[ $check_ext ] = self::$upd_config[ $check_ext ];
				}
			}

			if ( empty( $ext_config ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: no plugins to check for updates' );
				}

				if ( ! $quiet || $this->p->debug->enabled ) {

					$notice_key = __FUNCTION__ . '_no_plugins_defined';

					$this->p->notice->err( sprintf( __( 'No plugins defined for updates.',
						$this->text_domain ), $info['name'] ), null, $notice_key );
				}

				return;
			}

			foreach ( $ext_config as $ext => $info ) {

				if ( ! self::is_installed( $ext ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: not installed' );
					}

					continue;
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: checking for update' );
				}

				if ( $read_cache ) {
					$update_data = self::get_option_data( $ext );
				} else {
					$update_data = false;
				}

				if ( empty( $update_data ) ) {
					$update_data                 = new StdClass;
					$update_data->lastCheck      = 0;
					$update_data->checkedVersion = 0;
					$update_data->update         = null;
				}

				$update_data->lastCheck      = time();
				$update_data->checkedVersion = $this->get_ext_version( $ext );
				$update_data->update         = $this->get_update_data( $ext, $read_cache );

				if ( self::update_option_data( $ext, $update_data ) ) {

					if ( empty( self::$upd_config[ $ext ][ 'uerr' ] ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: update information saved in ' . $info['option_name'] );
						}

						if ( ! $quiet || $this->p->debug->enabled ) {

							$notice_key = __FUNCTION__ . '_' . $ext . '_' . $info['option_name'] . '_success';

							$this->p->notice->inf( sprintf( __( 'Update information for %s has been retrieved and saved.',
								$this->text_domain ), $info['name'] ), null, $notice_key );
						}

					} else {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: error returned getting update information' );
						}

						if ( ! $quiet || $this->p->debug->enabled ) {

							$notice_key = __FUNCTION__ . '_' . $ext . '_' . $info['option_name'] . '_error_returned';

							$this->p->notice->warn( sprintf( __( 'An error was returned while getting update information for %s.',
								$this->text_domain ), $info['name'] ), null, $notice_key );
						}
					}

				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: failed saving update information in ' . $info['option_name'] );
					}

					if ( ! $quiet || $this->p->debug->enabled ) {

						$notice_key = __FUNCTION__ . '_' . $ext . '_' . $info['option_name'] . '_failed_saving';

						$this->p->notice->err( sprintf( __( 'Failed saving retrieved update information for %s.',
							$this->text_domain ), $info['name'] ), null, $notice_key );
					}
				}
			}
		}
	
		/**
		 * Returns an array of configured plugin lowercase acronyms.
		 */
		public function get_config_keys( $include = null, $exclude = null, $read_cache = true ) {

			$quiet = true;
			$keys  = array();

			$this->set_config( $quiet, $read_cache );	// Private method.

			/**
			 * Optionally include only some plugin keys.
			 */
			if ( ! empty( $include ) ) {

				if ( ! is_array( $include ) ) {
					$include = array( $include );
				}

				foreach ( $include as $ext ) {
					if ( isset( self::$upd_config[ $ext ] ) ) {
						$keys[] = $ext;
					}
				}

			} elseif ( is_array( self::$upd_config ) ) {
				$keys = array_keys( self::$upd_config );	// Include all keys.
			}

			/**
			 * Optionally exclude some plugin keys.
			 */
			if ( ! empty( $exclude ) ) {

				if ( ! is_array( $exclude ) ) {
					$exclude = array( $exclude );
				}

				$old_keys = $keys;

				$keys = array();	// Start a new array.

				foreach ( $old_keys as $old_key ) {

					foreach ( $exclude as $ext ) {

						if ( $old_key === $ext ) {
							continue 2;	// Skip this key.
						}
					}

					$keys[] = $old_key;
				}

				unset( $old_keys );	// Cleanup.
			}

			return $keys;
		}

		public function filter_save_options( $opts ) {

			$cache_md5_pre  = $this->p->lca . '_';
			$cache_salt     = __CLASS__ . '::upd_config';
			$cache_id       = $cache_md5_pre . md5( $cache_salt );

			delete_transient( $cache_id );

			self::$upd_config = array();	// Reset the config array.

			return $opts;
		}

		private function check_pp( $ext = '', $lic = true, $rv = true, $uc = true ) {
			return method_exists( $this->p->check, 'pp' ) ?
				$this->p->check->pp( $ext, $lic, $rv, $uc ) :
				$this->p->check->aop( $ext, $lic, $rv, $uc );	// Deprecated on 2018/08/27.
		}

		/**
		 * $quiet is false by default, to show a warning if one or more development version filters are selected.
		 */
		private function set_config( $quiet = false, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$cache_md5_pre  = $this->p->lca . '_';
			$cache_exp_secs = WEEK_IN_SECONDS;
			$cache_salt     = __CLASS__ . '::upd_config';
			$cache_id       = $cache_md5_pre . md5( $cache_salt );

			if ( $read_cache ) {

				self::$upd_config = get_transient( $cache_id );

				if ( ! empty( self::$upd_config ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'config retrieved from transient cache' );
					}

					return;
				}
			}

			self::$upd_config = array();	// Reset the config array.

			$has_pdir = $this->p->avail['*']['p_dir'];
			$has_pp   = $this->check_pp( $this->plugin_lca, true, $has_pdir, $read_cache );
			$has_dev  = false;

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( ! $has_pp && $ext !== $this->plugin_lca && $ext !== $this->plugin_lca . 'um' ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - pp required' );
					}

					continue;
				}

				$auth_type = $this->get_auth_type( $ext );
				$auth_id   = $this->get_auth_id( $ext );

				if ( $auth_type !== 'none' && empty( $auth_id ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - auth type without id' );
					}

					continue;

				} elseif ( empty( $info['slug'] ) || empty( $info['base'] ) || empty( $info['url']['update'] ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - incomplete config' );
					}

					continue;
				}

				$ext_version = $this->get_ext_version( $ext );

				if ( false === $ext_version ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: skipped - version is false' );
					}

					continue;
				}

				if ( strpos( $ext_version, 'not-installed' ) !== false ) {	// Anywhere in string.
					$filter_name = 'stable';
				} else {
					$filter_name = $this->get_filter_name( $ext );
				}

				if ( $filter_name !== 'stable' ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: non-stable filter found' );
					}

					$has_dev = true;
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: installed version is ' . $ext_version . ' with ' . $filter_name . ' version filter' );
				}

				/**
				 * Add the auth type and id to the update url.
				 */
				$inconsistency_msg = sprintf( __( 'An inconsistency was found in the %1$s update server information &mdash;',
					$this->text_domain ), $info[ 'name' ] );

				$update_disabled_msg = sprintf( __( 'Update checks for %1$s are disabled while this inconsistency persists.',
					$this->text_domain ), $info[ 'short' ] );
					
				$update_disabled_msg .= empty( $info['url']['support'] ) ? '' : ' ' .
					sprintf( __( 'You may <a href="%1$s">open a new support ticket</a> if you believe this error message is incorrect.',
						$this->text_domain ), $info['url']['support'] );

				if ( filter_var( $info['url']['update'], FILTER_VALIDATE_URL ) === false ) {	// Invalid url.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: config update url is invalid (' . $info['url']['update'] . ')' );
					}

					$error_msg = $inconsistency_msg . ' ' . 
						sprintf( __( 'the update URL provided by the plugin configuration is invalid (%1$s).',
							$this->text_domain ), $info['url']['update'] ) . ' ' .
								$update_disabled_msg;

					self::set_umsg( $ext, 'err', $error_msg );

					if ( $ext === $this->plugin_lca ) {
						$has_pp = false;
					}

					continue;
				}

				$auth_url = $info['url']['update'];

				if ( $auth_type !== 'none' ) {
					$auth_url = add_query_arg( array( $auth_type => $auth_id ), $auth_url );

				}

				$locale = is_admin() && function_exists( 'get_user_locale' ) ?
					get_user_locale() : get_locale();

				$auth_url = add_query_arg( array( 
					'api_version'       => self::$api_version,
					'installed_version' => $ext_version,
					'version_filter'    => $filter_name,
					'sched_hours'       => $this->sched_hours,
					'locale'            => $locale,
				), $auth_url );

				if ( filter_var( $auth_url, FILTER_VALIDATE_URL ) === false ) {	// Invalid url.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: add_query_arg() returned an invalid authentication URL (' . $auth_url . ')' );
					}

					$error_msg = $inconsistency_msg . ' ' . 
						sprintf( __( 'the WordPress add_query_arg() function returned an invalid authentication URL (%1$s).',
							$this->text_domain ), $auth_url ) . ' ' .
								$update_disabled_msg;

					self::set_umsg( $ext, 'err', $error_msg );

					if ( $ext === $this->plugin_lca ) {
						$has_pp = false;
					}

					continue;
				}

				self::$upd_config[ $ext ] = array(
					'name'              => $info['name'],
					'short'             => $info['short'],
					'slug'              => $info['slug'],				// Example: wpsso.
					'base'              => $info['base'],				// Example: wpsso/wpsso.php.
					'api_version'       => self::$api_version,
					'installed_version' => $ext_version,
					'version_filter'    => $filter_name,
					'json_url'          => $auth_url,
					'support_url'       => isset( $info['url']['support'] ) ? $info['url']['support'] : '',
					'data_expire'       => 86100,					// Plugin data expiration (almost 24 hours).
					'option_name'       => 'external_update-' . $info['slug'],	// Example: external_update-wpsso.
				);

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: update info configured (auth_type is ' . $auth_type . ')' );
				}
			}

			if ( ! $quiet || $this->p->debug->enabled ) {

				if ( $has_dev && $this->p->notice->is_admin_pre_notices() ) {

					$notice_key   = 'non-stable-update-version-filters-selected';
					$dismiss_time = MONTH_IN_SECONDS * 3;

					$this->p->notice->warn( sprintf( __( 'Please note that one or more non-stable / development %s have been selected.',
						$this->text_domain ), $this->p->util->get_admin_url( 'um-general', _x( 'Update Version Filters',
							'metabox title', $this->text_domain ) ) ), null, $notice_key, $dismiss_time );
				}
			}

			set_transient( $cache_id, self::$upd_config, $cache_exp_secs );

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'config saved to transient cache for ' . $cache_exp_secs . ' seconds' );
			}
		}

		private function install_hooks() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$this->p->util->add_plugin_filters( $this, array( 'save_options' => 1 ), -10000 );

			if ( empty( self::$upd_config ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'skipping all update checks - update config array is empty' );
				}

				return;
			}

			add_filter( 'plugins_api_result', array( $this, 'external_plugin_data' ), PHP_INT_MAX, 3 );
			add_filter( 'transient_update_plugins', array( $this, 'maybe_add_plugin_update' ), 1000, 1 );
			add_filter( 'site_transient_update_plugins', array( $this, 'maybe_add_plugin_update' ), 1000, 1 );
			add_filter( 'pre_site_transient_update_plugins', array( $this, 'reenable_plugin_update' ), 1000, 1 );
			add_filter( 'http_request_host_is_external', array( $this, 'allow_update_package' ), 2000, 3 );
			add_filter( 'http_headers_useragent', array( $this, 'check_wpua_value' ), PHP_INT_MAX, 1 );

			/**
			 * Maybe remove the old plugin update hook.
			 */
			if ( wp_get_schedule( 'plugin_update-' . $this->plugin_slug ) ) {
				wp_clear_scheduled_hook( 'plugin_update-' . $this->plugin_slug );
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'adding ' . $this->cron_hook . ' schedule for ' . $this->sched_name );
			}

			add_action( $this->cron_hook, array( $this, 'check_all_for_updates' ) );

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

		public function allow_update_package( $is_allowed, $ip, $url ) {

			if ( $is_allowed ) {	// Already allowed.
				return $is_allowed;
			}

			foreach ( self::$upd_config as $ext => $info ) {
				if ( ! empty( $info['plugin_update']->package ) && $info['plugin_update']->package === $url ) {
					return true;
				}
			}

			return $is_allowed;
		}

		public function check_wpua_value( $wpua ) {

			global $wp_version;

			$correct = 'WordPress/' . $wp_version . '; ' . SucomUpdateUtilWP::raw_home_url();

			if ( $correct !== $wpua ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'incorrect wpua for ' . $wpua );
				}
				return $correct;
			} else {
				return $wpua;
			}
		}

		/**
		 * Provide plugin data from the json api for free / pro add-ons not hosted on wordpress.org.
		 */
		public function external_plugin_data( $result, $action = null, $args = null ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( $action !== 'plugin_information' ) {	// This filter only provides plugin data.
				return $result;
			} elseif ( empty( $args->slug ) ) {	// Make sure we have a slug in the request.
				return $result;
			} elseif ( ! empty( $args->unfiltered ) ) {	// Flag for the update manager filter.
				return $result;
			} elseif ( empty( $this->p->cf['*']['slug'][$args->slug] ) ) {	// Make sure the plugin slug is one of ours.
				return $result;
			}

			/**
			 * Get the plugin acronym to read its update config.
			 */
			$ext = $this->p->cf[ '*' ][ 'slug' ][ $args->slug ];

			/**
			 * Make sure we have an update config for that slug.
			 */
			if ( empty( self::$upd_config[ $ext ]['slug'] ) ) {
				return $result;
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'getting plugin data for ' . $ext );
			}

			/**
			 * Get plugin data from the json api.
			 */
			$plugin_data = $this->get_plugin_data( $ext, true );	// $read_cache is true.

			/**
			 * Make sure we have something to return.
			 */
			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {
				return $result;
			}

			return $plugin_data->json_to_wp();
		}

		public function maybe_add_plugin_update( $updates = false ) {

			foreach ( self::$upd_config as $ext => $info ) {

				if ( ! self::is_installed( $ext ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: not installed' );
					}

					continue;
				}

				/**
				 * Remove existing update information to make sure it is correct (not from wordpress.org).
				 */
				if ( isset( $updates->response[$info['base']] ) ) {
					unset( $updates->response[$info['base']] );	// Example: wpsso/wpsso.php.
				}

				/**
				 * Check the local static cache first.
				 */
				if ( isset( self::$upd_config[ $ext ]['plugin_update'] ) ) {

					/**
					 * only provide update information when an update is required.
					 */
					if ( self::$upd_config[ $ext ]['plugin_update'] !== false ) {	// False when installed version is current.
						$updates->response[$info['base']] = self::$upd_config[ $ext ]['plugin_update'];
					}
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: using saved update information' );
						$this->p->debug->log( $ext . ' plugin: calling method/function', 5 );
					}
					continue;	// Get the next plugin from the config.
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

				} elseif ( ( $ext_version = $this->get_ext_version( $ext ) ) &&
					version_compare( $update_data->update->version, $ext_version, '>' ) ) {

					/**
					 * Save to the local static cache.
					 */
					self::$upd_config[ $ext ]['plugin_update'] = $updates->response[$info['base']] = $update_data->update->json_to_wp();

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: installed version (' . $ext_version . ') ' . 
							'different than update version (' . $update_data->update->version . ')' );
						$this->p->debug->log_arr( 'option_data', $updates->response[$info['base']], 5 );
					}
				} else {
					self::$upd_config[ $ext ]['plugin_update'] = false;	// False when installed is current.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: installed version is current (or newer) than update version' );
						$this->p->debug->log_arr( 'option_data', $update_data->update->json_to_wp(), 5 );
					}
				}
			}

			return $updates;
		}
	
		/**
		 * If the WordPress update system has been disabled and/or manipulated (ie. $updates is not false), 
		 * then re-enable updates by including our update data (if a new plugin version is available).
		 */
		public function reenable_plugin_update( $updates = false ) {

			if ( $updates !== false ) {
				$updates = $this->maybe_add_plugin_update( $updates );
			}

			return $updates;
		}

		public function add_custom_schedule( $schedules ) {

			if ( $this->sched_hours > 0 ) {
				$schedules[$this->sched_name] = array(
					'interval' => $this->sched_hours * HOUR_IN_SECONDS,
					'display' => sprintf( 'Every %d hours', $this->sched_hours )
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

		public function get_plugin_data( $ext, $read_cache = true ) {

			if ( empty( self::$upd_config[ $ext ][ 'slug' ] ) ) { // Make sure we have a config for that slug.

				return null;
			}

			if ( empty( self::$upd_config[ $ext ][ 'json_url' ] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: exiting early - update json_url is empty' );
				}

				return null;
			}

			global $wp_version;

			$has_pdir    = $this->p->avail[ '*' ][ 'p_dir' ];
			$home_url    = SucomUpdateUtilWP::raw_home_url();
			$json_url    = self::$upd_config[ $ext ][ 'json_url' ];
			$ext_version = $this->get_ext_version( $ext );

			$cache_md5_pre = $this->plugin_lca . '_';
			$cache_salt    = 'SucomUpdate::plugin_data(json_url:' . $json_url . '_home_url:' . $home_url . ')';
			$cache_id      = $cache_md5_pre . md5( $cache_salt );

			if ( $read_cache ) {

				if ( isset( self::$upd_config[ $ext ][ 'plugin_data' ]->plugin ) ) {
					$plugin_data = self::$upd_config[ $ext ][ 'plugin_data' ];
				} else {
					$plugin_data = self::$upd_config[ $ext ][ 'plugin_data' ] = get_transient( $cache_id );
				}

				if ( $plugin_data !== false ) { // False if transient is expired or not found.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: returning plugin data from cache' );
					}

					return $plugin_data;
				}

			} else {
				delete_transient( $cache_id );
			}

			$plugin_data = null;
			
			$inconsistency_msg = sprintf( __( 'An inconsistency was found in the %1$s update server information &mdash;',
				$this->text_domain ), self::$upd_config[ $ext ][ 'name' ] );

			$update_disabled_msg = sprintf( __( 'Update checks for %1$s are disabled while this inconsistency persists.',
				$this->text_domain ), self::$upd_config[ $ext ][ 'short' ] );
				
			$update_disabled_msg .= empty( self::$upd_config[ $ext ]['support_url'] ) ? '' : ' ' .
				sprintf( __( 'You may <a href="%1$s">open a new support ticket</a> if you believe this error message is incorrect.',
					$this->text_domain ), self::$upd_config[ $ext ]['support_url'] );

			/**
			 * Check the local resolver and DNS IPv4 values for inconsistencies.
			 */
			$json_host = preg_replace( '/^https?:\/\/([^\/]+)\/.*$/', '$1', $json_url );	// Returns false or original URL on failure.

			if ( empty( $json_host ) || $json_host === $json_url ) {	// Check for false or original URL.

				$json_host = preg_replace( '/\?.*$/', '', $json_url );

				$error_msg = $inconsistency_msg . ' ' .
					sprintf( __( 'the update server URL (%1$s) does not appear to be a valid URL.',
						$this->text_domain ), $json_host ) . ' ' .
							$update_disabled_msg;

				set_umsg( $ext, 'err', $error_msg );

				self::$upd_config[ $ext ][ 'plugin_data' ] = $plugin_data;

				set_transient( $cache_id, new stdClass, self::$upd_config[ $ext ]['data_expire'] );

				return $plugin_data;	// Returns null.
			}

			static $host_cache = array(); // Local cache to lookup the host ip only once.

			if ( ! isset( $host_cache[$json_host]['ip'] ) ) {
				$host_cache[$json_host]['ip'] = gethostbyname( $json_host ); // Returns an IPv4 address, or the hostname on failure.
			}

			if ( ! isset( $host_cache[$json_host]['a'] ) ) {

				$dns_rec = dns_get_record( $json_host . '.', DNS_A ); // Returns an array of associative arrays.

				$host_cache[$json_host]['a'] = empty( $dns_rec[0]['ip'] ) ? false : $dns_rec[0]['ip'];
			}

			if ( $host_cache[$json_host]['ip'] !== $host_cache[$json_host]['a'] ) {

				$error_msg = $inconsistency_msg . ' ' .
					sprintf( __( 'the IPv4 address (%1$s) from the local host does not match the DNS IPv4 address (%2$s).',
						$this->text_domain ), $host_cache[$json_host]['ip'], $host_cache[$json_host]['a'] ) . ' ' .
							$update_disabled_msg;

				self::set_umsg( $ext, 'err', $error_msg );

				self::$upd_config[ $ext ][ 'plugin_data' ] = $plugin_data;

				set_transient( $cache_id, new stdClass, self::$upd_config[ $ext ][ 'data_expire' ] );

				return $plugin_data;	// Returns null.
			}

			/**
			 * Set wp_remote_get() options.
			 */
			$ua_wpid = 'WordPress/' . $wp_version . ' (' . self::$upd_config[ $ext ]['slug'] . '/' . $ext_version . '/' . 
				( $this->check_pp( $ext, true, $has_pdir ) ? 'L' :
				( $this->check_pp( $ext, false ) ? 'U' : 'G' ) ) . '); ' . $home_url;

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

			if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
				SucomUtil::protect_filter_value( 'http_headers_useragent' ); // Make sure the user agent does not change.
			}

			$request = wp_remote_get( $json_url, $get_options );

			/**
			 * Check for "cURL error 52: Empty reply from server" and retry wp_remote_get() after pausing for 1 second.
			 */
			if ( is_wp_error( $request ) && strpos( $request->get_error_message(), 'cURL error 52:' ) === 0 ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: wp error code ' . $request->get_error_code() . ' - ' . $request->get_error_message() );
					$this->p->debug->log( $ext . ' plugin: (retry) calling wp_remote_get() for ' . $json_url );
				}

				if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
					SucomUtil::protect_filter_value( 'http_headers_useragent' );
				}

				sleep( 1 ); // Pause 1 second before retrying.

				$request = wp_remote_get( $json_url, $get_options );
			}

			if ( is_wp_error( $request ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: wp error code ' . $request->get_error_code() . ' - ' . $request->get_error_message() );
				}
				$this->p->notice->err( sprintf( __( 'Update error from the WordPress wp_remote_get() function: %s',
					$this->text_domain ), $request->get_error_message() ) );

			} elseif ( isset( $request['response']['code'] ) && (int) $request['response']['code'] === 200 && ! empty( $request['body'] ) ) {

				$payload = json_decode( $request['body'], true, 32 ); // Create an associative array.

				/**
				 * Add or remove existing response messages.
				 */
				foreach ( array( 'err', 'inf' ) as $mtype ) {
					self::set_umsg( $ext, $mtype, ( empty( $payload[ 'api_response' ][ $mtype ] ) ?
						false : $payload[ 'api_response' ][ $mtype ] ) );
				}

				if ( empty( $request['headers']['x-error-msg'] ) && 
					empty( $request['headers']['x-update-error'] ) && 
						empty( $request['headers']['x-smp-error'] ) ) {	// Deprecated on 2018/06/03.

					self::$upd_config[ $ext ][ 'uerr' ] = false;

					$plugin_data = SucomPluginData::data_from_json( $request['body'] ); // Returns null on json error.

					if ( empty( $plugin_data->plugin ) ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: returned plugin data is incomplete' );
						}

						$plugin_data = null;

					} elseif ( $plugin_data->plugin !== self::$upd_config[ $ext ]['base'] ) {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: property ' . $plugin_data->plugin . 
								' does not match ' . self::$upd_config[ $ext ]['base'] );
						}

						$plugin_data = null;
					}
				}
			}

			self::set_umsg( $ext, 'time', time() );

			self::$upd_config[ $ext ][ 'plugin_data' ] = $plugin_data; // Save to local static cache.

			if ( null === $plugin_data ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: saving empty stdClass to transient ' . $cache_id );
				}

				set_transient( $cache_id, new stdClass, self::$upd_config[ $ext ]['data_expire'] );

			} else {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: saving plugin data to transient ' . $cache_id );
				}

				set_transient( $cache_id, $plugin_data, self::$upd_config[ $ext ]['data_expire'] );
			}

			return $plugin_data;
		}
	
		public function get_ext_version( $ext ) {

			$info = array();

			if ( isset( self::$ext_versions[ $ext ] ) ) {
				return self::$ext_versions[ $ext ];	// Return from cache.
			} else {
				self::$ext_versions[ $ext ] = 0;
				$version =& self::$ext_versions[ $ext ];	// Shortcut.
			}

			if ( isset( $this->p->cf['plugin'][ $ext ] ) ) {
				$info = $this->p->cf['plugin'][ $ext ];
			}

			/**
			 * Plugin is active - get the plugin version from the config array.
			 */
			if ( isset( $info['version'] ) ) {

				$version = $info['version'];
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: version from plugin config' );
				}

			/**
			 * Plugin is not active (or not installed) - use get_plugins() to get the plugin version.
			 */
			} elseif ( isset( $info['base'] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: not active / installed' );
				}

				if ( method_exists( 'SucomUtil', 'get_wp_plugins' ) ) {	// Uses a common cache for all plugins.

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: getting plugins list from common class method' );
					}

					$wp_plugins = SucomUtil::get_wp_plugins();

				} else {

					if ( ! function_exists( 'get_plugins' ) ) {	// Load the library if necessary.

						$plugin_lib = trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';

						if ( file_exists( $plugin_lib ) ) {	// Just in case.
							require_once $plugin_lib;
						} else {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext . ' plugin: library file ' . $plugin_lib . ' is missing' );
							}
							$this->p->notice->err( sprintf( __( 'WordPress library file %s is missing and required.', 
								$this->text_domain ), '<code>' . $plugin_lib . '</code>' ) );
						}
					}

					if ( function_exists( 'get_plugins' ) ) {	// Just in case.

						static $wp_plugins = null;	// Get the plugins list from WordPress only once.

						if ( null === $wp_plugins ) {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext . ' plugin: getting plugins list from WordPress' );
							}
							$wp_plugins = get_plugins();	// Save to static cache.
						} else {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext . ' plugin: getting plugins list from static cache' );
							}
						}

					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: function get_plugins() is missing' );
						}

						$func_name = 'get_plugins()';
						$func_url  = __( 'https://developer.wordpress.org/reference/functions/get_plugins/', $this->text_domain );

						$this->p->notice->err( sprintf( __( 'The <a href="%1$s">WordPress %2$s function</a> is missing and required.',
							$this->text_domain ), $func_url, '<code>' . $func_name . '</code>' ) );

						$wp_plugins = array();
					}
				}

				/**
				 * The plugin is installed.
				 */
				if ( isset( $wp_plugins[$info['base']] ) ) {

					/**
					 * Use the version found in the plugins array.
					 */
					if ( isset( $wp_plugins[$info['base']]['Version'] ) ) {

						$version = $wp_plugins[$info['base']]['Version'];

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: installed version is ' . $version . ' according to WordPress' );
						}

					} else {

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: ' . $info['base'] . ' version key missing from plugins array' );
						}

						$this->p->notice->err( sprintf( __( 'The %1$s plugin (%2$s) version number is missing from the WordPress plugins array.',
							$this->text_domain ), $info['name'], $info['base'] ) );

						/**
						 * Save to cache and stop here.
						 */
						return $version = '0-no-version';
					}

				/**
				 * Plugin is not installed.
				 */
				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: ' . $info['base'] . ' plugin not installed' );
					}

					/**
					 * Save to cache and stop here.
					 */
					return $version = 'not-installed';
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
				return $version = false;
			}

			$filter_regex = $this->get_filter_regex( $ext );

			if ( ! preg_match( $filter_regex, $version ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: ' . $version . ' does not match version filter' );
				}

				/**
				 * Save to cache and stop here.
				 */
				return $version = '0.' . $version;

			} else {

				$auth_type = $this->get_auth_type( $ext );
				$auth_id = $this->get_auth_id( $ext );

				if ( $auth_type !== 'none' ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext . ' plugin: auth type is defined' );
					}

					if ( $this->check_pp( $ext, false, $this->p->avail['*']['p_dir'] ) ) {

						if ( empty( $auth_id ) ) {	// p_dir without an auth_id.

							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext . ' plugin: have p_dir but no auth_id' );
							}

							/**
							 * Save to cache and stop here.
							 */
							return $version = '0.' . $version;

						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: have p_dir with an auth_id' );
						}

					} elseif ( ! empty( $auth_id ) ) {	// Free with an auth_id.

						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext . ' plugin: free with an auth_id' );
						}

						/**
						 * Save to cache and stop here.
						 */
						return $version = '0.' . $version;
					}

				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext . ' plugin: no auth type' );
				}
			}

			return $version;
		}

		public function get_auth_type( $ext ) {
			if ( empty( $this->p->cf['plugin'][ $ext ]['update_auth'] ) ) {
				return 'none';
			} else {
				return $this->p->cf['plugin'][ $ext ]['update_auth'];
			}
		}

		public function get_auth_id( $ext ) {
			$auth_type = $this->get_auth_type( $ext );
			if ( $auth_type === 'none' ) {
				return '';
			}
			$opt_key = 'plugin_' . $ext . '_' . $auth_type;
			if ( empty( $this->p->options[$opt_key] ) ) {
				return '';	// Empty string.
			} else {
				return $this->p->options[$opt_key];
			}
		}

		public function get_filter_name( $ext ) {

			if ( ! empty( $this->p->options['update_filter_for_' . $ext] ) ) {

				$filter_name = $this->p->options['update_filter_for_' . $ext];

				if ( ! empty( $this->p->cf['um']['version_regex'][$filter_name] ) ) {	// Make sure the name is valid.
					return $filter_name;
				}
			}

			return 'stable';
		}

		/**
		 * Include extra checks to make sure we have fallback values.
		 */
		public function get_filter_regex( $ext ) {

			$filter_name = $this->get_filter_name( $ext );	// Returns a valid filter name or 'stable'.

			if ( ! empty( $this->p->cf['um']['version_regex'][$filter_name] ) ) {	// Just in case.
				return $this->p->cf['um']['version_regex'][$filter_name];
			}

			return '/^[0-9][0-9\.\-]+$/';	// Stable regex.
		}

		public static function is_enabled() {
			return empty( self::$upd_config ) ? false : true;
		}

		public static function is_configured( $ext = null ) {

			if ( empty( $ext ) ) {
				return count( self::$upd_config );
			} elseif ( isset( self::$upd_config[ $ext ] ) ) {
				return true;
			}

			return false;
		}

		public static function is_installed( $ext ) {

			if ( empty( $ext ) ) {

				return false;

			} elseif ( ! isset( self::$upd_config[ $ext ] ) ) {

				return false;

			} else {

				$info = self::$upd_config[ $ext ];

				if ( ! isset( $info['installed_version'] ) ) {	// Just in case.
					return false;
				} elseif ( strpos( $info['installed_version'], 'not-installed' ) !== false ) {	// Anywhere in string.
					return false;
				}
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
		private static function set_umsg( $ext, $mtype, $val ) {

			if ( empty( $val ) ) {

				delete_option( $ext . '_uapi' . self::$api_version . $mtype );

				self::$upd_config[ $ext ][ 'u' . $mtype ] = false;	// Just in case.

			} else {

				update_option( $ext . '_uapi' . self::$api_version . $mtype, base64_encode( $val ) );	// Save as string.

				self::$upd_config[ $ext ][ 'u' . $mtype ] = $val;
			}

			return self::$upd_config[ $ext ][ 'u' . $mtype ];
		}

		public static function get_umsg( $ext, $mtype = 'err', $def = false ) {

			if ( ! isset( self::$upd_config[ $ext ]['u' . $mtype] ) ) {

				$opt_name = $ext . '_uapi' . self::$api_version . $mtype;

				if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
					SucomUtil::protect_filter_value( 'pre_option_' . $opt_name );
				}

				$val = get_option( $opt_name, $def );

				if ( ! is_bool( $val ) ) {
					$val = base64_decode( $val );	// Value is saved as a string.
				}

				if ( empty( $val ) ) {
					self::$upd_config[ $ext ]['u' . $mtype] = false;
				} else {
					self::$upd_config[ $ext ]['u' . $mtype] = $val;
				}
			}

			return self::$upd_config[ $ext ]['u' . $mtype];
		}

		/**
		 * Returns null if $idx does not exist (since v1.10.0).
		 */
		public static function get_option( $ext, $idx = false ) {

			$not_found = false !== $idx ? null : false;	// Return null if $idx does not exist.

			if ( ! empty( self::$upd_config[ $ext ]['option_name'] ) ) {

				$option_data = self::get_option_data( $ext );

				if ( $idx !== false ) {
					if ( is_object( $option_data->update ) && isset( $option_data->update->$idx ) ) {
						return $option_data->update->$idx;
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

			if ( ! isset( self::$upd_config[ $ext ]['option_data'] ) ) {

				if ( ! empty( self::$upd_config[ $ext ]['option_name'] ) ) {

					$opt_name = self::$upd_config[ $ext ]['option_name'];

					if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
						SucomUtil::protect_filter_value( 'pre_option_' . $opt_name );
					}

					self::$upd_config[ $ext ]['option_data'] = get_option( $opt_name, $def );

				} else {
					self::$upd_config[ $ext ]['option_data'] = $def;
				}
			}

			return self::$upd_config[ $ext ]['option_data'];
		}

		private static function update_option_data( $ext, $option_data ) {

			self::$upd_config[ $ext ]['option_data'] = $option_data;

			if ( ! empty( self::$upd_config[ $ext ]['option_name'] ) ) {
				return update_option( self::$upd_config[ $ext ]['option_name'], $option_data );
			}

			return false;
		}

		private static function clear_option_data( $ext ) {

			unset( self::$upd_config[ $ext ]['option_data'] );

			if ( ! empty( self::$upd_config[ $ext ]['option_name'] ) ) {
				return delete_option( self::$upd_config[ $ext ]['option_name'] );
			}

			return false;
		}
	}
}

if ( ! class_exists( 'SucomUpdateUtilWP' ) ) {

	if ( class_exists( 'SucomUtilWP' ) ) {	// Just in case.
	
		class SucomUpdateUtilWP extends SucomUtilWP {
		}

	} else {

		class SucomUpdateUtilWP {

			/**
			 * Unfiltered version of home_url() from wordpress/wp-includes/link-template.php
			 * Last synchronized with WordPress v4.8.2 on 2017/10/22.
			 */
			public static function raw_home_url( $path = '', $scheme = null ) {
				return self::raw_get_home_url( null, $path, $scheme );
			}

			/**
			 * Unfiltered version of get_home_url() from wordpress/wp-includes/link-template.php
			 * Last synchronized with WordPress v4.8.2 on 2017/10/22.
			 */
			public static function raw_get_home_url( $blog_id = null, $path = '', $scheme = null ) {

				global $pagenow;

				if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
					SucomUtil::protect_filter_value( 'pre_option_home' );
				}

				if ( empty( $blog_id ) || ! is_multisite() ) {
					$url = get_option( 'home' );
				} else {
					switch_to_blog( $blog_id );
					$url = get_option( 'home' );
					restore_current_blog();
				}

				if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {
					if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $pagenow ) {
						$scheme = 'https';
					} else {
						$scheme = parse_url( $url, PHP_URL_SCHEME );
					}
				}

				$url = self::set_url_scheme( $url, $scheme );

				if ( $path && is_string( $path ) ) {
					$url .= '/' . ltrim( $path, '/' );
				}

				return $url;
			}

			/**
			 * Unfiltered version of set_url_scheme() from wordpress/wp-includes/link-template.php
			 * Last synchronized with WordPress v4.8.2 on 2017/10/22.
			 */
			private static function set_url_scheme( $url, $scheme = null ) {

				if ( ! $scheme ) {
					$scheme = is_ssl() ? 'https' : 'http';
				} elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
					$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
				} elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
					$scheme = is_ssl() ? 'https' : 'http';
				}

				$url = trim( $url );

				if ( substr( $url, 0, 2 ) === '//' ) {
					$url = 'http:' . $url;
				}

				if ( 'relative' === $scheme ) {
					$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );
					if ( $url !== '' && $url[0] === '/' ) {
						$url = '/' . ltrim( $url, "/ \t\n\r\0\x0B" );
					}
				} else {
					$url = preg_replace( '#^\w+://#', $scheme . '://', $url );
				}

				return $url;
			}
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

			$json_data = json_decode( $json_encoded );

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
				'homepage'       => 'url',	// Plugin homepage URL.
				'download_url'   => 'package',	// Update download URL.
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
