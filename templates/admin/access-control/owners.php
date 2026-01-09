<?php
/**
 * Access control dashboard template.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\AccessControlPage
 */

use SmartLicenseServer\Security\Owner;

defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-admin-repository-template">
    <?php self::print_header(); ?>
    
    <div class="smliser-admin-table-body">

        <div>
            <a href="<?php echo esc_url( smliser_get_current_url()->add_query_param( 'section', 'add-new' ) ); ?>" class="button action smliser-nav-btn">Add Owner</a>

            <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
        </div>

        <ul class="subsubsub">
            <?php foreach ( Owner::get_allowed_statuses() as $k => $v ) : ?>
                <?php if ( Owner::count_status( $k ) > 0 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'status' => $k ), smliser_repo_page() ) ); ?>" class="smliser-status-link">
                        <?php echo esc_html( ucfirst( $v ) ); ?> (<?php echo absint( Owner::count_status( $k ) ); ?>)
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <br class="clear" />
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
        </table>

    </div>

</div>
