<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Actors\User;

use function defined, array_unshift, sprintf, smliser_json_encode_attr, smliser_render_template, compact;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin bulk message page class
 */
class AccessControlPage {
    /**
     * Page router.
     */
    public static function router( Request $request ) : void {
        $tab     = $request->get( 'tab' );
        $section = $request->get( 'section' );

        $routes = [
            'users' => [
                'default'   => [__CLASS__, 'users_page'],
                'add-new'   => [__CLASS__, 'users_form_page'],
                'edit'      => [__CLASS__, 'users_form_page'],
            ],
            'organizations' => [
                'default'           => [__CLASS__, 'organizations_page'],
                'add-new'           => [__CLASS__, 'organizations_form_page'],
                'edit'              => [__CLASS__, 'organizations_form_page'],
                'add-new-member'    => [__CLASS__, 'organizations_members_form_page'],
                'edit-member'       => [__CLASS__, 'organizations_members_form_page'],
            ],
            'owners'    => [
                'default'   => [__CLASS__, 'owners_page'],
                'add-new'   => [__CLASS__, 'owners_form_page'],
                'edit'      => [__CLASS__, 'owners_form_page'],
            ],
            'service-account'  => [
                'default'   => [__CLASS__, 'rest_api_page'],
                'add-new'   => [__CLASS__, 'rest_api_form_page'],
                'edit'      => [__CLASS__, 'rest_api_form_page'],
            ],
        ];

        if ( isset( $routes[ $tab ] ) ) {
            $handler = $routes[ $tab ][ $section ] ?? $routes[ $tab ]['default'];
            $handler( $request );
            return;
        }

        self::dashboard( $request );
    }


    /**
     * Access control page dashbard.
     * 
     * @param Request $request
     */
    public static function dashboard( Request $request ) {
        $account_summaries  = ContextServiceProvider::get_accounts_summary_report();
        $vars               = compact( 'request', 'account_summaries' );
        smliser_render_template( 'admin.accounts.index', $vars );
    
    }

    /**
     * Users page.
     * 
     * @param Request $request
     */
    private static function users_page( $request ) : void {
        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $all            = User::get_all( $page, $limit );
        $entity_class   = User::class;
        $type           = 'user';

        $vars           = compact( 'request', 'all', 'entity_class', 'type' );

        smliser_render_template( 'admin.accounts.principals', $vars );
    }

    /**
     * The users creation and edit page
     * 
     * @param Request $request
     */
    private static function users_form_page( $request ) {
        $user_id        = $request->get( 'id' );
        $user           = User::get_by_id( (int) $user_id );

        $title          = sprintf( '%s User', $user ? 'Edit' : 'Add New' );
        $roles_title    = sprintf( '%s User Role', $user ? 'Update' : 'Set' );

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

        $avatar_url     = 
            $user && $user->get_avatar()->is_valid() 
            ? $user->get_avatar()->add_query_param( 'ver', time() )
            : new URL( smliser_get_placeholder_icon( 'avatar' ) );

        $avatar_name    = $user ? 'View image' : $avatar_url->basename();
        
        // Notice we are not passing the `OwnerSubjectInterface` parameter,
        // An individual user extends the `OwnerSubjectInterface`, the context service
        // provider will resolve to using the user properties to build its role object.
        $role_obj   = $user ? ContextServiceProvider::get_principal_role( $user ) : null;

        if ( $role_obj ) {
            $collection = Collection::make( $role_obj->to_array() );
            $role       = $collection->toArray();

        } else {
            $role = null;
        }

        $vars           = compact( 'request', 'form_fields', 'avatar_name',
        'avatar_url', 'role', 'title', 'roles_title' );

        smliser_render_template( 'admin.accounts.access-control-form', $vars );
    }

    /**
     * Organizations page.
     * 
     * @param Request $request
     */
    private static function organizations_page( Request $request ) {
        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $all            = Organization::get_all( $page, $limit );
        $entity_class   = Organization::class;
        $type           = 'organization';

        $vars           = compact( 'request', 'all', 'entity_class', 'type' );

        smliser_render_template( 'admin.accounts.principals', $vars );
    }

    /**
     * The organization creation and edit page.
     * 
     * @param Request $request
     */
    private static function organizations_form_page( Request $request ) {

        $org_id         = (int) $request->get( 'id', 0 );
        $organization   = Organization::get_by_id( $org_id );

        $title          = sprintf( '%s Organization', $organization ? 'Edit' : 'Add New' );

        $_org_statuses  = Organization::get_allowed_statuses();
        $_status_titles = array_map( 'ucwords', array_values( $_org_statuses ) );
        $_status_keys   = array_values( $_org_statuses );
        $_statuses      = array_combine( $_status_keys, $_status_titles );

        $form_fields = array(
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'id',
                    'value' => $organization ? $organization->get_id() : 0,
                )
            ),

            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'entity',
                    'value' => 'organization',
                )
            ),
            array(
                'label' => __( 'Organization Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'display_name',
                    'value' => $organization ? $organization->get_display_name() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        'placeholder'   => 'Enter full name',
                        'style'         => 'width: unset',
                        // Accessibility
                        'aria-required' => 'true',
                        'aria-label'    => __( 'Organization Name', 'smliser' ),
                    )
                )
            ),

            array(
                'label' => __( 'Organization Slug', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'org_slug',
                    'value' => $organization ? $organization->get_slug() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'placeholder'   => 'Enter organization slug',
                        // Accessibility
                        'aria-label'    => __( 'Organization Slug', 'smliser' ),
                    )
                )
            ),

            array(
                'label' => __( 'Status', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'status',
                    'value' => $organization ? $organization->get_status() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        // Accessibility
                        'aria-required' => 'true',
                        'aria-label'    => __( 'Select Organization Status', 'smliser' ),
                    ),
                    'options'   => $_statuses
                )
            ),
        );

        $avatar_url     = 
            $organization && $organization->get_avatar()->is_valid() 
            ? $organization->get_avatar()->add_query_param( 'ver', time() )
            : new URL( smliser_get_placeholder_icon( 'organization' ) );

        $avatar_name    = $organization ? 'View image' : $avatar_url->basename();

        $vars           = compact( 'request', 'form_fields', 'avatar_name', 'avatar_url',
        'title', 'organization',  );

        smliser_render_template( 'admin.accounts.access-control-form', $vars );
    }

    /**
     * The organization member creation and edit page.
     * 
     * @param Request $request
     */
    private static function organizations_members_form_page( Request $request ) {

        $org_id             = $request->get( 'org_id' );
        $organization       = Organization::get_by_id( (int) $org_id );
        $member_id          = (int) $request->get( 'member_id' );
        $member             = $organization?->get_members()->get( $member_id );
        $org_name           = $organization?->get_display_name();

        $title              = sprintf( '%s %s Member', $member ? 'Edit' : 'Add New', $org_name );
        $roles_title        = sprintf( '%s Member Role in %s', $member ? 'Update' : 'Set', $org_name );

        $_org_statuses      = Organization::get_allowed_statuses();
        $_status_titles     = array_map( 'ucwords', array_values( $_org_statuses ) );
        $_status_keys       = array_values( $_org_statuses );
        $_statuses          = array_combine( $_status_keys, $_status_titles );
        $selected_member    = $member ? [
            sprintf( '%s:%s', $member->get_type(), $member->get_id() ) => $member->get_display_name()
        ] : [];

        $role   = $member ? $member->get_role()?->to_array() : null;

        $form_fields = array(
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'organization_id',
                    'value' => $org_id,
                )
            ),
            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'member_id',
                    'value' => $member_id,
                )
            ),

            array(
                'label' => '',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'entity',
                    'value' => 'organization_member',
                )
            ),

            array(
                'label' => __( 'Organization Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'org_name',
                    'value' => $org_name,
                    'attr'  => array(
                        'readonly'      => true,
                        // Accessibility
                        'aria-readonly' => 'true',
                        'aria-label'    => __( 'Organization Name', 'smliser' ),
                    )
                )
            ),

            array(
                'label' => __( 'Organization Slug', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'org_slug',
                    'value' => $organization?->get_slug(),
                    'attr'  => array(
                        'readonly'      => true,
                        // Accessibility
                        'aria-readonly' => 'true',
                        'aria-label'    => __( 'Organization Slug', 'smliser' ),
                    )
                )
            ),

            array(
                'label' => __( 'Member', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'user_id',
                    'value' => $member?->get_user()->get_id(),
                    'attr'  => array(
                        'readonly'  => true,
                        // Accessibility
                        'aria-readonly' => 'true',
                        'aria-label'    => __( 'Member Name', 'smliser' ),
                    ),
                    'options'   => $selected_member,
                )
            ),

            array(
                'label' => __( 'Status', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'status',
                    'value' => $member?->get_status(),
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                        // Accessibility
                        'aria-required' => 'true',
                        'aria-label'    => __( 'Member Status', 'smliser' ),
                    ),
                    'options'   => $_statuses
                )
            ),
        );

        $avatar_url     = 
            $member && $member->get_avatar()->is_valid() 
            ? $member->get_avatar()->add_query_param( 'ver', time() )
            : new URL( smliser_get_placeholder_icon( 'avatar' ) );

        $avatar_name    = $member ? 'View image' : $avatar_url->basename();

        $vars = compact( 'request', 'form_fields', 'avatar_name', 'avatar_url',
        'title', 'organization', 'role', 'roles_title'  );

        smliser_render_template( 'admin.accounts.access-control-form', $vars );
    }

    /**
     * Resource owners.
     * 
     * @param Request $request
     */
    private static function owners_page( Request $request ) {
        $page   = (int) $request->get( 'paged', 1 );
        $limit  = (int) $request->get( 'limit', 25 );
        $owners = Owner::get_all( $page, $limit );

        $vars   = compact( 'owners', 'request' );
        smliser_render_template( 'admin.accounts.owners', $vars );
    }

    /**
     * The owners creation and edit page.
     * 
     * @param Request $request
     */
    private static function owners_form_page( Request $request ) {
        $owner_id           = $request->get( 'id' );
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
        $subject_option     = array();

        if ( $owner ) {
            $_owner_type    = $owner->get_type();
            $subject_id     = $owner->get_subject_id();
            $_entity_class  = ContextServiceProvider::get_entity_classname( $_owner_type );
            $subject        = $_entity_class ? $_entity_class::get_by_id( $subject_id ) : '';

            $pr_name        = $subject ? $subject->get_display_name() : '[Deleted entity]';

            $subject_option   = [$subject_id => $pr_name];
            
            unset( $_owner_type, $entity_class, $_entity_class, $subject, $pr_name );

        }

        $form_fields = array(
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
                'label' => __( 'Owner Subject', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'subject_id',
                    'value' => $owner ? $owner->get_subject_id() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true,
                    ),
                    'options'       => $subject_option
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

        $vars = compact( 'request', 'form_fields', 'title' );

        smliser_render_template( 'admin.accounts.access-control-form', $vars );
    }

    /**
     * REST API setting page.
     * 
     * @param Request $request
     */
    private static function rest_api_page( Request $request ) {
        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $all            = ServiceAccount::get_all( $page, $limit );
        $entity_class   = ServiceAccount::class;
        $type           = 'Service Account';
        $vars           = compact( 'request', 'all', 'entity_class', 'type' );

        smliser_render_template( 'admin.accounts.principals', $vars );      
    }

    /**
     * The users creation and edit page.
     * 
     * @param Request $request
     */
    private static function rest_api_form_page( Request $request ) {
        $user_id        = $request->get( 'id' );
        $sa_acc         = ServiceAccount::get_by_id( (int) $user_id );

        $title          = sprintf( '%s Service Account', $sa_acc ? 'Edit' : 'Add New' );
        $roles_title    = sprintf( '%s Service Account Role & Permissions', $sa_acc ? 'Update' : 'Set' );

        $_sa_statuses = ServiceAccount::get_allowed_statuses();
        $_status_titles = array_map( 'ucwords', array_values( $_sa_statuses ) );
        $_status_keys   = array_values( $_sa_statuses );
        $_statuses      = array_combine( $_status_keys, $_status_titles );
        $owner_option   = [];
        $role           = null;

        if ( $sa_acc ) {
            $owner      = $sa_acc->get_owner();
            $subject    = ContextServiceProvider::get_owner_subject( $owner );
            $role_obj   = ContextServiceProvider::get_principal_role( $sa_acc, $subject );

            if ( $role_obj ) {
                $collection = Collection::make( $role_obj->to_array() );

                $role   = $collection->toArray();

                unset( $collection );
            }

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
            : new URL( smliser_get_placeholder_icon( 'api-key' ) );

        $avatar_name    = $sa_acc ? 'View image' : $avatar_url->basename();

        $vars           = compact( 'request', 'form_fields', 'avatar_name', 'avatar_url', 'role',
        'title', 'roles_title' );

        smliser_render_template( 'admin.accounts.access-control-form', $vars );
    }

    /**
     * Print admin header.
     * 
     * @param Request $request
     */
    public static function print_header( Request $request ) {
        $tab        = $request->get( 'tab' );
        $title      = match( $tab ) {
            'users'             => 'Users',
            'organizations'     => 'Organizations',
            'owners'            => 'Resource Owners',
            'service-account'   => 'Service Accounts',
            default             => 'Security & Access Control'

        };

        $args   = array(
            'breadcrumbs' => array(
                array(
                    'label' => $title,
                ),
            ),
            'actions'   => array(
                array(
                    'title'     => 'Users',
                    'label'     => 'Users',
                    'url'       => smliser_access_control_page_url()->add_query_param( 'tab', 'users' ),
                    'icon'      => 'ti ti-user',
                    'active'    => $tab === 'users'
                ),

                array(
                    'title'     => 'REST API Service Accounts',
                    'label'     => 'Service Accounts',
                    'url'       => smliser_access_control_page_url()->add_query_param( 'tab', 'service-account' ),
                    'icon'      => 'ti ti-robot',
                    'active'    => $tab === 'service-account'
                ),

                array(
                    'title'     => 'Resource Owners',
                    'label'     => 'Owners',
                    'url'       => smliser_access_control_page_url()->add_query_param( 'tab', 'owners' ),
                    'icon'      => 'ti ti-source-code',
                    'active'    => $tab === 'owners'
                ),

                array(
                    'title'     => 'Organizations',
                    'label'     => 'Organizations',
                    'url'       => smliser_access_control_page_url()->add_query_param( 'tab', 'organizations' ),
                    'icon'      => 'ti ti-users-group',
                    'active'    => $tab === 'organizations'
                ),
            )
        );

        if ( $tab ) {
            $home = array(
                'label' => 'Security & Access Control',
                'url'   => smliser_access_control_page_url(),
                'icon'  => 'ti ti-home'
            );
            
            array_unshift( $args['breadcrumbs'], $home );
        }

        smliser_print_admin_content_header( $args );
    }
}
