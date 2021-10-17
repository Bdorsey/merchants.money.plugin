<?php
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Woo_Yodlee_Orders_Table extends WP_List_Table 
{
	
	function __construct(){
		parent::__construct( array(
			'ajax'      => false
        ));
	}


	/**
	 * Add columns to grid view
	 */
	function get_columns(){
		$columns = array(		
            'sr_no'       => '#',
            'order_id'    => 'Order',
            'reference_id'    => 'Reference ID',
            'created_at'    => 'Created At',
            'status'      => 'Status',
            'total'     => 'Total',
		);
		return $columns;
	}	

	function column_default( $item, $column_name ) {

        $order = new WC_Order($item->ID);
		switch( $column_name ) { 
            
            case 'sr_no':
                return $item->sr_no;

            case 'created_at':
                $date = $order->get_date_created();
                return $date->date_i18n( get_option( 'date_format' ) ). ' <br/><span style="color:silver">' . $date->date_i18n( get_option( 'time_format' ) ).'</span>';

            case 'total':
                $qty = '';
                $order_items = $order->get_items();
                $qty = count($order_items);
                if($qty==1) {
                    $qty.=' Item';
                }
                else {
                    $qty.=' Items';
                }
                return $order->get_formatted_order_total().'<br><span style="color:silver">'.$qty.'</span>';

            case 'status':
                return wc_get_order_status_name($order->get_status());

            case 'order_id':
                return '<a href="'.get_edit_post_link($item->ID).'"><strong>#'.$item->ID.' '.$order->get_billing_first_name().' '.$order->get_billing_last_name().'</strong></a>';

            case 'reference_id':
                $reference_id = get_post_meta($item->ID, '_woo_yodlee_payment_ref', true);
                if($reference_id) {
                    return '#'.$reference_id;
                }
                return '-';

		    default:
		        return print_r( $item, true );
		}
	}			
    
	protected function get_views() { 

        global $wpdb;
        $query = "SELECT p.post_status, COUNT( * ) AS num_posts 
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm
                    ON p.ID=pm.post_id
                    WHERE p.post_type = 'shop_order'
                    AND p.post_status !=  'trash'
                    AND pm.meta_key='_payment_method'
                    AND pm.meta_value='".WC_YODLEE_PAYMENT_SLUG."'";
        $query .= ' GROUP BY p.post_status';
    
        $results = (array) $wpdb->get_results($query);
        $post_counts = [];
        $all = 0;
        if($results) {
            foreach($results as $result) {
                $post_counts[$result->post_status] = $result->num_posts;
                $all = $all + $result->num_posts;
            }
        }
        
        $views = array();
        $current = ( !empty($_REQUEST['view']) ? $_REQUEST['view'] : 'all');

        // All link
        $class = ($current == 'all' ? ' class="current"' :'');
        $all_url = remove_query_arg('view');
        $views['all'] = "<a href='{$all_url }' {$class} >All ({$all})</a>";

        // Processing
        $foo_url = add_query_arg('view','processing');
        $class = ($current == 'processing' ? ' class="current"' :'');
        $views['processing'] = "<a href='{$foo_url}' {$class} >Processing (".(isset($post_counts['wc-processing'])?$post_counts['wc-processing']:0).")</a>";

        // Pending Payment
        $bar_url = add_query_arg('view','pending');
        $class = ($current == 'pending' ? ' class="current"' :'');
        $views['pending'] = "<a href='{$bar_url}' {$class} >Pending Payment (".(isset($post_counts['wc-pending'])?$post_counts['wc-pending']:0).")</a>";

        // Completed
        $bar_url = add_query_arg('view','completed');
        $class = ($current == 'completed' ? ' class="current"' :'');
        $views['completed'] = "<a href='{$bar_url}' {$class} >Completed (".(isset($post_counts['wc-completed'])?$post_counts['wc-completed']:0).")</a>";

        // Failed
        $bar_url = add_query_arg('view','failed');
        $class = ($current == 'failed' ? ' class="current"' :'');
        $views['failed'] = "<a href='{$bar_url}' {$class} >Failed (".(isset($post_counts['wc-failed'])?$post_counts['wc-failed']:0).")</a>";

        // Cancelled
        $bar_url = add_query_arg('view','cancelled');
        $class = ($current == 'cancelled' ? ' class="current"' :'');
        $views['cancelled'] = "<a href='{$bar_url}' {$class} >Cancelled (".(isset($post_counts['wc-cancelled'])?$post_counts['wc-cancelled']:0).")</a>";

	    return $views;
	}
	
	function get_sortable_columns() {
		$sortable_columns = array(
            'created_at'  => array('post_date',false)
		);
		return $sortable_columns;
	}	

	/**
	 * Prepare admin view
	 */	
	function prepare_items() {
		
        $search = '';
        if(isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $search = $_REQUEST['s'];
        }
        
        //Retrieve $customvar for use in query to get items.
        $status = '';
        $view = ( isset($_REQUEST['view']) ? $_REQUEST['view'] : 'all');
        if($view!='all' && !empty($view)) {
            $status = 'wc-'.$view;
        }
        else {
            $status = array_keys( wc_get_order_statuses() );
        }
		
		// Order by
        $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'post_date';

	  	// If no order, default to desc
	  	$order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'desc';
        
        $per_page = 50;
        $current_page = $this->get_pagenum();

        $args = [
            'post_type' => wc_get_order_types(),
            'post_status' => $status,
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'   => '_payment_method',
                    'value' => WC_YODLEE_PAYMENT_SLUG,
                    'compare' => '='
                ]
            ]
        ];

        // If search keyword added
        if($search!='') {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'   => '_woo_yodlee_payment_ref',
                    'value' => $search,
                    'compare' => 'LIKE'
                ],
                [
                    'key'   => '_billing_email',
                    'value' => $search,
                    'compare' => '='
                ]
            ];
        }

        $query = new WP_Query($args);
        $items = $query->get_posts();
        
		$columns = $this->get_columns();
        $hidden = array();
        
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);	
        
        $sr_no = 1;
        if($current_page>1) {
            $sr_no = ($current_page-1)*($per_page) + 1;
        }
        foreach($items as $key=>$item) {
            $items[$key]->sr_no=$sr_no;
            $sr_no++;
        }
        
		$this->items = $items;

		// Set the pagination
		$this->set_pagination_args( array(
			'total_items' => $query->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $query->max_num_pages
		) );
	}

}