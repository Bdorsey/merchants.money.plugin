<?php
/**
 * Plugin Name: Merchants Money
 * Description: Merchants Money payment gateway plugin for WooCommerce. Start accepting payments on your store. 
 * Version: 1.0.0
 * Requires at least: 4.9
 * Requires PHP: 7.0
 */
 
defined( 'ABSPATH' ) or die( 'No script allowed.' );
define( 'WC_YODLEE_PLUGIN_DIR', plugin_dir_url( __FILE__ )); 
define( 'WC_YODLEE_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ )); 
define( 'WC_YODLEE_PAYMENT_SLUG', 'woo_yodlee_payment'); 

define('WC_YODLEE_LIC_VERIFICATION_KEY', '5fa032e7775e82.56603060' );
define('WC_YODLEE_LIC_HOST_SERVER', 'https://element.ps');

// Include all required files
require_once WC_YODLEE_PLUGIN_DIR_PATH . 'includes/class-woo-yodlee-payment.php';
require_once WC_YODLEE_PLUGIN_DIR_PATH . 'admin/class-woo-yodlee-license.php';


// Write a new permalink entry on code activation
register_activation_hook( __FILE__, 'woo_yodlee_activation' );
function woo_yodlee_activation() {
}

// If the plugin is deactivated, clean the permalink structure
register_deactivation_hook( __FILE__, 'woo_yodlee_deactivation' );
function woo_yodlee_deactivation() {
}

/**
 * Add plugin action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_yodlee_plugin_action_links' );
function wc_yodlee_plugin_action_links( $links ) {
	$plugin_links = array(
		'<a href="'.admin_url().'admin.php?page=wc-settings&tab=checkout&section='.WC_YODLEE_PAYMENT_SLUG.'">' . esc_html__( 'Settings', WC_YODLEE_PAYMENT_SLUG ) . '</a>',
	);
	return array_merge( $plugin_links, $links );
}

// check if WooCommerce is activated
function woo_yodlee_wc_check(){

	if ( class_exists( 'WooCommerce' ) ) {

		return true;

	} else {

		return false;

	}

}


add_action( 'plugins_loaded', 'wc_yodlee_init_payment_gateway_class' );
function wc_yodlee_init_payment_gateway_class() {
	if(woo_yodlee_wc_check()) {

		$woo_yodlee_payment = new Woo_Yodlee_Payment();
		// if($woo_yodlee_payment->license_key) {
			require_once WC_YODLEE_PLUGIN_DIR_PATH . 'includes/class-woo-yodlee-gateway.php';
		// }

	}
}


?>