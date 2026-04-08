<?php
/**
 * file name main.php
 * Queued tasks template page
 * 
 * @author Callistus
 * @since 0.2.0
 * @package Smliser\templates
 * @var array{0: int, 1: array{license_id: int, event_type: string, ip_address: string, user_agent: string, website: string, comment: string, duration: string, created_at: int}} $all_tasks
 */

use SmartLicenseServer\Admin\LicensePage;
use SmartLicenseServer\Environments\WordPress\AdminMenu;
use SmartLicenseServer\Utils\Format;

defined( 'SMLISER_ABSPATH' ) || exit;
?>

<div class="smliser-admin-page">
    <?php AdminMenu::print_admin_top_menu( LicensePage::get_menu_args( $request ) ); ?>

    <div class="smliser-admin-body">
        <div class="notice notice-info" style="margin: 15px;">
            <p>Logs over three months are automatically deleted</p>
        </div>
        
        <?php if ( empty( $all_tasks ) ) : ?>
            <?php echo smliser_not_found_container( 'All recent license activities will appear here.' ); ?>
        <?php else: ?>
            <table class="smliser-table widefat striped">
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
                <?php else: foreach( $all_tasks as $task_data ):?>
                <tr>
                    <td><?php echo esc_html( Format::datetime( $task_data['created_at'] ?? 0, smliser_datetime_format() ) );?></td>
                    <td><?php echo esc_html( $task_data['user_agent'] ?? 'N/A' );?></td>
                    <td><?php echo esc_html( $task_data['ip_address'] ?? 'N/A' );?></td>
                    <td><?php echo esc_html( $task_data['comment'] ?? 'N/A' );?></td>
                    <td><?php echo esc_html( smliser_readable_duration( $task_data['duration'] ?? 0 ) );?></td>
                    <td><?php echo esc_html( smliser_get_base_url( $task_data['website']?? 'N/A' ) );?></td>
                    <td><a href="<?php echo esc_url( smliser_license_admin_action_page( 'view', $task_data['license_id'] ?? 0 ) ) ?>">View License</a></td>
                </tr>
                <?php endforeach; endif;?>
            </table>
            <p class="smliser-table-count"><?php echo intval( count( $all_tasks ) ); echo ' Item'. ( count( $all_tasks ) > 1 ? 's' : '' ); ?></p>
        <?php endif; ?>      
    </div>

</div>
