<?php
class Woo_Yodlee_License {

	public $option_key = 'woo_yodlee_license';

	protected $remote_settings = array(
		'timeout' => 20,
		'sslverify' => false
	);

    public function __construct() {
		
    }

	/**
	 * Check license for changes
	 *
	 * @since    1.0.0
	 */
	public function check_license() {

		$check = true;
		$last_check = get_option('woo_yodlee_license_check');
		$current_time = current_time('timestamp');

		if($last_check) {
			if($last_check < $current_time) {
				$diff = $current_time - $last_check;
				if($diff < 1*60*60*24) {
					$check = false;
				}
			}
		}

		if($check) {
			
			update_option('woo_yodlee_license_check', $current_time);
			$license_key = $this->get_license_key();
			$response = $this->activate_license($license_key);
			
			if(!$response['status']) {
				$this->remove_license();

				$message = 'Your license was deactivated.';
				switch($response['reason']) {
					case 'EXPIRED':
						$message = 'Your license was expired, please add new license key to activate it again.';
					break;
					case 'PENDING':
						$message = 'Your license was deactivated, because the status of you license is not active.';
					break;
					case 'BLOCKED':
						$message = 'Your license was blocked, please add new license key or contact support to activate it.';
					break;
					case 'MAX_DOMAINS':
						$message = 'Your license was deactivated, because the maximum domains limit was reached.';
					break;
				}
				update_option('woo_yodlee_license_deactivated', $message);
			}

		}

	}

	/**
	 * License Page
	 *
	 * @since    1.0.0
	 */
	public function license_page() {
		
		$response = null;
		if(isset( $_POST['submit'] )) {
			if( sanitize_text_field ( $_POST['action'] ) == "activate" ){
				$response = $this->activate_license();
			}elseif( sanitize_text_field ( $_POST['action'] ) == "deactivate" ){
				$response = $this->deactivate_license();
			}
		}

		$license_key = $this->get_license_key();
		$license = $this->get_license();
		$license_deactivate_reason = get_option('woo_yodlee_license_deactivated');
		require WC_YODLEE_PLUGIN_DIR_PATH . '/admin/includes/license.php';

    }

	/**
	 * Function to activate license
	 *
	 * @since    1.0.0
	 */
    public function activate_license($license_key=null) {

		$arrResponse = [
			'status' => false,
			'message' => 'Unknow error occured.',
			'reason' => false
		];

		// License key and domain
		if(!$license_key) {
			$license_key = ( isset( $_POST['license_key'] ) ) ? sanitize_text_field ( $_POST['license_key']) : '' ;
		}
		$domain = $_SERVER['SERVER_NAME'];
		if(!$license_key || !$domain) {
			return $arrResponse;
		}

		// Check Api response status
		$response = $this->api_check_license($license_key);
		if($response['result'] != 'success') {
			$arrResponse['message'] = $response['message'];
			return $arrResponse;
		}

		// Check license status from server
		if($response['status'] != 'active') {

			if($response['status'] == 'blocked') {
				$arrResponse['message'] = 'Your license is blocked.';	
				$arrResponse['reason'] = 'BLOCKED';
			}
			else if($response['status'] == 'expired') {
				$arrResponse['message'] = 'Your license is expired.';
				$arrResponse['reason'] = 'EXPIRED';
			}
			else {
				$arrResponse['message'] = 'Your license is not active.';
				$arrResponse['reason'] = 'PENDING';
			}
			return $arrResponse;
		}

		// Validate expiry date
		$expiry_date = $response['date_expiry'];
		$current_date = current_time('Y-m-d');
		if(strtotime($expiry_date) < strtotime($current_date)) {
			$arrResponse['message'] = 'Your license is expired.';
			$arrResponse['reason'] = 'EXPIRED';
			return $arrResponse;
		}

		// Check if license active for existing domain
		$domain_already_active = false;
		$registered_domains = $response['registered_domains'];
		foreach($registered_domains as $registered_domain) {
			if($registered_domain['registered_domain'] == $domain) {
				$domain_already_active = true;
			}
		}

		// Check if max domains limit is reached
		$max_domains = $response['max_allowed_domains'];
		if($max_domains) {

			if(!$domain_already_active) {
				if($max_domains <= count($registered_domains)) {
					$arrResponse['message'] = 'Maximum allowed domains reached.';
					$arrResponse['reason'] = 'MAX_DOMAINS';
					return $arrResponse;
				}
			}
		}

		// Activate the license on server
		if(!$domain_already_active) {
			$this->api_activate_license($license_key, $domain);
		}

		// Store the license in database
		$license = [];
		$license['license_key'] = $license_key;
		$license['registered_domain'] = $domain;
		$license['date_expiry'] = $expiry_date;
		$license['date_renewed'] = $response['date_renewed'];
		$license['date_created'] = $response['date_created'];
		$license['email'] = $response['email'];
		$this->store_license($license);

		$arrResponse['status'] = true;
		$arrResponse['message'] = 'License activated successfully.';

		return $arrResponse;

    }

	/**
	 * Function will deactivate the license
	 *
	 * @since    1.0.0
	 */
    public function deactivate_license() {
		
		$license_key = $this->get_license_key();
		$domain = $_SERVER['SERVER_NAME'];

		if($license_key) {
			$this->api_deactivate_license($license_key, $domain);
		}

		$this->remove_license();		
		
		return $response = [
			'status' => true,
			'message' => 'License deactivated successfully.'
		];
	}

	/**
	 * Api call to get license information from server
	 *
	 * @since    1.0.0
	 */
	public function api_check_license($license_key) {

		$api_params = array(
			'plm_action' => 'plm_check',
			'secret_key' => WC_YODLEE_LIC_VERIFICATION_KEY,
			'license_key' => $license_key
		);
		
		$response = wp_remote_get( add_query_arg( $api_params, WC_YODLEE_LIC_HOST_SERVER ), $this->remote_settings);

		if ( is_wp_error( $response ) ) {
			$license_data = array(
				'result' => 'error',
				'message' => "Unexpected Error! The query returned with an error."
			);
		}else{
			$license_data = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return $license_data;

	}

	/**
	 * Api call to activate license on server
	 *
	 * @since    1.0.0
	 */
	public function api_activate_license($license_key, $domain) {

		$api_params = array(
			'plm_action' => 'plm_activate',
			'secret_key' => WC_YODLEE_LIC_VERIFICATION_KEY,
			'license_key' => $license_key,
			'registered_domain' => $domain,
		);
		
		$response = wp_remote_get( add_query_arg( $api_params, WC_YODLEE_LIC_HOST_SERVER ), $this->remote_settings);

		if ( is_wp_error( $response ) ) {
			$license_data = array(
				'result' => 'error',
				'message' => "Unexpected Error! The query returned with an error."
			);
		}else{
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		}

		return $license_data;

	}

	/**
	 * Api call to deactivate license on server
	 *
	 * @since    1.0.0
	 */
	public function api_deactivate_license($license_key, $domain) {

		$api_params = array(
			'plm_action' => 'plm_deactivate',
			'secret_key' => WC_YODLEE_LIC_VERIFICATION_KEY,
			'license_key' => $license_key,
			'registered_domain' => $domain,
		);
		
		$response = wp_remote_get( add_query_arg( $api_params, WC_YODLEE_LIC_HOST_SERVER ), $this->remote_settings);

		if ( is_wp_error( $response ) ) {
			$license_data = array(
				'result' => 'error',
				'message' => "Unexpected Error! The query returned with an error."
			);
		}else{
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		}

		return $license_data;

	}

	/**
	 * Get license information
	 *
	 * @since    1.0.0
	 */
	public function get_license() {
		$license = get_option($this->option_key);
		if($license) {
			$license = unserialize(base64_decode($license));
		}
		return $license;
	}

	/**
	 * Get license key if valid
	 *
	 * @since    1.0.0
	 */
	public function get_license_key() {

		$license = $this->get_license();

		// License key
		if(!$license || !isset($license['license_key'])) {
			return false;
		}

		// Domain check
		$domain = $_SERVER['SERVER_NAME'];
		if($domain != $license['registered_domain']) {
			return false;
		}

		// Expiry date check
		$date_expiry = $license['date_expiry'];
		if($date_expiry) {
			$current_date = current_time('Y-m-d');
			if(strtotime($date_expiry) < strtotime($current_date)) {
				return false;
			}
		}
		return $license['license_key'];

	}

	public function store_license($license) {
		update_option($this->option_key, base64_encode(serialize($license)));
		delete_option('woo_yodlee_license_deactivated');
		return true;
	}

	public function remove_license() {
		delete_option($this->option_key);
		delete_option('woo_yodlee_license_deactivated');
		return true;
	}

}