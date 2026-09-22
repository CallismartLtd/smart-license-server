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

    $text = is_scalar( $value ) ? (string) $value : json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

    return $text ?? '';
};
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $page_handler->get_top_menu_args( $request ) ); ?>

    <div class="smliser-table-wrapper">
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
                                    <span class="smliser-badge smliser-badge--<?php echo escAttr( strtolower( $status ) ); ?>">
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
    </div>
</div>