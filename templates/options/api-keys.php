<?php
/**
 * File name options.php
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;
?>
<h1>API KEYS</h1>
<?php if ( isset( $_GET['action'] ) && 'add-new-key' === sanitize_key( $_GET['action'] ) ):?>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=smliser-options&path=api-keys' ) ); ?>"><span class="dashicons dashicons-editor-break"></span></a>
<form method="post" action="" class="smliser-form" id="smliser-api-key-generation-form">
    <?php if ( get_transient( 'smliser_form_validation_message' ) ) :?>
        <?php echo wp_kses_post( smliser_form_message( get_transient( 'smliser_form_validation_message' ) ) ) ;?>
    <?php endif;?>
    <div class="smliser-form-container">
        <div class="spinner-overlay">
            <img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>" alt="Loading..." class="spinner-img">
        </div>

        <div class="smliser-form-row">
            <label for="smliser-plugin-name" class="smliser-form-label">Description:</label>
            <span class="smliser-form-description" title="Add a description for your reference">?</span>
            <textarea type="text" name="description" id="description" class="smliser-form-input"></textarea>
        </div>

        <div class="smliser-form-row">
            <label for="user_id" class="smliser-form-label">User:</label>
            <span class="smliser-form-description" title="API key owner">?</span>
            <?php
            wp_dropdown_users(
                array(
                    'name'              => 'user_id',
                    'show_option_none'  => esc_html__( 'Select a User', 'smliser' ),
                    'option_none_value' => 0,
                    'class'             => 'smliser-form-input',
                    'show'              => 'display_name_with_login',
                )
            );
            ?>
        </div>

        <div class="smliser-form-row">
            <label for="permission" class="smliser-form-label">Permission</label>
            <span class="smliser-form-description" title="Select the appropriate permission for these keys">?</span>
            <select name="permission" id="permission" class="smliser-select-input">
                <option value="read"><?php esc_html_e( 'Read', 'smliser' ); ?></option>
                <option value="write"><?php esc_html_e( 'Write', 'smliser' ); ?></option>
                <option value="read_write"><?php esc_html_e( 'Read/Write', 'smliser' ); ?></option>
            </select>
        </div>

        <input type="submit" value="Generate API key" class="button action smliser-bulk-action-button" id=""/>

    </div>
</form>

<?php return; endif;?>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=smliser-options&path=api-keys&action=add-new-key' ) ) ?>" class="button action smliser-nav-btn">Generate New Key</a>

<table class="smliser-table">
    <tr>
        <th>API User</th>
        <th>Key Ending With</th>
        <th>Permission</th>
        <th>App Name</th>
        <th>Status</th>
        <th>Revoke</th>
    </tr>

    <?php if ( empty( $all_api_data ) ):?>
        <tr>
            <td colspan="5">No API key created.</td>
        </tr> 
    <?php else: foreach( $all_api_data as $api_key ):?>
    <tr>
        <div class="spinner-overlay">
                <img src="<?php echo esc_url( admin_url( 'images/spinner.gif' ) ); ?>" alt="Loading..." class="spinner-img">
        </div>
        
        <td><?php echo esc_html( $api_key->get_user()->display_name );?></td>
        <td><?php echo esc_html( $api_key->get_key( 'key_ending' ) );?></td>
        <td><?php echo esc_html( $api_key->get_permission() );?></td>
        <td><?php echo esc_html( $api_key->get_token( 'app_name' ) );?></td>
        <td><?php echo esc_html( $api_key->get_status() );?></td>
        <td><button class="smliser-revoke-btn" data-key-id="<?php echo esc_attr( $api_key->get_id() ); ?>">Revoke</button></td>
    </tr>
    <?php endforeach; endif;?>
</table>
<p class="smliser-table-count"><?php echo absint( count( $all_api_data ) ); echo ' Item'. ( count( $all_api_data ) > 1 ? 's' : '' ); ?></p>
