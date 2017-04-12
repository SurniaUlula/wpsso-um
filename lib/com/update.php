<?php
/* 
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'SucomUpdate' ) ) {

	class SucomUpdate {
	
		private $p;
		private $cron_hook;
		private $sched_hours;
		private $sched_name;
		private $text_domain = 'sucom';
		private static $api_version = 2;
		private static $config = array();

		public function __construct( &$plugin, &$extensions, $check_hours = 24, $text_domain = 'sucom' ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// begin timer
			}

			$slug = $extensions[$this->p->cf['lca']]['slug'];		// example: wpsso
			$this->cron_hook = 'plugin_update-'.$slug;			// example: plugin_update-wpsso
			$this->sched_hours = $check_hours >= 24 ? $check_hours : 24;	// example: 24 (minimum)
			$this->sched_name = 'every'.$this->sched_hours.'hours';		// example: every24hours
			$this->text_domain = $text_domain;				// example: wpsso-um

			$this->set_config( $extensions );
			$this->install_hooks();

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark( 'update manager setup' );	// end timer
			}
		}

		// called by delete_options() in the register class
		public static function get_api_version() {
			return self::$api_version;
		}

		// called by get_plugin_data() when the transient / object cache is empty and/or not used
		private static function set_umsg( $ext, $msg, $val ) {
			if ( empty( $val ) ) {
				delete_option( $ext.'_uapi'.self::$api_version.$msg );
				self::$config[$ext]['u'.$msg] = false;	// just in case
			} else {
				update_option( $ext.'_uapi'.self::$api_version.$msg, base64_encode( $val ) );	// save as string
				self::$config[$ext]['u'.$msg] = $val;
			}
			return self::$config[$ext]['u'.$msg];
		}

		public static function get_umsg( $ext, $msg = 'err', $def = false ) {
			if ( ! isset( self::$config[$ext]['u'.$msg] ) ) {
				$val = get_option( $ext.'_uapi'.self::$api_version.$msg, $def );
				if ( ! is_bool( $val ) ) {
					$val = base64_decode( $val );	// value is saved as a string
				}
				if ( empty( $val ) ) {
					self::$config[$ext]['u'.$msg] = false;
				} else {
					self::$config[$ext]['u'.$msg] = $val;
				}
			}
			return self::$config[$ext]['u'.$msg];
		}

		public static function get_option( $ext, $idx = false ) {
			if ( ! empty( self::$config[$ext]['opt_name'] ) ) {
				$opt_data = self::get_option_data( $ext );
				if ( $idx !== false ) {
					if ( is_object( $opt_data->update ) &&
						isset( $opt_data->update->$idx ) ) {
						return $opt_data->update->$idx;
					}
				} else {
					return $opt_data;
				}
			}
			return false;
		}

		private static function get_option_data( $ext, $def = false ) {
			if ( ! isset( self::$config[$ext]['opt_data'] ) ) {
				if ( ! empty( self::$config[$ext]['opt_name'] ) ) {
					self::$config[$ext]['opt_data'] = get_option( self::$config[$ext]['opt_name'], $def );
				} else {
					self::$config[$ext]['opt_data'] = $def;
				}
			}
			return self::$config[$ext]['opt_data'];
		}

		private static function update_option_data( $ext, $opt_data ) {
			self::$config[$ext]['opt_data'] = $opt_data;
			if ( ! empty( self::$config[$ext]['opt_name'] ) ) {
				return update_option( self::$config[$ext]['opt_name'], $opt_data );
			}
			return false;
		}

		public function set_config( &$extensions ) {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			foreach ( $extensions as $ext => $info ) {

				$auth_type = empty( $info['update_auth'] ) ? 'none' : $info['update_auth'];
				$auth_key = 'plugin_'.$ext.'_'.$auth_type;
				$auth_id = empty( $this->p->options[$auth_key] ) ? '' : $this->p->options[$auth_key];

				if ( $auth_type !== 'none' && empty( $auth_id ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: extension skipped - empty '.$auth_key.' option value' );
					}
					continue;
				} elseif ( empty( $info['slug'] ) || empty( $info['base'] ) || empty( $info['url']['update'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: extension skipped - incomplete config array' );
					}
					continue;
				}

				$auth_url = apply_filters( 'sucom_update_url', $info['url']['update'], $info['slug'] );

				if ( $auth_type !== 'none' ) {
					$auth_url = add_query_arg( array( $auth_type => $auth_id ), $auth_url );
				}

				$installed_version = $this->get_installed_version( $ext );

				if ( strpos( $installed_version, '-no-plugin' ) !== false ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: extension skipped - plugin not installed' );
					}
					continue;
				}

				$auth_url = add_query_arg( array( 
					'api_version' => self::$api_version,
					'version_filter' => empty( $this->p->options['update_filter_for_'.$ext] ) ?	// just in case
						'stable' : $this->p->options['update_filter_for_'.$ext],
					'installed_version' => $installed_version,
				), $auth_url );

				self::$config[$ext] = array(
					'name' => $info['name'],
					'slug' => $info['slug'],			// wpsso
					'base' => $info['base'],			// wpsso/wpsso.php
					'opt_name' => 'external_update-'.$info['slug'],	// external_update-wpsso
					'json_url' => $auth_url,
					'expire' => 86100,				// almost 24 hours
				);

				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: extension defined for update (auth_type is '.$auth_type.')' );
				}
			}
		}

		public static function is_enabled() {
			return empty( self::$config ) ? false : true;
		}

		public static function is_configured( $ext = null ) {
			if ( empty( $ext ) ) {
				return count( self::$config );
			} elseif ( isset( self::$config[$ext] ) ) {
				return true;
			} else {
				return false;
			}
		}

		public function install_hooks() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( empty( self::$config ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'skipping all update checks - update config array is empty' );
				}
				return;
			}

			add_filter( 'plugins_api_result', array( &$this, 'external_plugin_data' ), PHP_INT_MAX, 3 );
			add_filter( 'transient_update_plugins', array( &$this, 'maybe_add_plugin_update' ), 1000, 1 );
			add_filter( 'site_transient_update_plugins', array( &$this, 'maybe_add_plugin_update' ), 1000, 1 );
			add_filter( 'pre_site_transient_update_plugins', array( &$this, 'reenable_plugin_update' ), 1000, 1 );
			add_filter( 'http_headers_useragent', array( &$this, 'check_wpua_value' ), PHP_INT_MAX, 1 );

			if ( $this->sched_hours > 0 && ! empty( $this->sched_name ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'adding cron actions and '.
						$this->cron_hook.' schedule for '.$this->sched_name );
				}

				add_action( $this->cron_hook, array( &$this, 'check_for_updates' ) );
				add_filter( 'cron_schedules', array( &$this, 'add_custom_schedule' ) );

				$schedule = wp_get_schedule( $this->cron_hook );
				$is_scheduled = false;

				if ( ! empty( $schedule ) ) {
					if ( $schedule !== $this->sched_name ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( 'changing '.$this->cron_hook.
								' schedule from '.$schedule.' to '.$this->sched_name );
						}
						wp_clear_scheduled_hook( $this->cron_hook );
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $this->cron_hook.
								' already registered for schedule '.$this->sched_name );
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
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'clearing the '.$this->cron_hook.' schedule' );
				}
				wp_clear_scheduled_hook( $this->cron_hook );
			}
		}

		public function check_wpua_value( $wpua ) {
			global $wp_version;
			$correct = 'WordPress/'.$wp_version.'; '.$this->home_url();
			if ( $correct !== $wpua ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'incorrect wpua for '.$wpua );
				}
				return $correct;
			} else {
				return $wpua;
			}
		}

		/*
		 * Provide plugin data from the json api for free / pro extensions not hosted on wordpress.org.
		 */
		public function external_plugin_data( $res, $action = null, $args = null ) {

			// this filter only provides plugin data
			if ( $action !== 'plugin_information' ) {
				return $res;
			// make sure we have a slug in the request
			} elseif ( empty( $args->slug ) ) {
				return $res;
			// check that the plugin slug is known
			} elseif ( empty( $this->p->cf['*']['slug'][$args->slug] ) ) {
				return $res;
			}

			// get the extension acronym for the config
			$ext = $this->p->cf['*']['slug'][$args->slug];

			// make sure we have a config for that slug
			if ( empty( self::$config[$ext]['slug'] ) ) {
				return $res;
			}

			// get plugin data from the json api
			$plugin_data = $this->get_plugin_data( $ext, true );	// $use_cache = true

			// make sure we have something to return
			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {
				return $res;
			}

			return $plugin_data->json_to_wp();
		}

		public function maybe_add_plugin_update( $updates = false ) {

			foreach ( self::$config as $ext => $info ) {

				if ( empty( $info['base'] ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: missing base in configuration' );
					}
					continue;
				}

				// remove existing update information to make sure it is correct (not from wordpress.org)
				if ( isset( $updates->response[$info['base']] ) ) {
					unset( $updates->response[$info['base']] );	// wpsso/wpsso.php
				}

				// check the local static property cache first
				if ( isset( self::$config[$ext]['plugin_update'] ) ) {
					// only provide update information when an update is required
					if ( self::$config[$ext]['plugin_update'] !== false ) {	// false when installed version is current
						$updates->response[$info['base']] = self::$config[$ext]['plugin_update'];
					}
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: using saved update information' );
						$this->p->debug->log( $ext.' plugin: calling method/function', 5 );
					}
					continue;	// get the next plugin from the config
				}

				$option_data = self::get_option_data( $ext );

				if ( empty( $option_data ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update option is empty' );
					}
				} elseif ( empty( $option_data->update ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: no update information' );
					}
				} elseif ( ! is_object( $option_data->update ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update property is not an object' );
					}
				} elseif ( ( $installed_version = $this->get_installed_version( $ext ) ) &&
					version_compare( $option_data->update->version, $installed_version, '>' ) ) {

					// save to the local static property cache
					self::$config[$ext]['plugin_update'] = $updates->response[$info['base']] = $option_data->update->json_to_wp();

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: installed version ('.$installed_version.') '.
							'different than update version ('.$option_data->update->version.')' );
						$this->p->debug->log_arr( 'option_data', $updates->response[$info['base']], 5 );
					}
				} else {
					self::$config[$ext]['plugin_update'] = false;	// false when installed is current

					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: installed version is current (or newer) than update version' );
						$this->p->debug->log_arr( 'option_data', $option_data->update->json_to_wp(), 5 );
					}
				}
			}
			return $updates;
		}
	
		/*
		 * If the wordpress update system has been disabled and/or manipulated (ie. $updates is not false), 
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
					'interval' => $this->sched_hours * 3600,
					'display' => sprintf( 'Every %d hours', $this->sched_hours )
				);
			}
			return $schedules;
		}
	
		public function check_for_updates( $ext = null, $notice = false, $use_cache = true ) {
			if ( empty( $ext ) ) {
				$plugins = self::$config;	// check all plugins defined
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'checking all extensions for updates' );
				}
			} elseif ( isset( self::$config[$ext] ) ) {
				$plugins = array( $ext => self::$config[$ext] );	// check only one specific plugin
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( 'exiting early: invalid extension value' );
				}
				return;
			}
			foreach ( $plugins as $ext => $info ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: checking for update' );
				}
				if ( $use_cache ) {
					$option_data = self::get_option_data( $ext );
				} else {
					$option_data = false;
				}
				if ( empty( $option_data ) ) {
					$option_data = new StdClass;
					$option_data->lastCheck = 0;
					$option_data->checkedVersion = 0;
					$option_data->update = null;
				}

				$option_data->lastCheck = time();
				$option_data->checkedVersion = $this->get_installed_version( $ext );
				$option_data->update = $this->get_update_data( $ext, $use_cache );

				if ( self::update_option_data( $ext, $option_data ) ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update information saved in '.$info['opt_name'] );
					}
					if ( $notice || $this->p->debug->enabled ) {
						$this->p->notice->inf( sprintf( __( 'Update information for %s has been retrieved and saved.',
							$this->text_domain ), $info['name'] ), true, 'check_for_updates_'.$ext.'_'.$info['opt_name'], true );
					}
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: failed saving update information in '.$info['opt_name'] );
					}
					if ( $notice || $this->p->debug->enabled ) {
						$this->p->notice->err( sprintf( __( 'Failed saving retrieved update information for %s.',
							$this->text_domain ), $info['name'] ) );
					}
				}
			}
		}
	
		public function get_update_data( $ext, $use_cache = true ) {

			// get plugin data from the json api
			$plugin_data = $this->get_plugin_data( $ext, $use_cache );

			if ( ! is_object( $plugin_data ) || ! method_exists( $plugin_data, 'json_to_wp' ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: returned update data is invalid' );
				}
				return null;
			}

			return SucomPluginUpdate::update_from_data( $plugin_data );
		}
	
		public function get_plugin_data( $ext, $use_cache = true ) {

			// make sure we have a config for that slug
			if ( empty( self::$config[$ext]['slug'] ) ) {
				return null;
			}

			global $wp_version;
			$home_url = $this->home_url();
			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( 'home url = '.$home_url );
			}
			$json_url = empty( self::$config[$ext]['json_url'] ) ?
				'' : self::$config[$ext]['json_url'];
			$installed_version = $this->get_installed_version( $ext );

			if ( empty( $json_url ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: exiting early - update json_url is empty' );
				}
				return null;
			}

			$lca = $this->p->cf['lca'];
			$cache_salt = __METHOD__.'(json_url:'.$json_url.'_home_url:'.$home_url.')';
			$cache_id = $lca.'_'.md5( $cache_salt );

			if ( $use_cache ) {
				if ( isset( self::$config[$ext]['plugin_data']->plugin ) ) {
					$plugin_data = self::$config[$ext]['plugin_data'];
				} else {
					$plugin_data = self::$config[$ext]['plugin_data'] = get_transient( $cache_id );
				}
				// false if transient is expired or not found
				if ( $plugin_data !== false ) {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: returning plugin data from cache' );
					}
					return $plugin_data;
				}
			}

			$ua_plugin = self::$config[$ext]['slug'].'/'.$installed_version.'/'.
				( $this->p->check->aop( $ext ) ? 'L' :
				( $this->p->check->aop( $ext, false ) ? 'U' : 'G' ) );
			$ua_wpid = 'WordPress/'.$wp_version.' ('.$ua_plugin.'); '.$home_url;
			$get_options = array(
				'timeout' => 10, 
				'user-agent' => $ua_wpid,
				'headers' => array( 
					'Accept' => 'application/json',
					'X-WordPress-Id' => $ua_wpid,
				),
			);
			$plugin_data = null;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( $ext.' plugin: calling wp_remote_get() for '.$json_url );
			}
			$res = wp_remote_get( $json_url, $get_options );

			if ( is_wp_error( $res ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: update error - '.$res->get_error_message() );
				}
				$this->p->notice->err( sprintf( __( 'Update error from the WordPress wp_remote_get() function &mdash; %s',
					$this->text_domain ), $res->get_error_message() ) );

			} elseif ( isset( $res['response']['code'] ) && (int) $res['response']['code'] === 200 && ! empty( $res['body'] ) ) {

				// create an associative array
				$payload = json_decode( $res['body'], true, 32 );

				// add new or remove existing response messages
				foreach ( array( 'err', 'inf' ) as $msg ) {
					self::$config[$ext]['u'.$msg] = self::set_umsg( $ext, $msg,
						( empty( $payload['api_response'][$msg] ) ? false : $payload['api_response'][$msg] ) );
				}

				if ( empty( $res['headers']['x-smp-error'] ) ) {
					self::$config[$ext]['uerr'] = false;
					$plugin_data = SucomPluginData::data_from_json( $res['body'] );	// returns null on error
					if ( empty( $plugin_data->plugin ) ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: returned plugin data is incomplete' );
						}
						$plugin_data = null;
					} elseif ( $plugin_data->plugin !== self::$config[$ext]['base'] ) {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: plugin property '.$plugin_data->plugin.
								' does not match '.self::$config[$ext]['base'] );
						}
						$plugin_data = null;
					}
				}
			}

			self::$config[$ext]['utime'] = self::set_umsg( $ext, 'time', time() );
			self::$config[$ext]['plugin_data'] = $plugin_data;	// save to local static property cache

			delete_transient( $cache_id );	// just in case

			if ( $plugin_data === null ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: saving empty stdClass to transient '.$cache_id );
				}
				set_transient( $cache_id, new stdClass, self::$config[$ext]['expire'] );
			} else {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: saving plugin data to transient '.$cache_id );
				}
				set_transient( $cache_id, $plugin_data, self::$config[$ext]['expire'] );
			}

			return $plugin_data;
		}
	
		public function get_installed_version( $ext ) {
			$version = 0;
			$info = array();

			if ( isset( $this->p->cf['plugin'][$ext] ) ) {
				$info = $this->p->cf['plugin'][$ext];
			}

			if ( isset( $info['version'] ) ) {	// plugin is active

				$version = $info['version'];
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: extension config version is '.$version );
				}

			} elseif ( isset( $info['base'] ) ) {	// plugin is not active

				if ( ! function_exists( 'get_plugins' ) ) {
					$plugin_lib = trailingslashit( ABSPATH ).'wp-admin/includes/plugin.php';
					if ( file_exists( $plugin_lib ) ) {	// just in case
						require_once $plugin_lib;
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: '.$plugin_lib.' file is missing' );
						}
						$this->p->notice->err( sprintf( __( 'The WordPress library file %s is missing and required.', 
							$this->text_domain ), '<code>'.$plugin_lib.'</code>' ) );
					}
				}
	
				if ( function_exists( 'get_plugins' ) ) {
					$plugins = get_plugins();
				} else {
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: '.$plugin_lib.' functions is not available' );
					}
					$this->p->notice->err( sprintf( __( 'The WordPress %s function is not available and required.',
						$this->text_domain ), '<code>get_plugins()</code>' ) );
					$plugins = array();
				}

				if ( isset( $plugins[$info['base']] ) ) {
					if ( isset( $plugins[$info['base']]['Version'] ) ) {
						$version = $plugins[$info['base']]['Version'];
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: installed version is '.$version );
						}
					} else {
						if ( $this->p->debug->enabled ) {
							$this->p->debug->log( $ext.' plugin: '.$info['base'].' version key missing from plugins array' );
						}
						$this->p->notice->err( sprintf( __( 'The %1$s plugin (%2$s) version number is missing from the WordPress plugins array.',
							$this->text_domain ), $info['name'], $info['base'] ) );
						return '0.0-no-version';	// stop here
					}
				} else {	// plugin is not installed
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: '.$info['base'].' plugin not installed' );
					}
					return '0.0-no-plugin';	// stop here
				}

			} else {	// plugin is not configured
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: extension config not found' );
				}
				return '0.0-no-config';	// stop here
			}

			$filter_regex = $this->get_version_filter_regex( $ext );

			if ( ! preg_match( $filter_regex, $version ) ) {
				if ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: '.$version.' does not match filter' );
				}
				$version = '0.'.$version;
			} else {
				$auth_type = empty( $info['update_auth'] ) ? 'none' : $info['update_auth'];
				$auth_key = 'plugin_'.$ext.'_'.$auth_type;
				$auth_id = empty( $this->p->options[$auth_key] ) ? '' : $this->p->options[$auth_key];

				if ( $auth_type !== 'none' ) {
					if ( $this->p->check->aop( $ext, false ) ) {
						if ( empty( $auth_id ) ) {
							$version = '0.'.$version;
						}
					} elseif ( ! empty( $auth_id ) )
						$version = '0.'.$version;
				}
			}

			return $version;
		}

		public function get_version_filter_regex( $ext ) {
	
			$filter_name = empty( $this->p->options['update_filter_for_'.$ext] ) ?	// just in case
				'stable' : $this->p->options['update_filter_for_'.$ext];

			$filter_regex = empty( $this->p->cf['um']['version_regex'][$filter_name] ) ?	// just in case
				$this->p->cf['um']['version_regex']['stable'] :
				$this->p->cf['um']['version_regex'][$filter_name];

			return $filter_regex;
		}

		// an unfiltered version of the same wordpress function
		// last synchronized with wordpress v4.5 on 2016/04/05
		private function home_url( $path = '', $scheme = null ) {
			return $this->get_home_url( null, $path, $scheme );
		}

		// an unfiltered version of the same wordpress function
		// last synchronized with wordpress v4.5 on 2016/04/05
		private function get_home_url( $blog_id = null, $path = '', $scheme = null ) {
			global $pagenow;

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

			$url = $this->set_url_scheme( $url, $scheme );

			if ( $path && is_string( $path ) ) {
				$url .= '/'.ltrim( $path, '/' );
			}

			return $url;
		}

		// an unfiltered version of the same wordpress function
		// last synchronized with wordpress v4.5 on 2016/04/05
		private function set_url_scheme( $url, $scheme = null ) {

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

			if ( 'relative' == $scheme ) {
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
		public $downloaded;
		public $rating;
		public $num_ratings;
		public $last_updated;
		public $sections;
		public $banners;
	
		public function __construct() {
		}

		public static function data_from_json( $json ) {

			$json_data = json_decode( $json );

			if ( empty( $json_data ) || ! is_object( $json_data ) )  {
				return null;
			}

			if ( isset( $json_data->plugin ) && ! empty( $json_data->plugin ) && 
				isset( $json_data->version ) && ! empty( $json_data->version ) ) {

				$plugin_data = new SucomPluginData();

				foreach( get_object_vars( $json_data ) as $key => $value ) {
					$plugin_data->$key = $value;
				}

				return $plugin_data;
			} else {
				return null;
			}
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
				'downloaded', 
				'rating', 
				'num_ratings', 
				'last_updated',
			) as $prop_name ) {
				if ( isset( $this->$prop_name ) ) {
					if ( $prop_name === 'download_url' ) {
						$plugin_data->download_link = $this->download_url;
					} elseif ( $prop_name === 'author_homepage' ) {
						$plugin_data->author = strpos( $this->author, '<a href=' ) === false ?
							sprintf( '<a href="%s">%s</a>', $this->author_homepage, $this->author ) :
							$this->author;
					} else {
						$plugin_data->$prop_name = $this->$prop_name;
					}
				} elseif ( $prop_name === 'author_homepage' ) {
					$plugin_data->author = $this->author;
				}
			}

			if ( is_array( $this->sections ) )  {
				$plugin_data->sections = $this->sections;
			} elseif ( is_object( $this->sections ) ) {
				$plugin_data->sections = get_object_vars( $this->sections );
			} else {
				$plugin_data->sections = array( 'description' => '' );
			}

			if ( is_array( $this->banners ) ) {
				$plugin_data->banners = $this->banners;
			} elseif ( is_object( $this->banners ) ) {
				$plugin_data->banners = get_object_vars( $this->banners );
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
		public $homepage;
		public $download_url;
		public $upgrade_notice;
		public $qty_used;

		public function __construct() {
		}

		public static function update_from_json( $json ) {

			$plugin_data = SucomPluginData::data_from_json( $json );

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
				'homepage', 
				'download_url', 
				'upgrade_notice',
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
				'homepage' => 'url',
				'download_url' => 'package',
				'upgrade_notice' => 'upgrade_notice',
				'qty_used' => 'qty_used',
			) as $json_update_prop => $wp_update_prop ) {
				if ( isset( $this->$json_update_prop ) ) {
					$plugin_update->$wp_update_prop = $this->$json_update_prop;
				}
			}

			return $plugin_update;
		}
	}
}

?>
