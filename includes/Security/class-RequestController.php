<?php
/**
 * Security request controller file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\RequestController
 */

namespace SmartLicenseServer\Security;

use InvalidArgumentException;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\FileSystemHelper;

use const PASSWORD_ARGON2ID;

use function defined, is_smliser_error, sprintf, smliser_safe_json_encode, password_hash,
password_verify, in_array, is_string, method_exists, str_replace, ucwords, compact;

defined( 'SMLISER_ABSPATH' ) || exit;
/**
 * This controller is used to CRUD requests for security entities.
 */
class RequestController {
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
                    'message'   => sprintf( '%s saved successfully.', ucwords( str_replace( '_', ' ', $entity ) ) )
                )
            ];
            return ( new Response( 200, [], smliser_safe_json_encode( $data ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
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
        $email          = $request->get( 'email' );

        if ( empty( $email ) || ! is_email( $email )) {
            return new RequestException( 'required_param', 'Please provide a valid email address.', ['status' => 400] );
        }

        if ( ! $user->exists() && User::email_exists( $email ) ) {

            return new RequestException( 'email_exists' );

        } else if ( $user->exists() && $user->get_email() !== $email ) {

            $email_owner  = User::get_by_email( $email );

            if ( $email_owner && $user->get_id() !== $email_owner->get_id() ) {
                return new RequestException( 'email_exists' );
            }

        }

        $display_name   = $request->get( 'display_name' );
        $status         = $request->get( 'status' );

        if ( ! in_array( $status, User::get_allowed_statuses(), true ) ) {
            return new RequestException( 'invalid_user_status', 'The status provided is not allowed.', ['status' => 400] );
        }
        
        $password_1     = $request->get( 'password_1' );
        $password_2     = $request->get( 'password_2' );

        if ( $password_1 !== $password_2 ) {
            return new RequestException( 'password_mismatch', 'Password mismatch, please check and try again.', ['status' => 400] );
        }

        if ( strlen( $password_1 ) > 128 ) {
            return new RequestException( 'password_too_long', 'Password exceeds maximum length.', ['status' => 400] );
        }

        if ( ! $user->exists() && ( empty( $password_1 ) || empty( $password_2 ) ) ) {

            return new RequestException( 'required_param', 'Please provide new user passwords.', ['status' => 400] );

        }

        if ( ! empty( $password_1 ) ) {
            $password_hash  = password_hash( $password_1, PASSWORD_ARGON2ID );

            if ( $user->exists() && password_verify( $password_1, $user->get_password_hash() ) ) {
                return new RequestException( 'same_password', 'Cannot reuse old password, please use new ones.', ['status' => 409] );
            }

            $user->set_password_hash( $password_hash );
        }

        $user->set_email( $email )
        ->set_display_name( $display_name )
        ->set_status( $status );
        
        if ( ! $user->save() ) {
            return new RequestException( 'database_error', 'Unable to save user', ['status' => 500] );
        }

        $avatar = $request->get( 'avatar' );

        if ( ! empty( $avatar ) ) {
            FileSystemHelper::upload_avatar( $avatar, 'user', \md5( $user->get_email() ) );
        }
        return true;
    }

    /**
     * Save the owner object.
     *
     * @param Owner   $owner   The Owner object.
     * @param Request $request The request object.
     * @return bool|RequestException
     */
    protected static function save_owner( Owner $owner, Request $request ): bool|RequestException {

        $principal_id = $request->get( 'principal_id' );

        if ( empty( $principal_id ) || ! is_int( $principal_id ) ) {
            return new RequestException(
                'required_param',
                'Please provide a valid principal_id.',
                [ 'status' => 400 ]
            );
        }

        $owner_type = $request->get( 'owner_type' );

        if (
            empty( $owner_type ) ||
            ! in_array( $owner_type, Owner::get_allowed_owner_types(), true )
        ) {
            return new RequestException(
                'required_param',
                'Please provide a valid resource owner type.',
                [ 'status' => 400 ]
            );
        }

        $res_owner_class = ContextServiceProvider::get_entity_classname( $owner_type );
        /** @var User|Organization|null $principal */
        $principal = $res_owner_class::get_by_id( $principal_id );

        if ( ! $principal instanceof User && ! $principal instanceof Organization ) {
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
                ->set_principal_id( $principal_id )
                ->set_status( $status )
                ->set_type( $owner_type );

            if ( ! $owner->save() ) {
                throw new RequestException(
                    'owner_save_error',
                    'Unable to save resource owner.',
                    [ 'status' => 500 ]
                );
            }

            // Role.
            $caps       = (array) $request->get( 'capabilities', [] );
            $role_slug  = $request->get( 'role_slug' );
            $role_label = $request->get( 'role_label' );
            $role       = Role::get_by_slug( $role_slug );

            if ( ! $role ) {
                $role = ( new Role() )
                ->set_slug( $role_slug );

            }

            $role->set_label( $role_label )
            ->set_capabilities( $caps );
           
            if ( ! $role->save() ) {
                throw new RequestException(
                    'role_save_error',
                    'Owner has been saved but unable to save role.',
                    [ 'status' => 500 ]
                );
            }
            
            // Context binding.
            ContextServiceProvider::save_resource_owner_role( $owner, $role, $principal );

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
            $data       = [];

            foreach ( $results['items'] as $entity ) {
                $data[] = $entity->to_array();
            }

            $results['success'] = true;
            $results['items']   = $data;

            return ( new Response( 200, [], smliser_safe_json_encode( $results ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
        
    }

}