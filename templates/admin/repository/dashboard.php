<?php
/**
 * The admin repository page template
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var SmartLicenseServer\HostedApps\AbstractHostedApp[] $apps
 * @var array $pagination
 * @var string|null $type
 * @var string|null $status
 * @var \SmartLicenseServer\Core\URL $add_url
 * @var \SmartLicenseServer\Core\URL $current_url
 */

use SmartLicenseServer\Admin\Menu;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args  = array(
    'breadcrumbs'   => array(
        array(
            'title' => 'Repository',
            'label' => 'Repository',
            'url'   => $current_url ->remove_query_param( ['tab', 'type', 'status'] )->get_href()
        ),
        array(
            'label' => $page_title
        )
    ),

    'actions'   => array(
        array(
            'title'     => 'Upload New Application',
            'label'     => 'Upload New',
            'url'       => $add_url->get_href(),
            'icon'      => 'ti ti-upload'
        ),

        array(
            'title' => 'Plugin Repository',
            'label' => 'Plugins',
            'url'   => $current_url ->add_query_param( 'type', 'plugin' )->get_href(),
            'icon'  => 'ti ti-plug'
        ),
        
        array(
            'title' => 'Theme Repository',
            'label' => 'Themes',
            'url'   => $current_url ->add_query_param( 'type', 'theme' )->get_href(),
            'icon'  => 'ti ti-palette'
        ),
        
        array(
            'title' => 'Software Repository',
            'label' => 'Software',
            'url'   => $current_url ->add_query_param( 'type', 'software' )->get_href(),
            'icon'  => 'ti ti-device-desktop-code'
        ),
        
    )
);

if ( count( $current_url ->get_query_params() ) === 1 ) {
    unset( $menu_args['breadcrumbs'][0] ); // Remove the home link on home page.
}
?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>
    <div class="smliser-table-wrapper">
      
        <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <ul class="subsubsub smliser-status-filter">
            <?php $current_status = smliser_get_query_param( 'status', '' ); ?>

            <?php foreach ( AbstractHostedApp::STATUSES as $k => $label ) : 

                $is_active = ( $current_status === $k );
                $count     = HostedApplicationService::count_apps(
                    array(
                        'status' => $k,
                        'types'  => $type,
                    )
                );
                ?>

                <li class="smliser-status-item">
                    <a 
                        href="<?php echo esc_url( $current_url->add_query_param( 'status', $k ) ); ?>"
                        class="smliser-status-link<?php echo $is_active ? ' is-active' : ''; ?>"
                        aria-current="<?php echo $is_active ? 'page' : 'false'; ?>"
                    >
                        <span class="smliser-status-label">
                            <?php echo esc_html( $label ); ?>
                        </span>
                        <span class="smliser-status-count">
                            (<?php echo absint( $count ); ?>)
                        </span>
                    </a>
                </li>

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

                <table class="smliser-table widefat striped">
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

            <?php smliser_render_pagination( $pagination ); ?>
        <?php endif; ?>
    </div>
</div>