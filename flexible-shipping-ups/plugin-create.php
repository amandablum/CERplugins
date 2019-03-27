<?php

use WPDesk\PluginBuilder\BuildDirector\LegacyBuildDirector;
use WPDesk\PluginBuilder\Builder\InfoBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Plugin info.
 *
 * @var WPDesk_Plugin_Info $plugin_info
 */
$builder        = new InfoBuilder( $plugin_info );
$build_director = new LegacyBuildDirector( $builder );
$build_director->build_plugin();

/**
 * @return Flexible_Shipping_UPS_Plugin
 */
function flexible_shipping_ups_plugin() {
	$storage = new \WPDesk\PluginBuilder\Storage\StaticStorage();
	return $storage->get_from_storage( Flexible_Shipping_UPS_Plugin::class );
}

