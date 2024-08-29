<?php
/**
 * Download token generation form
 * 
 * @author Callistus
 * @since 1.0.0
 * @package smliser\templates
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="smliser-gen-token-form-container">
    
    <div class="smliser-token-body" id="smliser-token-body">
        <span id="remove">&times;</span>
        <h2>Generate Download Token</h2>
        <hr>

        <span class="smliser-loader" style="display: none; color:#000000;" id="spinner"></span>
    
        <p>The token will be valid to download "<strong><?php echo esc_html( $plugin_name ); ?></strong>", token will expire in 10 days if no expiry date is selected</p>
        <div id="smliserNewToken"></div>
        <div class="smliser-token-expiry-field" id="formBody">
            <label for="expiryDate">Choose Expiry Date</label>
            <input type="datetime-Local" id="expiryDate" />
            <input type="hidden" id="pop-up-license-key" name="license_key" value="<?php echo esc_attr( $license_key );?>"/>
            <input type="hidden" id="popup-item-id" name="item_id" value="<?php echo absint( $item_id );?>"/>
        </div>
        <button id="createToken" class="button action smliser-nav-btn">generate token</button>
    </div>
</div>
<p id="timer-div"></p>