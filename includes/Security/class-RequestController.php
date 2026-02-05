<?php
/**
 * Security request controller file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\RequestController
 */

namespace SmartLicenseServer\Security;

use InvalidArgumentException;
use Mpdf\Tag\S;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Actors\OrganizationMember;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use const PASSWORD_ARGON2ID;

use function defined, is_smliser_error, sprintf, smliser_safe_json_encode, password_hash,
password_verify, in_array, is_string, method_exists, str_replace, ucwords, compact, md5,
strpos, class_implements;

defined( 'SMLISER_ABSPATH' ) || exit;
/**
 * This controller is used to CRUD requests for security entities.
 */
class RequestController {
    use SanitizeAwareTrait;
    /**
     * Process request to create or update a security entity.
     * 
     * @param Request $request The request object.
     * @return Response The response object.
     */
    public static function save_entity( Request $request ) : Response {
        try {

            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'permission_denied' );
            }

            $entity = $request->get( 'entity' );

            if ( ! $entity ) {
                throw new RequestException( 'required_param', 'Please provide the security entity.' );
            }

            /** @var Owner|Organization|User|ServiceAccount|Role|null $class */
            $class  = ContextServiceProvider::get_entity_classname( $entity );

            if ( ! $class ) {
                throw new RequestException( 'required_param', 'This provided security entity is not supported.', ['status' => 400] );
            }

            $id     = $request->get( 'id' );

            $ent_object = $class::get_by_id( $id );

            if ( ! $ent_object ) {
                $ent_object = new $class;
            }

            $self_method    = "save_{$entity}";

            if ( ! method_exists( __CLASS__, $self_method ) ) {
                throw new RequestException( 'internal_server_error', sprintf( 'Internal save method for "%s" does not exist.', $entity ), ['status' => 500] );
            }

            $result    = self::$self_method( $ent_object, $request );

            if ( is_smliser_error( $result ) ) {
                throw $result;
            }

            $data   = [
                'success'   => true,
                'data'      => array(
                    'message'   => sprintf( '%s saved successfully.', ucwords( str_replace( '_', ' ', $entity ) ) ),
                    'entity_id' => $ent_object->get_id()
                )
            ];

            if ( $entity === 'service_account' ) {
                $new_api_keys  = $request->get( 'new_api_keys', null );

                if ( $new_api_keys ) {
                    $data['data']['api_keys'] = $new_api_keys;
                }
            }

            return ( new Response( 200, [], smliser_safe_json_encode( $data ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Process result to delete a security entity.
     * 
     * @param Request $request
     * @return Response
     */
    public static function delete_entity( Request $request ) : Response {
        try {
            $entity = $request->get( 'entity' );

            if ( ! $entity ) {
                throw new RequestException( 'required_param', 'Please provide the security entity.' );
            }

            /** @var Owner|Organization|User|ServiceAccount|null $class */
            $class  = ContextServiceProvider::get_entity_classname( $entity );

            if ( ! $class ) {
                throw new RequestException( 'required_param', 'This provided security entity is not supported.', ['status' => 400] );
            }

            $id     = $request->get( 'id' );

            $ent_object = $class::get_by_id( $id );

            if ( ! $ent_object ) {
                $ent_object = new $class;
            }


            $result    = ContextServiceProvider::delete_entity( $ent_object );

            if ( is_smliser_error( $result ) ) {
                throw $result;
            }

            $data   = [
                'success'   => true,
                'data'      => array(
                    'message'   => sprintf( '%s deleted successfully.', ucwords( str_replace( '_', ' ', $entity ) ) ),
                    'entity_id' => $ent_object->get_id()
                )
            ];

            return ( new Response( 200, [], smliser_safe_json_encode( $data ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        } catch ( Exception $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Save the user object.
     * 
     * @param User $user The user object.
     * @param Request $request The request object.
     * @return bool|RequestException
     */
    protected static function save_user( User $user, Request $request ) : bool|RequestException {
        try {
            $email  = $request->get( 'email' );

            if ( empty( $email ) || ! is_email( $email )) {
                throw new RequestException( 'required_param', 'Please provide a valid email address.', ['status' => 400] );
            }

            if ( ! $user->exists() && User::email_exists( $email ) ) {

                throw new RequestException( 'email_exists' );

            } else if ( $user->exists() && $user->get_email() !== $email ) {

                $email_owner  = User::get_by_email( $email );

                if ( $email_owner && $user->get_id() !== $email_owner->get_id() ) {
                    throw new RequestException( 'email_exists' );
                }

            }

            $display_name   = $request->get( 'display_name' );
            $status         = $request->get( 'status' );

            if ( ! in_array( $status, User::get_allowed_statuses(), true ) ) {
                throw new RequestException( 'invalid_user_status', 'The status provided is not allowed.', ['status' => 400] );
            }
            
            $password_1     = $request->get( 'password_1' );
            $password_2     = $request->get( 'password_2' );

            if ( $password_1 !== $password_2 ) {
                throw new RequestException( 'password_mismatch', 'Password mismatch, please check and try again.', ['status' => 400] );
            }

            if ( strlen( $password_1 ) > 128 ) {
                throw new RequestException( 'password_too_long', 'Password exceeds maximum length.', ['status' => 400] );
            }

            if ( ! $user->exists() && ( empty( $password_1 ) || empty( $password_2 ) ) ) {

                throw new RequestException( 'required_param', 'Please provide new user passwords.', ['status' => 400] );

            }

            if ( ! empty( $password_1 ) ) {
                $password_hash  = password_hash( $password_1, PASSWORD_ARGON2ID );

                if ( $user->exists() && password_verify( $password_1, $user->get_password_hash() ) ) {
                    throw new RequestException( 'same_password', 'Cannot reuse old password, please use new ones.', ['status' => 409] );
                }

                $user->set_password_hash( $password_hash );
            }

            $user->set_email( $email )
            ->set_display_name( $display_name )
            ->set_status( $status );
            
            if ( ! $user->save() ) {
                throw new RequestException( 'database_error', 'Unable to save user', ['status' => 500] );
            }

            self::save_role( $user, $request );

            $avatar = $request->get( 'avatar' );

            if ( ! empty( $avatar ) ) {
                FileSystemHelper::upload_avatar( $avatar, 'user', md5( $user->get_email() ) );
            }
            return true;
        } catch ( InvalidArgumentException $e ) {

            return new RequestException(
                'invalid_argument',
                $e->getMessage(),
                [ 'status' => 400 ]
            );

        } catch ( RequestException $e ) {

            return $e;

        } catch ( Exception $e ) {

            return new RequestException(
                $e->get_error_code(),
                $e->get_error_message(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Delete the user object
     * 
     * @param User $user
     */

    /**
     * Save service account object.
     * 
     * @param ServiceAccount $sa_acc The service account object.
     * @param Request $request The request object.
     * @return bool|RequestException
     */
    protected static function save_service_account( ServiceAccount $sa_acc, Request $request ) : bool|RequestException {
        try {
            $display_name   = $request->get( 'display_name' );

            if ( empty( $display_name ) || ! is_string( $display_name )) {
                throw new RequestException( 'required_param', 'Please provide a valid service account display name.', ['status' => 400] );
            }

            $status = $request->get( 'status' );

            if ( ! in_array( $status, ServiceAccount::get_allowed_statuses(), true ) ) {
                throw new RequestException( 'invalid_service_account_status', 'The status provided is not allowed.', ['status' => 400] );
            }

            $owner_id   = static::sanitize_int( $request->get( 'owner_id', 0 ) );
            $owner      = Owner::get_by_id( $owner_id );
            
            if ( ! $owner || ! $owner->exists() ) {
                throw new RequestException( 'owner_not_found', 'The resource owner for this service account was not found.', ['status' => 400] );
            }

            $subject    = ContextServiceProvider::get_owner_subject( $owner );
            $request->set( 'subject', $subject );

            if ( $request->isEmpty( 'role_slug' ) ) {
                throw new RequestException( 'required_param', 'Please select a role for this service account', [ 'status' => 400 ] );
            }

            $description    = $request->get( 'description', '' );
            $sa_acc->set_display_name( $display_name )
            ->set_status( $status )
            ->set_owner_id( $owner->get_id() )
            ->set_description( $description );
            
            $account_exists = $sa_acc->exists();

            if ( ! $sa_acc->save() ) {
                throw new RequestException( 'database_error', 'Unable to save service account', ['status' => 500] );
            }

            if ( ! $account_exists ) {
                $request->set( 'new_api_keys', $sa_acc->get_new_api_key_data() );
            }

            self::save_role( $sa_acc, $request );

            $avatar = $request->get( 'avatar' );

            if ( ! empty( $avatar ) ) {
                FileSystemHelper::upload_avatar( $avatar, 'service_account', md5( $sa_acc->get_identifier() ) );
            }
            
            return true;
        } catch ( InvalidArgumentException $e ) {

            return new RequestException(
                'invalid_argument',
                $e->getMessage(),
                [ 'status' => 400 ]
            );

        } catch ( RequestException $e ) {

            return $e;

        } catch ( Exception $e ) {

            return new RequestException(
                $e->get_error_code(),
                $e->get_error_message(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Save organization object.
     * 
     * @param Organization $organization The organization object.
     * @param Request $request The request object.
     * @return bool|RequestException
     */
    protected static function save_organization( Organization $organization, Request $request ) : bool|RequestException{
        try {
            $display_name   = $request->get( 'display_name' );

            if ( empty( $display_name ) || ! is_string( $display_name )) {
                throw new RequestException( 'required_param', 'Please provide a valid organization name.', ['status' => 400] );
            }

            $status = $request->get( 'status' );

            if ( ! in_array( $status, Organization::get_allowed_statuses(), true ) ) {
                throw new RequestException( 'invalid_organization_status', 'The status provided is not allowed.', ['status' => 400] );
            }

            if ( ! $organization->exists() ) {
                if ( $request->isEmpty( 'slug' ) ) {
                    $request->set( 'slug', strtolower( str_replace( [' ', '-'], ['_', '_'], $display_name ) ) );
                }

                $organization->set_slug( $request->get( 'slug' ) );
            }

            $organization->set_display_name( $display_name )
            ->set_status( $status );
            
            if ( ! $organization->save() ) {
                throw new RequestException( 'database_error', 'Unable to save organization', ['status' => 500] );
            }

            $avatar = $request->get( 'avatar' );

            if ( ! empty( $avatar ) ) {
                FileSystemHelper::upload_avatar( $avatar, 'organization', md5( $organization->get_slug() ) );
            }
            return true;
        } catch ( InvalidArgumentException $e ) {

            return new RequestException(
                'invalid_argument',
                $e->getMessage(),
                [ 'status' => 400 ]
            );

        } catch ( RequestException $e ) {

            return $e;

        } catch ( Exception $e ) {

            return new RequestException(
                $e->get_error_code(),
                $e->get_error_message(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Save organization member
     * 
     * @param Request $request
     */
    public static function save_organization_member( Request $request ) : Response {
        try {
            $role_slug  = (string) $request->get( 'role_slug' );
            $role       = Role::get_by_slug( $role_slug );

            if ( ! $role ) {
                throw new RequestException( 'bad_request', 'Member must have a valid role.', ['status' => 400] );
            }

            $org_id         = static::sanitize_int( $request->get( 'organization_id' ) );
            $organization   = Organization::get_by_id( $org_id );

            if ( ! $organization ) {
                throw new RequestException( 'bad_request', 'The member must belong to an existing organization.', ['status' => 400] );
            }

            $user_id    = static::sanitize_int( $request->get( 'user_id' ) );
            $subject    = User::get_by_id( $user_id );

            if ( ! $subject ) {
                throw new RequestException( 'bad_request', 'The member subject must be an existing user.', ['status' => 400] );
            }
           
            $member_id  = static::sanitize_int( $request->get( 'member_id' ) );
            $member     = $organization->get_members()->get( $member_id );

            if ( ! $member ) {
                $collection = Collection::make( ['role' => $role ] );
                $member = new OrganizationMember( $subject, $collection );

                $organization->get_members()->add( $member );
            }

            ContextServiceProvider::save_organization_member( $member, $organization, $role );

            $data = array(
                'success'   => true,
                'data'      => array(
                    'message'   => 'Member saved successfully.',
                    'member'    => $member->to_array()
                )
            );
            return ( new Response( 200, [], smliser_safe_json_encode( $data ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( InvalidArgumentException $e ) {
            $error   = new RequestException(
                'invalid_argument',
                $e->getMessage(),
                [ 'status' => 400 ]
            );

        } catch ( Exception $e ) {

            $error   = new RequestException(
                $e->get_error_code(),
                $e->get_error_message(),
                [ 'status' => 500 ]
            );
        } catch ( RequestException $e ) {
            $error   = $e;

        }

        $response   = ( new Response() )
            ->set_exception( $error )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        return $response;

    }

    /**
     * Save the owner object.
     *
     * @param Owner   $owner   The Owner object.
     * @param Request $request The request object.
     * @return bool|RequestException
     */
    protected static function save_owner( Owner $owner, Request $request ): bool|RequestException {

        $subject_id = static::sanitize_int( $request->get( 'subject_id' ) );

        // Subject ID must exist.
        if ( empty( $subject_id ) ) {
            return new RequestException(
                'required_param',
                'Please provide a valid subject_id.',
                [ 'status' => 400 ]
            );
        }

        $owner_type = $request->get( 'owner_type' );
        $is_valid_owner_type = in_array( $owner_type, Owner::get_allowed_owner_types(), true );

        // Owner type must be valid.
        if ( empty( $owner_type ) || ! is_string( $owner_type ) || ! $is_valid_owner_type ) {
            return new RequestException(
                'required_param',
                'Please provide a valid resource owner type.',
                [ 'status' => 400 ]
            );
        }

        $subject_class = ContextServiceProvider::get_entity_classname( $owner_type );
        /** @var OwnerSubjectInterface|null $principal */
        $subject =  $subject_class ? $subject_class::get_by_id( $subject_id ) : null;

        // Owner subject entity must be valid and exists.
        if ( ! $subject || ! in_array( OwnerSubjectInterface::class, (array) class_implements( $subject ), true ) ) {
            return new RequestException( 'resource_owner_not_found' );
        }

        $owner_name = $request->get( 'name' );

        if ( empty( $owner_name ) || ! is_string( $owner_name ) ) {
            return new RequestException(
                'required_param',
                'Please provide a valid resource owner name.',
                [ 'status' => 400 ]
            );
        }

        $status = $request->get( 'status' );

        if ( ! in_array( $status, Owner::get_allowed_statuses(), true ) ) {
            return new RequestException(
                'required_param',
                'The status provided is not allowed.',
                [ 'status' => 400 ]
            );
        }
        
        try {

            // Owner
            $owner->set_name( $owner_name )
                ->set_subject_id( $subject_id )
                ->set_status( $status )
                ->set_type( $owner_type );

            if ( ! $owner->save() ) {
                throw new RequestException(
                    'owner_save_error',
                    'Unable to save resource owner.',
                    [ 'status' => 500 ]
                );
            }

            return true;

        } catch ( InvalidArgumentException $e ) {

            return new RequestException(
                'invalid_argument',
                $e->getMessage(),
                [ 'status' => 400 ]
            );

        } catch ( RequestException $e ) {

            return $e;

        } catch ( Exception $e ) {

            return new RequestException(
                $e->get_error_code(),
                $e->get_error_message(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Save role to the database
     * 
     * @param Request $request The request object.
     * @param ActorInterface $actor The either User or ServiceAccount instance.
     * @throws Exception
     */
    protected static function save_role( ActorInterface $actor, Request $request ) {
        $caps       = (array) $request->get( 'capabilities', [] );
        $role_slug  = $request->get( 'role_slug' );
        $role_label = $request->get( 'role_label' );
        $role       = Role::get_by_slug( $role_slug );

        /** @var OwnerSubjectInterface|null $subject */
        $subject    = $request->get( 'subject', null );

        if ( ! $role ) {
            $role = ( new Role() )
            ->set_slug( $role_slug );

        }

        $role->set_label( $role_label )
        ->set_capabilities( $caps );
        
        if ( ! $role->save() ) {
            throw new RequestException(
                'role_save_error',
                'Unable to save role to the database.',
                [ 'status' => 500 ]
            );
        }
        
        // Context binding.
        ContextServiceProvider::save_actor_role( $actor, $role, $subject );
        
    }

    /**
     * Search for users and organizations.
     * 
     * @param Request $request The request object.
     * @return Response The response object.
     */
    public static function search_users_orgs( Request $request ) : Response {
        try {
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'permission_denied' );
            }

            $term   = $request->get( 'search_term' );

            if ( empty( $term ) ) {
                throw new RequestException( 'required_param', 'Missing parameter "search".' );
            }

            $types  = (array) $request->get( 'types', [] );
            $status = (string) $request->get( 'status', 'active' );

            $args   = compact( 'term', 'types', 'status' );

            $results    = ContextServiceProvider::search( $args );
            $data       = Collection::make( $results['items'] )->map( 'smliser_value_to_array' );

            return ( new Response(
                200,
                [],
                smliser_safe_json_encode( [
                    'success'       => true,
                    'items'         => $data->toArray(),
                    'pagination'    => $results['pagination'],
                ] )
            ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
        
    }

    /**
     * Search for resource owners.
     * 
     * @param Request $request The request object.
     * @return Response The response object.
     */
    public static function search_resource_owners( Request $request ) : Response {
        try {
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'permission_denied' );
            }

            $term   = $request->get( 'search_term' );

            if ( empty( $term ) ) {
                throw new RequestException( 'required_param', 'Missing parameter "search".' );
            }

            $status = (string) $request->get( 'status', '' );

            $args   = compact( 'term', 'status' );

            $results    = ContextServiceProvider::search_owners( $args );
            $data       = Collection::make( $results['items'] )->map( 'smliser_value_to_array' );

            return ( new Response(
                200,
                [],
                smliser_safe_json_encode( [
                    'success'       => true,
                    'items'         => $data->toArray(),
                    'pagination'    => $results['pagination'],
                ] )
            ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Delete/remove a member from an organization.
     * 
     * @param Request $request The request object.
     * @return Response
     */
    public static function delete_org_member( Request $request ): Response {
        try {
            $org_id     = static::sanitize_int( $request->get( 'organization_id' ) );
            $member_id  = static::sanitize_int( $request->get( 'member_id' ) );

            $organization   = Organization::get_by_id( $org_id );

            if ( ! $organization ) {
                throw new RequestException( 'error', 'The provided orgaization does exist.', ['status' => 400] );
            }

            if ( ! $organization->is_member( $member_id ) ) {
                throw new RequestException( 'error', 'The provided member does not belong to this organization, try refreshing the current page.', ['status' => 400] );
            }

            $member = $organization->get_members()->get( $member_id );
            ContextServiceProvider::delete_organization_member( $member, $organization );

            $data   = array(
                'success'   => true,
                'data'      => array(
                    'message'   => sprintf( '%s has been successfully removed from %s.', $member->get_display_name(), $organization->get_display_name() )
                )
            );
            return ( new Response( 200, [], smliser_safe_json_encode( $data ) ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        } catch ( Exception $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }
}