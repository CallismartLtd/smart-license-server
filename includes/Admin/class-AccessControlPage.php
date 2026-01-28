<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Organization;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Actors\User;

use function defined, smliser_get_query_param, array_unshift, sprintf, time, basename, call_user_func, 
smliser_json_encode_attr, array_map, admin_url;

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
                'add-new'   => [__CLASS__, 'rest_api_form_page'],
                'edit'      => [__CLASS__, 'rest_api_form_page'],
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
        $roles_title    = sprintf( '%s User Role', $user ? 'Update' : 'Set' );

        $_user_statuses = User::get_allowed_statuses();
        $_status_titles = array_map( 'ucwords', array_values( $_user_statuses ) );
        $_status_keys   = array_values( $_user_statuses );
        $_statuses      = array_combine( $_status_keys, $_status_titles );

        $role           = $user ? [] : '';

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

        $avatar_url     = 
            $user && $user->get_avatar()->is_valid() 
            ? $user->get_avatar()->add_query_param( 'ver', time() )
            : new URL( smliser_get_placeholder_icon( 'avatar' ) );

        $avatar_name    = $user ? 'View image' : $avatar_url->basename();
        $role_obj       = $user ? ContextServiceProvider::get_principal_role( $user ) : '';

        if ( $role_obj ) {
            $collection = Collection::make( $role_obj->to_array() );

            $role   = $collection->toArray();

            unset( $collection );
        }

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

        $title          = sprintf( '%s Resource Owner', $owner ? 'Edit' : 'Add New' );
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
                'label' => __( 'Owner Type', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'owner_type',
                    'value' => $owner ? $owner->get_type() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'readonly'      => true,
                    ),
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
        $page           = (int) smliser_get_query_param( 'paged', 1 );
        $limit          = (int) smliser_get_query_param( 'limit', 25 );
        $all            = ServiceAccount::get_all( $page, $limit );
        $entity_class   = ServiceAccount::class;
        $type           = 'Service Accounts';
        include_once SMLISER_PATH . 'templates/admin/access-control/actors-principals-list.php';       
    }

    /**
     * The users creation and edit page
     */
    private static function rest_api_form_page() {
        $user_id        = smliser_get_query_param( 'id' );
        $sa_acc         = ServiceAccount::get_by_id( (int) $user_id );

        $title          = sprintf( '%s Service Account', $sa_acc ? 'Edit' : 'Add New' );
        $roles_title    = sprintf( '%s Service Account Role & Permissions', $sa_acc ? 'Update' : 'Set' );

        $_sa_statuses = ServiceAccount::get_allowed_statuses();
        $_status_titles = array_map( 'ucwords', array_values( $_sa_statuses ) );
        $_status_keys   = array_values( $_sa_statuses );
        $_statuses      = array_combine( $_status_keys, $_status_titles );

        $role           = $sa_acc ? [] : '';
        $owner_option   = [];

        if ( $sa_acc ) {
            $owner          = $sa_acc->get_owner();

            $owner_name     = $owner ? $owner->get_name() : '[Deleted Owner]';
            $owner_id       = $owner ? $owner->get_id() : 0;

            $owner_option   = [$owner_id => $owner_name];
            unset( $entity_class, $owner_name );

        }

        $form_fields    = array(
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'id',
                    'value' => $sa_acc ? $sa_acc->get_id() : 0,
                )
            ),
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'entity',
                    'value' => 'service_account',
                )
            ),

            array(
                'label' => __( 'Account Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'display_name',
                    'value' => $sa_acc ? $sa_acc->get_display_name() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Enter rest api credential name',
                        'style'         => 'width: unset'
                    )
                )
            ),

            array(
                'label' => __( 'Owner', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'owner_id',
                    'value' => $sa_acc ? $sa_acc->get_owner_id() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                    ),
                    'options'       => $owner_option
                )
            ),

            array(
                'label' => __( 'Owner Type', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'owner_type',
                    'value' => isset( $owner ) ? $owner->get_type() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'readonly'      => true,
                    ),
                )
            ),

            array(
                'label' => __( 'Status', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'status',
                    'value' => $sa_acc ? $sa_acc->get_status() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true
                    ),
                    'options'   => $_statuses
                )
            ),

            array(
                'label' => __( 'Description', 'smliser' ),
                'input' => array(
                    'type'  => 'textarea',
                    'name'  => 'description',
                    'value' => $sa_acc ? $sa_acc->get_description() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'placeholder'   => 'Enter description'
                    )
                )
            ),
     
        );

        $avatar_url     = 
            $sa_acc && $sa_acc->get_avatar()->is_valid() 
            ? $sa_acc->get_avatar()->add_query_param( 'ver', time() )
            : new URL( smliser_get_placeholder_icon( 'avatar' ) );

        $avatar_name    = $sa_acc ? 'View image' : $avatar_url->basename();
        $role_obj       = $sa_acc ? ContextServiceProvider::get_principal_role( $sa_acc ) : '';

        if ( $role_obj ) {
            $collection = Collection::make( $role_obj->to_array() );

            $role   = $collection->toArray();

            unset( $collection );
        }

        include_once SMLISER_PATH . 'templates/admin/access-control/access-control-form.php';
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
                    'title' => 'Users',
                    'label' => 'Users',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=users'),
                    'icon'  => 'ti ti-user'
                ),

                array(
                    'title' => 'REST API Service Accounts',
                    'label' => 'Service Accounts',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=rest-api'),
                    'icon'  => 'ti ti-robot'
                ),

                array(
                    'title' => 'Resource Owners',
                    'label' => 'Owners',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=owners'),
                    'icon'  => 'ti ti-source-code'
                ),

                array(
                    'title' => 'Organizations',
                    'label' => 'Organizations',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=organizations'),
                    'icon'  => 'ti ti-users-group'
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
