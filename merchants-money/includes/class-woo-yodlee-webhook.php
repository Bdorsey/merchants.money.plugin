<?php
class Woo_Yodlee_Webhook {

    protected $namespace = 'woo-yodlee-custom/v1';

    protected $notification_email = '';

    public function __construct() {
        
        $this->notification_email = get_option('woo_904_webhook_notification_email');

    }

    public function register_all_routes() {

        // 1. Update tracking information
        register_rest_route( $this->namespace, 'tracking', array(
            'methods' => 'POST',
            'callback' => function($rest) {
                $this->call_action($rest, 'update_tracking');
            },
            'permission_callback' => '__return_true'
        ));

    }

    public function call_action($rest, $action) {
        call_user_func([$this, $action], $rest);
    }

    public function update_tracking() 
    {        
        // Get raw data
        $raw_data = file_get_contents('php://input');        

        if(!trim($raw_data)) {
            $this->responseError(['msg' => 'No data received in request.'], 400);
        }
        
        $details = json_decode($raw_data,true);
        
        $order_id = $details['order_id'];
        $status = $details['status'];
        
        $order = new WC_Order($order_id);
        
        if($status == 2)
        {
            $order->update_status('failed');
        }
        else
        {
            $order->update_status('processing');
        }
        
        $this->responseSuccess(['msg' => 'Tracking information updated successfully.'], 200);

    }

    public function responseSuccess($response, $code) {

        error_log("\n".$code.': '.json_encode($response)."\n");

        $resp = [
            'status' => true,
            'data' => $response
        ];
        wp_send_json($resp, $code);
    }
}