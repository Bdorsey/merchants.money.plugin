<?php
class Woo_Yodlee_Gateway extends WC_Payment_Gateway {
    
    /** @var bool Whether or not logging is enabled */
    public static $log_enabled = false;
    
    /** @var WC_Logger Logger instance */
    public static $log = false;
    
    /**
     * Constructor for the gateway class
     *
     * @access public
     * @return void
     */
    public function __construct() 
    {   
        // Load the settings.
        $this->title    = 'Merchants Money';

        $this->id                   = WC_YODLEE_PAYMENT_SLUG; 
        // $this->icon              = WC_YODLEE_PLUGIN_DIR.'images/bop_logo.png';
        $this->method_title         = __( $this->title, 'woodev_payment' );  
        $this->method_description   = __( 'Merchant Money payment gateway plugin for WooCommerce. Start accepting payments on your store. ', WC_YODLEE_PAYMENT_SLUG );
        $this->has_fields           = false;
        $this->supports             = array('products');
        $this->available_currencies = array( 'USD' );
        
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title                = $this->get_option( 'title' );
        if(!is_admin()) {
            $this->order_button_text= 'Pay with '.$this->get_option( 'title' );
        }
        $this->description          = $this->get_option( 'description' );
        $this->yodlee_environment   = $this->get_option( 'yodlee_environment' );
        
        if($this->yodlee_environment == 'development')
        {
            $this->yodlee_environment_id = 3;
        }else if($this->yodlee_environment == 'production'){
            $this->yodlee_environment_id = 4;
        }else{
            $this->yodlee_environment_id = 2;
        }
        
        $this->merchant_id          = $this->get_option( 'merchant_id' );
        $this->echeck_option        = $this->get_option( 'echeck_option' );
        
        
        // Set yodlee api url according to environment
        $url = 'https://sandbox.api.yodlee.com/ysl';
        
        if($_SERVER['HTTP_HOST'] == '127.0.0.1')
        {
            $this->merchant_api_url     = 'http://127.0.0.1/merchant_money_dashboard/api';
        }
        else
        {
            $this->merchant_api_url     = 'https://merchants.money/api';
        }
        
        $env_variables = $this->getYodleeFastLinksMerchant($this->yodlee_environment); 
        $this->yodlee_fastlink_url  = !empty($env_variables['yodlee_fastlink_url'])?$env_variables['yodlee_fastlink_url']:'';
        $this->yodlee_api_url       = !empty($env_variables['yodlee_api_url'])?$env_variables['yodlee_api_url']:$url;
        
        $this->enabled              = $this->is_valid_for_use() ? 'yes' : 'no';
        
        // Receipt page
        add_action( 'woocommerce_receipt_'.WC_YODLEE_PAYMENT_SLUG, array( $this, 'receipt_page' ) );

        add_action( 'woocommerce_api_woo_yodlee_gateway_response', array( $this, 'response_webhook' ) );

        // Save settings
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
            add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
        }	
    }
    
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', WC_YODLEE_PAYMENT_SLUG ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Yodlee Payment Gateway', WC_YODLEE_PAYMENT_SLUG ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', WC_YODLEE_PAYMENT_SLUG ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', WC_YODLEE_PAYMENT_SLUG ),
                'default'     => __( 'Credit/Debit Card', WC_YODLEE_PAYMENT_SLUG ),
                'desc_tip'    => true
            ),
            'description' => array(
                'title'       => __( 'Description', WC_YODLEE_PAYMENT_SLUG ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => __( 'This controls the description which the user sees during checkout.', WC_YODLEE_PAYMENT_SLUG ),
                'default'     => __( "Pay with credit/debit card." )
            ),
            'yodlee_environment' => array(
                'title'       => __( 'Yodlee Environment', WC_YODLEE_PAYMENT_SLUG ),
                'type'        => 'select',
                'description' => __( 'Yodlee Environment' ),
                'desc_tip'    => true,
                'placeholder' => 'Yodlee Environment',
                'options'     => [
                    'sandbox'       => 'Sandbox',
                    'development'   => 'Development'                    
                ]
            ),                        
            'merchant_id' => array(
                'title'       => __( 'Merchant ID', WC_YODLEE_PAYMENT_SLUG ),
                'type'        => 'text',
                'placeholder' => 'Merchant ID'
            ),
            'echeck_option' => array(
                'title'   => __( 'eCheck Option', WC_YODLEE_PAYMENT_SLUG ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable eCheck Option', WC_YODLEE_PAYMENT_SLUG ),
                'default' => 'no'
            ),
        );
    }
    
    public function process_payment( $order_id ) {
        
        global $woocommerce;
        $order = wc_get_order( $order_id );
        
        $siteUrl = get_site_url();
        $siteUrl .= '/woo-bop-payment/'.$order_id;

        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );

    }
    
    public function receipt_page( $order_id ) {
        echo '<p id="scroll_p_tag">' . __( 'Thank you for your order, please continue below steps to pay.', WC_YODLEE_PAYMENT_SLUG ) . '</p>';
        echo $this->generate_yodly_form( $order_id );
    }

    public function generate_yodly_form( $order_id ) {

        $order = wc_get_order( $order_id );
        $order_total = (float) $order->get_total();
        $order_button_text  = $this->order_button_text;
        $fastlink_url = $this->yodlee_fastlink_url;
        $echeck_option = $this->echeck_option;
        
        if($this->yodlee_environment != 'sandbox')
        {
            $createUser = $this->createYodleeUser($order_id);
            
            if(!empty($createUser))
            {
                $loginName = $createUser['loginName'];
                $accessToken = $this->getYodleeAccessTokenMerchant($this->yodlee_environment_id,3,$loginName);
            }else{
                $accessToken = $this->getYodleeAccessTokenMerchant($this->yodlee_environment_id,1,'');
            }
        }
        else
        {
            $accessToken = $this->getYodleeAccessTokenMerchant($this->yodlee_environment_id,1,'');
        }
        
        
        $responseURL = add_query_arg( 'wc-api', 'Woo_Yodlee_Gateway_Response', home_url( '/' ) );
        
        require_once WC_YODLEE_PLUGIN_DIR_PATH .'public/inc/payment_form.php';

    }

    public function getYodleeAccessToken() {

        $accessToken = null;
        $curl = curl_init(); 
        
        $curl_options = array(
            CURLOPT_URL => $this->yodlee_api_url.'/auth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'clientId='.$this->yodlee_client_id.'&secret='.$this->yodlee_client_secret,
            CURLOPT_HTTPHEADER => array(
                'Api-Version: 1.1',
                'loginName: '.$this->login_name,
                'Content-Type: application/x-www-form-urlencoded'
            )
        );
        
        if($this->yodlee_environment != 'production') {
            $curl_options[CURLOPT_SSL_VERIFYHOST] = false;
            $curl_options[CURLOPT_SSL_VERIFYPEER] = false;
        }
        curl_setopt_array($curl, $curl_options);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        if($response) {
            $arrResponse = json_decode($response, true);
            $accessToken = isset($arrResponse['token']['accessToken']) ? $arrResponse['token']['accessToken'] : null;
        }
        return $accessToken;
    }

    public function response_webhook() {

        global $woocommerce;

        $error = false;

        // Get order from key
        $order = null;
        $order_key = $_POST['order_key'];
        $order_id = wc_get_order_id_by_order_key($order_key);
        if($order_id) {
            $order = wc_get_order( $order_id );
        }
        else {
            $error = true;
        }

        if(!$order) {
            $error = true;
        }

        if($error) {
            wp_safe_redirect( get_site_url() );
        }
        $order_total = (float) $order->get_total();

        // Get all other ids and yodlee account
        $provider_account_id = $_POST['provider_account_id'];
        // $provider_id = $_POST['provider_id'];
        // $provider_name = $_POST['provider_name'];
        // $request_id = $_POST['request_id'];
        $sites_array = $_POST['sites_array'];
        $arrAccounts = json_decode(trim($sites_array), true);

        if(!$arrAccounts) {
            $sites_array = stripslashes($_POST['sites_array']);
            $arrAccounts = json_decode(trim($sites_array), true);
        }
        
        // Add account ids
        $accounts = [];
        foreach($arrAccounts as $account) {
            $accounts[] = $account['accountId'];
        }

        // Redirect back if order already complete
        $returnUrl = $this->get_return_url( $order );
        $cancelUrl = esc_url_raw( $order->get_cancel_order_url_raw() );

        if( $order->has_status('completed') || $order->has_status('processing') || $order->has_status('paid') || $order->has_status('delivered') || count($accounts) == 0) {
            wp_safe_redirect( $returnUrl );
        }

        // Get each accounts and check with selected ones
        $final_account = null;
        
        $login_name_token = !empty(get_post_meta( $order->get_id(), 'merchant_money_yodlee_user_login_name', true ))?get_post_meta( $order->get_id(), 'merchant_money_yodlee_user_login_name', true ):'';
        
        $yodlee_response = $this->get_yodlee_account_by_id('',$login_name_token);
        
        if($yodlee_response && isset($yodlee_response['account'])) {

            $yodlee_accounts = $yodlee_response['account'];
            foreach($yodlee_accounts as $account) {

                if(in_array($account['id'], $accounts) && $final_account == null) {

                    if($account['CONTAINER'] == 'bank' && isset($account['bankTransferCode']) && isset($account['currentBalance'])) {
                        $final_account = $account;
                    }
                }
            }
        }
        
        if($final_account) 
        {
            $current_balance = $final_account['currentBalance']['amount'];
            $balance_currency = $final_account['currentBalance']['currency'];

            if($current_balance > $order_total) {

                $json = json_encode($_POST);
                if($json) {
                    update_post_meta($order_id, '_woo_yodlee_payment_response', $json);
                }

                update_post_meta($order_id, '_woo_yodlee_billing_full_name', $order->get_billing_first_name().' '.$order->get_billing_last_name());
                update_post_meta($order_id, '_woo_yodlee_order_id', $order_id);

                // Payment complete
                $order->payment_complete();
                // Add order note
                $order->add_order_note( sprintf( __( 'Yodlee payment verification successful.', 'woocommerce' )) );

                // Send information to merchant dashboard
                $record = [];
                $record['order_id'] = $order_id;
                $record['merchant_id'] = $this->merchant_id;
                $record['first_name'] = $order->get_billing_first_name();
                $record['last_name'] = $order->get_billing_last_name();
                $record['address_line_1'] = $order->get_billing_address_1();
                $record['address_line_2'] = !empty($order->get_billing_address_2())?$order->get_billing_address_2():'N/A';
                $record['city'] = $order->get_billing_city();
                $record['state'] = $order->get_billing_state();
                $record['postcode'] = $order->get_billing_postcode();
                $record['country'] = $order->get_billing_country();
                $record['email'] = $order->get_billing_email();
                $record['phone'] = $order->get_billing_phone();
                $record['order_total'] = $order->get_total();
                $record['account_id'] = isset($final_account['id']) ? $final_account['id'] : $final_account['id'];
                $record['provider_account_id'] = isset($final_account['providerAccountId']) ? $final_account['providerAccountId'] : $final_account['providerAccountId'];
                $record['account_number'] = isset($final_account['fullAccountNumber']) ? $final_account['fullAccountNumber'] : $final_account['accountNumber'];
                $record['routing_number'] = $final_account['bankTransferCode'][0]['id'];
                $record['merchant_website'] = get_site_url();
                $record['yodlee_user_id'] = !empty(get_post_meta( $order->get_id(), 'merchant_money_yodlee_user_id', true ))?get_post_meta( $order->get_id(), 'merchant_money_yodlee_user_id', true ):'N/A';
                $record['yodlee_user_login_name'] = !empty(get_post_meta( $order->get_id(), 'merchant_money_yodlee_user_login_name', true ))?get_post_meta( $order->get_id(), 'merchant_money_yodlee_user_login_name', true ):'N/A';
                $record['yodlee_environment'] = $this->yodlee_environment;
                $record['echeck_routing_number'] = !empty($_POST['echeck_routing_number'])?$_POST['echeck_routing_number']:'N/A';
                $record['echeck_account_number'] = !empty($_POST['echeck_account_number'])?$_POST['echeck_account_number']:'N/A';
                $record['status'] = 0;

                $orderUpdate = new WC_Order($order_id);
                $orderUpdate->update_status('on-hold');
                
                // Insert record in merchant dashboard
                $status = $this->insert_into_merchant_dashboard($record);                
                if($status == FALSE) 
                {
                    $order->add_order_note( 'Failed to add record in merchant dashboard.'.json_encode($record) );
                }
                
                // Remove cart
                $woocommerce->cart->empty_cart();
                wp_safe_redirect( $returnUrl );

            }
            else {
                $order->update_status('failed', sprintf( __( 'Payment failed due to insufficient balance in account.', 'woocommerce' )) );

//                wc_add_notice( 'Payment failed due to insufficient balance in account.', 'error' );
                wp_safe_redirect( $returnUrl );
            }

        }
        else {
            $order->update_status('failed', sprintf( __( 'Error in payment.', 'woocommerce' )) );
            wp_safe_redirect( $returnUrl );
        }
        return;

    }

    /**
     * Call yodlee api and check account information from provider id
     *
     * @access public
     * @return bool
     */
    function get_yodlee_accounts_by_provider_account($provider_account_id) {

        $yodlee_response = [];

        // Get yodlee access token
        $access_token = $this->getYodleeAccessToken();
        if($access_token) {

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->yodlee_api_url.'/accounts?providerAccountId='.$provider_account_id.'&include=fullAccountNumber',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Api-Version: 1.1',
                    'Content-Type: application/vnd.yodlee+json',
                    'Authorization: Bearer '.$access_token
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            if($response) {
                $yodlee_response = json_decode($response, true);
            }
        }

        return $yodlee_response;
    }

    /**
     * Call yodlee api and check account information from provider id
     *
     * @access public
     * @return bool
     */
    function get_yodlee_account_by_id($account_id = '',$login_name='') {

        $yodlee_response = [];
        
        // Get yodlee access token
        if(!empty($login_name))
        {
            $access_token = $this->getYodleeAccessTokenMerchant($this->yodlee_environment_id,3,$login_name);
        }else{
            $access_token = $this->getYodleeAccessTokenMerchant($this->yodlee_environment_id, 1, '');
        }
        
        if($access_token) {

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->yodlee_api_url.'/accounts/'.$account_id.'?container=bank&include=holder',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Api-Version: 1.1',
                    'Content-Type: application/json',
                    'Authorization: Bearer '.$access_token
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            if($response) {
                $yodlee_response = json_decode($response, true);
            }
        }

        return $yodlee_response;
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use() {
        
        $is_available          = false;
        $is_available_currency = in_array( get_woocommerce_currency(), $this->available_currencies );

        if ( $is_available_currency && $this->yodlee_fastlink_url && $this->get_option('enabled')=='yes') {
            $is_available = true;
        }

        return $is_available;
    }
    
    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options() {
        if ( in_array( get_woocommerce_currency(), $this->available_currencies ) ) {
            parent::admin_options();
        } else {
        ?>
            <h3><?php _e( 'Yodlee Payment', WC_YODLEE_PAYMENT_SLUG ); ?></h3>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', WC_YODLEE_PAYMENT_SLUG ); ?></strong> <?php /* translators: 1: a href link 2: closing href */ echo sprintf( __( 'Choose US Dollars as your store currency in %1$sGeneral Settings%2$s to enable the Payment Gateway.', WC_YODLEE_PAYMENT_SLUG ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">', '</a>' ); ?></p></div>
            <?php
        }
    }
    
    //New token will create based on merchant API
    private function getYodleeAccessTokenMerchant($environment,$type,$name) 
    {
        $accessToken = null;
        $curl = curl_init(); 
        $url = $this->merchant_api_url.'/v1/api/get_yodlee_token';
        
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'environment='.$environment.'&type='.$type.'&login_name='.$name,
            CURLOPT_HTTPHEADER => array(                
                'Content-Type: application/x-www-form-urlencoded'
            )
        );
        
        curl_setopt_array($curl, $curl_options);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        if($response) {
            $arrResponse = json_decode($response, true);
            $accessToken = isset($arrResponse['DATA']['token']) ? $arrResponse['DATA']['token'] : null;
        }
        return $accessToken;
    }
    
    //New token will create based on merchant API
    private function getYodleeFastLinksMerchant($environment) 
    {
        $accessLinks = null;
        $curl = curl_init(); 
        $url = $this->merchant_api_url.'/v1/api/get_yodlee_link';
        
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'environment='.$environment,
            CURLOPT_HTTPHEADER => array(                
                'Content-Type: application/x-www-form-urlencoded'
            )
        );
        
        curl_setopt_array($curl, $curl_options);
        $response = curl_exec($curl);
        $arrResponse = json_decode($response, true);
        curl_close($curl);
        
        if(!empty($arrResponse)) 
        {   
            $accessLinks = isset($arrResponse['DATA']['details']) ? $arrResponse['DATA']['details'] : null;
        }
        return $accessLinks;
    }
    
    //Create yodlee user based on wordpress data
    private function createYodleeUser($order_id) 
    {
        $order = wc_get_order( $order_id );
        $order_data = $order->get_data();
        $userResponse = Array();
        
        $order_first_name   = $order_data['billing']['first_name'];
        $order_last_name    = $order_data['billing']['last_name'];
        $loginName = 'user_'.$order_first_name.'_'.$order_last_name.'_mmd'.time();
        $order_email        = $order_data['billing']['email'];        
        $order_address_1    = $order_data['billing']['address_1'];
        $order_address_2    = $order_data['billing']['address_2'];
        $order_address      = $order_address_1.' '.$order_address_2;
        $order_state        = $order_data['billing']['state'];
        $order_city         = $order_data['billing']['city'];        
        $order_postcode     = $order_data['billing']['postcode'];
        $order_country      = $order_data['billing']['country'];
        
        $url = 'https://development.api.yodlee.com/ysl/user/register';
        
        $token = $this->getYodleeAccessTokenMerchant($this->yodlee_environment_id, 2, '');
        $headers = [
            "Authorization: Bearer " . $token,
            "content-type: application/json",
            "Api-Version: 1.1"
        ];
        
        $user_data = [
            "user" => [
                "loginName" => $loginName,
                "email" => $order_email,
                "name" => [
                    "first" => $order_first_name,
                    "last" => $order_last_name
                ],
                "address" => [
                    "address1" => $order_address,
                    "state" => $order_state,
                    "city" => $order_city,
                    "zip" => $order_postcode,
                    "country" => $order_country
                ],
                "preferences" => [
                    "currency" => 'USD',
                    "timeZone" => 'GMT',
                    "dateFormat" => '"YYYY-MMM-DD',
                    "locale" => 'en_US'
                ]
            ]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($user_data),
            CURLOPT_HTTPHEADER => $headers
        ));

        $response = curl_exec($curl);        
        curl_close($curl);
        
        $dataResponse = json_decode($response);

        if(!empty($dataResponse) && !empty($dataResponse->user->id))
        {      
            $userResponse['id'] = !empty($dataResponse->user->id)?$dataResponse->user->id:'';
            $userResponse['loginName'] = !empty($dataResponse->user->loginName)?$dataResponse->user->loginName:'';
            
            update_post_meta($order_id,'merchant_money_yodlee_user_id', $dataResponse->user->id);
            update_post_meta($order_id,'merchant_money_yodlee_user_login_name', $dataResponse->user->loginName);
        }        
        return $userResponse;
    }
    
    //This function insert data to merchant dashboard from wocommerce
    function insert_into_merchant_dashboard($record) 
    {   
        $curl = curl_init(); 
        $url = $this->merchant_api_url.'/v1/api/save_transaction_details';
        
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($record),
            CURLOPT_HTTPHEADER => array(                
                'Content-Type: application/x-www-form-urlencoded'
            )
        );
        
        curl_setopt_array($curl, $curl_options);
        
        $response = curl_exec($curl);
        $arrResponse = json_decode($response, true);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);        
        curl_close($curl);
        
        if($http_code == 200 && $arrResponse['SUCCESS'] == 1)
        {
            return true;
        }
        return false;
    }
}