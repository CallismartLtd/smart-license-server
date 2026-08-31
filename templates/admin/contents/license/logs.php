<?php
/**
 * file name main.php
 * Queued tasks template page
 * 
 * @author Callistus
 * @since 0.2.0
 * @package Smliser\templates
 * @var array{0: int, 1: array{license_id: int, event_type: string, ip_address: string, user_agent: string, website: string, comment: string, duration: string, created_at: int}} $logs
 * @var \SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Admin\Handlers\LicensePage $page_handler
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var int $log_duration
 */

use SmartLicenseServer\Utils\Format;

defined( 'SMLISER_ROOT' ) || exit;
?>

<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $page_handler->get_menu_args( $request ) ); ?>

    <div class="smliser-admin-body">
        <div class="notice notice-info" style="margin: 15px;">
            <p>
                <?php 
                    printf( 
                        'Logs over %s days are automatically deleted.',
                        escHtml( $log_duration )
                    )
                
                ?>
            </p>
        </div>
        
        <?php if ( empty( $logs ) ) : ?>
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
                <?php if ( empty( $logs ) ):?>
                    <tr>
                        <td colspan="5">All license activation tasks will appear here.</td>
                    </tr> 
                <?php else: foreach( $logs as $task_data ):?>
                <tr>
                    <td><?php echo escHtml( Format::datetime( $task_data['created_at'] ?? 0, smliser_datetime_format() ) );?></td>
                    <td><?php echo escHtml( $task_data['user_agent'] ?? 'N/A' );?></td>
                    <td><?php echo escHtml( $task_data['ip_address'] ?? 'N/A' );?></td>
                    <td><?php echo escHtml( $task_data['comment'] ?? 'N/A' );?></td>
                    <td><?php echo escHtml( smliser_readable_duration( $task_data['duration'] ?? 0 ) );?></td>
                    <td><?php echo escHtml( smliser_url_origin( $task_data['website']?? 'N/A' ) );?></td>
                    <td><a href="<?php echo escUrl( $urlmanager->admin_license_page_url( 'view', [ 'id' => $task_data['license_id'] ?? 0] )->url() ) ?>">View License</a></td>
                </tr>
                <?php endforeach; endif;?>
            </table>
            <p class="smliser-table-count"><?php echo intval( count( $logs ) ); echo ' Item'. ( count( $logs ) > 1 ? 's' : '' ); ?></p>
        <?php endif; ?>      
    </div>

</div>
