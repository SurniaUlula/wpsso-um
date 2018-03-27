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
	
		private $p;
		private $plugin_lca = '';
		private $plugin_slug = '';
		private $text_domain = '';
		private $cron_hook = '';
		private $sched_hours = 24;
		private $sched_name = 'every24hours';
		private static $api_version = 2;
		private static $upd_config = array();
		private static $ext_versions = array();

		public function __construct( &$plugin, $check_hours = 24, $text_domain = '' ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// begin timer
			}

			if ( isset( $this->p->lca ) ) {
				$this->plugin_lca = $this->p->lca;
			} elseif ( isset( $this->p->cf['lca'] ) ) {
				$this->plugin_lca = $this->p->cf['lca'];
			}

			if ( ! empty( $this->plugin_lca ) ) {
				$this->plugin_slug = $this->p->cf['plugin'][$this->plugin_lca]['slug'];	// example: wpsso
				$this->text_domain = $text_domain;					// example: wpsso-um
				$this->cron_hook = $this->plugin_lca . '_update_manager_check';		// example: wpsso_update_manager_check
				$this->sched_hours = $check_hours < 12 ? 12 : $check_hours;		// example: 12 (12 hours minimum)
				$this->sched_name = 'every' . $this->sched_hours . 'hours';		// example: every24hours
				$this->set_config();	// private method
				$this->install_hooks();	// private method
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// end timer
			}
		}

		/**
		 * Called by the WordPress cron.
		 */
		public function check_all_for_updates( $quiet = true, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$check_ext = null; // check all ext by default

			$this->check_ext_for_updates( $this->plugin_lca, $quiet, $read_cache ); // check the lca plugin first

			$check_ext = $this->get_config_keys( $check_ext, $this->plugin_lca, $read_cache ); // reset config and get ext array (exclude lca)

			$this->check_ext_for_updates( $check_ext, $quiet, $read_cache ); // check all remaining plugins
		}

		// deprecated on 2017/10/26
		public function check_for_updates( $check_ext = null, $show_notice = false, $read_cache = true ) {
			return $this->check_ext_for_updates( $check_ext, ( $show_notice ? false : true ), $read_cache );
		}

		public function check_ext_for_updates( $check_ext = null, $quiet = true, $read_cache = true ) {

			$ext_config = array();

			if ( empty( $check_ext ) ) {
				$ext_config = self::$upd_config;	// check all plugins defined
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'checking all known plugins for updates' );
				}
			} elseif ( is_array( $check_ext ) ) {
				foreach ( $check_ext as $ext ) {
					if ( isset( self::$upd_config[$ext] ) ) {
						$ext_config[$ext] = self::$upd_config[$ext];
					}
				}
			} elseif ( is_string( $check_ext ) ) {
				if ( isset( self::$upd_config[$check_ext] ) ) {
					$ext_config[$check_ext] = self::$upd_config[$check_ext];
				}
			}

			if ( empty( $ext_config ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: no plugins to check for updates' );
				}
				return;
			}

			foreach ( $ext_config as $ext => $info ) {

				if ( ! self::is_installed( $ext ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: not installed' );
					}
					continue;
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: checking for update' );
				}

				if ( $read_cache ) {
					$update_data = self::get_option_data( $ext );
				} else {
					$update_data = false;
				}

				if ( empty( $update_data ) ) {
					$update_data = new StdClass;
					$update_data->lastCheck = 0;
					$update_data->checkedVersion = 0;
					$update_data->update = null;
				}

				$update_data->lastCheck = time();
				$update_data->checkedVersion = $this->get_ext_version( $ext );
				$update_data->update = $this->get_update_data( $ext, $read_cache );

				if ( self::update_option_data( $ext, $update_data ) ) {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update information saved in '.$info['option_name'] );
					}

					if ( ! $quiet || $this->p->debug->enabled ) {
						$dismiss_key = __FUNCTION__.'_'.$ext.'_'.$info['option_name'];
						$this->p->notice->inf( sprintf( __( 'Update information for %s has been retrieved and saved.',
							$this->text_domain ), $info['name'] ), true, $dismiss_key, true );
					}

				} else {

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: failed saving update information in '.$info['option_name'] );
					}

					if ( ! $quiet || $this->p->debug->enabled ) {
						$this->p->notice->err( sprintf( __( 'Failed saving retrieved update information for %s.',
							$this->text_domain ), $info['name'] ) );
					}
				}
			}
		}
	
		/**
		 * Returns an array of configured plugin lowercase acronyms.
		 */
		public function get_config_keys( $include = null, $exclude = null, $read_cache = true ) {

			$quiet = true;
			$keys = array();
			$this->set_config( $quiet, $read_cache );	// private method

			// optionally include only some plugin keys
			if ( ! empty( $include ) ) {
				if ( ! is_array( $include ) ) {
					$include = array( $include );
				}
				foreach ( $include as $ext ) {
					if ( isset( self::$upd_config[$ext] ) ) {
						$keys[] = $ext;
					}
				}
			} elseif ( is_array( self::$upd_config ) ) {
				$keys = array_keys( self::$upd_config );	// include all keys
			}

			// optionally exclude some plugin keys
			if ( ! empty( $exclude ) ) {
				if ( ! is_array( $exclude ) ) {
					$exclude = array( $exclude );
				}
				$old_keys = $keys;
				$keys = array();	// start a new array
				foreach ( $old_keys as $old_key ) {
					foreach ( $exclude as $ext ) {
						if ( $old_key === $ext ) {
							continue 2;	// skip this key
						}
					}
					$keys[] = $old_key;
				}
				unset( $old_keys );	// cleanup
			}

			return $keys;
		}

		/**
		 * $quiet is false by default, to show a warning if one or more development version filters are selected.
		 */
		private function set_config( $quiet = false, $read_cache = true ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			$has_pdir = $this->p->avail['*']['p_dir'];
			$has_aop = $this->p->check->aop( $this->plugin_lca, true, $has_pdir, $read_cache );
			$has_dev = false;

			self::$upd_config = array();	// set / reset the config array

			foreach ( $this->p->cf['plugin'] as $ext => $info ) {

				if ( ! $has_aop && $ext !== $this->plugin_lca && $ext !== $this->plugin_lca.'um' ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: skipped - aop required' );
					}
					continue;
				}

				$auth_type = $this->get_auth_type( $ext );
				$auth_id = $this->get_auth_id( $ext );

				if ( $auth_type !== 'none' && empty( $auth_id ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: skipped - auth type without id' );
					}
					continue;
				} elseif ( empty( $info['slug'] ) || empty( $info['base'] ) || empty( $info['url']['update'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: skipped - incomplete config' );
					}
					continue;
				}

				$ext_version = $this->get_ext_version( $ext );

				if ( false === $ext_version ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: skipped - version is false' );
					}
					continue;
				}

				$filter_name = $this->get_filter_name( $ext );

				if ( $filter_name !== 'stable' ) {
					$has_dev = true;
				}

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: installed version is '.$ext_version.' with '.$filter_name.' filter' );
				}

				// add the auth type and id to the update url
				if ( $auth_type !== 'none' ) {
					$auth_url = add_query_arg( array( $auth_type => $auth_id ), $info['url']['update'] );
				} else {
					$auth_url = $info['url']['update'];
				}

				$auth_url = add_query_arg( array( 
					'api_version' => self::$api_version,
					'installed_version' => $ext_version,
					'version_filter' => $filter_name,
					'sched_hours' => $this->sched_hours,
					'locale' => is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale(),
				), $auth_url );

				self::$upd_config[$ext] = array(
					'name' => $info['name'],
					'short' => $info['short'],
					'slug' => $info['slug'],				// wpsso
					'base' => $info['base'],				// wpsso/wpsso.php
					'api_version' => self::$api_version,
					'installed_version' => $ext_version,
					'version_filter' => $filter_name,
					'json_url' => $auth_url,
					'support_url' => isset( $info['url']['support'] ) ?
						$info['url']['support'] : '',
					'data_expire' => 86100,					// plugin data expiration (almost 24 hours)
					'option_name' => 'external_update-'.$info['slug'],	// external_update-wpsso
				);

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: configured for update (auth_type is '.$auth_type.')' );
				}
			}

			if ( ! $quiet || $this->p->debug->enabled ) {
				if ( $has_dev && $this->p->notice->is_admin_pre_notices() ) {
					$dismiss_key = 'non-stable-update-version-filters-selected';
					$this->p->notice->warn( sprintf( __( 'Please note that one or more non-stable / development %s have been selected.',
						$this->text_domain ), $this->p->util->get_admin_url( 'um-general', _x( 'Update Version Filters',
							'metabox title', $this->text_domain ) ) ), true, $dismiss_key, MONTH_IN_SECONDS * 3, true );	// $silent = true
				}
			}
		}

		private function install_hooks() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( empty( self::$upd_config ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'skipping all update checks - update config array is empty' );
				}
				return;
			}

			add_filter( 'plugins_api_result', array( &$this, 'external_plugin_data' ), PHP_INT_MAX, 3 );
			add_filter( 'transient_update_plugins', array( &$this, 'maybe_add_plugin_update' ), 1000, 1 );
			add_filter( 'site_transient_update_plugins', array( &$this, 'maybe_add_plugin_update' ), 1000, 1 );
			add_filter( 'pre_site_transient_update_plugins', array( &$this, 'reenable_plugin_update' ), 1000, 1 );
			add_filter( 'http_request_host_is_external', array( &$this, 'allow_update_package' ), 2000, 3 );
			add_filter( 'http_headers_useragent', array( &$this, 'check_wpua_value' ), PHP_INT_MAX, 1 );

			// remove the old plugin update hook
			if ( wp_get_schedule( 'plugin_update-' . $this->plugin_slug ) ) {
				wp_clear_scheduled_hook( 'plugin_update-' . $this->plugin_slug );
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'adding '.$this->cron_hook.' schedule for '.$this->sched_name );
			}

			add_action( $this->cron_hook, array( &$this, 'check_all_for_updates' ) );

			add_filter( 'cron_schedules', array( &$this, 'add_custom_schedule' ) );

			$schedule = wp_get_schedule( $this->cron_hook );

			$is_scheduled = false;

			if ( ! empty( $schedule ) ) {
				if ( $schedule !== $this->sched_name ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( 'changing '.$this->cron_hook.' schedule from '.$schedule.' to '.$this->sched_name );
					}
					wp_clear_scheduled_hook( $this->cron_hook );
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $this->cron_hook.' already registered for schedule '.$this->sched_name );
					}
					$is_scheduled = true;
				}
			}

			if ( ! $is_scheduled && ! defined( 'WP_INSTALLING' ) && ! wp_next_scheduled( $this->cron_hook ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'registering '.$this->cron_hook.' for schedule '.$this->sched_name );
				}
				wp_schedule_event( time(), $this->sched_name, $this->cron_hook );
			}
		}

		public function allow_update_package( $is_allowed, $ip, $url ) {
			if ( ! $is_allowed ) {	// don't bother if already allowed
				foreach ( self::$upd_config as $ext => $info ) {
					if ( ! empty( $info['plugin_update']->package ) && $info['plugin_update']->package === $url ) {
						return true;
					}
				}
			}
			return $is_allowed;
		}

		public function check_wpua_value( $wpua ) {
			global $wp_version;
			$correct = 'WordPress/'.$wp_version.'; '.SucomUpdateUtilWP::raw_home_url();
			if ( $correct !== $wpua ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'incorrect wpua for '.$wpua );
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

			// this filter only provides plugin data
			if ( $action !== 'plugin_information' ) {
				return $result;
			// make sure we have a slug in the request
			} elseif ( empty( $args->slug ) ) {
				return $result;
			// flag for the update manager filter
			} elseif ( ! empty( $args->unfiltered ) ) {
				return $result;
			// make sure the plugin slug is one of ours
			} elseif ( empty( $this->p->cf['*']['slug'][$args->slug] ) ) {
				return $result;
			}

			// get the plugin acronym for the config
			$ext = $this->p->cf['*']['slug'][$args->slug];

			// make sure we have a config for that slug
			if ( empty( self::$upd_config[$ext]['slug'] ) ) {
				return $result;
			}

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'getting plugin data for '.$ext );
			}

			// get plugin data from the json api
			$plugin_data = $this->get_plugin_data( $ext, true );	// $read_cache = true

			// make sure we have something to return
			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {
				return $result;
			}

			return $plugin_data->json_to_wp();
		}

		public function maybe_add_plugin_update( $updates = false ) {

			foreach ( self::$upd_config as $ext => $info ) {

				if ( ! self::is_installed( $ext ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: not installed' );
					}
					continue;
				}

				// remove existing update information to make sure it is correct (not from wordpress.org)
				if ( isset( $updates->response[$info['base']] ) ) {
					unset( $updates->response[$info['base']] );	// wpsso/wpsso.php
				}

				// check the local static cache first
				if ( isset( self::$upd_config[$ext]['plugin_update'] ) ) {
					// only provide update information when an update is required
					if ( self::$upd_config[$ext]['plugin_update'] !== false ) {	// false when installed version is current
						$updates->response[$info['base']] = self::$upd_config[$ext]['plugin_update'];
					}
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: using saved update information' );
						$this->p->debug->log( $ext.' plugin: calling method/function', 5 );
					}
					continue;	// get the next plugin from the config
				}

				$update_data = self::get_option_data( $ext );

				if ( empty( $update_data ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update option is empty' );
					}
				} elseif ( empty( $update_data->update ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: no update information' );
					}
				} elseif ( ! is_object( $update_data->update ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update property is not an object' );
					}
				} elseif ( ( $ext_version = $this->get_ext_version( $ext ) ) &&
					version_compare( $update_data->update->version, $ext_version, '>' ) ) {

					// save to the local static cache
					self::$upd_config[$ext]['plugin_update'] = $updates->response[$info['base']] = $update_data->update->json_to_wp();

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: installed version ('.$ext_version.') '.
							'different than update version ('.$update_data->update->version.')' );
						$this->p->debug->log_arr( 'option_data', $updates->response[$info['base']], 5 );
					}
				} else {
					self::$upd_config[$ext]['plugin_update'] = false;	// false when installed is current

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: installed version is current (or newer) than update version' );
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

			// get plugin data from the json api
			$plugin_data = $this->get_plugin_data( $ext, $read_cache );

			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: returned update data is invalid' );
				}
				return null;
			}

			return SucomPluginUpdate::update_from_data( $plugin_data );
		}
	
		public function get_plugin_data( $ext, $read_cache = true ) {

			if ( empty( self::$upd_config[$ext]['slug'] ) ) { // Make sure we have a config for that slug.
				return null;
			}

			if ( empty( self::$upd_config[$ext]['json_url'] ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: exiting early - update json_url is empty' );
				}
				return null;
			}

			global $wp_version;

			$has_pdir = $this->p->avail['*']['p_dir'];
			$home_url = SucomUpdateUtilWP::raw_home_url();
			$json_url = self::$upd_config[$ext]['json_url'];
			$ext_version = $this->get_ext_version( $ext );

			$cache_md5_pre = $this->plugin_lca.'_';
			$cache_salt = 'SucomUpdate::plugin_data(json_url:'.$json_url.'_home_url:'.$home_url.')';
			$cache_id = $cache_md5_pre.md5( $cache_salt );

			if ( $read_cache ) {
				if ( isset( self::$upd_config[$ext]['plugin_data']->plugin ) ) {
					$plugin_data = self::$upd_config[$ext]['plugin_data'];
				} else {
					$plugin_data = self::$upd_config[$ext]['plugin_data'] = get_transient( $cache_id );
				}
				if ( $plugin_data !== false ) { // False if transient is expired or not found.
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: returning plugin data from cache' );
					}
					return $plugin_data;
				}
			} else {
				delete_transient( $cache_id );
			}

			$plugin_data = null;

			/**
			 * Check the local resolver and DNS IPv4 values for inconsistencies.
			 */
			$json_host = preg_replace( '/^.*:\/\/([^\/]+)\/.*$/', '$1', $json_url );

			static $host_cache = array(); // Local cache to lookup the host ip only once.

			if ( ! isset( $host_cache[$json_host]['ip'] ) ) {
				$host_cache[$json_host]['ip'] = gethostbyname( $json_host ); // Returns an IPv4 address, or the hostname on failure.
			}

			if ( ! isset( $host_cache[$json_host]['a'] ) ) {
				$dns_rec = dns_get_record( $json_host . '.', DNS_A ); // Returns an array of associative arrays.
				$host_cache[$json_host]['a'] = empty( $dns_rec[0]['ip'] ) ? false : $dns_rec[0]['ip'];
			}

			if ( $host_cache[$json_host]['ip'] !== $host_cache[$json_host]['a'] ) {

				// translators: %1$s is the plugin name, %2$s is an IPv4 address, and %3$s is an IPv4 address
				$error_msg = sprintf( __( 'An inconsistency was found in the %1$s update server address &mdash; the IPv4 address (%2$s) from the local host does not match the DNS IPv4 address ($3%s).', $this->text_domain ), self::$upd_config[$ext]['name'] );
				
				$error_msg .= ' '.sprintf( __( 'Update checks for %1$s are disabled while this inconsistency persists.', $this->text_domain ), self::$upd_config[$ext]['short'] );

				if ( ! empty( self::$upd_config[$ext]['support_url'] ) ) {
					$error_msg .= ' '.sprintf( __( 'You may <a href="%1$s">open a new support ticket</a> if you believe this error message is incorrect or inaccurate.', $this->text_domain ), self::$upd_config[$ext]['support_url'] );
				}

				self::$upd_config[$ext]['uerr'] = self::set_umsg( $ext, 'err', $error_msg );
				self::$upd_config[$ext]['plugin_data'] = $plugin_data;

				set_transient( $cache_id, new stdClass, self::$upd_config[$ext]['data_expire'] );

				return $plugin_data;
			}

			/**
			 * Set wp_remote_get() options.
			 */
			$ua_wpid = 'WordPress/'.$wp_version.' ('.self::$upd_config[$ext]['slug'].'/'.$ext_version.'/'.
				( $this->p->check->aop( $ext, true, $has_pdir ) ? 'L' :
				( $this->p->check->aop( $ext, false ) ? 'U' : 'G' ) ).'); '.$home_url;

			$ssl_verify = apply_filters( $this->plugin_lca.'_um_sslverify', true );

			$get_options = array(
				'timeout'     => 15, // default timeout is 5 seconds
				'redirection' => 5,  // default redirection is 5
				'sslverify'   => $ssl_verify,
				'user-agent'  => $ua_wpid,
				'headers'     => array(
					'Accept' => 'application/json',
					'X-WordPress-Id' => $ua_wpid
				)
			);

			/**
			 * Call wp_remote_get().
			 */
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( $ext.' plugin: sslverify is '.( $ssl_verify ? 'true' : 'false' ) );
				$this->p->debug->log( $ext.' plugin: calling wp_remote_get() for '.$json_url );
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
					$this->p->debug->log( $ext.' plugin: wp error code '.$request->get_error_code().' - '.$request->get_error_message() );
					$this->p->debug->log( $ext.' plugin: (retry) calling wp_remote_get() for '.$json_url );
				}
				if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
					SucomUtil::protect_filter_value( 'http_headers_useragent' );
				}
				sleep( 1 ); // Pause 1 second before retrying.
				$request = wp_remote_get( $json_url, $get_options );
			}

			if ( is_wp_error( $request ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: wp error code '.$request->get_error_code().' - '.$request->get_error_message() );
				}
				$this->p->notice->err( sprintf( __( 'Update error from the WordPress wp_remote_get() function: %s',
					$this->text_domain ), $request->get_error_message() ) );

			} elseif ( isset( $request['response']['code'] ) && (int) $request['response']['code'] === 200 && ! empty( $request['body'] ) ) {

				$payload = json_decode( $request['body'], true, 32 ); // Create an associative array.

				/**
				 * Add or remove existing response messages.
				 */
				foreach ( array( 'err', 'inf' ) as $msg ) {
					self::$upd_config[$ext]['u'.$msg] = self::set_umsg( $ext, $msg,
						( empty( $payload['api_response'][$msg] ) ?
							false : $payload['api_response'][$msg] ) );
				}

				if ( empty( $request['headers']['x-smp-error'] ) ) {
					self::$upd_config[$ext]['uerr'] = false;
					$plugin_data = SucomPluginData::data_from_json( $request['body'] ); // Returns null on error.
					if ( empty( $plugin_data->plugin ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: returned plugin data is incomplete' );
						}
						$plugin_data = null;
					} elseif ( $plugin_data->plugin !== self::$upd_config[$ext]['base'] ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: property '.$plugin_data->plugin.
								' does not match '.self::$upd_config[$ext]['base'] );
						}
						$plugin_data = null;
					}
				}
			}

			self::$upd_config[$ext]['utime'] = self::set_umsg( $ext, 'time', time() );
			self::$upd_config[$ext]['plugin_data'] = $plugin_data; // Save to local static cache.

			if ( null === $plugin_data ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: saving empty stdClass to transient '.$cache_id );
				}
				set_transient( $cache_id, new stdClass, self::$upd_config[$ext]['data_expire'] );
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: saving plugin data to transient '.$cache_id );
				}
				set_transient( $cache_id, $plugin_data, self::$upd_config[$ext]['data_expire'] );
			}

			return $plugin_data;
		}
	
		public function get_ext_version( $ext ) {

			$info = array();

			if ( isset( self::$ext_versions[$ext] ) ) {
				return self::$ext_versions[$ext];	// return from cache
			} else {
				self::$ext_versions[$ext] = 0;
				$version =& self::$ext_versions[$ext];	// shortcut
			}

			if ( isset( $this->p->cf['plugin'][$ext] ) ) {
				$info = $this->p->cf['plugin'][$ext];
			}

			// plugin is active
			// get the plugin version from the config array
			if ( isset( $info['version'] ) ) {

				$version = $info['version'];
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: version from plugin config' );
				}

			// plugin is not active (or not installed)
			// use the get_plugins() function to get the plugin version
			} elseif ( isset( $info['base'] ) ) {

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: not active / installed' );
				}

				if ( method_exists( 'SucomUtil', 'get_wp_plugins' ) ) {	// uses a common cache for all plugins
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: getting plugins list from common class method' );
					}
					$wp_plugins = SucomUtil::get_wp_plugins();
				} else {
					if ( ! function_exists( 'get_plugins' ) ) {	// load the library if necessary
						$plugin_lib = trailingslashit( ABSPATH ).'wp-admin/includes/plugin.php';
						if ( file_exists( $plugin_lib ) ) {	// just in case
							require_once $plugin_lib;
						} else {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext.' plugin: library file '.$plugin_lib.' is missing' );
							}
							$this->p->notice->err( sprintf( __( 'WordPress library file %s is missing and required.', 
								$this->text_domain ), '<code>'.$plugin_lib.'</code>' ) );
						}
					}
					if ( function_exists( 'get_plugins' ) ) {	// just in case
						static $wp_plugins = null;	// get the plugins list from WordPress only once
						if ( null === $wp_plugins ) {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext.' plugin: getting plugins list from WordPress' );
							}
							$wp_plugins = get_plugins();	// save to static cache
						} else {
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext.' plugin: getting plugins list from static cache' );
							}
						}
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: function get_plugins() is missing' );
						}

						$func_name = 'get_plugins()';
						$func_url  = __( 'https://developer.wordpress.org/reference/functions/get_plugins/', $this->text_domain );

						$this->p->notice->err( sprintf( __( 'The <a href="%1$s">WordPress %2$s function</a> is missing and required.',
							$this->text_domain ), $func_url, '<code>'.$func_name.'</code>' ) );

						$wp_plugins = array();
					}
				}

				// the plugin is installed
				if ( isset( $wp_plugins[$info['base']] ) ) {

					// use the version found in the plugins array
					if ( isset( $wp_plugins[$info['base']]['Version'] ) ) {
						$version = $wp_plugins[$info['base']]['Version'];
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: installed version is '.$version.' according to WordPress' );
						}
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: '.$info['base'].' version key missing from plugins array' );
						}
						$this->p->notice->err( sprintf( __( 'The %1$s plugin (%2$s) version number is missing from the WordPress plugins array.',
							$this->text_domain ), $info['name'], $info['base'] ) );

						// save to cache and stop here
						return $version = '0-no-version';
					}

				// plugin is not installed
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: '.$info['base'].' plugin not installed' );
					}
					// save to cache and stop here
					return $version = 'not-installed';
				}

			// plugin missing version and/or slug
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: config is missing version and plugin base keys' );
				}
				// save to cache and stop here
				return $version = false;
			}

			$filter_regex = $this->get_filter_regex( $ext );

			if ( ! preg_match( $filter_regex, $version ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: '.$version.' does not match filter' );
				}
				// save to cache and stop here
				return $version = '0.'.$version;
			} else {
				$auth_type = $this->get_auth_type( $ext );
				$auth_id = $this->get_auth_id( $ext );

				if ( $auth_type !== 'none' ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: auth type is defined' );
					}
					if ( $this->p->check->aop( $ext, false, $this->p->avail['*']['p_dir'] ) ) {
						if ( empty( $auth_id ) ) {	// p_dir without an auth_id
							if ( $this->p->debug->enabled ) {
								$this->p->debug->log( $ext.' plugin: have p_dir but no auth_id' );
							}
							// save to cache and stop here
							return $version = '0.'.$version;
						} elseif ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: have p_dir with an auth_id' );
						}
					} elseif ( ! empty( $auth_id ) ) {	// free with an auth_id
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: free with an auth_id' );
						}
						// save to cache and stop here
						return $version = '0.'.$version;
					}
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: no auth type' );
				}
			}

			return $version;
		}

		public function get_auth_type( $ext ) {
			if ( empty( $this->p->cf['plugin'][$ext]['update_auth'] ) ) {
				return 'none';
			} else {
				return $this->p->cf['plugin'][$ext]['update_auth'];
			}
		}

		public function get_auth_id( $ext ) {
			$auth_type = $this->get_auth_type( $ext );
			if ( $auth_type === 'none' ) {
				return '';
			}
			$opt_key = 'plugin_'.$ext.'_'.$auth_type;
			if ( empty( $this->p->options[$opt_key] ) ) {
				return '';	// empty string
			} else {
				return $this->p->options[$opt_key];
			}
		}

		public function get_filter_name( $ext ) {
			if ( ! empty( $this->p->options['update_filter_for_'.$ext] ) ) {
				$filter_name = $this->p->options['update_filter_for_'.$ext];
				if ( ! empty( $this->p->cf['um']['version_regex'][$filter_name] ) ) {	// make sure the name is valid
					return $filter_name;
				}
			}
			return 'stable';
		}

		// 'version_regex' is available in the config array since 3.40.12
		// include extra checks to make sure we have fallback values
		public function get_filter_regex( $ext ) {
			$filter_name = $this->get_filter_name( $ext );	// returns a valid filter name or 'stable'
			if ( ! empty( $this->p->cf['um']['version_regex'][$filter_name] ) ) {	// just in case
				return $this->p->cf['um']['version_regex'][$filter_name];
			}
			return '/^[0-9][0-9\.\+\-]+$/';	// stable regex
		}

		public static function is_enabled() {
			return empty( self::$upd_config ) ? false : true;
		}

		public static function is_configured( $ext = null ) {
			if ( empty( $ext ) ) {
				return count( self::$upd_config );
			} elseif ( isset( self::$upd_config[$ext] ) ) {
				return true;
			}
			return false;
		}

		public static function is_installed( $ext ) {
			if ( empty( $ext ) ) {
				return false;
			} elseif ( ! isset( self::$upd_config[$ext] ) ) {
				return false;
			} else {
				$info = self::$upd_config[$ext];
				if ( ! isset( $info['installed_version'] ) ) {	// just in case
					return false;
				} elseif ( strpos( $info['installed_version'], 'not-installed' ) !== false ) {
					return false;
				}
			}
			return true;
		}

		// called by delete_options() in the register class
		public static function get_api_version() {
			return self::$api_version;
		}

		// called by get_plugin_data() when the transient / object cache is empty and/or not used
		private static function set_umsg( $ext, $msg, $val ) {
			if ( empty( $val ) ) {
				delete_option( $ext.'_uapi'.self::$api_version.$msg );
				self::$upd_config[$ext]['u'.$msg] = false;	// just in case
			} else {
				update_option( $ext.'_uapi'.self::$api_version.$msg, base64_encode( $val ) );	// save as string
				self::$upd_config[$ext]['u'.$msg] = $val;
			}
			return self::$upd_config[$ext]['u'.$msg];
		}

		public static function get_umsg( $ext, $msg = 'err', $def = false ) {
			if ( ! isset( self::$upd_config[$ext]['u'.$msg] ) ) {
				$opt_name = $ext.'_uapi'.self::$api_version.$msg;
				if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
					SucomUtil::protect_filter_value( 'pre_option_'.$opt_name );
				}
				$val = get_option( $opt_name, $def );
				if ( ! is_bool( $val ) ) {
					$val = base64_decode( $val );	// value is saved as a string
				}
				if ( empty( $val ) ) {
					self::$upd_config[$ext]['u'.$msg] = false;
				} else {
					self::$upd_config[$ext]['u'.$msg] = $val;
				}
			}
			return self::$upd_config[$ext]['u'.$msg];
		}

		public static function get_option( $ext, $idx = false ) {
			if ( ! empty( self::$upd_config[$ext]['option_name'] ) ) {
				$option_data = self::get_option_data( $ext );
				if ( $idx !== false ) {
					if ( is_object( $option_data->update ) && isset( $option_data->update->$idx ) ) {
						return $option_data->update->$idx;
					}
				} else {
					return $option_data;
				}
			}
			return false;
		}

		private static function get_option_data( $ext, $def = false ) {
			if ( ! isset( self::$upd_config[$ext]['option_data'] ) ) {
				if ( ! empty( self::$upd_config[$ext]['option_name'] ) ) {
					$opt_name = self::$upd_config[$ext]['option_name'];
					if ( method_exists( 'SucomUtil', 'protect_filter_value' ) ) {
						SucomUtil::protect_filter_value( 'pre_option_'.$opt_name );
					}
					self::$upd_config[$ext]['option_data'] = get_option( $opt_name, $def );
				} else {
					self::$upd_config[$ext]['option_data'] = $def;
				}
			}
			return self::$upd_config[$ext]['option_data'];
		}

		private static function update_option_data( $ext, $option_data ) {
			self::$upd_config[$ext]['option_data'] = $option_data;
			if ( ! empty( self::$upd_config[$ext]['option_name'] ) ) {
				return update_option( self::$upd_config[$ext]['option_name'], $option_data );
			}
			return false;
		}

		private static function clear_option_data( $ext ) {
			unset( self::$upd_config[$ext]['option_data'] );
			if ( ! empty( self::$upd_config[$ext]['option_name'] ) ) {
				return delete_option( self::$upd_config[$ext]['option_name'] );
			}
			return false;
		}
	}
}

if ( ! class_exists( 'SucomUpdateUtilWP' ) ) {

	if ( class_exists( 'SucomUtilWP' ) ) {	// just in case
	
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
					$url .= '/'.ltrim( $path, '/' );
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
						$url = '/'.ltrim( $url, "/ \t\n\r\0\x0B" );
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
		public $homepage;
		public $download_url;
		public $upgrade_notice;
		public $icons;
		public $exp_date;
		public $qty_used;

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
				'id' => 'id',
				'slug' => 'slug',
				'plugin' => 'plugin',
				'version' => 'new_version',
				'tested' => 'tested',
				'homepage' => 'url',			// plugin homepage url
				'download_url' => 'package',		// update download url
				'upgrade_notice' => 'upgrade_notice',
				'icons' => 'icons',
				'exp_date' => 'exp_date',
				'qty_used' => 'qty_used',
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

