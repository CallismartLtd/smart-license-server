<?php
/**
 * Access control form page template.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\AccessControlPage
 * @var string $title
 * @var string $roles_title
 * @var array $form_fields
 * @var \SmartLicenseServer\Core\URL $avatar_url
 * @var string $avatar_name
 * @var array $role
 * @var SmartLicenseServer\Security\OwnerSubjects\Organization $organization
 */

use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
defined( 'SMLISER_ABSPATH' ) || exit;

$current_tab        = smliser_get_query_param( 'tab', '' );
$current_section    = smliser_get_query_param( 'section', '' );
$render_roles       = in_array( $current_tab, ['rest-api', 'users', ], true )
    || in_array( $current_section, ['add-new-member', 'edit-member'], true );
$render_avatar      = ! in_array( $current_tab, ['owners'] );

$render_image_only  = in_array( $current_section, ['add-new-member', 'edit-member'], true );

if ( $render_image_only ) {
    $render_avatar = false;
}

?>

<div class="smliser-admin-repository-template">
    <?php self::print_header(); ?>
    <form class="smliser-access-control-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <h2><?php echo esc_html( $title ); ?></h2>
        <div class="smliser-two-rows">
            <div class="smliser-two-rows_left">
                <?php foreach( $form_fields as $field ) : ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="action" value="smliser_access_control_save">
            <?php if ( $render_avatar ) : ?>
                <div class="smliser-two-rows_right">
                    <div class="smliser-avatar-upload">
                        <div class="smliser-avatar-upload_image-holder">
                            <img class="smliser-avatar-upload_image-preview" src="<?php echo esc_url( $avatar_url ); ?>" title="<?php echo esc_attr( $avatar_url->basename() ); ?>" alt="avatar">
                        </div>
                        <div class="smliser-avatar-upload_data">
                            <input type="file" name="avatar" id="smliser-avatar-input" accept="image/*" class="hidden">
                            <div class="smliser-avatar-upload_buttons-row">
                                <button type="button" class="button add-file"><i class="ti ti-camera"></i> Upload Image</button>
                                <button type="button" class="button clear hidden"><i class="ti ti-x"></i> Clear</button>
                            </div>
                            <em>Upload an image(170 x 170 pixels recommended).</em>

                            <span class="smliser-avatar-upload_data-filename"><?php echo esc_html( $avatar_name ) ?></span>
                        </div>

                    </div>
                </div>
            <?php elseif ( $render_image_only ) : ?>
                <div class="smliser-two-rows_right">
                    <div class="smliser-avatar-upload">
                        <div class="smliser-avatar-upload_image-holder">
                            <img class="smliser-avatar-upload_image-preview avatar-only" src="<?php echo esc_url( $avatar_url ); ?>" title="<?php echo esc_attr( $avatar_url->basename() ); ?>" alt="avatar">
                        </div>

                    </div>
                </div>
            <?php endif; ?>
        </div>

       <div class="smliser-spinner"></div>

       <?php if ( 'organizations' === $current_tab && 'edit' === $current_section ) : ?>
            <ul class="smliser-organization-members-list">
                <?php foreach ( $organization->get_members() as $member ) : ?>
                    <li class="smliser-org-member">
                        <div class="smliser-org-member_header">
                            <img src="<?php echo esc_url( $member->get_avatar() ); ?>" alt="Member avatar" width="38" height="38">
                            <strong class="smliser-org-member_name"><?php echo esc_html( $member->get_display_name() ); ?></strong>
                        </div>

                        <?php 
                            $role = ContextServiceProvider::get_principal_role( $member, $organization );
                        
                        ?>
                        <dl class="smliser-org-member_meta">
                            <dt>Role:</dt>
                            <dd><?php echo esc_html( $role ? $role->get_label(): 'Unknown'  ); ?></dd>
                            <dt>Member Since:</dt>
                            <dd><?php echo esc_html( $member->get_created_at()->format( smliser_datetime_format() ) ); ?></dd>
                        </dl>

                        <div class="smliser-org-member_actions">
                            <button type="button" class="button edit-member" data-member-id="<?php echo esc_attr( $member->get_id() ); ?>">Edit</button>
                            <button type="button" class="button" data-member-id="<?php echo esc_attr( $member->get_id() ); ?>">Delete</button>
                        </div>
                    </li>
                <?php endforeach; ?>

                <li>
                    <button type="button" class="smliser-add-member-to-org-btn">
                        <i class="ti ti-user-plus" aria-hidden="true"></i>
                        <span>Add member</span>
                    </button>                
                </li>

            </ul>

        <?php endif; ?>
        
        <?php if ( $render_roles ) : ?>
            <h2 class="smliser-access-control-role-deading"><?php echo esc_html( $roles_title ); ?></h2>
            <!-- Mounted dynamically - @see role-builder.js -->
            <div id="smliser-role-builder" data-roles="<?php echo esc_attr( smliser_json_encode_attr( isset( $role ) ? $role : [] ) ); ?>"></div>
        <?php endif; ?>
        <button type="submit" class="button smliser-save-button">Save</button>
    </form>


</div>