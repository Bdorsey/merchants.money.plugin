    <div class="wrap">
			<h1 class="wp-heading">	Yodlee Payment Gateway License</h1>
			<form method="post">
                <table class="form-table">		
                    <tbody>
				        <tr valign="top">
			                <th scope="row" class="titledesc">
				                <label for="">License Key <span class="woocommerce-help-tip"></span></label>
			                </th>
			                <td class="forminp">
                                <?php
                                if($license_key) {
                                    echo '<p>XXXXXXXX'.substr($license_key,8).'</p>';
                                    ?>
                                    <input type="hidden" name="action" value="deactivate">
                                    <?php
                                }
                                else {
                                ?>
                                    <input id="license_key" type="text" name="license_key" value="" style="min-width:400px;" required="required">
                                    <input type="hidden" name="action" value="activate">
                                    <br/><small>Enter you license key you received and activate your license</small></p>
                                
                                <?php
                                }

                                if($response) {
                                    $color = '#AA0000';
                                    if($response['status']) {
                                        $color = '#00AA00';
                                    }
                                    ?>
                                    <p style="color: <?php echo $color; ?>"><?php echo $response['message']; ?>
                                    <?php
                                }

                                
                                if($license_deactivate_reason) {
                                ?>
                                    <p style="color: #AA0000"><?php echo $license_deactivate_reason; ?>
                                <?php
                                }
                                ?>
			                </td>
		                </tr>
                        <?php
                            if($license_key) {
                            ?>
                            <tr valign="top">
                                <th scope="row" class="titledesc">
                                    <label for="">Status <span class="woocommerce-help-tip"></span></label>
                                </th>
                                <td class="forminp">
                                    <span style="color: #00AA00;">Active</span>
                                </td>
                            </tr>
                            <?php
                                if($license['email']) {
                                ?>
                                <tr valign="top">
                                    <th scope="row" class="titledesc">
                                        <label for="">Email <span class="woocommerce-help-tip"></span></label>
                                    </th>
                                    <td class="forminp">
                                        <?php echo $license['email']; ?>
                                    </td>
                                </tr>
                                <?php
                                }

                                if($license['date_created']) {
                                ?>
                                <tr valign="top">
                                    <th scope="row" class="titledesc">
                                        <label for="">Date Created <span class="woocommerce-help-tip"></span></label>
                                    </th>
                                    <td class="forminp">
                                        <?php echo date('jS M, Y', strtotime($license['date_created'])); ?>
                                    </td>
                                </tr>
                                <?php
                                }

                                if($license['date_renewed']) {
                                ?>
                                <tr valign="top">
                                    <th scope="row" class="titledesc">
                                        <label for="">Date Renewed <span class="woocommerce-help-tip"></span></label>
                                    </th>
                                    <td class="forminp">
                                        <?php echo date('jS M, Y', strtotime($license['date_renewed'])); ?>
                                    </td>
                                </tr>
                                <?php
                                }

                                if($license['date_expiry']) {
                                ?>
                                <tr valign="top">
                                    <th scope="row" class="titledesc">
                                        <label for="">Date of Expiry <span class="woocommerce-help-tip"></span></label>
                                    </th>
                                    <td class="forminp">
                                        <?php echo date('jS M, Y', strtotime($license['date_expiry'])); ?>
                                    </td>
                                </tr>
                                <?php
                                }
                            }
                        ?>
                        <tr valign="top">
			                <th scope="row" class="">
                                
                            </th>
                            <td>
                                <input type="submit" name="submit" class="button" value="<?php echo (!$license_key)?'Activate':'Deactivate'; ?> License">
                            </td>
                        </tr>
		            </tbody>
                </table>
			</form>
		</div>