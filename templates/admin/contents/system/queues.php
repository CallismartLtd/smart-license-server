<?php
/**
 * The admin system queues page template.
 *
 * Renders both the active jobs queue and the failed jobs archive,
 * depending on ?section=failed. Long fields (error message, result,
 * payload) are shown via a "View" trigger into a shared modal instead
 * of being truncated inline — this page is for auditing, so nothing
 * gets clipped.
 *
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Background\Queue\JobDTO[] $jobs
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var array<string, int> $queue_stats
 * @var int $log_rentention
 */

use SmartLicenseServer\Background\Queue\JobDTO;

$section    = $request->query( 'section' );
$is_failed  = 'failed' === $section;

/**
 * JSON-encode a value for safe embedding in a data-* attribute.
 *
 * @param mixed $value
 * @return string
 */
$encode_for_modal = static function ( mixed $value ): string {
    if ( null === $value ) {
        return '';
    }

    $text = is_scalar( $value ) ? (string) $value : smliser_safe_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

    return $text ?? '';
};

$menu_args  = $page_handler->get_top_menu_args( $request );

$menu_args['actions'] = [
    [
        'label'     => 'Failed Jobs',
        'url'       => $urlmanager->admin_tools_page_url( 'queues' )->add_query_param( 'section', 'failed' ),
        'icon'      => 'ti ti-clock-exclamation',
        'active'    => 'failed' === $request->query( 'section' )
    ]
];

if ( 'failed' === $request->query( 'section' ) ) {
    $menu_args['breadcrumbs'][1]['url']     = $urlmanager->admin_tools_page_url( 'queues' );
    $menu_args['breadcrumbs'][1]['icon']    = 'ti ti-clock';
    $menu_args['breadcrumbs'][]             = [
        'label' => 'Failed Jobs'
    ];
}

?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>

    <div class="smliser-tools-actions" id="smliser-background-actions">
        <?php if ( ! $is_failed ) : ?>
            <button
                type="button"
                class="smliser-btn smliser-btn-glass"
                data-bg-action="queue/process"
                data-confirm="Process queued jobs now, for up to 20 seconds, in this request?"
            ><i class="ti ti-player-play"></i> Process queue</button>
            <button
                type="button"
                class="smliser-btn smliser-btn-glass"
                data-bg-action="queue/release-stale"
                data-confirm="Put jobs that have been running for more than 5 minutes back in the queue? Only do this if no worker is still running them."
            ><i class="ti ti-refresh-alert"></i> Release stale jobs</button>
            <button
                type="button"
                class="smliser-btn smliser-btn-glass"
                data-bg-action="queue/purge"
                data-days="7"
                data-confirm="Delete completed jobs older than 7 days?"
            ><i class="ti ti-trash"></i> Purge completed</button>
        <?php else : ?>
            <button
                type="button"
                class="smliser-btn smliser-btn-glass"
                data-bg-action="queue/purge-failed"
                data-days="<?php echo escAttr( (string) $log_rentention ); ?>"
                data-confirm="<?php echo escAttr( sprintf( 'Delete failed job records older than %d days? They are the audit trail of past failures.', $log_rentention ) ); ?>"
            ><i class="ti ti-trash"></i> Purge failed records</button>
        <?php endif; ?>
        <span class="smliser-section-description">
            <i class="ti ti-info-circle"></i>
            Jobs normally run in the queue worker (`queue work`). "Process queue" runs them in this request; it is refused while an update is queued or being installed.
        </span>
    </div>

    <div class="smliser-table-wrapper">
        <?php if( ! $is_failed ) : ?>
            <ul class="subsubsub smliser-status-filter">
                <?php $current_status = $request->query( 'status', '' ); ?>

                <?php foreach ( $queue_stats as $label => $total ) : 
                    $is_active  = $label === $request->query( 'status' );
                ?>

                    <li class="smliser-status-item">
                        <a 
                            href="<?php echo escUrl( smliser_get_current_url()->add_query_param( 'status', $label )->url() ); ?>"
                            class="smliser-status-link<?php echo $is_active ? ' is-active' : ''; ?>"
                            aria-current="<?php echo $is_active ? 'page' : 'false'; ?>"
                        >
                            <span class="smliser-status-label">
                                <?php echo escHtml( $label ); ?>
                            </span>
                            <span class="smliser-status-count">
                                (<?php echo intval( $total ); ?>)
                            </span>
                        </a>
                    </li>

                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <table class="smliser-table widefat striped">
            <thead class="<?php echo empty( $jobs ) ? 'smliser-hide' : ''; ?>">
                <tr>
                    <th>ID</th>
                    <th>Job Class</th>
                    <th>Queue</th>
                    <?php if ( $is_failed ) : ?>
                        <th>Priority</th>
                        <th>Attempts</th>
                        <th>Created</th>
                        <th>Failed At</th>
                        <th>Error</th>
                    <?php else : ?>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Available At</th>
                    <?php endif; ?>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $jobs ) ) : ?>
                    <tr>
                        <td class="empty-state" colspan="<?php echo $is_failed ? 8 : 7; ?>">
                            <?php echo $is_failed ? 'No failed jobs recorded.' : 'No queued job registered.'; ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $jobs as $job ) : ?>
                        <?php
                        $status        = (string) $job->get( JobDTO::KEY_STATUS );
                        $result        = $job->get( JobDTO::KEY_RESULT );
                        $error_message = $job->get( JobDTO::KEY_ERROR_MESSAGE );
                        ?>
                        <tr>
                            <td><?php echo escHtml( (string) $job->get( JobDTO::KEY_ID ) ); ?></td>
                            <td><?php echo escHtml( (string) $job->get( JobDTO::KEY_JOB_CLASS ) ); ?></td>
                            <td><?php echo escHtml( (string) $job->get( JobDTO::KEY_QUEUE ) ); ?></td>

                            <?php if ( $is_failed ) : ?>
                                <td><?php echo escHtml( (string) $job->get( JobDTO::KEY_PRIORITY ) ); ?></td>
                                <td>
                                    <?php echo escHtml( sprintf(
                                        '%d / %d',
                                        (int) $job->get( JobDTO::KEY_ATTEMPTS ),
                                        (int) $job->get( JobDTO::KEY_MAX_ATTEMPTS )
                                    ) ); ?>
                                </td>
                                <td><?php echo escHtml( $job->get( JobDTO::KEY_CREATED_AT )->format( smliser_datetime_format() ) ); ?></td>
                                <?php /* KEY_AVAILABLE_AT holds failed_at for archived rows — see adapter docblock. */ ?>
                                <td><?php echo escHtml( $job->get( JobDTO::KEY_AVAILABLE_AT )->format( smliser_datetime_format() ) ); ?></td>
                                <td>
                                    <?php if ( empty( $error_message ) ) : ?>
                                        &mdash;
                                    <?php else : ?>
                                        <button
                                            type="button"
                                            class="smliser-btn-link smliser-view-detail"
                                            data-title="Error &mdash; Job #<?php echo escAttr( (string) $job->get( JobDTO::KEY_ID ) ); ?>"
                                            data-content="<?php echo escAttr( $encode_for_modal( $error_message ) ); ?>"
                                        >View</button>
                                    <?php endif; ?>
                                </td>
                            <?php else : ?>
                                <td>
                                    <span class="smliser-badge smliser-badge-<?php echo escAttr( strtolower( $status ) ); ?>">
                                        <?php echo escHtml( strtoupper( $status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo escHtml( sprintf(
                                        '%d / %d',
                                        (int) $job->get( JobDTO::KEY_ATTEMPTS ),
                                        (int) $job->get( JobDTO::KEY_MAX_ATTEMPTS )
                                    ) ); ?>
                                </td>
                                <td><?php echo escHtml( $job->get( JobDTO::KEY_AVAILABLE_AT )->format( smliser_datetime_format() ) ); ?></td>
                            <?php endif; ?>

                            <td>
                                <?php if ( null === $result ) : ?>
                                    &mdash;
                                <?php else : ?>
                                    <button
                                        type="button"
                                        class="smliser-btn-link smliser-view-detail"
                                        data-title="Result &mdash; Job #<?php echo escAttr( (string) $job->get( JobDTO::KEY_ID ) ); ?>"
                                        data-content="<?php echo escAttr( $encode_for_modal( $result ) ); ?>"
                                    >View</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <span class="smliser-section-description">
            <i class="ti ti-info-circle"></i>
            <?php printf( 'These records are automatically purged after %d days', $log_rentention ); ?>
        </span>
    </div>
</div>