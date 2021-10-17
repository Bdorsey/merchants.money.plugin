<?php
    require_once WC_YODLEE_PLUGIN_DIR_PATH . '/admin/class-woo-yodlee-orders-table.php';
?>
<style>
    .column-sr_no {
        width: 40px;
    }
</style>
<div class="wrap">
    <h1>Yodlee Orders</h1>
    <?php
    if(woo_yodlee_wc_check()) {
        // include __DIR__.'/includes/navigation.php';
		$pmc_fs_table = new Woo_Yodlee_Orders_Table();
		$pmc_fs_table->prepare_items();
        echo '<br/>';
		$pmc_fs_table->views();
        echo '<form method="get">';	
        echo '<input type="hidden" name="page" value="'.$_REQUEST['page'].'" />';
        echo '<input type="hidden" name="view" value="'.(isset($_REQUEST['view'])?$_REQUEST['view']:'').'" />';
		$pmc_fs_table->search_box( 'Search', 'search_id' );
		$pmc_fs_table->display();  
        echo '</form>';
    }
    else {
    ?>
        <div class="notice notice-error">
            <p>The plugin requires woocommerce installed.</p>
        </div>
    <?php
    }
    ?>
</div>