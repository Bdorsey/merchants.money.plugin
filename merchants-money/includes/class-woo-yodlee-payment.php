<?php
class Woo_Yodlee_Payment {

	public $license;

	public $license_key;

	public function __construct() 
        {		
            $this->init();
            $this->add_actions();
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-yodlee-webhook.php';        
            $this->define_public_hooks();        
	}
        
         private function define_public_hooks() 
        {
            $woo_yodlee_webhook = new Woo_Yodlee_Webhook();
            add_action( 'rest_api_init', [$woo_yodlee_webhook, 'register_all_routes']);
        }
        
	public function init() {
		// $this->license = new Woo_Yodlee_License();
		// $this->license_key = $this->license->get_license_key();
		// if($this->license_key) {
		// 	$this->license->check_license();
		// }
            
	}

	public function add_actions() {
		add_action( 'admin_menu', [$this, 'admin_menu'] );
		// add_action( 'init', [$this, 'check_license'] );

		// if($this->license_key) {
			add_action( 'init', [$this, 'payment_settings'] );
			add_action( 'woocommerce_init', [$this, 'enable_session'] );
			add_action( 'wp', [$this, 'wc_yodly_show_error_messages'] );
			add_filter( 'woocommerce_payment_gateways', [$this, 'wc_yodlee_add_payment_gateway_class'] );
		// }
	}
	
	public function check_license() {

		// if(isset($_GET['page']) && !empty($_GET['page'])) {

		// 	$arrPages = [
		// 		'woo-yodlee-gateway-settings',
		// 		'woo-yodlee-gateway-orders'
		// 	];
		// 	if(in_array($_GET['page'], $arrPages)) {
				
		// 		if($this->license_key) {
		// 			return true;
		// 		}
		// 		else {
		// 			wp_redirect(admin_url().'admin.php?page=woo-yodlee-gateway-license');
		// 			exit();
		// 		}

		// 	}

		// }
		
	}

	/**;
	 * Add admin menu
	 *
	 * @since    1.0.0
	 */
	public function admin_menu() {

		/* add new top level */
		add_menu_page(
			__('Merchants Money'),
			__('Merchants Money'),
			'manage_woocommerce',
			'woo-yodlee-gateway-orders',
			[$this, 'orders_page'],
			plugins_url('assets/images/yodlee_icon.png', __DIR__),
			56
		);
	
		/* add the submenus */
		add_submenu_page(
			'woo-yodlee-gateway-orders',
			__('Orders'),
			__('Orders'),
			'manage_woocommerce',
			'woo-yodlee-gateway-orders',
			[$this, 'orders_page']
		);
		
		// add_submenu_page(
		// 	'woo-yodlee-gateway-orders',
		// 	__('License'),
		// 	__('License'),
		// 	'manage_woocommerce',
		// 	'woo-yodlee-gateway-license',
		// 	[$this->license, 'license_page']
		// );

		add_submenu_page(
			'woo-yodlee-gateway-orders',
			__('Settings'),
			__('Settings'),
			'manage_woocommerce',
			'woo-yodlee-gateway-settings',
			[$this, 'settings_page']
		);

	}

    /**
	 * Orders Page
	 *
	 * @since    1.0.0
	 */
	public function orders_page() {
		require WC_YODLEE_PLUGIN_DIR_PATH . '/admin/includes/orders.php';
    }
    
    /**
	 * Settings Page
	 *
	 * @since    1.0.0
	 */
	public function settings_page() {
		
    }

    /**
	 * Settings Page redirect
	 *
	 * @since    1.0.0
	 */
	public function payment_settings() {
		
        if(isset($_GET['page']) && !empty($_GET['page'])) {

            if($_GET['page'] == 'woo-yodlee-gateway-settings') {
                wp_redirect(admin_url().'admin.php?page=wc-settings&tab=checkout&section='.WC_YODLEE_PAYMENT_SLUG);
                exit();
            }
        }
    }

	public function enable_session() {
		if(WC()->session) {
			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}
		}
	}

	/**
	 * Show error messages if exists in session
	 */
	public function wc_yodly_show_error_messages() {
			
		if(WC()->session) {
			$error_message = WC()->session->get( 'wc_yodly_error' );
			if($error_message) {
				wc_add_notice( $error_message, 'error' );
				WC()->session->__unset( 'wc_yodly_error' );
			}
		}
		
	}

	/**
	 * Add Gateway class to all payment gateway methods
	 */
	public function wc_yodlee_add_payment_gateway_class( $methods ) {
		
		$methods[] = 'Woo_Yodlee_Gateway'; 
		return $methods;
	}

}