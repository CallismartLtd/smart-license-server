<?php
/**
 * The admin repository page template
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var SmartLicenseServer\HostedApps\AbstractHostedApp[] $apps
 */

use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-table-wrapper">
    <h1><?php printf( '%s Repository %s', $type ? ucfirst( $type ) : '', $status ); ?></h1>    

    <div>
        <a href="<?php echo esc_url( $add_url ); ?>" class="button action smliser-nav-btn">Upload New</a>
        <?php if ( isset( $type ) ) : ?>
            <a href="<?php echo esc_url( smliser_repo_page() ); ?>" class="button action smliser-nav-btn">Repository Listing</a>
        <?php endif; ?>
        <a href="<?php echo esc_url( add_query_arg( array( 'type' => 'plugin' ), smliser_repo_page() ) ); ?>" class="button action smliser-nav-btn">Plugin Repository</a>
        <a href="<?php echo esc_url( add_query_arg( array( 'type' => 'theme' ), smliser_repo_page() )); ?>" class="button action smliser-nav-btn">Theme Repository</a>
        <a href="<?php echo esc_url( add_query_arg( array( 'type' => 'software' ), smliser_repo_page() ) ); ?>" class="button action smliser-nav-btn">Software Repository</a>

        <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>
    </div>

    <ul class="subsubsub">
        <?php foreach ( AbstractHostedApp::get_statuses() as $k => $v ) : ?>
            <?php if ( HostedApplicationService::count_apps( ['status' => $k] ) > 0  && $k !== $status ) : ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'status' => $k ), smliser_repo_page() ) ); ?>" class="smliser-status-link">
                    <?php echo esc_html( $v ); ?> (<?php echo absint( HostedApplicationService::count_apps( ['status' => $k, 'type' => $type ] ) ); ?>)
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <br class="clear" />

    <?php if ( empty( $apps ) ) : ?>
        <?php 
            $type_name  = $type ? $type : 'app';
            $upload_url = smliser_admin_repo_tab( 'add-new', array( 'type' => $type ) );
            echo wp_kses_post( 
                    smliser_not_found_container(
                    sprintf( 'Your %1$s %2$s repository is empty, upload your first %1$s <a href="%3$s">here</a>.',
                        esc_html( $type_name ), 
                        esc_html( $status ?? '' ),
                        esc_url( $upload_url )
                    )
                )
            );
        ?>           
    <?php else: ?>

        <form id="smliser-bulk-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <div class="smliser-actions-wrapper">
                <div class="smliser-bulk-actions">
                    <select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>
                        <option value=""><?php echo esc_html__( 'Bulk Actions', 'smliser' ); ?></option>
                        <?php foreach ( AbstractHostedApp::get_statuses() as $status_key => $status_label ) : ?>
                            <?php if ( $status === $status_key) : continue; endif; ?>
                            <option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button action smliser-bulk-action-button"><?php echo esc_html__( 'Apply', 'smliser' ); ?></button>
                </div>
                <div class="smliser-search-box">
                    <input type="search" id="smliser-search" class="smliser-search-input" placeholder="<?php echo esc_attr__( 'Search Applications', 'smliser' ); ?>">
                </div>
            </div>
        
            <input type="hidden" name="action" value="smliser_bulk_action">
            <input type="hidden" name="context" value="repository">
            <?php wp_nonce_field( 'smliser_table_nonce', 'smliser_table_nonce'); ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="smliser-select-all"></th>
                        <th><?php echo esc_html__( 'APP ID', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'App Name', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'App Author', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'App Type', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Version', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Slug', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Status', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Last Updated', 'smliser' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $apps as $app ) : ?>
                        <tr>
                            <td><input type="checkbox" class="smliser-license-checkbox" name="ids[]" value="<?php printf( '%s:%s', esc_attr( $app->get_type() ), esc_attr( $app->get_slug() ) ); ?>"> </td>
                            <td class="smliser-edit-row">
                                <?php echo absint( $app->get_id() ); ?>
                                <div class="smliser-edit-link">
                                    <a href="<?php echo esc_url( smliser_admin_repo_tab( 'edit', array( 'app_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>">edit</a> 
                                    |
                                    <a href="<?php echo esc_url( smliser_admin_repo_tab( 'view', array( 'app_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>">view</a>
                                </div>
                            </td>
                            <td><?php echo esc_html( $app->get_name() ); ?></td>
                            <td><?php echo $app->get_author(); ?></td>
                            <td><code><?php echo esc_html( $app->get_type() ); ?></code></td>
                            <td><?php echo esc_html( $app->get_version() ); ?></td>
                            <td><?php echo esc_html( $app->get_slug() ); ?></td>
                            <td><?php echo esc_html( $app->get_status() ); ?></td>
                            <td><?php echo esc_html( smliser_check_and_format( $app->get_last_updated(), true ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>

        <p class="smliser-table-count">
            <?php echo absint( $pagination['total'] ); ?> 
            item<?php echo ( $pagination['total'] > 1 ? 's' : '' ); ?>
        </p>

        <?php if ( $pagination['total_pages'] > 1 ) : ?>
            <div class="smliser-tablenav-pages">
                <span class="smliser-displaying-num">
                    <?php printf( __( 'Page %d of %d', 'smliser' ), $pagination['page'], $pagination['total_pages'] ); ?>
                </span>

                <span class="smliser-pagination-links">
                    <?php
                    $base_url  = remove_query_arg( array( 'paged', 'limit' ) );
                    $prev_page = max( 1, $pagination['page'] - 1 );
                    $next_page = min( $pagination['total_pages'], $pagination['page'] + 1 );

                    // Previous
                    if ( $pagination['page'] > 1 ) {
                        echo '<a class="prev-page button" href="' . esc_url( add_query_arg( array( 'paged' => $prev_page, 'limit' => $pagination['limit'] ), $base_url ) ) . '">&laquo;</a>';
                    } else {
                        echo '<span class="smliser-navspan button disabled">&laquo;</span>';
                    }

                    // Page numbers
                    for ( $i = 1; $i <= $pagination['total_pages']; $i++ ) {
                        $class = ( $i === $pagination['page'] ) ? 'button current' : 'button';
                        echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( array( 'paged' => $i, 'limit' => $pagination['limit'] ), $base_url ) ) . '">' . $i . '</a>';
                    }

                    // Next
                    if ( $pagination['page'] < $pagination['total_pages'] ) {
                        echo '<a class="next-page button" href="' . esc_url( add_query_arg( array( 'paged' => $next_page, 'limit' => $pagination['limit'] ), $base_url ) ) . '">&raquo;</a>';
                    } else {
                        echo '<span class="smliser-navspan button disabled">&raquo;</span>';
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>