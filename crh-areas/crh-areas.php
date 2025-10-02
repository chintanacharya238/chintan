<?php
/**
 * Plugin Name: CRH Allocated Areas
 * Description: Allow customers to allocate product quantities to named areas on product, cart, and checkout. Persists allocations to order items, displays in admin/emails, and exposes via REST API.
 * Author: Client Rental Hub
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: crh-areas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants
if ( ! defined( 'CRH_AREAS_FILE' ) ) {
	define( 'CRH_AREAS_FILE', __FILE__ );
}
if ( ! defined( 'CRH_AREAS_DIR' ) ) {
	define( 'CRH_AREAS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CRH_AREAS_URL' ) ) {
	define( 'CRH_AREAS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CRH_AREAS_VERSION' ) ) {
	define( 'CRH_AREAS_VERSION', '1.0.0' );
}
if ( ! defined( 'CRH_AREAS_VER' ) ) {
	// Use filemtime during dev if WP_DEBUG is on, else use plugin version
	$dev_ver = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : CRH_AREAS_VERSION;
	define( 'CRH_AREAS_VER', $dev_ver );
}

// Includes
require_once CRH_AREAS_DIR . 'includes/Admin.php';
require_once CRH_AREAS_DIR . 'includes/Areas.php';

// Bootstrap
add_action( 'plugins_loaded', function() {
	// Load translations if any
	load_plugin_textdomain( 'crh-areas', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	\CRH\Admin::init();
	\CRH\Areas::init();
} );
