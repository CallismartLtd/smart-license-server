<?php
/**
 * file name main.php
 * Queued tasks template page
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\templates
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="smliser-task-main">
    <h1>Missed License Activation Tasks</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tasks' ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-controls-back"></span> Tasks</a>

    <table class="smliser-table">
        <tr>
            <th>Next Validation</th>
            <th>License ID</th>
            <th>IP Address</th>
            <th>Website</th>
        </tr>
        <?php if ( empty( $all_tasks ) ):?>
            <tr>
                <td colspan="5">No missed task in the last 24hrs.</td>
            </tr> 
        <?php else: foreach( $all_tasks as $timestamp => $task_data ):?>
        <tr>
            <td><?php echo esc_html( $obj->calculate_next_validation_time( $timestamp, $cron_timestamp ) );?></td>
            <td><?php echo esc_html( $task_data[0]['license_id']);?></td>
            <td><?php echo esc_html( $task_data[0]['IP Address']);?></td>
            <td><?php echo esc_html( smliser_get_base_address( $task_data[0]['callback_url'] ) );?></td>
        </tr>
        <?php endforeach; endif;?>
    </table>
    <p class="smliser-table-count"><?php echo absint( count( $all_tasks ) ); echo ' Item'. ( count( $all_tasks ) > 1 ? 's' : '' ); ?></p>
</div>
