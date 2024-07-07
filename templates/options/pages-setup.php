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
<p>You can customize the the structures for your repository URLs here. Using <code>plugins</code> will make your repository links like <code><?php echo esc_url( home_url( '/plugins' ) ); ?>/plugin-slug/</code></p>


<form action="" method="post" >
    <?php wp_nonce_field( 'smliser_perma_form', 'smliser_perma_form' );?>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th>
                    <label for="repos-perma-struct">Repository Base Slug</label>
                </th>
                <td>
                    <input type="text" id="repos-perma-struct" name="smliser_permalink" value="/<?php echo esc_html( get_option( 'smliser_repo_base_perma', 'plugins' ) );?>/" class="regular-text"> current slug is set to <code><?php echo esc_url( home_url( '/' . get_option( 'smliser_repo_base_perma', 'plugins' ) . '/' ) ); ?></code>
                </td>
            </tr>  
        </tbody>     
    </table>
</form>