<?php
/*
	Plugin Name: Flexible Shipping UPS
	Plugin URI: https://wordpress.org/plugins/flexible-shipping-ups/
	Description: WooCommerce UPS Shipping Method and live rates.
	Version: 1.4.1
	Author: WP Desk
	Author URI: https://www.wpdesk.net/
	Text Domain: flexible-shipping-ups
	Domain Path: /lang/
	Requires at least: 4.5
    Tested up to: 5.1.0
    WC requires at least: 3.1.0
    WC tested up to: 3.5.6

	Copyright 2017 WP Desk Ltd.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// Only PHP 5.2 compatible code
if ( ! class_exists( 'WPDesk_Basic_Requirement_Checker' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/wpdesk/wp-basic-requirements/src/Basic_Requirement_Checker.php';
}


/* THESE TWO VARIABLES CAN BE CHANGED AUTOMATICALLY */
$plugin_version           = '1.4.1';
$plugin_release_timestamp = '2018-11-13';

$plugin_name        = 'Flexible Shipping UPS';
$plugin_class_name  = 'Flexible_Shipping_UPS_Plugin';
$plugin_text_domain = 'flexible-shipping-ups';

define( 'FLEXIBLE_SHIPPING_UPS_VERSION', $plugin_version );
define( $plugin_class_name, $plugin_version );

$requirements_checker = new WPDesk_Basic_Requirement_Checker(
	__FILE__,
	$plugin_name,
	$plugin_text_domain,
	'5.5',
	'4.5'
);
$requirements_checker->add_plugin_require( 'woocommerce/woocommerce.php', 'Woocommerce' );


if ( $requirements_checker->are_requirements_met() ) {
	if ( ! class_exists( 'WPDesk_Plugin_Info' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/wpdesk/wp-basic-requirements/src/Plugin/Plugin_Info.php';
	}

	$plugin_info = new WPDesk_Plugin_Info();
	$plugin_info->set_plugin_file_name( plugin_basename( __FILE__ ) );
	$plugin_info->set_plugin_dir( dirname( __FILE__ ) );
	$plugin_info->set_class_name( $plugin_class_name );
	$plugin_info->set_version( $plugin_version );
	$plugin_info->set_product_id( $plugin_text_domain );
	$plugin_info->set_text_domain( $plugin_text_domain );
	$plugin_info->set_release_date( new DateTime( $plugin_release_timestamp ) );
	$plugin_info->set_plugin_url( plugins_url( dirname( plugin_basename( __FILE__ ) ) ) );

	require_once dirname( __FILE__ ) . '/plugin-load.php';

} else {
	$requirements_checker->disable_plugin_render_notice();
}

require_once 'classes/tracker.php';
$ups_tracker = new Flexible_Shipping_UPS_Tracker();
$ups_tracker->hooks();
