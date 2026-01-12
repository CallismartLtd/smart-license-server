<?php
/**
 * Access control dashboard template.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\AccessControlPage
 * @var \SmartLicenseServer\Security\PrincipalInterface $entity_class
 */


defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-admin-repository-template">
    <?php self::print_header(); ?>
    
    <div class="smliser-admin-table-body">

        <div>
            <a href="<?php echo esc_url( smliser_get_current_url()->add_query_param( 'section', 'add-new' ) ); ?>" class="button action smliser-nav-btn"><?php printf( 'Add %s', esc_html( $type ) ); ?></a>

            <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
        </div>

        <ul class="subsubsub">
            <?php foreach ( $entity_class::get_allowed_statuses() as $k => $v ) : ?>
                <?php if ( $entity_class::count_status( $k ) > 0 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'status' => $k ), smliser_repo_page() ) ); ?>" class="smliser-status-link">
                        <?php echo esc_html( ucfirst( $v ) ); ?> (<?php echo absint( $entity_class::count_status( $k ) ); ?>)
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <br class="clear" />
        <table class="widefat striped">
            <thead class="<?php echo ( empty( $all ) ) ? 'hidden': '' ?>">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $all ) ) : ?>
                    <tr>
                        <td colspan="4" class="align-center bg-white"><?php printf( 'All %s all will be listed here', esc_html( smliser_pluralize( $type )) ); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ( $all as $entity ) : ?>
                        <tr>
                            <td><?php echo esc_html( $entity->get_id() ); ?></td>
                            <td><?php echo esc_html( $entity->get_name() ); ?></td>
                            <td><?php echo esc_html( $entity->get_type() ); ?></td>
                            <td><?php echo esc_html( $entity->get_status() ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

</div>
