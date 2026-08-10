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
 * @var array $menu_args
 * @var \SmartLicenseServer\Core\Request $request
 * @var array $app_types
 */

defined( 'SMLISER_ROOT' ) || exit; ?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    <div class="smliser-table-wrapper">
      
        <?php if ( $message = $request->get( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo escHtml( $message ); ?></p></div>
        <?php endif; ?>

        <div class="smliser-app-search-page smliser-table-wrapper">
            <form class="smliser-admin-search" method="GET" action="<?php echo escUrl( $current_url->get_href() ) ?>">
                <input type="hidden" name="page" value="smliser-repository">
                <input type="hidden" name="tab" value="search">
                <select name="app_types" id="app_types" class="smliser-app-type-select">
                    <option value="<?php echo implode( '|', $app_types ); ?>">All</option>
                    <?php foreach( $app_types as $type ) : ?>
                        <option value="<?php echo escHtml( $type ); ?>" <?php selected( $type, $request->get( 'app_types' ) ); ?>><?php echo escHtml( ucfirst( $type ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="app_search" value="<?php echo $request->get( 'app_search' ) ?>" id="smliser-app-search-input" placeholder="Search apps">
                <button type="submit" class="button smliser-btn">Search</button>
            </form>

            <table class="smliser-table widefat striped">
                <thead class="<?php printf( '%s', empty( $apps ) ? ' smliser-hide' : '' ) ?>">
                    <tr>
                        <th><input type="checkbox" id="smliser-select-all"></th>
                        <th><?php echo escHtml( 'APP ID' ); ?></th>
                        <th><?php echo escHtml( 'App Name' ); ?></th>
                        <th><?php echo escHtml( 'App Author' ); ?></th>
                        <th><?php echo escHtml( 'App Type' ); ?></th>
                        <th><?php echo escHtml( 'Version' ); ?></th>
                        <th><?php echo escHtml( 'Slug' ); ?></th>
                        <th><?php echo escHtml( 'Status' ); ?></th>
                        <th><?php echo escHtml( 'Last Updated' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $apps ) ) : ?>
                        <?php if ( $request->get( 'app_search' ) ) :
                            $message    = sprintf(
                                'No app found matching the search term "%s". <a href="%s">Reset Search</a>',
                                escHtml( $request->get( 'app_search' ) ),
                                escUrl( $current_url->add_query_param( 'tab', 'search' )->get_href() )
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
                            <td><input type="checkbox" class="smliser-license-checkbox" name="ids[]" value="<?php printf( '%s:%s', escAttr( $app->get_type() ), escAttr( $app->get_slug() ) ); ?>"> </td>
                            <td class="smliser-edit-row">
                                <?php echo intval( $app->get_id() ); ?>
                                <div class="smliser-edit-link">
                                    <a href="<?php echo escUrl( smliser_admin_repo_tab( 'edit', array( 'app_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>">edit</a> 
                                    |
                                    <a href="<?php echo escUrl( smliser_admin_repo_tab( 'view', array( 'app_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>">view</a>
                                </div>
                            </td>
                            <td><?php echo escHtml( $app->get_name() ); ?></td>
                            <td><?php echo $app->get_author(); ?></td>
                            <td><code><?php echo escHtml( $app->get_type() ); ?></code></td>
                            <td><?php echo escHtml( $app->get_version() ); ?></td>
                            <td><?php echo escHtml( $app->get_slug() ); ?></td>
                            <td><?php echo escHtml( $app->get_status() ); ?></td>
                            <td><?php echo escHtml( $app->get_updated_at()->format( smliser_datetime_format() ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php smliser_render_pagination( $pagination ); ?>
        </div>
    </div>
</div>