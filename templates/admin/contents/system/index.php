<?php
/**
 * The admin system schedules page template.
 * 
 * @author Callistus Nwachukwu
 * @var array<string, array{task: \SmartLicenseServer\Background\Schedule\ScheduledTask, state: array{last_ran_at: \DateTimeImmutable|null, next_run_at: \DateTimeImmutable|null, last_error: string|null}}> $tasks
 * @var SmartLicenseServer\Admin\ContentHandlers\SystemPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $page_handler->get_top_menu_args( $request ) ); ?>

    <div class="smliser-table-wrapper">
        <table class="smliser-table widefat striped">
            <thead class="<?php echo empty( $tasks ) ? 'smliser-hide': ''; ?>">
                <tr>
                    <th>Task ID</th>
                    <th>Label</th>
                    <th>Last Ran</th>
                    <th>Next Run</th>
                    <th>State</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $tasks ) ) : ?>
                    <tr>
                        <td class="empty-state" colspan="5">No scheduled tasks registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ( $tasks as $data ) : 
                        $task  = $data['task'];
                        $state = $data['state'];

                        $last_ran   = $state['last_ran_at'] ? $state['last_ran_at']->format( \smliser_datetime_format() ) : 'Never';
                        $next_run   = $state['next_run_at'] ? $state['next_run_at']->format( \smliser_datetime_format() ) : 'N/A';
                        $status     = $task->is_due( $state['last_ran_at'] ) ? 'DUE' : 'WAITING';

                        $status     = $state['last_error'] ? 'ERROR' : $status;
                    ?>
                        <tr >
                            <td><?php echo escHtml( $task->get_id() ) ?></td>
                            <td><?php echo escHtml( $task->get_label() ) ?></td>
                            <td><?php echo escHtml( $last_ran ); ?></td>
                            <td><?php echo escHtml( $next_run ); ?></td>
                            <td><?php echo escHtml( $status ); ?></td>                            
                        </tr>

                    <?php endforeach; ?>
                <?php endif; ?>                
            </tbody>
        </table>
    </div>

</div>
