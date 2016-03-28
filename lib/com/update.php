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
		private $text_dom = 'sucom';
		private static $api_version = 2;
		private static $config = array();

		public function __construct( &$plugin, &$ext, $text_dom = 'sucom' ) {
			$this->p =& $plugin;
			if ( $this->p->debug->enabled )
				$this->p->debug->mark( 'update manager setup' );	// begin timer

			$lca = $this->p->cf['lca'];					// ngfb
			$this->cron_hook = 'plugin_updates-'.$ext[$lca]['slug'];	// plugin_updates-nextgen-facebook
			$this->sched_hours = $this->p->cf['update_check_hours'];	// 24
			$this->sched_name = 'every'.$this->sched_hours.'hours';		// every24hours
			$this->text_dom = $text_dom;					// nextgen-facebook-um
			$this->set_config( $ext );
			$this->install_hooks();

			if ( $this->p->debug->enabled )
				$this->p->debug->mark( 'update manager setup' );	// end timer
		}

		// $val can be text or timestamp
		private static function set_umsg( $lca, $msg, $val ) {
			update_option( $lca.'_uapi'.self::$api_version.$msg,
				base64_encode( $val ) );	// saved as string
			return $val;
		}

		public static function get_umsg( $lca, $msg = 'err', $def = false ) {
			if ( ! isset( self::$config[$lca]['u'.$msg] ) ) {
				$val = get_option( $lca.'_uapi'.self::$api_version.$msg, $def );
				if ( ! is_bool( $val ) )
					$val = base64_decode( $val );	// saved as string
				if ( empty( $val ) )
					self::$config[$lca]['u'.$msg] = false;
				else self::$config[$lca]['u'.$msg] = $val;
			}
			return self::$config[$lca]['u'.$msg];
		}

		public static function get_option( $lca, $idx = false ) {
			if ( ! empty( self::$config[$lca]['opt_name'] ) ) {
				$opt_data = self::get_option_data( $lca );
				if ( $idx !== false ) {
					if ( is_object( $opt_data->update ) &&
						isset( $opt_data->update->$idx ) )
							return $opt_data->update->$idx;
				} else return $opt_data;
			}
			return false;
		}

		private static function get_option_data( $lca, $def = false ) {
			if ( ! isset( self::$config[$lca]['opt_data'] ) ) {
				if ( ! empty( self::$config[$lca]['opt_name'] ) )
					self::$config[$lca]['opt_data'] = get_option( self::$config[$lca]['opt_name'], $def );
				else self::$config[$lca]['opt_data'] = $def;
			}
			return self::$config[$lca]['opt_data'];
		}

		private static function update_option_data( $lca, $opt_data ) {
			self::$config[$lca]['opt_data'] = $opt_data;
			if ( ! empty( self::$config[$lca]['opt_name'] ) )
				return update_option( self::$config[$lca]['opt_name'], $opt_data );
			return false;
		}

		public function set_config( &$ext ) {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			foreach ( $ext as $lca => $info ) {

				// make sure we have all basic info for the plugin / extension
				if ( empty( $info['slug'] ) || empty( $info['base'] ) || empty( $info['url']['update'] ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: update config skipped - '.
							'incomplete config array' );
					continue;
				}

				$auth_type = empty( $info['update_auth'] ) ?
					'none' : $info['update_auth'];
				$auth_key = 'plugin_'.$lca.'_'.$auth_type;
				$auth_id = empty( $this->p->options[$auth_key] ) ?
					'' : $this->p->options[$auth_key];

				if ( $auth_type !== 'none' && empty( $auth_id ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: update config skipped - '.
							'empty '.$auth_key.' option value' );
					continue;
				}

				$auth_url = apply_filters( 'sucom_update_url', 
					$info['url']['update'], $info['slug'] );
				if ( $auth_type !== 'none' ) {
					$auth_url = add_query_arg( array( 
						$auth_type => $auth_id,
						'api_version' => self::$api_version,
					), $auth_url );
				}

				if ( $this->p->debug->enabled )
					$this->p->debug->log( $lca.' plugin: update config defined '.
						'(auth_type is '.( empty( $auth_type ) ?
							'none' : $auth_type ).')' );

				self::$config[$lca] = array(
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

		public static function is_configured() {
			return count( self::$config );
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
			if ( strpos( $url, '/'.$this->p->cf['allow_update_host'].'/' ) !== false ) {
				foreach ( self::$config as $lca => $info ) {
					$plugin_data = $this->get_json( $lca );
					if ( $url == $plugin_data->download_url ) {
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
				foreach ( self::$config as $lca => $info ) {
					if ( ! empty( $info['slug'] ) && 
						$args->slug === $info['slug'] ) {
						$plugin_data = $this->get_json( $lca );
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

			foreach ( self::$config as $lca => $info ) {
				if ( empty( $info['base'] ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: missing base value in configuration' );
					continue;
				}

				// remove existing information to make sure it is correct (not from wordpress.org)
				if ( isset( $updates->response[$info['base']] ) )
					unset( $updates->response[$info['base']] );					// nextgen-facebook/nextgen-facebook.php

				if ( isset( self::$config[$lca]['inject_update'] ) ) {
					// only return update information when an update is required
					if ( self::$config[$lca]['inject_update'] !== false )				// false when installed is current
						$updates->response[$info['base']] = self::$config[$lca]['inject_update'];
					if ( $this->p->debug->enabled ) {
						$this->p->debug->mark();
						$this->p->debug->log( $lca.' plugin: calling method/function', 4 );	// show calling method/function
						$this->p->debug->log( $lca.' plugin: using saved update status' );
					}
					continue;	// get the next plugin
				}
				
				$option_data = self::get_option_data( $lca );

				if ( empty( $option_data ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: update option is empty' );
				} elseif ( empty( $option_data->update ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: no update information' );
				} elseif ( ! is_object( $option_data->update ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: update property is not an object' );
				} elseif ( version_compare( $option_data->update->version, $this->get_installed_version( $lca ), '>' ) ) {
					// save to local static cache as well
					self::$config[$lca]['inject_update'] = $updates->response[$info['base']] = $option_data->update->json_to_wp();
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $lca.' plugin: update version ('.$option_data->update->version.')'.
							' is different than installed ('.$this->get_installed_version( $lca ).')' );
						$this->p->debug->log( $updates->response[$info['base']], 5 );
					}
				} else {
					self::$config[$lca]['inject_update'] = false;					// false when installed is current
					if ( $this->p->debug->enabled ) {
						$this->p->debug->log( $lca.' plugin: installed version is current - no update required' );
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
	
		public function check_for_updates( $lca = null, $notice = false, $use_cache = true ) {
			if ( empty( $lca ) )
				$plugins = self::$config;	// check all plugins defined
			elseif ( isset( self::$config[$lca] ) )
				$plugins = array( $lca => self::$config[$lca] );	// check only one specific plugin
			else {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'no plugins to check' );
				return;
			}
			foreach ( $plugins as $lca => $info ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'checking for '.$lca.' plugin update' );

				$option_data = self::get_option_data( $lca );
				if ( empty( $option_data ) ) {
					$option_data = new StdClass;
					$option_data->lastCheck = 0;
					$option_data->checkedVersion = 0;
					$option_data->update = null;
				}
				$option_data->lastCheck = time();
				$option_data->checkedVersion = $this->get_installed_version( $lca );
				$option_data->update = $this->get_update_data( $lca, $use_cache );

				if ( self::update_option_data( $lca, $option_data ) ) {
					if ( $this->p->debug->enabled )
						$this->p->debug->log( $lca.' plugin: update information saved in '.$info['opt_name'].' option' );
					if ( $notice === true || $this->p->debug->enabled )
						$this->p->notice->inf( sprintf( __( 'Plugin update information for %s has been retrieved and saved.',
							$this->text_dom ), $info['name'] ), true );
				} elseif ( $this->p->debug->enabled ) {
					$this->p->debug->log( $lca.' plugin: failed saving update information in '.$info['opt_name'].' option' );
					$this->p->debug->log( $option_data );
				}
			}
		}
	
		public function get_update_data( $lca, $use_cache = true ) {
			$plugin_data = $this->get_json( $lca, $use_cache );
			if ( empty( $plugin_data ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $lca.' plugin: update data from get_json() is empty' );
				return null;
			} else return SucomPluginUpdate::from_plugin_data( $plugin_data );
		}
	
		public function get_json( $lca, $use_cache = true ) {
			if ( empty( self::$config[$lca]['slug'] ) )
				return null;

			global $wp_version;
			$home_url = $this->home_url();
			if ( $this->p->debug->enabled )
				$this->p->debug->log( 'home_url = '.$home_url );
			$json_url = empty( self::$config[$lca]['json_url'] ) ? '' : self::$config[$lca]['json_url'];
			$query_args = array( 'installed_version' => $this->get_installed_version( $lca ) );

			if ( empty( $json_url ) ) {
				if ( $this->p->debug->enabled )
					$this->p->debug->log( $lca.' plugin: exiting early - empty json_url' );
				return null;
			}
			
			if ( ! empty( $query_args ) ) 
				$json_url = add_query_arg( $query_args, $json_url );

			$cache_salt = __METHOD__.'(json_url:'.$json_url.'_home_url:'.$home_url.')';
			$cache_id = $this->p->cf['lca'].'_'.md5( $cache_salt );
			$cache_type = 'object cache';

			if ( $use_cache ) {
				$last_utime = self::get_umsg( $lca, 'time' );
				if ( $this->p->is_avail['cache']['transient'] && $last_utime ) {
					$plugin_data = get_transient( $cache_id );
				} elseif ( $this->p->is_avail['cache']['object'] && $last_utime ) {
					$plugin_data = wp_cache_get( $cache_id, __METHOD__ );
				} elseif ( isset( self::$config[$lca]['plugin_data'] ) )
					$plugin_data = self::$config[$lca]['plugin_data'];
				if ( $plugin_data !== false )
					return $plugin_data;
			}

			$ua_plugin = self::$config[$lca]['slug'].'/'.$query_args['installed_version'];
			if ( has_filter( $lca.'_ua_plugin' ) )
				$ua_plugin = apply_filters( $lca.'_ua_plugin', $ua_plugin );
			else $ua_plugin = apply_filters( 'sucom_ua_plugin', $ua_plugin, $lca );
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
				$this->p->debug->log( $lca.' plugin: calling wp_remote_get() for '.$json_url );
			$result = wp_remote_get( $json_url, $options );
			if ( is_wp_error( $result ) ) {

				if ( isset( $this->p->notice ) && is_object( $this->p->notice ) )
					$this->p->notice->err( sprintf( __( 'Update error: %s',
						$this->text_dom ), $result->get_error_message() ) );
				if ( $this->p->debug->enabled )
					$this->p->debug->log( 'update error: '.$result->get_error_message() );

			} elseif ( isset( $result['response']['code'] ) && 
				$result['response']['code'] == 200 && ! empty( $result['body'] ) ) {

				$payload = json_decode( $result['body'], true, 32 );	// create an associative array

				if ( ! empty( $payload['api_response'] ) ) {
					foreach ( array( 'err', 'inf' ) as $msg ) {
						if ( ! empty( $payload['api_response'][$msg] ) ) {
							self::$config[$lca]['u'.$msg] = self::set_umsg( $lca,
								$msg, $payload['api_response'][$msg] );
						}
					}
				}

				if ( empty( $result['headers']['x-smp-error'] ) ) {
					self::$config[$lca]['uerr'] = false;
					delete_option( $lca.'_uerr' );
					$plugin_data = SucomPluginData::from_json( $result['body'] );

					if ( empty( $plugin_data->plugin ) ) {
						if ( $this->p->debug->enabled )
							$this->p->debug->log( 'missing data: plugin property missing from json' );
					} elseif ( $plugin_data->plugin !== self::$config[$lca]['base'] ) {
						if ( $this->p->debug->enabled )
							$this->p->debug->log( 'incorrect data: plugin property '.$plugin_data->plugin.
								' does not match '.self::$config[$lca]['base'] );
						$plugin_data = null;
					}
				}
			}

			// save timestamp of last update check
			self::$config[$lca]['utime'] = self::set_umsg( $lca, 'time', time() );

			if ( $this->p->is_avail['cache']['transient'] )
				set_transient( $cache_id, ( $plugin_data === null ?
					'' : $plugin_data ), self::$config[$lca]['expire'] );
			elseif ( $this->p->is_avail['cache']['object'] )
				wp_cache_set( $cache_id, ( $plugin_data === null ?
					'' : $plugin_data ), __METHOD__, self::$config[$lca]['expire'] );
			else self::$config[$lca]['plugin_data'] = $plugin_data;

			return $plugin_data;
		}
	
		public function get_installed_version( $lca ) {
			$version = 0;
			if ( isset( self::$config[$lca]['base'] ) ) {
				$base = self::$config[$lca]['base'];
				if ( ! function_exists( 'get_plugins' ) ) 
					require_once( ABSPATH.'/wp-admin/includes/plugin.php' );
				$plugins = get_plugins();
				if ( isset( $plugins[$base] ) ) {
					if ( isset( $plugins[$base]['Version'] ) ) {
						$version = $plugins[$base]['Version'];
						if ( $this->p->debug->enabled )
							$this->p->debug->log( $lca.' plugin: installed version is '.$version );
					} elseif ( $this->p->debug->enabled )
						$this->p->debug->log( $base.' does not have a Version key' );
				} elseif ( $this->p->debug->enabled )
					$this->p->debug->log( $base.' missing from the plugins array' );
			}
			if ( has_filter( $lca.'_installed_version' ) )
				return apply_filters( $lca.'_installed_version', $version );
			else return apply_filters( 'sucom_installed_version', $version, $lca );
		}

		// an unfiltered version of the same wordpress function
		private function home_url( $path = '', $scheme = null ) {
			return $this->get_home_url( null, $path, $scheme );
		}

		// an unfiltered version of the same wordpress function
		private function get_home_url( $blog_id = null, $path = '', $scheme = null ) {

			if ( empty( $blog_id ) || ! is_multisite() )
				$url = get_option( 'home' );
			else {
				switch_to_blog( $blog_id );
				$url = get_option( 'home' );
				restore_current_blog();
			}

			if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {
				if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $GLOBALS['pagenow'] )
					$scheme = 'https';
				else $scheme = parse_url( $url, PHP_URL_SCHEME );
			}

			$url = $this->set_url_scheme( $url, $scheme );

			if ( $path && is_string( $path ) )
				$url .= '/'.ltrim( $path, '/' );

			return $url;
		}

		// an unfiltered version of the same wordpress function
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
