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

<div class="smliser-admin-repository-template" role="main">
    <?php self::print_header(); ?>

    <form  class="smliser-access-control-form" 
        method="post" 
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
        aria-labelledby="smliser-form-title"
    >

        <h2 id="smliser-form-title"><?php echo esc_html( $title ); ?></h2>

        <div class="smliser-two-rows">
            <div class="smliser-two-rows_left" role="group" aria-label="Form fields">
                <?php foreach( $form_fields as $field ) : ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>
            </div>

            <input type="hidden" name="action" value="smliser_access_control_save">

            <?php if ( $render_avatar ) : ?>
                <div class="smliser-two-rows_right" role="region" aria-labelledby="avatar-upload-heading">

                    <div class="smliser-avatar-upload">

                        <h3 id="avatar-upload-heading" class="screen-reader-text">
                            Avatar Upload
                        </h3>

                        <div class="smliser-avatar-upload_image-holder">
                            <img 
                                class="smliser-avatar-upload_image-preview"
                                src="<?php echo esc_url( $avatar_url ); ?>"
                                title="<?php echo esc_attr( $avatar_url->basename() ); ?>"
                                alt="<?php echo esc_attr__( 'User avatar preview', 'smart-license-server' ); ?>"
                            >
                        </div>

                        <div class="smliser-avatar-upload_data">

                            <input
                                type="file"
                                name="avatar"
                                id="smliser-avatar-input"
                                accept="image/*"
                                class="hidden"
                                aria-describedby="avatar-upload-help"
                            >

                            <div class="smliser-avatar-upload_buttons-row" role="group" aria-label="Avatar actions">

                                <button
                                    type="button"
                                    class="button add-file"
                                    aria-controls="smliser-avatar-input"
                                >
                                    <i class="ti ti-camera" aria-hidden="true"></i>
                                    <span>Upload Image</span>
                                </button>

                                <button
                                    type="button"
                                    class="button clear hidden"
                                    aria-label="Clear selected avatar image"
                                >
                                    <i class="ti ti-x" aria-hidden="true"></i>
                                    <span>Clear</span>
                                </button>

                            </div>

                            <em id="avatar-upload-help">
                                Upload an image (170 x 170 pixels recommended).
                            </em>

                            <span 
                                class="smliser-avatar-upload_data-filename"
                                aria-live="polite"
                            >
                                <?php echo esc_html( $avatar_name ); ?>
                            </span>

                        </div>

                    </div>
                </div>

            <?php elseif ( $render_image_only ) : ?>

                <div class="smliser-two-rows_right" role="region" aria-label="Current avatar">
                    <div class="smliser-avatar-upload">
                        <div class="smliser-avatar-upload_image-holder">
                            <img
                                class="smliser-avatar-upload_image-preview avatar-only"
                                src="<?php echo esc_url( $avatar_url ); ?>"
                                alt="<?php echo esc_attr__( 'Current avatar image', 'smart-license-server' ); ?>"
                            >
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>

        <div 
            class="smliser-spinner" 
            role="status" 
            aria-live="polite" 
            aria-label="Processing"
        ></div>

        <?php if ( 'organizations' === $current_tab && 'edit' === $current_section ) : ?>

            <section 
                aria-labelledby="org-members-heading"
                class="smliser-organization-members-wrapper"
            >

                <h2 id="org-members-heading" class="screen-reader-text">
                    Organization Members
                </h2>

                <ul class="smliser-organization-members-list" role="list">

                    <?php foreach ( $organization->get_members() as $member ) : ?>

                        <li class="smliser-org-member" role="listitem">

                            <div class="smliser-org-member_header">
                                <img 
                                    src="<?php echo esc_url( $member->get_avatar() ); ?>" 
                                    alt="<?php echo esc_attr__( 'Member avatar', 'smart-license-server' ); ?>" 
                                    width="38" 
                                    height="38"
                                >

                                <strong class="smliser-org-member_name">
                                    <?php echo esc_html( $member->get_display_name() ); ?>
                                </strong>
                            </div>

                            <?php $role = $member->get_role(); ?>

                            <table 
                                class="smliser-org-member_meta"
                                role="presentation"
                                aria-label="Member details"
                            >
                                <tr>
                                    <th scope="row">Role:</th>
                                    <td>
                                        <?php echo esc_html( $role ? $role->get_label() : 'Unknown' ); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Member Since:</th>
                                    <td>
                                        <?php echo esc_html( $member->get_created_at()->format( smliser_datetime_format() ) ); ?>
                                    </td>
                                </tr>
                            </table>

                            <div 
                                class="smliser-org-member_actions"
                                role="group"
                                aria-label="Actions for <?php echo esc_attr( $member->get_display_name() ); ?>"
                            >
                                <button
                                    type="button"
                                    class="button edit-member"
                                    data-member-id="<?php echo absint( $member->get_id() ); ?>"
                                >
                                    Edit
                                </button>

                                <button
                                    type="button"
                                    class="button delete-member"
                                    data-member-id="<?php echo absint( $member->get_id() ); ?>"
                                >
                                    Delete
                                </button>
                            </div>

                        </li>

                    <?php endforeach; ?>

                    <li role="listitem">

                        <button
                            type="button"
                            class="smliser-add-member-to-org-btn"
                            aria-label="Add new member to organization"
                        >
                            <i class="ti ti-user-plus" aria-hidden="true"></i>
                            <span>Add member</span>
                        </button>

                    </li>

                </ul>

            </section>

        <?php endif; ?>

        <?php if ( $render_roles ) : ?>

            <h2 
                id="smliser-roles-title"
                class="smliser-access-control-role-deading"
            >
                <?php echo esc_html( $roles_title ); ?>
            </h2>

            <div
                id="smliser-role-builder"
                role="region"
                aria-labelledby="smliser-roles-title"
                data-roles="<?php echo esc_attr( smliser_json_encode_attr( isset( $role ) ? $role : [] ) ); ?>"
            ></div>

        <?php endif; ?>

        <button
            type="submit"
            class="button smliser-save-button"
            aria-label="Save changes"
        >
            Save
        </button>

    </form>
</div>
