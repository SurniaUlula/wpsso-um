<?php
/* 
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl.txt
 * Copyright 2015-2016 Jean-Sebastien Morisset (http://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'SucomUpdate' ) ) {

	class SucomUpdate {
	
		private $p;
		private $cron_hook;
		private $sched_hours;
		private $sched_name;
		private $text_domain = 'sucom';
		private $allow_host = '';
		private static $api_version = 2;
		private static $config = array();

		public function __construct( &$plugin, &$extensions, $check_hours = 24, $allow_host = '', $text_domain = 'sucom' ) {
			$this->p =& $plugin;
			if ( $this->p->debug->enabled )
				$this->p->debug->mark( 'update manager setup' );	// begin timer

			$slug = $extensions[$this->p->cf['lca']]['slug'];		// example: nextgen-facebook
			$this->cron_hook = 'plugin_updates-'.$slug;			// example: plugin_updates-nextgen-facebook
			$this->sched_hours = $check_hours >= 24 ? $check_hours : 24;	// example: 24 (minimum)
			$this->sched_name = 'every'.$this->sched_hours.'hours';		// example: every24hours
			$this->text_domain = $text_domain;				// example: nextgen-facebook-um
			$this->allow_host = $allow_host;				// example: surniaulula.com
			$this->set_config( $extensions );
			$this->install_hooks();

			if ( $this->p->debug->enabled )
				$this->p->debug->mark( 'update manager setup' );	// end timer
		}

		public static function get_api_version() {
			return self::$api_version;
		}

		private static function set_umsg( $ext, $msg, $val ) {
			wp_cache_delete( 'alloptions', 'options' );
			update_option( $ext.'_uapi'.self::$api_version.$msg,
				base64_encode( $val ) );	// save value as string
			return $val;
		}

		public static function get_umsg( $ext, $msg = 'err', $def = false ) {
			if ( ! isset( self::$config[$ext]['u'.$msg] ) ) {
				$val = get_option( $ext.'_uapi'.self::$api_version.$msg, $def );
				if ( ! is_bool( $val ) )
					$val = base64_decode( $val );	// value saved as string
				if ( empty( $val ) )
					self::$config[$ext]['u'.$msg] = false;
				else self::$config[$ext]['u'.$msg] = $val;
			}
			return self::$config[$ext]['u'.$msg];
		}

		public static function get_option( $ext, $idx = false ) {
			if ( ! empty( self::$config[$ext]['opt_name'] ) ) {
				$opt_data = self::get_option_data( $ext );
				if ( $idx !== false ) {
					if ( is_object( $opt_data->update ) &&
						isset( $opt_data->update->$idx ) )
							return $opt_data->update->$idx;
				} else return $opt_data;
			}
			return false;
		}

		private static function get_option_data( $ext, $def = false ) {
			if ( ! isset( self::$config[$ext]['opt_data'] ) ) {
				if ( ! empty( self::$config[$ext]['opt_name'] ) )
					self::$config[$ext]['opt_data'] = get_option( self::$config[$ext]['opt_name'], $def );
				else self::$config[$ext]['opt_data'] = $def;
			}
			return self::$config[$ext]['opt_data'];
		}

		private static function update_option_data( $ext, $opt_data ) {
			self::$config[$ext]['opt_data'] = $opt_data;
			if ( ! empty( self::$config[$ext]['opt_name'] ) )
				return update_option( self::$config[$ext]['opt_name'], $opt_data );
			return false;
		}

		public function set_config( &$extensions ) {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			foreach ( $extensions as $ext => $info ) {

				$auth_type = empty( $info['update_auth'] ) ?
					'none' : $info['update_auth'];
				$auth_key = 'plugin_'.$ext.'_'.$auth_type;
				$auth_id = empty( $this->p->options[$auth_key] ) ?
					'' : $this->p->options[$auth_key];

				if ( $auth_type !== 'none' && empty( $auth_id ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: update config skipped - empty '.$auth_key.' option value' );
					continue;
				} elseif ( empty( $info['slug'] ) || empty( $info['base'] ) || empty( $info['url']['update'] ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: update config skipped - incomplete config array' );
					continue;
				}

				$auth_url = apply_filters( 'sucom_update_url', $info['url']['update'], $info['slug'] );

				if ( $auth_type !== 'none' )
					$auth_url = add_query_arg( array( $auth_type => $auth_id ), $auth_url );

				$auth_url = add_query_arg( array( 
					'api_version' => self::$api_version,
					'version_filter' => isset( $this->p->options['update_filter_for_'.$ext] ) ?
						$this->p->options['update_filter_for_'.$ext] : 'stable',
					'installed_version' => $this->get_installed_version( $ext ),
				), $auth_url );

				if ( $this->p->debug->enabled )
					$this->p->debug->log( $ext.' plugin: update config defined (auth_type is '.$auth_type.')' );

				self::$config[$ext] = array(
					'name' => $info['name'],
					'slug' => $info['slug'],				// nextgen-facebook
					'base' => $info['base'],				// nextgen-facebook/nextgen-facebook.php
					'opt_name' => 'external_updates-'.$info['slug'],	// external_updates-nextgen-facebook
					'json_url' => $auth_url,
					'expire' => 86100,					// almost 24 hours
				);
			}
		}

		public static function is_enabled() {
			return empty( self::$config ) ?
				false : true;
		}

		public static function is_configured( $ext = null ) {
			if ( empty( $ext ) )
				return count( self::$config );
			elseif ( isset( self::$config[$ext] ) )
				return true;
			else return false;
		}

		public function install_hooks() {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( empty( self::$config ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'skipping all update checks - empty update config array' );
				return;
			}

			add_filter( 'plugins_api', array( &$this, 'inject_data' ), 100, 3 );
			add_filter( 'transient_update_plugins', array( &$this, 'inject_update' ), 1000, 1 );
			add_filter( 'site_transient_update_plugins', array( &$this, 'inject_update' ), 1000, 1 );
			add_filter( 'pre_site_transient_update_plugins', array( &$this, 'enable_update' ), 1000, 1 );
			add_filter( 'http_headers_useragent', array( &$this, 'check_wpua' ), 9000, 1 );
			add_filter( 'http_request_host_is_external', array( &$this, 'allow_host' ), 1000, 3 );

			if ( $this->sched_hours > 0 && ! empty( $this->sched_name ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'adding schedule '.$this->cron_hook.' for '.$this->sched_name );
				add_action( $this->cron_hook, array( &$this, 'check_for_updates' ) );
				add_filter( 'cron_schedules', array( &$this, 'custom_schedule' ) );

				$schedule = wp_get_schedule( $this->cron_hook );
				if ( ! empty( $schedule ) && $schedule !== $this->sched_name ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( 'changing '.$this->cron_hook.' schedule from '.
							$schedule.' to '.$this->sched_name );
					wp_clear_scheduled_hook( $this->cron_hook );
				}
				if ( ! defined('WP_INSTALLING') &&
					! wp_next_scheduled( $this->cron_hook ) )
						wp_schedule_event( time(), $this->sched_name, $this->cron_hook );
			} else wp_clear_scheduled_hook( $this->cron_hook );
		}

		public function check_wpua( $cur_wpua ) {
			global $wp_version;
			$def_wpua = 'WordPress/'.$wp_version.'; '.$this->home_url();
			if ( $def_wpua !== $cur_wpua ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'incorrect wpua found: '.$cur_wpua );
				return $def_wpua;
			} else return $cur_wpua;
		}
	
		public function allow_host( $allow, $ip, $url ) {
			if ( ! empty( $this->allow_host ) &&
				strpos( $url, '/'.$this->allow_host.'/' ) !== false ) {

				// check if the url matches a known plugin download url
				foreach ( self::$config as $ext => $info ) {
					$plugin_data = $this->get_json( $ext );
					if ( $url === $plugin_data->download_url ) {
						if ( $this->p->debug->enabled )
							$this->p->debug->log( 'allowing external host url: '.$url );
						return true;
					}
				}
			}
			return $allow;
		}

		public function inject_data( $result, $action = null, $args = null ) {
		    	if ( $action == 'plugin_information' && isset( $args->slug ) ) {
				foreach ( self::$config as $ext => $info ) {
					if ( ! empty( $info['slug'] ) && 
						$args->slug === $info['slug'] ) {
						$plugin_data = $this->get_json( $ext );
						if ( ! empty( $plugin_data ) ) 
							return $plugin_data->json_to_wp();
					}
				}
			}
			return $result;
		}

		// if updates have been disabled and/or manipulated (ie. $updates is not false), 
		// then re-enable by including our update data (if a new version is present)
		public function enable_update( $updates = false ) {
			if ( $updates !== false )
				$updates = $this->inject_update( $updates );
			return $updates;
		}

		public function inject_update( $updates = false ) {

			foreach ( self::$config as $ext => $info ) {
				if ( empty( $info['base'] ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: missing base value in configuration' );
					continue;
				}

				// remove existing information to make sure it is correct (not from wordpress.org)
				if ( isset( $updates->response[$info['base']] ) )
					unset( $updates->response[$info['base']] );					// nextgen-facebook/nextgen-facebook.php

				if ( isset( self::$config[$ext]['inject_update'] ) ) {
					// only return update information when an update is required
					if ( self::$config[$ext]['inject_update'] !== false )				// false when installed is current
						$updates->response[$info['base']] = self::$config[$ext]['inject_update'];
					if ( $this->p->debug->enabled ) {
						$this->p->debug->mark();
						$this->p->debug->log( $ext.' plugin: calling method/function', 4 );	// show calling method/function
						$this->p->debug->log( $ext.' plugin: using saved update status' );
					}
					continue;	// get the next plugin
				}
				
				$option_data = self::get_option_data( $ext );

				if ( empty( $option_data ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: update option is empty' );

				} elseif ( empty( $option_data->update ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: no update information' );

				} elseif ( ! is_object( $option_data->update ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: update property is not an object' );

				} elseif ( ( $installed_version = $this->get_installed_version( $ext ) ) &&
					version_compare( $option_data->update->version, $installed_version, '>' ) ) {

					// save to local static cache as well
					self::$config[$ext]['inject_update'] = $updates->response[$info['base']] = $option_data->update->json_to_wp();
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: update version ('.$option_data->update->version.')'.
							' is different than installed ('.$installed_version.')' );
						$this->p->debug->log( $updates->response[$info['base']], 5 );
					}
				} else {
					self::$config[$ext]['inject_update'] = false;					// false when installed is current
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $ext.' plugin: installed version is current - no update required' );
						$this->p->debug->log( $option_data->update->json_to_wp(), 5 );
					}
				}
			}
			return $updates;
		}
	
		public function custom_schedule( $schedule ) {
			if ( $this->sched_hours > 0 ) {
				$schedule[$this->sched_name] = array(
					'interval' => $this->sched_hours * 3600,
					'display' => sprintf( 'Every %d hours', $this->sched_hours )
				);
			}
			return $schedule;
		}
	
		public function check_for_updates( $ext = null, $notice = false, $use_cache = true ) {
			if ( empty( $ext ) )
				$plugins = self::$config;	// check all plugins defined
			elseif ( isset( self::$config[$ext] ) )
				$plugins = array( $ext => self::$config[$ext] );	// check only one specific plugin
			else {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'no plugins to check' );
				return;
			}
			foreach ( $plugins as $ext => $info ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'checking for '.$ext.' plugin update' );

				$option_data = self::get_option_data( $ext );
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
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: update information saved in '.$info['opt_name'].' option' );
					if ( $notice || $this->p->debug->enabled )
						$this->p->notice->inf( sprintf( __( 'Plugin update information for %s has been retrieved and saved.',
							$this->text_domain ), $info['name'] ), true );
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( $ext.' plugin: failed saving update information in '.$info['opt_name'].' option' );
					$this->p->debug->log( $option_data );
				}
			}
		}
	
		public function get_update_data( $ext, $use_cache = true ) {
			$plugin_data = $this->get_json( $ext, $use_cache );
			if ( empty( $plugin_data ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $ext.' plugin: update data from get_json() is empty' );
				return null;
			} else return SucomPluginUpdate::from_plugin_data( $plugin_data );
		}
	
		public function get_json( $ext, $use_cache = true ) {
			if ( empty( self::$config[$ext]['slug'] ) )
				return null;

			global $wp_version;
			$home_url = $this->home_url();
			if ( $this->p->debug->enabled )
				$this->p->debug->log( 'home_url = '.$home_url );
			$json_url = empty( self::$config[$ext]['json_url'] ) ?
				'' : self::$config[$ext]['json_url'];
			$installed_version = $this->get_installed_version( $ext );

			if ( empty( $json_url ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $ext.' plugin: exiting early - empty json_url' );
				return null;
			}
			
			$cache_salt = __METHOD__.'(json_url:'.$json_url.'_home_url:'.$home_url.')';
			$cache_id = $this->p->cf['lca'].'_'.md5( $cache_salt );
			$cache_type = 'object cache';

			if ( $use_cache ) {
				$last_utime = self::get_umsg( $ext, 'time' );
				$plugin_data = false;

				if ( $this->p->is_avail['cache']['transient'] && $last_utime ) {
					$plugin_data = get_transient( $cache_id );
				} elseif ( $this->p->is_avail['cache']['object'] && $last_utime ) {
					$plugin_data = wp_cache_get( $cache_id, __METHOD__ );
				} elseif ( isset( self::$config[$ext]['plugin_data'] ) )
					$plugin_data = self::$config[$ext]['plugin_data'];

				if ( $plugin_data !== false )
					return $plugin_data;
			}

			$ua_plugin = self::$config[$ext]['slug'].'/'.$installed_version.'/'.
				( $this->p->check->aop( $ext ) ? 'L' :
				( $this->p->check->aop( $ext, false ) ? 'U' : 'G' ) );
			$ua_wpid = 'WordPress/'.$wp_version.' ('.$ua_plugin.'); '.$home_url;

			$options = array(
				'timeout' => 10, 
				'user-agent' => $ua_wpid,
				'headers' => array( 
					'Accept' => 'application/json',
					'X-WordPress-Id' => $ua_wpid,
				),
			);

			$plugin_data = null;

			if ( $this->p->debug->enabled )
				$this->p->debug->log( $ext.' plugin: calling wp_remote_get() for '.$json_url );

			$result = wp_remote_get( $json_url, $options );

			if ( is_wp_error( $result ) ) {

				if ( isset( $this->p->notice ) && is_object( $this->p->notice ) )
					$this->p->notice->err( sprintf( __( 'Update error: %s',
						$this->text_domain ), $result->get_error_message() ) );
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'update error: '.$result->get_error_message() );

			} elseif ( isset( $result['response']['code'] ) && 
				$result['response']['code'] == 200 && ! empty( $result['body'] ) ) {

				$payload = json_decode( $result['body'], true, 32 );	// create an associative array

				if ( ! empty( $payload['api_response'] ) ) {
					foreach ( array( 'err', 'inf' ) as $msg ) {
						if ( ! empty( $payload['api_response'][$msg] ) ) {
							self::$config[$ext]['u'.$msg] = self::set_umsg( $ext,
								$msg, $payload['api_response'][$msg] );
						}
					}
				}

				if ( empty( $result['headers']['x-smp-error'] ) ) {
					self::$config[$ext]['uerr'] = false;
					delete_option( $ext.'_uerr' );
					$plugin_data = SucomPluginData::from_json( $result['body'] );

					if ( empty( $plugin_data->plugin ) ) {
						if ( $this->p->debug->enabled )
							$this->p->debug->log( 'missing data: plugin property missing from json' );
					} elseif ( $plugin_data->plugin !== self::$config[$ext]['base'] ) {
						if ( $this->p->debug->enabled )
							$this->p->debug->log( 'incorrect data: plugin property '.$plugin_data->plugin.
								' does not match '.self::$config[$ext]['base'] );
						$plugin_data = null;
					}
				}
			}

			// save timestamp of last update check
			self::$config[$ext]['utime'] = self::set_umsg( $ext, 'time', time() );

			if ( $this->p->is_avail['cache']['transient'] ) {
				wp_cache_delete( $cache_id, __METHOD__ );	// just in case
				set_transient( $cache_id, ( $plugin_data === null ? '' : $plugin_data ), self::$config[$ext]['expire'] );
			} elseif ( $this->p->is_avail['cache']['object'] ) {
				delete_transient( $cache_id );			// just in case
				wp_cache_set( $cache_id, ( $plugin_data === null ? '' : $plugin_data ), __METHOD__, self::$config[$ext]['expire'] );
			} else {
				delete_transient( $cache_id );			// just in case
				wp_cache_delete( $cache_id, __METHOD__ );	// just in case
				self::$config[$ext]['plugin_data'] = $plugin_data;
			}

			return $plugin_data;
		}
	
		public function get_installed_version( $ext ) {
			$version = 0;

			if ( isset( $this->p->cf['plugin'][$ext] ) )
				$info = $this->p->cf['plugin'][$ext];
			else {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $ext.' plugin: configuration not found' );
				return $version;
			}

			if ( ! function_exists( 'get_plugins' ) ) 
				require_once( ABSPATH.'/wp-admin/includes/plugin.php' );

			$plugins = get_plugins();

			if ( isset( $plugins[$info['base']] ) ) {
				if ( isset( $plugins[$info['base']]['Version'] ) ) {
					$version = $plugins[$info['base']]['Version'];
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $ext.' plugin: installed version is '.$version );
				} elseif ( $this->p->debug->enabled )
					$this->p->debug->log( $info['base'].' array does not have a version key' );
			} elseif ( $this->p->debug->enabled )
				$this->p->debug->log( $info['base'].' missing from the plugins array' );

			$filter_regex = $this->get_version_filter_regex( $ext );

			if ( ! preg_match( $filter_regex, $version ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $ext.' plugin: '.$version.' does not match filter' );
				$version = '0.'.$version;
			} else {
				$auth_type = empty( $info['update_auth'] ) ?
					'none' : $info['update_auth'];
				$auth_key = 'plugin_'.$ext.'_'.$auth_type;
				$auth_id = empty( $this->p->options[$auth_key] ) ?
					'' : $this->p->options[$auth_key];
				if ( $auth_type !== 'none' ) {
					if ( $this->p->check->aop( $ext, false ) ) {
						if ( empty( $auth_id ) )
							$version = '0.'.$version;
					} elseif ( ! empty( $auth_id ) )
						$version = '0.'.$version;
				}
			}

			return $version;
		}

		public function get_version_filter_regex( $ext ) {
			$filter_name = isset( $this->p->options['update_filter_for_'.$ext] ) ?
				$this->p->options['update_filter_for_'.$ext] : 'stable';
			$filter_regex = isset( $this->p->cf['update']['version_regex'][$filter_name] ) ?
				$this->p->cf['update']['version_regex'][$filter_name] :
				$this->p->cf['update']['version_regex']['stable'];
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

			if ( empty( $blog_id ) || ! is_multisite() )
				$url = get_option( 'home' );
			else {
				switch_to_blog( $blog_id );
				$url = get_option( 'home' );
				restore_current_blog();
			}

			if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {
				if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $pagenow )
					$scheme = 'https';
				else $scheme = parse_url( $url, PHP_URL_SCHEME );
			}

			$url = $this->set_url_scheme( $url, $scheme );

			if ( $path && is_string( $path ) )
				$url .= '/'.ltrim( $path, '/' );

			return $url;
		}

		// an unfiltered version of the same wordpress function
		// last synchronized with wordpress v4.5 on 2016/04/05
		private function set_url_scheme( $url, $scheme = null ) {

			if ( ! $scheme )
				$scheme = is_ssl() ? 'https' : 'http';
			elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' )
				$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
			elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' )
				$scheme = is_ssl() ? 'https' : 'http';

			$url = trim( $url );
			if ( substr( $url, 0, 2 ) === '//' )
				$url = 'http:' . $url;

			if ( 'relative' == $scheme ) {
				$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );
				if ( $url !== '' && $url[0] === '/' )
					$url = '/'.ltrim( $url, "/ \t\n\r\0\x0B" );
			} else $url = preg_replace( '#^\w+://#', $scheme . '://', $url );

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
		public $banners;
		public $homepage;
		public $sections;
		public $download_url;
		public $author;
		public $author_homepage;
		public $requires;
		public $tested;
		public $upgrade_notice;
		public $rating;
		public $num_ratings;
		public $downloaded;
		public $last_updated;
	
		public function __construct() {
		}

		public static function from_json( $json ) {
			$json_data = json_decode( $json );
			if ( empty( $json_data ) || 
				! is_object( $json_data ) ) 
					return null;
			if ( isset( $json_data->name ) && 
				! empty( $json_data->name ) && 
				isset( $json_data->version ) && 
				! empty( $json_data->version ) ) {

				$plugin_data = new SucomPluginData();
				foreach( get_object_vars( $json_data ) as $key => $value)
					$plugin_data->$key = $value;
				return $plugin_data;
			} else return null;
		}
	
		public function json_to_wp(){

			$fields = array(
				'name', 
				'slug', 
				'plugin', 
				'version', 
				'tested', 
				'num_ratings', 
				'homepage', 
				'download_url',
				'author_homepage',
				'requires', 
				'upgrade_notice',
				'rating', 
				'downloaded', 
				'last_updated',
			);
			$data = new StdClass;

			foreach ( $fields as $field ) {
				if ( isset( $this->$field ) ) {
					if ( $field == 'download_url' ) {
						$data->download_link = $this->download_url; }
					elseif ( $field == 'author_homepage' ) {
						$data->author = strpos( $this->author, '<a href=' ) === false ?
							sprintf( '<a href="%s">%s</a>', $this->author_homepage, $this->author ) :
							$this->author;
					} else { $data->$field = $this->$field; }
				} elseif ( $field == 'author_homepage' )
					$data->author = $this->author;
			}

			if ( is_array( $this->sections ) ) 
				$data->sections = $this->sections;
			elseif ( is_object( $this->sections ) ) 
				$data->sections = get_object_vars( $this->sections );
			else $data->sections = array( 'description' => '' );

			if ( is_array( $this->banners ) ) 
				$data->banners = $this->banners;
			elseif ( is_object( $this->banners ) ) 
				$data->banners = get_object_vars( $this->banners );

			return $data;
		}
	}
}
	
if ( ! class_exists( 'SucomPluginUpdate' ) ) {

	class SucomPluginUpdate {
	
		public $id = 0;
		public $slug;
		public $plugin;
		public $qty_used;
		public $version = 0;
		public $homepage;
		public $download_url;
		public $upgrade_notice;

		public function __construct() {
		}

		public function from_json( $json ) {
			$plugin_data = SucomPluginData::from_json( $json );
			if ( $plugin_data !== null ) 
				return self::from_plugin_data( $plugin_data );
			else return null;
		}
	
		public static function from_plugin_data( $data ){
			$plugin_update = new SucomPluginUpdate();
			$fields = array(
				'id', 
				'slug', 
				'plugin', 
				'qty_used', 
				'version', 
				'homepage', 
				'download_url', 
				'upgrade_notice'
			);
			foreach( $fields as $field )
				if ( isset( $data->$field ) )
					$plugin_update->$field = $data->$field;
			return $plugin_update;
		}
	
		public function json_to_wp() {
			$data = new StdClass;
			$fields = array(
				'id' => 'id',
				'slug' => 'slug',
				'plugin' => 'plugin',
				'qty_used' => 'qty_used',
				'new_version' => 'version',
				'url' => 'homepage',
				'package' => 'download_url',
				'upgrade_notice' => 'upgrade_notice'
			);
			foreach ( $fields as $new_field => $old_field ) {
				if ( isset( $this->$old_field ) )
					$data->$new_field = $this->$old_field;
			}
			return $data;
		}
	}
}

?>
