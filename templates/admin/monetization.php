<?php
/**
 * Software Monetization admin page template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Admin
 * @since 0.0.5 
 */

use SmartLicenseServer\Monetization\Monetization;

defined( 'ABSPATH' ) || exit; 

$id         = smliser_get_query_param( 'item_id' );
$item_type  = smliser_get_query_param( 'item_type' );
$is_new     = false;

$object     = Monetization::get_by_item( $item_type, $id );

if ( empty( $object ) ) {
    $is_new = true;
    $object = new Monetization();
    $object->set_item_id( $id )
        ->set_item_type( $item_type );
}

?>

<h1>Software Monetization</h1>

<?php if ( empty( $object ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'Repository item does not exists <a href="' . smliser_repo_page() . '">Back</a>' ) ); ?>
<?php else : ?>

<?php endif; ?>