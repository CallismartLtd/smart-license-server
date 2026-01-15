<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Security\ContextServiceProvider;
use SmartLicenseServer\Security\DefaultRoles;
use SmartLicenseServer\Security\Organization;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\User;

use function defined, smliser_get_query_param, array_unshift, sprintf,time, 
smliser_json_encode_attr, array_map, array_combine, array_values, is_array;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin bulk message page class
 */
class AccessControlPage {
    /**
     * Page router.
     */
    public static function router() {
        $tab     = smliser_get_query_param( 'tab' );
        $section = smliser_get_query_param( 'section' );

        $routes = [
            'users' => [
                'default'   => [__CLASS__, 'users_page'],
                'add-new'   => [__CLASS__, 'users_form_page'],
                'edit'      => [__CLASS__, 'users_form_page'],
            ],
            'organizations' => [
                'default'       => [__CLASS__, 'organizations_page'],
                'edit'       => [__CLASS__, 'add_new_organizations_page'],
            ],
            'owners'    => [
                'default'   => [__CLASS__, 'owners_page'],
                'add-new'   => [__CLASS__, 'owners_form_page'],
                'edit'      => [__CLASS__, 'owners_form_page'],
            ],
            'rest-api'  => [
                'default'   => [__CLASS__, 'rest_api_page'],
                'add-new'   => [__CLASS__, 'add_new_rest_api_page'],
            ],
        ];

        if ( isset( $routes[ $tab ] ) ) {
            $handler = $routes[ $tab ][ $section ] ?? $routes[ $tab ]['default'];
            call_user_func( $handler );
            return;
        }

        self::dashboard();
    }


    /**
     * Access control page dashbard.
     */
    public static function dashboard() {
          
        include_once SMLISER_PATH . 'templates/admin/access-control/dashboard.php';
    
    }

    /**
     * Users page.
     */
    private static function users_page() {
        $page           = (int) smliser_get_query_param( 'paged', 1 );
        $limit          =  (int) smliser_get_query_param( 'limit', 25 );
        $all            = User::get_all( $page, $limit );
        $entity_class   = User::class;
        $type           = 'user';

        include_once SMLISER_PATH . 'templates/admin/access-control/actors-principals-list.php';
    }

    /**
     * The users creation and edit page
     */
    private static function users_form_page() {
        $user_id        = smliser_get_query_param( 'id' );
        $user           = User::get_by_id( (int) $user_id );

        $title          = sprintf( '%s User', $user ? 'Edit' : 'Add New' );
        $roles_title    = 'Ownership Roles';

        $_user_statuses = User::get_allowed_statuses();
        $_status_titles = array_map( 'ucwords', array_values( $_user_statuses ) );
        $_status_keys   = array_values( $_user_statuses );
        $_statuses      = array_combine( $_status_keys, $_status_titles );

        $form_fields    = array(
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'id',
                    'value' => $user ? $user->get_id() : 0,
                )
            ),

            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'entity',
                    'value' => 'user',
                )
            ),
            array(
                'label' => __( 'Full Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'display_name',
                    'value' => $user ? $user->get_display_name() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Enter full name',
                        'style'         => 'width: unset'
                    )
                )
            ),

            array(
                'label' => __( 'Email', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'email',
                    'value' => $user ? $user->get_email() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Enter email address'
                    )
                )
            ),
     
            array(
                'label' => __( 'Password', 'smliser' ),
                'input' => array(
                    'type'  => 'password',
                    'name'  => 'password_1',
                    'value' => '',
                    'attr'  => array(
                        'autocomplete'  => \time(),
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Enter password',
                        'disabled'      => true
                    )
                )
            ),
     
            array(
                'label' => __( 'Confirm Password', 'smliser' ),
                'input' => array(
                    'type'  => 'password',
                    'name'  => 'password_2',
                    'value' => '',
                    'attr'  => array(
                        'autocomplete'  => \time(),
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Confirm passowrd',
                        'disabled'      => true
                    )
                )
            ),
     
            array(
                'label' => __( 'Generate Password', 'smliser' ),
                'input' => array(
                    'type'  => 'button',
                    'name'  => 'smliser-generate-password',
                    'value' => 'Generate Password',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'class'         => 'button',
                        'data-fields'   => smliser_json_encode_attr( [ 'password_1', 'password_2'] )
                    )
                )
            ),
     
            array(
                'label' => __( 'Status', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'status',
                    'value' => $user ? $user->get_status() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true
                    ),
                    'options'   => $_statuses
                )
            ),
     
        );

        $avatar_url     = $user ? $user->get_avatar()->add_query_param( 'ver', time() ) : smliser_get_placeholder_icon( 'avatar' );
        $avatar_name    = $user ? 'View image' : \basename( $avatar_url );
        include_once SMLISER_PATH . 'templates/admin/access-control/access-control-form.php';
    }

    /**
     * Organizations page.
     */
    private static function organizations_page() {

   
        include_once SMLISER_PATH . 'templates/admin/access-control/organizations.php';
    }

    /**
     * Resource owners.
     */
    private static function owners_page() {
        $page   = (int) smliser_get_query_param( 'paged', 1 );
        $limit  = (int) smliser_get_query_param( 'limit', 25 );
        $owners = Owner::get_all( $page, $limit );

        include_once SMLISER_PATH . 'templates/admin/access-control/owners.php';
    }

    /**
     * The owners creation and edit page
     */
    private static function owners_form_page() {
        $owner_id           = smliser_get_query_param( 'id' );
        $owner              = Owner::get_by_id( (int) $owner_id );

        $title              = 'Add New Resource Owner';
        $roles_title        = 'Ownership Roles';

        $_owner_statuses    = Owner::get_allowed_statuses();
        $_status_titles     = array_map( 'ucwords', array_values( $_owner_statuses ) );
        $_status_keys       = $_owner_statuses;
        $_statuses          = array_combine( $_status_keys, $_status_titles );

        $_owner_types_keys  = Owner::get_allowed_owner_types();
        $_owner_types_titles= array_map( 'ucwords', $_owner_types_keys );
        $_owner_types       = array_combine( $_owner_types_keys, $_owner_types_titles );
        $principal_option   = array();

        if ( $owner ) {
            $_owner_type    = $owner->get_type();
            $principal_id   = $owner->get_principal_id();
            $_entity_class  = ContextServiceProvider::get_entity_classname( $_owner_type );
            $principal      = $_entity_class ? $_entity_class::get_by_id( $principal_id ) : '';

            $pr_name        = $principal ? $principal->get_name() : '[Deleted entity]';

            $principal_option   = [$principal_id => $pr_name];

            unset( $_owner_type, $entity_class, $_entity_class, $principal, $pr_name );

            $default_roles  = $owner->get_roles();
            
            if ( is_array( $default_roles ) ) {
                $roles = [];

                foreach ( $default_roles as $def_role ) {
                    $roles[]    = $def_role->to_array();
                }
            } else {
                $roles      = $default_roles ? $default_roles->to_array() : null;
                $role_id    = $default_roles->get_id();
            }

        }

        $form_fields    = array(
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'id',
                    'value' => $owner ? $owner->get_id() : 0,
                )
            ),

            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'entity',
                    'value' => 'owner',
                )
            ),

            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'role_id',
                    'value' => isset( $role_id ) ? $role_id : 0,
                )
            ),
            array(
                'label' => __( 'Owner Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'name',
                    'value' => $owner ? $owner->get_name() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Enter owner name'
                    )
                )
            ),
            array(
                'label' => __( 'Principal', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'principal_id',
                    'value' => $owner ? $owner->get_principal_id() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                    ),
                    'options'       => $principal_option
                )
            ),
            array(
                'label' => __( 'Type', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'type',
                    'value' => $owner ? $owner->get_type() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true
                    ),
                    'options'   => $_owner_types
                )
            ),
     
            array(
                'label' => __( 'Status', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'status',
                    'value' => $owner ? $owner->get_status() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true
                    ),
                    'options'   => $_statuses 
                )
            ),
     
        );

        include_once SMLISER_PATH . 'templates/admin/access-control/access-control-form.php';
    }

    /**
     * REST API setting page.
     */
    private static function rest_api_page() {

        include_once SMLISER_PATH . 'templates/admin/access-control/rest-api.php';
       
    }

    /**
     * Print admin header
     */
    protected static function print_header() {
        $tab        = smliser_get_query_param( 'tab' );
        $title      = match( $tab ) {
            'users'         => 'Users',
            'organizations' => 'Organization',
            'owners'        => 'Resource Owners',
            'rest-api'      => 'REST API Credentials',
            default         => 'Security & Access Control'

        };

        $args   = array(
            'breadcrumbs' => array(
                array(
                    'label' => $title,
                ),
            ),
            'actions'   => array(
                array(
                    'title' => 'Resource Owners',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=owners'),
                    'icon'  => 'ti ti-source-code'
                ),

                array(
                    'title' => 'Users',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=users'),
                    'icon'  => 'ti ti-user'
                ),

                array(
                    'title' => 'Organizations',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=organizations'),
                    'icon'  => 'ti ti-users-group'
                ),
                array(
                    'title' => 'REST API',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=rest-api'),
                    'icon'  => 'ti ti-api'
                ),
            )
        );

        if ( $tab ) {
            $home = array(
                'title' => 'Security & Access Control',
                'url'   => admin_url( 'admin.php?page=smliser-access-control' ),
                'icon'  => 'ti ti-home'
            );
            
            array_unshift( $args['actions'], $home );
        }

        Menu::print_admin_top_menu( $args );
    }
}
