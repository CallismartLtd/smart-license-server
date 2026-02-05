<?php
/**
 * Software Monetization admin page template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Admin
 * @since 0.0.5 
 * @var SmartLicenseServer\Core\URL $url
 */

use SmartLicenseServer\Core\URL;

use SmartLicenseServer\Monetization\Monetization,
    SmartLicenseServer\Monetization\ProviderCollection;

;

defined( 'SMLISER_ABSPATH' ) || exit; 

$id         = smliser_get_query_param( 'app_id' );
$app_type   = smliser_get_query_param( 'type' );
$is_new     = false;

$object     = Monetization::get_by_app( $app_type, $id );
$providers  = ProviderCollection::instance()->get_providers();

if ( empty( $object ) ) {
    $is_new = true;
    $object = new Monetization();
    $object->set_app_id( $id )
        ->set_app_type( $app_type );
}

$app = $object->get_app();
$view_url   = clone( $url );
$view_url->add_query_params( [ 'tab' => 'view', 'app_id' => $id, 'type' => $app_type] );
$edit_url = clone $view_url;
$edit_url->add_query_param( 'tab', 'edit' );
?>

<h1>Software Monetization</h1>

<?php if ( empty( $app ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'This app does not exist in the repository <a href="' . smliser_repo_page() . '">Back</a>' ) ); ?>
<?php else : ?>
    <a href="<?php echo esc_url( $url->__toString() ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-database"></span> Repository</a>
    <a href="<?php echo esc_url( $view_url->__toString() ); ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-visibility"></span> View</a>
    <a href="<?php echo esc_url( $edit_url->__toString() ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-edit"></span> Edit Plugin</a>
    <p><span class="dashicons dashicons-info"></span><?php printf( 'Use this interface to %s monetization for <strong>%s</strong>', ( $app->is_monetized() ) ? 'manage' :'set up', $app->get_name() ); ?></p>

    <div class="smliser-monetization-ui">
        <div class="smliser-monetization-ui__software-info">

            <div class="smliser-monetization-ui__software-essentials">
                <h2>Application Details</h2>
                <table class="widefat striped">
                    <tr>
                        <th>App Name:</th>
                        <td><?php echo esc_html( $app->get_name() ); ?></td>
                    </tr>
                    
                    <tr>
                        <th>App Type:</th>
                        <td><?php echo esc_html( $app->get_type() ); ?></td>
                    </tr>

                    <tr>
                        <th>Version:</th>
                        <td><?php echo esc_html( $app->get_version() ); ?></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo wp_kses_post( $app->get_short_description() ); ?></td>
                    </tr>
                    <tr>
                        <th>Author:</th>
                        <td><?php echo esc_html( $app->get_author() ); ?></td>
                    </tr>
                    <tr>
                        <th>Monetization Status:</th>
                        <td>
                            <?php
                            smliser_render_toggle_switch( array(
                                'id'                   => 'monetization_enabled',
                                'name'                 => 'enabled',
                                'value'                => $object->is_enabled() ? 1 : 0,
                                'data-action'          => 'toggleMonetization',
                                'data-monetization-id' => absint( $object->get_id() ),
                            ) );
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="smliser-monetization-ui__software-tiers">
                <h2>Tiers</h2>
                <table class="widefat striped">
                    <?php if ( empty( $object->get_tiers() ) ) : ?>
                        <tr>
                            <td>No pricing tiers has been set</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $object->get_tiers() as $tier ) : ?>
                            <?php
                                $features = $tier->get_features();
                                $features_str = is_array( $features ) ? implode( ', ', $features ) : (string) $features;

                                // Encode the tier into JSON for JS handling
                                $tier_json = smliser_safe_json_encode( [
                                    'id'                => $tier->get_id(),
                                    'name'              => $tier->get_name(),
                                    'provider_id'       => $tier->get_provider_id(),
                                    'product_id'        => $tier->get_product_id(),
                                    'billing_cycle'     => $tier->get_billing_cycle(),
                                    'max_sites'         => $tier->get_max_sites(),
                                    'features'          => $features,
                                    'monetization_id'   => $tier->get_monetization_id(),
                                ] );
                            ?>
                            <tr>
                                <th><?php echo esc_html( $tier->get_name() ); ?></th>
                                <td>
                                    <div class="smliser-pricing-tier-info" data-json='<?php echo esc_attr( $tier_json ); ?>'>
                                        <p><strong>Provider:</strong> <?php echo esc_html( $tier->get_provider_id() ); ?></p>
                                        <p><strong>Product ID:</strong> <?php echo esc_html( $tier->get_product_id() ); ?></p>
                                        <p><strong>Billing Cycle:</strong> <?php echo esc_html( $tier->get_billing_cycle() ); ?></p>
                                        <p><strong>Features:</strong> <?php echo esc_html( $features_str ); ?></p>

                                        <div class="smliser-pricing-tier-actions">
                                            <button type="button" class="button smliser-tier-view smliser-nav-btn" data-action="viewProductData">
                                                <span class="dashicons dashicons-visibility"></span> View Product Data
                                            </button>

                                            <button type="button" class="button smliser-tier-edit smliser-nav-btn" data-action="editTier">
                                                <span class="dashicons dashicons-edit"></span> Edit
                                            </button>

                                            <button type="button" class="button smliser-tier-delete smliser-nav-btn" data-action="deleteTier">
                                                <span class="dashicons dashicons-trash"></span> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </table>

            </div>
        </div>

        <div class="smliser-monetization-ui__monetization-providers">
            <div class="smliser-monetization-ui__providiers-list">
                <div class="monetization-providers_header">
                    <h2>Monetization Providers</h2>
                    <button type="button" class="button action smliser-nav-btn" id="add-pricing-tier" data-command="addNewTier"><span class="dashicons dashicons-plus"></span> Add Pricing Tier</button>
                </div>
                <?php if ( empty( $providers ) ) : ?>
                    <?php echo smliser_not_found_container( 'No monetization provider found' ); ?>
                <?php else: ?>
                    <?php foreach( $providers as $provider ): ?>
                        <div class="smliser-monetization-ui__monetization-provider">
                            <p>Name: <strong><?php echo esc_html( $provider->get_name() ); ?></strong></p>
                            <p>Base URL: <?php echo esc_html( $provider->get_url() ); ?></p>
                            <p>Checkout URL: <?php echo esc_html( $provider->get_checkout_url() ); ?></p>

                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
        <div class="smliser-admin-modal pricing-tier hidden">
            <div class="smliser-admin-modal_content">
                <span class="dashicons dashicons-dismiss remove-modal" title="remove" data-command="closeModal"></span>
                <h2>Add Pricing Tier</h2>
                <em>A pricing tier represents a specific license option for the application</em>
                <form id="tier-form" class="smliser-admin-modal_content-form">
                    <input type="hidden" name="action" value="">
                    <input type="hidden" name="monetization_id" value="<?php echo absint( $object->get_id() ); ?>">
                    <input type="hidden" name="app_id" value="<?php echo absint( $app->get_id() ); ?>">
                    <input type="hidden" name="app_type" value="<?php echo esc_attr( $object->get_app_type() ); ?>">
                    <input type="hidden" name="tier_id">
                    <label for="tier_name">Tier Name:
                        <input type="text" name="tier_name" id="tier_name" field-name="Tier Name">
                    </label>
                    <label for="product_id">Product ID:
                        <input type="text" name="product_id" id="product_id" field-name="Product ID">
                    </label>
                    <label for="billing_cycle">Billing Cycle:
                        <input type="text" name="billing_cycle" id="billing_cycle" placeholder="example: monthly, yearly" field-name="Billing Cycle">
                    </label>
                    <label for="provider_id">Monetization Provider:
                        <select name="provider_id" id="provider_id" field-name="Monetization Provider">
                            <option value="">--Choose Provider--</option>
                            <?php foreach( $providers as $provider ) : ?>
                                <option value="<?php echo esc_attr( $provider->get_id() ) ?>"><?php echo esc_html( $provider->get_name() ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label for="max_sites">Maximum Websites:
                        <input type="number" name="max_sites" id="max_sites" placeholder="Leave empty for unlimited">
                    </label>
                    <label for="features">Features:
                        <textarea type="number" name="features" id="features" placeholder="example: feature1, feature2, feature3" field-name="Features"></textarea>
                    </label>
                    <button type="submit" class="button smliser-nav-btn"><span class="dashicons dashicons-cloud"></span> Save</button>
                </form>
            </div>

        </div>
    </div>
<?php endif; ?>