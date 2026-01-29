<?php
/**
 * Access control dashboard template.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\AccessControlPage
 * @var \SmartLicenseServer\Security\Actors\ActorInterface $entity_class
 * @var \SmartLicenseServer\Security\Actors\ActorInterface[] $all
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
            <?php foreach ( $entity_class::get_allowed_statuses() as $k => $v ) : $st_v = $entity_class::count_status( $v ); ?>
                <?php if ( $st_v > 0 ) : ?>
                    <a href="
                        <?php echo esc_url(
                            smliser_get_current_url()->add_query_param( 'status', $v )
                        );?>" class="smliser-status-link">
                        <?php echo esc_html( ucfirst( $v ) ); ?> (<?php echo absint( $st_v ); ?>)
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <br class="clear" />
        <table class="widefat striped">
            <thead class="<?php echo ( empty( $all ) ) ? 'hidden': '' ?>">
                <tr>
                    <th>ID</th>
                    <th></th>
                    <th>Name</th>
                    <th>Date Created</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $all ) ) : ?>
                    <tr>
                        <td colspan="4" class="align-center bg-white">
                            <?php printf(
                                'All %s all will be listed here',
                                esc_html( smliser_pluralize( $type ) )
                            );?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ( $all as $entity ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( $entity->get_id() ); ?>
                                <p class="smliser-edit-link">
                                    <a href="<?php
                                        echo esc_url( 
                                            smliser_get_current_url()
                                            ->add_query_params(
                                                ['section' => 'edit', 'id' => $entity->get_id()]
                                            ) ); 
                                    ?>">edit</a>
                                </p>
                            </td>
                            <td>
                                <img 
                                    src="<?php 
                                        echo esc_url( $entity->get_avatar()->is_valid() ? $entity->get_avatar() : smliser_get_placeholder_icon( 'avatar' ) ); 
                                        
                                    ?>"
                                    alt="<?php printf( '%s avatar', $entity->get_display_name() ) ?>" 
                                    width="32" height="32"
                                    loading="lazy" decoding="async">
                            </td>
                            <td><?php echo esc_html( $entity->get_display_name() ); ?></td>
                            <td><?php echo esc_html( $entity->get_created_at()->format( smliser_datetime_format() ) ); ?></td>
                            <td><?php echo esc_html( $entity->get_updated_at()->format( smliser_datetime_format() ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

</div>
