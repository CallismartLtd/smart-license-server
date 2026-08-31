<?php
/**
 * Admin monetization page - All providers list
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 * @var \SmartLicenseServer\Core\Request $request
 * @var array<int|string, \SmartLicenseServer\Contracts\ServiceProviderInterface> $providers
 * @var \SmartLicenseServer\Admin\Handlers\OptionsPage $page_handler
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

defined( 'SMLISER_ROOT' ) || exit; ?>

<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $page_handler->get_menu_args( $request ) ); ?>
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
                        <td>
                            <a href="<?php 
                            echo escUrl( $urlmanager->admin_options_url( 'monetization' )
                                ->add_query_params(  ['provider' => $provider::get_id() ] )->url()
                            ); ?>"
                            class="button smliser-nav-btn"> <i class="ti ti-settings"></i> Configure</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>