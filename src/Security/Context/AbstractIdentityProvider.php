<?php
/**
 * Abstract Identity provider class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Context
 */

namespace SmartLicenseServer\Security\Context;

use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Actors\User;

use const SMLISER_IDENTITY_FEDERATION_TABLE;

use function smliser_db, sprintf;

/**
 * Provides abstract implementation and shared method for identity provision.
 * 
 * @uses \SMLISER_IDENTITY_FEDERATION_TABLE to store known identities, providers.
 */
abstract class AbstractIdentityProvider implements IdentityProviderInterface {

    /**
     * Lookup an actor via federation mapping.
     *
     * @param string $issuer
     * @param string $external_id
     * @return ActorInterface|null
     */
    protected function find_actor( string $issuer, string $external_id ) : ?ActorInterface {

        $db    = smliser_db();
        $table = SMLISER_IDENTITY_FEDERATION_TABLE;

        $sql    = \smliserQueryBuilder()
            ->select( 'user_id' )
            ->from( $table )
            ->where( 'issuer', '=', $issuer )
            ->where( 'external_id', '=', $external_id );

        $row = $db->get_row( $sql->build(), $sql->get_bindings() );

        if ( ! $row ) {
            return null;
        }

        return $this->hydrate_actor( (int) $row['user_id'] );
    }

    /**
     * Lookup external ID of a an actor
     * 
     * @param string $issuer The identity provider ID
     * @param int $user_id The User ID
     * @return string|null
     */
    protected function find_external_id( string $issuer, int $user_id ) : ?string {
        $db    = smliser_db();
        $table = SMLISER_IDENTITY_FEDERATION_TABLE;

        $sql    = \smliserQueryBuilder()
            ->select( 'external_id' )
            ->from( $table )
            ->where( 'issuer', '=', $issuer )
            ->where( 'user_id', '=', $user_id );

        $row = $db->get_row( $sql->build(), $sql->get_bindings() );

        if ( ! $row ) {
            return null;
        }

        return $row['external_id'];
    }

    /**
     * Persist new federation mapping.
     *
     * @param int    $user_id
     * @param string $issuer
     * @param string $external_id
     * @return bool
     */
    protected function add( int $user_id, string $issuer, string $external_id ) : bool {
        if ( $this->find_actor( $issuer, $external_id ) ) {
            return true; // already federated — not an error
        }

        $db = smliser_db();

        $inserted   = $db->insert(
            SMLISER_IDENTITY_FEDERATION_TABLE,
            [
                'user_id'       => $user_id,
                'issuer'        => $issuer,
                'external_id'   => $external_id,
                'created_at'    => gmdate( 'Y-m-d H:i:s' )
            ]
        );

        return false !== $inserted;
    }

    /**
     * Remove federation mapping for a user.
     * 
     * @param int $user_id
     * @return bool
     */
    protected function remove( int $user_id ) : bool {
        return $this->remove_by( 'user_id', (string) $user_id );
    }

    /**
     * Remove a record from the federation table using the column and value.
     * 
     * @param string $column The column name to match (e.g., 'issuer' or 'external_id').
     * @param string $value The value to match for deletion.
     * @return bool True if deletion was successful, false otherwise.
     */
    protected function remove_by( string $column, string $value ) : bool {

        $allowed_columns = [ 'issuer', 'external_id', 'user_id' ];

        if ( ! in_array( $column, $allowed_columns, true ) ) {
            return false; // Invalid column name
        }

        $db = smliser_db();

        $deleted = $db->delete(
            SMLISER_IDENTITY_FEDERATION_TABLE,
            [ $column => $value ]
        );

        return false !== $deleted;
    }

    /**
     * Hydrate internal actor from ID.
     *
     * Let children override if needed.
     *
     * @param int $user_id
     * @return ActorInterface|null
     */
    protected function hydrate_actor( int $user_id ) : ?ActorInterface {
        return User::get_by_id( $user_id );
    }
}