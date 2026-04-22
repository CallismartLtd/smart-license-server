<?php
/**
 * The admin license page dashboard template.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var SmartLicenseServer\Monetization\License[] $licenses
 */

use SmartLicenseServer\Admin\LicensePage;

defined( 'SMLISER_ABSPATH' ) || exit;

/** @var array $args */
$args   = LicensePage::get_menu_args( $request );

\array_unshift(
    $args['actions'],
    array(
        'title'     => 'Add New License',
        'label'     => 'Add New',
        'url'       => $add_url,
        'icon'      => 'ti ti-plus',
    ),

    array(
        'title'     => 'View Activity Logs',
        'label'     => 'Activity Logs',
        'url'       => smliser_license_page()->add_query_param( 'tab', 'logs' ),
        'icon'      => 'ti ti-activity',
    )

);
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $args ); ?>
    <div class="smliser-app-search-page smliser-table-wrapper">
            <form class="smliser-admin-search" method="GET" action="<?php echo esc_url( $current_url->get_href() ) ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( $request->get( 'page' ) ) ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $request->get( 'tab' ) ) ?>">
                
                <input type="search" name="search_term" value="<?php echo escHtml( $request->get( 'search_term' ) ) ?>" id="smliser-license-search-input" placeholder="Search licenses...">
                <button type="submit" class="button smliser-btn">Search</button>
            </form>
            
        <table class="smliser-table widefat striped">
            <thead class="<?php printf( '%s', empty( $licenses ) ? 'smliser-hide' : '' ) ?>">
                <tr>
                    <th><?php echo esc_html__( 'License ID', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Licensee Name', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'License Key', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Service ID', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Licensed App', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Status', 'smliser' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $licenses ) ) : ?>
                    <tr>
                        <td class="align-center bg-white">No licenses found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ( $licenses as $license ) :
                        ?>        
                        <tr>
                            <td class="smliser-edit-row">
                                <?php echo escHtml( $license->get_id() ); ?>
                                <p class="smliser-edit-link">
                                    <a href="<?php echo esc_url( smliser_license_admin_action_page( 'edit', $license->get_id() ) ); ?>">edit</a> | <a href="<?php echo esc_url( smliser_license_admin_action_page( 'view', $license->get_id() ) ); ?>">view</a>
                                </p>
                            </td>
                        
                            <td><?php echo escHtml( $license->get_licensee_fullname() ); ?></td>
                            <td>
                                <div class="smliser-license-obfuscation">
                                    <div class="smliser-license-obfuscation_data">
                                        <span class="smliser-license-input">
                                            <input type="text" id="<?php echo escHtml( $license->get_id() ); ?>" value="<?php echo escHtml( $license->get_license_key()) ?>" readonly class="smliser-license-text" />
                                            <span class="dashicons dashicons-admin-page copy-key smliser-tooltip" title="copy license key"></span>
                                        </span>

                                        <span class="smliser-obfuscated-license-text">
                                            <?php echo $license->get_partial_key(); ?>
                                        </span>
                                    </div>
                                    <input type="checkbox" id="<?php echo intval( microtime( true ) ); ?>" class="smliser-licence-key-visibility-toggle smliser-tooltip" title="toggle visibility">
                                </div>
                            </td>
                            <td><?php echo escHtml( $license->get_service_id() ); ?></td>
                            <td><?php echo escHtml( $license->get_app_prop() ); ?></td>
                            <td><?php echo escHtml( $license->get_status() ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php smliser_render_pagination( $pagination ); ?>
    </div>
</div>