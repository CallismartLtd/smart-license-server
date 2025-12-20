<?php
/**
 * Page set up options template.
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 1.0.0
 */

defined( 'SMLISER_ABSPATH' ) || exit;
?>
<h2>Pages Setup</h2>

<?php flush_rewrite_rules(); ?>

<?php if ( $message = smliser_get_query_param( 'message' ) ):?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ) ?></p></div>
<?php endif;?>

<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" >
    <?php wp_nonce_field( 'smliser_options_form', 'smliser_options_form' );?>
    <input type="hidden" name="action" value="smliser_options">
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th>
                    <label for="repos-perma-struct">Repository Base Slug</label>
                </th>
                <td>
                    <span class="smliser-form-description" title="You can customize the the structures for your repository URLs here. Using 'repository' will make your repository links like '<?php echo esc_url( home_url( '/repository' ) ); ?>/app-slug/'">?</span>
                    <input type="text" id="repos-perma-struct" name="smliser_permalink" value="/<?php echo esc_html( \smliser_settings_adapter()->get( 'smliser_repo_base_perma', 'repository' ) );?>/" class="regular-text"> current slug is set to <code><?php echo esc_url( home_url( '/' . \smliser_settings_adapter()->get( 'smliser_repo_base_perma', 'repository' ) . '/' ) ); ?></code>
                </td>
            </tr>  
        </tbody>     
    </table>
    <input type="submit" name="smliser_page_setup" class="button action smliser-bulk-action-button" value="Save">

</form>

