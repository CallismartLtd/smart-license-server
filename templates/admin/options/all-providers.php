<?php
/**
 * Admin monetization page - All providers tables
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

defined( 'ABSPATH' ) || exit; ?>
<h2>Monetization Providers</h2>
<table class="widefat striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach( $providers as $id => $provider ) : ?>
            <tr>
                <td><?php echo esc_html( $id ); ?></td>
                <td><?php echo esc_html( $provider->get_name() ); ?></td>
                <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=smliser-options&tab=monetization&provider=' . $provider->get_id() ) ) ?>" class="button smliser-nav-btn"> <span class="dashicons dashicons-admin-generic"></span> Manage</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>