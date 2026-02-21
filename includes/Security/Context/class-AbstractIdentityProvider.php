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

use function smliser_dbclass, sprintf;

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

        $db    = smliser_dbclass();
        $table = SMLISER_IDENTITY_FEDERATION_TABLE;

        $sql = sprintf(
            'SELECT user_id FROM %s WHERE issuer = ? AND external_id = ? LIMIT 1',
            $table
        );

        $row = $db->get_row( $sql, [ $issuer, $external_id ] );

        if ( ! $row ) {
            return null;
        }

        return $this->hydrate_actor( (int) $row['user_id'] );
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

        $db = smliser_dbclass();

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