<script type='text/javascript' src='https://cdn.yodlee.com/fastlink/v4/initialize.js'></script>
<style type="text/css">
.container_echeck {padding: 16px;background-color: #DFDCDE;}

input[type=text], input[type=password] {
  width: 100%;
  padding: 15px;
  margin: 5px 0 22px 0;
  display: inline-block;
  border: none;
  background: #f1f1f1;
}
.proceed {
  background-color: #04AA6D;
  color: white;
  padding: 16px 20px;
  margin: 8px 0;
  border: none;
  cursor: pointer;
  width: 100%;
  opacity: 0.9;
}

.proceed:hover {opacity: 1;}
</style>

<div id="container-fastlink">
    <input type="button" id="btn-fastlink" value="Pay" style="margin-bottom: 15px;display: none;">
</div>

<div id="echeck_div" style="<?php if($echeck_option == 'no'){ echo 'display: none;'; } ?>">    
    <form id="form_echeck" method="POST">
        <div class="container_echeck">
            <h1>eCheck Details</h1>
            
            <label for="routing_number"><b>Routing Number<span style="color:#F00">*</span> (9 digits)</b></label>
            <input type="text" placeholder="123456789" name="routing_number" id="routing_number" required>

            <label for="account_number"><b>Account Number<span style="color:#F00">*</span> (3-17 digits)</b></label>
            <input type="text" placeholder="123456789" name="account_number" id="account_number" required>            

            <button type="submit" class="proceed" id="proceed">Proceed</button>
        </div>        
    </form>
</div>

<form style="display:none;" method="POST" action="<?php echo $responseURL; ?>" id="woo_yodly_payment_form">
    <input type="hidden" name="order_key" id="woo_yoldy_order_id" value="<?php echo $order->get_order_key(); ?>">
    <input type="hidden" name="provider_account_id" id="woo_yoldy_provider_account_id">
    <input type="hidden" name="provider_id" id="woo_yoldy_provider_id">
    <input type="hidden" name="provider_name" id="woo_yoldy_provider_name">
    <input type="hidden" name="request_id" id="woo_yoldy_request_id">
    <input type="hidden" name="sites_array" id="woo_yoldy_sites_array">
    <input type="hidden" name="echeck_routing_number" id="echeck_routing_number">
    <input type="hidden" name="echeck_account_number" id="echeck_account_number">    
</form>

<script>

//    jQuery('html, body').animate({
//        scrollTop: jQuery("#scroll_p_tag").offset().top
//    }, 2000);

    var echeck_option = "<?php echo $echeck_option; ?>";

    jQuery("#form_echeck").submit(function(event)
    {
        event.preventDefault();
        jQuery('#echeck_routing_number').val(jQuery('#routing_number').val());
        jQuery('#echeck_account_number').val(jQuery('#account_number').val());
        jQuery('#echeck_div').hide();
        open_fastlink_popup();
    });

    (function (window) {
        //Open FastLink
        var fastlinkBtn = document.getElementById('btn-fastlink');
        fastlinkBtn.addEventListener(
                'click',
                function () {
                    open_fastlink_popup();
                },
                false
                );

    }(window));

    function open_fastlink_popup() {

        window.fastlink.open({
            fastLinkURL: '<?php echo $fastlink_url; ?>',
            accessToken: 'Bearer <?php echo $accessToken; ?>',
            params: {
                configName: 'AggregationPlusVerification'
            },
            onSuccess: function (data) {

                // will be called on success. For list of possible message, refer to onSuccess(data) Method.
                console.log('Success');
                console.log(data);
                // if(data.status == "SUCCESS") {
                //     jQuery('#woo_yoldy_provider_account_id').val(data.providerAccountId);
                //     jQuery('#woo_yoldy_provider_id').val(data.providerId);
                //     jQuery('#woo_yoldy_provider_name').val(data.providerName);
                //     jQuery('#woo_yoldy_request_id').val(data.requestId);

                //     jQuery('#woo_yodly_payment_form').submit();
                // }
                // else {
                //     alert('Something went wrong, your payment was unsuccessful.');
                // }
            },
            onError: function (data) {
                // will be called on error. For list of possible message, refer to onError(data) Method.
                console.log('Error');
                console.log(data);
                // alert('Something went wrong, your payment was unsuccessful.');
            },
            onClose: function (data) {
                // will be called called to close FastLink. For list of possible message, refer to onClose(data) Method.
                console.log('Close');
                console.log(data);

                jQuery('#woo_yoldy_sites_array').val(JSON.stringify(data.sites));
                jQuery('#woo_yodly_payment_form').submit();

                // alert('Something went wrong, your payment was unsuccessful.');
            },
            onEvent: function (data) {
                // will be called on intermittent status update.
                console.log('Event');
                console.log(data);
            }
        },
                'container-fastlink');
    }

    jQuery(function ()
    {
        if (echeck_option == 'no')
        {
            open_fastlink_popup();
        }
    })
</script>

<!-- <script type="text/javascript">
    jQuery(document).ready(function () {

        jQuery("body").block({
            message: "<?php echo __('Thank you for your order. We are now redirecting you for payment.', 'woo_yodlee_payment'); ?>",
            overlayCSS:
            {
                background: "#fff",
                opacity: 0.6
            },
            css: {
                padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait"
            }
        });

        jQuery('#paymentForm').submit();
    });
</script> -->
