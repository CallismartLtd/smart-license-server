<?php
/**
 *  Admin License details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 * @var \SmartLicenseServer\Monetization\License $license
 * @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface $licensed_app
 */

defined( 'ABSPATH' ) || exit;

?>
<h1>License Details</h1>
<?php if ( empty( $license ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'Invalid or deleted license' ) ); ?>
<?php else: ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=licenses' ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-admin-home"></span> Licenses</a>
    <a href="<?php echo esc_url( smliser_license_admin_action_page( 'edit', $license->get_id() ) ); ?>" class="button action smliser-nav-btn"> <span class="dashicons dashicons-edit"></span> Edit License</a>
    <?php if ( $license->is_issued() && $licensed_app ) : ?>
        <a data-license-id="<?php echo absint( $license->get_id() ); ?>" data-plugin-name="<?php echo esc_html( $licensed_app->get_name() ); ?>" class="button action smliser-nav-btn" id="smliserDownloadTokenBtn"><span class="dashicons dashicons-download"></span> Generate Download Token</a>
    <?php endif;?>
    <a href="<?php echo esc_url( $delete_link ); ?>" class="button action smliser-nav-btn" id="smliser-license-delete-button"> <span class="dashicons dashicons-trash"></span>Delete License</a>
    <div class="smliser-admin-view-page-wrapper">
        <div class="smliser-admin-view-page-header"> 
            <div class="smliser-admin-view-page-header-child">
                <h2>Info</h2>
                <p><span class="dashicons dashicons-database-view"></span> License ID: <?php echo esc_html( absint( $license->get_id() ) ) ?></p>
                <p><span class="dashicons dashicons-yes-alt"></span> Status: <?php echo esc_html( $license->get_status() ) ?></p>
                <p>
                    <a href="<?php echo esc_url( get_edit_user_link( absint( $license->get_user_id() ) ) ) ?>">
                        <span class="dashicons dashicons-businessman"></span> Client: <?php echo esc_html( $client_fullname ) ?>
                    </a>
                </p>
            </div>

            <div class="smliser-admin-view-page-header-child">
                <h2>Statistics</h2>
                <p><span class="dashicons dashicons-admin-site-alt"></span> Max Allowed Domains: <?php echo esc_html( $license->get_max_allowed_domains() ) ?></p>
                <p><span class="dashicons dashicons-admin-site-alt3"></span> Total Domains Activated: <?php echo absint( $license->get_total_active_domains() )?></p>
                <p><span class="dashicons dashicons-plugins-checked"></span> App Prop: <?php echo esc_html( $license->get_app_prop() ) ?></p>
            </div>

        </div>
        
        <div class="smliser-loader-container">
            <span class="smliser-loader"></span>
        </div>
        
        <div id="ajaxContentContainer"></div>

        <div class="smliser-admin-view-page-body">
            <table class="widefat smliser-license-table">
                <tbody>
                    <tr>
                        <th>License ID</th>
                        <td><?php echo esc_html( absint( $license->get_id() ) ); ?></td>
                    </tr>
 
                    <tr>
                        <th>Is issued</th>
                        <td>
                            <?php if ( $license->is_issued() ) : ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-no"></span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>App Info</th>
                        <td>
                            <?php if ( $license->is_issued() ) : ?>
                                <a href="<?php echo esc_url( smliser_admin_repo_tab( 'view', $licensed_app->get_id() ) ); ?>">
                                    <?php echo esc_html( $licensed_app->get_name() ); ?> » <?php printf( '%s/%s', esc_html( $licensed_app->get_type() ), esc_html( $licensed_app->get_slug() ) ) ?>
                                </a>
                            <?php else : ?>
                                <span>N/L</span>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th>Service ID</th>
                        <td><?php echo esc_html( $license->get_service_id() ); ?></td>
                    </tr>

                    <tr>
                        <th>Start Date</th>
                        <td><?php echo esc_html( smliser_check_and_format( $license->get_start_date() ) ); ?></td>
                    </tr>

                    <tr>
                        <th>End Date</th>
                        <td><?php echo esc_html( smliser_check_and_format( $license->get_end_date() ) ); ?></td>
                    </tr>

                    <tr>
                        <th>Activated on</th>
                        <td><?php echo esc_html( $license->get_active_domains() ); ?></td>
                    </tr>

                    <tr>
                        <th>License Key</th>
                        <td>
                            <div class="smliser-license-obfuscation">
                                <div class="smliser-license-obfuscation_data">
                                    <span class="smliser-license-input">
                                        <input type="text" 
                                            id="<?php echo esc_html( $license->get_id() ); ?>" 
                                            value="<?php echo esc_html( $license->get_license_key() ); ?>" 
                                            readonly 
                                            class="smliser-license-text" 
                                        />
                                        <span class="dashicons dashicons-admin-page copy-key smliser-tooltip" title="Copy license key"></span>
                                    </span>

                                    <span class="smliser-obfuscated-license-text">
                                        <?php echo esc_html( $license->get_partial_key() ); ?>
                                    </span>
                                </div>

                                <input type="checkbox" 
                                    id="toggle-<?php echo esc_html( $license->get_id() ); ?>" 
                                    class="smliser-licence-key-visibility-toggle smliser-tooltip" 
                                    title="Toggle visibility" 
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2>REST API Documentation</h2>
        <div class="smliser-admin-api-description-section">
            <div class="smliser-api-base-url">
                <strong>Base URL:</strong>
                <code><?php echo esc_url( rest_url() ); ?></code>
            </div>
            
            <?php foreach ( $route_descriptions as $path => $html ) : 
                echo $html; // Already safely escaped in the V1 class
            endforeach; ?>
        </div>
    </div>
<?php endif; ?>