<?php
/*
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2016-2017 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) || 
	! defined( 'WP_UNINSTALL_PLUGIN' ) )
		die( 'These aren\'t the droids you\'re looking for...' );

$plugin_filepath = dirname( __FILE__ ).'/wpsso-um.php';

require_once( dirname( __FILE__ ).'/lib/config.php' );

WpssoUmConfig::set_constants( $plugin_filepath );
WpssoUmConfig::require_libs( $plugin_filepath );	// includes the register.php class library

WpssoUmRegister::network_uninstall();

?>
