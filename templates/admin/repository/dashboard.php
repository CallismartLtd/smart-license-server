<?php
/**
 * The admin repository page template
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 */

defined( 'ABSPATH' ) || exit; ?>
<div class="smliser-table-wrapper">
    <h1><?php printf( '%s Repository', $type ? ucfirst( $type ) : '' );?></h1>    
    <a href="<?php echo esc_url( $add_url ); ?>" class="button action smliser-nav-btn">Upload New</a>
    <?php if ( isset( $type ) ) : ?>
        <a href="<?php echo esc_url( smliser_repo_page() ); ?>" class="button action smliser-nav-btn">Repository Listing</a>
    <?php endif?>
    <a href="<?php echo esc_url( add_query_arg( array( 'type' => 'plugin' ), smliser_repo_page() ) ); ?>" class="button action smliser-nav-btn">Plugin Repository</a>
    <a href="<?php echo esc_url( add_query_arg( array( 'type' => 'theme' ), smliser_repo_page() )); ?>" class="button action smliser-nav-btn">Theme Repository</a>
    <a href="<?php echo esc_url( add_query_arg( array( 'type' => 'software' ), smliser_repo_page() ) ); ?>" class="button action smliser-nav-btn">Software Repository</a>
    <?php if ( empty( $apps ) ) : ?>
        <?php echo wp_kses_post( smliser_not_found_container( sprintf( 'Your %s repository is empty, upload your first %s.', ( $type ? ucfirst( $type ) : '' ), $type ?? 'application' ) ) ); ?>           
    <?php else: ?>
        <form id="smliser-bulk-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <div class="smliser-actions-wrapper">
                <div class="smliser-search-box">
                    <input type="search" id="smliser-search" class="smliser-search-input" placeholder="<?php echo esc_attr__( 'Search Applications', 'smliser' ); ?>">
                </div>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Item ID', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'App Name', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'App Author', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Version', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Slug', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Created at', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Last Updated', 'smliser' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $apps as $app ) : ?>
                        <tr>
                            <td class="smliser-edit-row">
                                <?php echo absint( $app->get_id() ); ?>
                                <div class="smliser-edit-link">
                                    <a href="<?php echo esc_url( smliser_admin_repo_tab( 'edit', array( 'item_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>">edit</a> 
                                    |
                                    <a href="<?php echo esc_url( smliser_admin_repo_tab( 'view', array( 'item_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>">view</a>
                                </div>
                            </td>
                            <td><?php echo esc_html( $app->get_name() ); ?></td>
                            <td><?php echo $app->get_author(); ?></td>
                            <td><?php echo esc_html( $app->get_version() ); ?></td>
                            <td><?php echo esc_html( $app->get_slug() ); ?></td>
                            <td><?php echo esc_html( smliser_check_and_format( $app->get_date_created(), true ) ); ?></td>
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