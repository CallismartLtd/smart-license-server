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
<form action="" class="smliser-form">
    <?php if ( get_transient( 'smliser_form_validation_message' ) ) :?>
        <?php echo wp_kses_post( smliser_form_message( get_transient( 'smliser_form_validation_message' ) ) ) ;?>
    <?php endif;?>
    <div class="smliser-form-container">
        <input type="hidden" name="action" value="smliser_options" />

        <div class="smliser-form-row">
            <label for="smliser-plugin-name" class="smliser-form-label">Plugin Name:</label>
            <span class="smliser-form-description" title="Add the plugin name, name must match with the name on plugin file header">?</span>
            <input type="text" name="smliser_plugin_name" id="smliser-plugin-name" class="smliser-form-input" required>
        </div>
    </div>
</form>
<?php return; endif;?>

<table class="smliser-table">
    <tr>
        <th>API User</th>
        <th>Key Ending With</th>
        <th>Permission</th>
        <th>Last Accessed</th>
        <th>IP Address</th>
    </tr>
    <?php if ( empty( $all_tasks ) ):?>
        <tr>
            <td colspan="5">No task scheduled</td>
        </tr> 
    <?php else: foreach( $all_tasks as $timestamp => $task_data ):?>
    <tr>
        <td><?php echo esc_html( smliser_tstmp_to_date( $timestamp ) );?></td>
        <td><?php echo esc_html( $obj->calculate_next_validation_time( $timestamp, $cron_timestamp ) );?></td>
        <td><?php echo esc_html( $task_data[0]['license_id']);?></td>
        <td><?php echo esc_html( $task_data[0]['IP Address']);?></td>
        <td><?php echo esc_html( get_base_address( $task_data[0]['callback_url'] ) );?></td>
    </tr>
    <?php endforeach; endif;?>
</table>
<p class="smliser-table-count"><?php echo absint( count( array() ) ); echo ' Item'. ( count( array()) > 1 ? 's' : '' ); ?></p>
