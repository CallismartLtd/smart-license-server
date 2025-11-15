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

<div class="smliser-admin-page">
    <h1>License Activity Log</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=licenses' ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-admin-home"></span> Licenses</a>
    <p>Logs over three months are automatically deleted</p>
    <table class="widefat striped">
        <tr>
            <th>Date</th>
            <th>User Agent</th>
            <th>Client IP Address</th>
            <th>Comment</th>
            <th>Duration</th>
            <th>Website</th>
            <th></th>

        </tr>
        <?php if ( empty( $all_tasks ) ):?>
            <tr>
                <td colspan="5">All license activation tasks will appear here.</td>
            </tr> 
        <?php else: foreach( $all_tasks as $date => $task_data ):?>
        <tr>
            <td><?php echo esc_html( $date );?></td>
            <td><?php echo esc_html( $task_data['user_agent'] ?? 'N/A' );?></td>
            <td><?php echo esc_html( $task_data['ip_address'] ?? 'N/A' );?></td>
            <td><?php echo esc_html( $task_data['comment'] ?? 'N/A' );?></td>
            <td><?php echo esc_html( smliser_readable_duration( $task_data['duration'] ?? 0 ) );?></td>
            <td><?php echo esc_html( smliser_get_base_url( $task_data['website']?? 'N/A' ) );?></td>
            <td><a href="<?php echo esc_url( smliser_license_admin_action_page( 'view', $task_data['license_id'] ) ) ?>">View License</a></td>
        </tr>
        <?php endforeach; endif;?>
    </table>
    <p class="smliser-table-count"><?php echo absint( count( $all_tasks ) ); echo ' Item'. ( count( $all_tasks ) > 1 ? 's' : '' ); ?></p>
</div>
