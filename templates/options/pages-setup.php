<?php
/**
 * Page set up options template.
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<h2>Pages Setup</h2>

<?php flush_rewrite_rules(); ?>

<?php if ( get_transient( 'smliser_form_success' ) ):?>
    <div class="notice notice-success is-dismissible"><p>Saved!</p></div>
<?php delete_transient( 'smliser_form_success' ); endif;?>

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
                    <span class="smliser-form-description" title="You can customize the the structures for your repository URLs here. Using 'plugins' will make your repository links like '<?php echo esc_url( home_url( '/plugins' ) ); ?>/plugin-slug/'">?</span>
                    <input type="text" id="repos-perma-struct" name="smliser_permalink" value="/<?php echo esc_html( get_option( 'smliser_repo_base_perma', 'plugins' ) );?>/" class="regular-text"> current slug is set to <code><?php echo esc_url( home_url( '/' . get_option( 'smliser_repo_base_perma', 'plugins' ) . '/' ) ); ?></code>
                </td>
            </tr>  
        </tbody>     
    </table>
    <input type="submit" name="smliser_page_setup" class="button action smliser-bulk-action-button" value="Save">

</form>

