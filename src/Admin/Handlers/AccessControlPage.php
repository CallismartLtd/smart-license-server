<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin\Handlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Templates\TemplateLocator;

use function array_unshift, sprintf, smliser_json_encode_attr, compact;

/**
 * The admin bulk message page class
 */
class AccessControlPage implements AdminPageInterface {
    public function __construct(
        protected TemplateLocator $locator,
        protected URLManager $urlmanager
    ) {}

    /**
     * Section router.
     * 
     * @return bool
     */
    private function sub_router( Request $request ) : bool {
        $submenu     = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
        $section = $request->get( 'section' );

        $routes = [
            'users' => [
                'add-new'   => [$this, 'users_form_page'],
                'edit'      => [$this, 'users_form_page'],
            ],
            'organizations' => [
                'add-new'           => [$this, 'organizations_form_page'],
                'edit'              => [$this, 'organizations_form_page'],
                'add-new-member'    => [$this, 'organizations_members_form_page'],
                'edit-member'       => [$this, 'organizations_members_form_page'],
            ],
            'owners'    => [
                'add-new'   => [$this, 'owners_form_page'],
                'edit'      => [$this, 'owners_form_page'],
            ],
            'service-account'  => [
                'add-new'   => [$this, 'service_accounts_form_page'],
                'edit'      => [$this, 'service_accounts_form_page'],
            ],
        ];

        if ( isset( $routes[ $submenu ][ $section ] ) ) {
            $handler = $routes[ $submenu ][ $section ];
            $handler( $request );
            return true;
        }

        return false;
    }

    /**
     * Access control page dashbard callback.
     * 
     * @param Request $request
     */
    public function dashboard( Request $request ) {
        $account_summaries  = ContextServiceProvider::get_accounts_summary_report();
        $page_handler       = $this;
        $urlmanager         = $this->urlmanager;
        $vars               = compact( 'request', 'account_summaries', 'urlmanager', 'page_handler' );
        $this->locator->render( 'admin.contents.accounts.index', $vars );
    
    }

    /**
     * Users submenu page callback.
     * 
     * @param Request $request
     */
    public function users_page( Request $request ) : void {
        if ( $this->sub_router( $request ) ) {
            return;
        }

        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $all            = User::get_all( $page, $limit );
        $entity_class   = User::class;
        $type           = 'user';

        $description    = 'Manage users';
        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'request', 'all', 'entity_class', 'type', 'description',
            'urlmanager', 'page_handler'
        );

        $this->locator->render( 'admin.contents.accounts.principals', $vars );
    }

    /**
     * The user account creation and edit page callback.
     * 
     * @param Request $request
     */
    public function users_form_page( $request ) {
        $user_id        = $request->get( 'id' );
        $user           = User::get_by_id( (int) $user_id );

        $title          = sprintf( '%s User', $user ? 'Edit' : 'Add New' );
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
        
        if ( $user ) {
            $identifier = md5( $user->get_unique_identifier() );
            $avatar_url = $this->urlmanager->avatar_url( $identifier, $user->get_type() );            
        } else {
            $avatar_url = URL::from( \smliser_get_placeholder_icon( 'avatar' ) );
        }

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

        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'request', 'form_fields', 'avatar_name',
        'avatar_url', 'role', 'title', 'page_handler', 'urlmanager' );

        $this->locator->render( 'admin.contents.accounts.access-control-form', $vars );
    }

    /**
     * Organizations submenu page callback.
     * 
     * @param Request $request
     */
    public function organizations_page( Request $request ) {
        if ( $this->sub_router( $request ) ) {
            return;
        }

        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $all            = Organization::get_all( $page, $limit );
        $entity_class   = Organization::class;
        $type           = 'organization';
        $description    = 'An organization is an account that represents a group, business, or other entity under which users, service accounts, resources, and access policies can be managed collectively.';
        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'request', 'all', 'entity_class', 'type', 'description',
            'page_handler', 'urlmanager' );

        $this->locator->render( 'admin.contents.accounts.principals', $vars );
    }

    /**
     * The organization creation and edit page.
     * 
     * @param Request $request
     */
    private function organizations_form_page( Request $request ) {

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

        if ( $organization ) {
            $identifier = md5( $organization->get_slug() );
            $avatar_url = $this->urlmanager->avatar_url( $identifier, $organization->get_type() );            
        } else {
            $avatar_url = URL::from( \smliser_get_placeholder_icon( 'avatar' ) );
        }

        $avatar_name    = $organization ? 'View image' : $avatar_url->basename();
        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'request', 'form_fields', 'avatar_name', 'avatar_url',
        'title', 'organization', 'urlmanager', 'page_handler' );

        $this->locator->render( 'admin.contents.accounts.access-control-form', $vars );
    }

    /**
     * The organization member creation and edit page.
     * 
     * @param Request $request
     */
    private function organizations_members_form_page( Request $request ) {

        $org_id             = $request->get( 'org_id' );
        $organization       = Organization::get_by_id( (int) $org_id );
        $member_id          = (int) $request->get( 'member_id' );
        $member             = $organization?->get_members()->get( $member_id );
        $org_name           = $organization?->get_display_name();

        $title              = sprintf( '%s %s Member', $member ? 'Edit' : 'Add New', $org_name );

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

        if ( $member ) {
            $identifier = md5( $member->get_unique_identifier() );
            $avatar_url = $this->urlmanager->avatar_url( $identifier, $member->get_type() );            
        } else {
            $avatar_url = URL::from( \smliser_get_placeholder_icon( 'avatar' ) );
        }

        $avatar_name    = $member ? 'View image' : $avatar_url->basename();
        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars = compact( 'request', 'form_fields', 'avatar_name', 'avatar_url',
        'title', 'organization', 'role', 'urlmanager', 'page_handler' );

        $this->locator->render( 'admin.contents.accounts.access-control-form', $vars );
    }

    /**
     * Resource owners.
     * 
     * @param Request $request
     */
    public function owners_page( Request $request ) {
        if ( $this->sub_router( $request ) ) {
            return;
        }

        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $owners         = Owner::get_all( $page, $limit );
        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'owners', 'request', 'urlmanager', 'page_handler' );
        $this->locator->render( 'admin.contents.accounts.owners', $vars );
    }

    /**
     * The owners creation and edit page.
     * 
     * @param Request $request
     */
    private function owners_form_page( Request $request ) {
        $owner_id           = $request->get( 'id' );
        $owner              = Owner::get_by_id( (int) $owner_id );

        $title              = sprintf( '%s Resource Owner', $owner ? 'Edit' : 'Add New' );
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

        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars = compact( 'request', 'form_fields', 'title', 'page_handler', 'urlmanager' );

        $this->locator->render( 'admin.contents.accounts.access-control-form', $vars );
    }

    /**
     * Service accounts submenu page callback.
     * 
     * @param Request $request
     */
    public function service_accounts_page( Request $request ) {
        if ( $this->sub_router( $request ) ) {
            return;
        }

        $page           = (int) $request->get( 'paged', 1 );
        $limit          = (int) $request->get( 'limit', 25 );
        $all            = ServiceAccount::get_all( $page, $limit );
        $entity_class   = ServiceAccount::class;
        $type           = 'Service Account';
        $description    = 'A service account is a non-human account used by software, an application, server, or automated process to authenticate and access resources.';

        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'request', 'all', 'entity_class', 'type', 'description',
            'urlmanager', 'page_handler' );

        $this->locator->render( 'admin.contents.accounts.principals', $vars );      
    }

    /**
     * The service account creation and edit page callback.
     * 
     * @param Request $request
     */
    private function service_accounts_form_page( Request $request ) {
        $user_id        = $request->get( 'id' );
        $sa_acc         = ServiceAccount::get_by_id( (int) $user_id );

        $title          = sprintf( '%s Service Account', $sa_acc ? 'Edit' : 'Add New' );

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

        if ( $sa_acc ) {
            $identifier = md5( $sa_acc->get_unique_identifier() );
            $avatar_url = $this->urlmanager->avatar_url( $identifier, $sa_acc->get_type() );            
        } else {
            $avatar_url = URL::from( \smliser_get_placeholder_icon( 'avatar' ) );
        }

        $avatar_name    = $sa_acc ? 'View image' : $avatar_url->basename();
        $page_handler   = $this;
        $urlmanager     = $this->urlmanager;
        $vars           = compact( 'request', 'form_fields', 'avatar_name', 'avatar_url', 'role',
        'title', 'urlmanager', 'page_handler' );

        $this->locator->render( 'admin.contents.accounts.access-control-form', $vars );
    }

    /**
     * Print admin header.
     * 
     * @param Request $request
     */
    public function print_header( Request $request ) {
        $tab        = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
        $title      = match( $tab ) {
            'users'             => 'Users',
            'organizations'     => 'Organizations',
            'owners'            => 'Resource Owners',
            'service-account'   => 'Service Accounts',
            default             => 'Accounts Overview'

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
                    'url'       => $this->urlmanager->admin_accounts_page_url( 'users' ),
                    'icon'      => 'ti ti-user',
                    'active'    => $tab === 'users'
                ),

                array(
                    'title'     => 'REST API Service Accounts',
                    'label'     => 'Service Accounts',
                    'url'       => $this->urlmanager->admin_accounts_page_url( 'service-account' ),
                    'icon'      => 'ti ti-robot',
                    'active'    => $tab === 'service-account'
                ),

                array(
                    'title'     => 'Resource Owners',
                    'label'     => 'Owners',
                    'url'       => $this->urlmanager->admin_accounts_page_url( 'owners' ),
                    'icon'      => 'ti ti-source-code',
                    'active'    => $tab === 'owners'
                ),

                array(
                    'title'     => 'Organizations',
                    'label'     => 'Organizations',
                    'url'       => $this->urlmanager->admin_accounts_page_url( 'organizations' ),
                    'icon'      => 'ti ti-users-group',
                    'active'    => $tab === 'organizations'
                ),
            )
        );

        if ( $tab && 'index' !== $tab ) {
            $home = array(
                'label' => 'Accounts Overview',
                'url'   => $this->urlmanager->admin_accounts_page_url(),
                'icon'  => 'ti ti-home'
            );
            
            array_unshift( $args['breadcrumbs'], $home );
        }

        smliser_print_admin_content_header( $args );
    }

    /*
    |---------------------------
    | INTERFACE IMPLEMENTATION
    |---------------------------
    */

    public function index_page_handler() : callable {
        return [$this, 'dashboard'];
    }

    /**
     * @inheritdoc
     */
    public function get_submenu() : array {
        return [
            [
                'title'         => 'Accounts overview',
                'slug'          => 'index',
                'callback'      => [$this, 'dashboard'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Users',
                'slug'          => 'users',
                'callback'      => [$this, 'users_page'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Service Accounts',
                'slug'          => 'service-account',
                'callback'      => [$this, 'service_accounts_page'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Resource Owners',
                'slug'          => 'owners',
                'callback'      => [$this, 'owners_page'],
                'visibility'    => true,
            ],
            [
                'title'         => 'Organizations',
                'slug'          => 'organizations',
                'callback'      => [$this, 'organizations_page'],
                'visibility'    => true,
            ],
            
        ];
    }
    
    public function get_menu_key() : string {
        return 'accounts';
    }

    public function get_menu_data() : array {
        return [
            'title'         => 'Accounts',
            'slug'          => 'accounts',
            'handler'       => $this,
            'icon'          => 'ti ti-cloud-lock',
            'visibility'    => true
        ];
    }
}
