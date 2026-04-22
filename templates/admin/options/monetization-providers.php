<?php
/**
 * Admin monetization page - All providers list
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

use SmartLicenseServer\Admin\OptionsPage;

defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( OptionsPage::get_menu_args( $request ) ); ?>
    <div class="smliser-table-wrapper">
        <table class="smliser-table widefat striped">
            <thead>
                <tr>
                    <th>Provider ID</th>
                    <th>Provider Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach( $providers as $id => $provider ) : ?>
                    <tr>
                        <td><?php echo escHtml( $id ); ?></td>
                        <td><?php echo escHtml( $provider::get_name() ); ?></td>
                        <td><a href="<?php echo esc_url( smliser_options_url()->add_query_params( ['tab' => 'monetization',  'provider' => $provider::get_id() ] ) ); ?>" class="button smliser-nav-btn"> <span class="dashicons dashicons-admin-generic"></span> Manage</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>