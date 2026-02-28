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

defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>
    <div class="smliser-table-wrapper">
      
        <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div class="smliser-app-search-page smliser-table-wrapper">
            <form class="smliser-app-search-form" method="GET" action="<?php echo esc_url( $current_url->get_href() ) ?>">
                <input type="hidden" name="page" value="repository">
                <input type="hidden" name="tab" value="search">
                <select name="app_types" id="app_types" class="smliser-app-type-select">
                    <option value="<?php echo implode( '|', $app_types ); ?>">All</option>
                    <?php foreach( $app_types as $type ) : ?>
                        <option value="<?php echo esc_html( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="app_search" value="<?php echo smliser_get_query_param( 'app_search' ) ?>" id="smliser-app-search-input" placeholder="Search apps">
                <button type="submit" class="button smliser-btn">Search</button>
            </form>

            <table class="smliser-table widefat striped">
                <thead class="<?php printf( '%s', empty( $apps ) ? ' smliser-hide' : '' ) ?>">
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
                    <?php if ( empty( $apps ) ) : ?>
                        <?php if ( smliser_get_query_param( 'app_search' ) ) :
                            $message    = sprintf(
                                'No app found matching the search term "%s". <a href="%s">Reset Search</a>',
                                esc_html( smliser_get_query_param( 'app_search' ) ),
                                esc_url( $current_url->add_query_param( 'tab', 'search' )->get_href() )
                            );
                        else:
                            $message    = 'Search for hosted applications in the repository.';
                        endif; ?>

                        <tr>
                            <td class="align-center bg-white">
                                <?php echo  $message; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
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
            <?php smliser_render_pagination( $pagination ); ?>
        </div>
    </div>
</div>