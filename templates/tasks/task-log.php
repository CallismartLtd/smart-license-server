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
    <h1>License Activation Log</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tasks' ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-share-alt"></span> Tasks</a>
    <p>Logs over three months are automatically deleted</p>
    <table class="smliser-table">
        <tr>
            <th>Date</th>
            <th>License ID</th>
            <th>IP Address</th>
            <th>Comment</th>
            <th>Duration</th>
            <th>Website</th>
        </tr>
        <?php if ( empty( $all_tasks ) ):?>
            <tr>
                <td colspan="5">All license activation tasks will appear here.</td>
            </tr> 
        <?php else: foreach( $all_tasks as $date => $task_data ):?>
        <tr>
            <td><?php echo esc_html( $date );?></td>
            <td><?php echo esc_html( $task_data['license_id'] );?></td>
            <td><?php echo esc_html( $task_data['ip_address'] );?></td>
            <td><?php echo esc_html( $task_data['comment'] );?></td>
            <td><?php echo esc_html( smliser_readable_duration( $task_data['duration'] ) );?></td>
            <td><?php echo esc_html( smliser_get_base_url( $task_data['website'] ) );?></td>
        </tr>
        <?php endforeach; endif;?>
    </table>
    <p class="smliser-table-count"><?php echo absint( count( $all_tasks ) ); echo ' Item'. ( count( $all_tasks ) > 1 ? 's' : '' ); ?></p>
</div>
