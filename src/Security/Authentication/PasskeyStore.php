<?php
/**
 * Passkey credential persistence.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Authentication;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\SQLBuilder;
use SmartLicenseServer\Schema\TableName;

/**
 * Persists the WebAuthn credentials a user registered as passkeys.
 *
 * A passkey is a sign-in credential: it replaces the password. It is not a
 * second factor, so it is deliberately kept apart from
 * {@see AuthenticationFactorManager}, which owns email codes, authenticator
 * apps and recovery codes.
 *
 * This class only stores and looks up records. The WebAuthn ceremonies that
 * create and verify them live in {@see WebAuthnManager}.
 */
final class PasskeyStore {

	/**
	 * Constructor.
	 *
	 * @param Database $db Database connection.
	 */
	public function __construct( private Database $db ) {}

	/**
	 * Active (not revoked) credential rows of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_user( int $user_id ) : array {
		$sql = $this->query()
			->select()->from( TableName::USER_PASSKEYS->table() )
			->where( 'user_id', '=', $user_id )->where_null( 'revoked_at' );

		return $this->db->get_results( $sql->build(), $sql->get_bindings() ) ?: [];
	}

	/**
	 * Display-safe summaries of a user's active passkeys.
	 *
	 * Never includes the credential record itself.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array{id:int,label:string,created_at:string,last_used:string}>
	 */
	public function summaries( int $user_id ) : array {
		return array_map( static fn ( array $item ) : array => [
			'id'         => (int) $item['id'],
			'label'      => (string) $item['label'],
			'created_at' => (string) $item['created_at'],
			'last_used'  => (string) ( $item['last_used_at'] ?? '' ),
		], $this->for_user( $user_id ) );
	}

	/**
	 * Store an attested credential.
	 *
	 * @param int    $user_id         Owner of the passkey.
	 * @param string $credential_id   Raw credential ID.
	 * @param string $credential_data Serialized credential record.
	 * @param string $label           Name the user gave the passkey.
	 * @return bool Whether the row was stored.
	 */
	public function add( int $user_id, string $credential_id, string $credential_data, string $label ) : bool {
		return false !== $this->db->insert( TableName::USER_PASSKEYS->table(), [
			'user_id'            => $user_id,
			'credential_id_hash' => hash( 'sha256', $credential_id ),
			'credential_data'    => $credential_data,
			'label'              => mb_substr( trim( $label ) ?: 'Passkey', 0, 120 ),
			'created_at'         => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/**
	 * One active credential, found by its raw credential ID.
	 *
	 * @param string $credential_id Raw credential ID.
	 * @return array<string,mixed>|null
	 */
	public function find_by_credential_id( string $credential_id ) : ?array {
		$sql = $this->query()
			->select()->from( TableName::USER_PASSKEYS->table() )
			->where( 'credential_id_hash', '=', hash( 'sha256', $credential_id ) )
			->where_null( 'revoked_at' )->limit( 1 );

		return $this->db->get_row( $sql->build(), $sql->get_bindings() );
	}

	/**
	 * Save the updated credential record (signature counter) after a valid sign-in.
	 *
	 * @param int    $id              Row ID.
	 * @param string $credential_data Serialized, updated credential record.
	 * @return void
	 */
	public function record_use( int $id, string $credential_data ) : void {
		$this->db->update( TableName::USER_PASSKEYS->table(), [
			'credential_data' => $credential_data,
			'last_used_at'    => gmdate( 'Y-m-d H:i:s' ),
		], [ 'id' => $id ] );
	}

	/**
	 * Revoke one of a user's active passkeys.
	 *
	 * @param int $user_id Owner of the passkey.
	 * @param int $id      Row ID.
	 * @return bool Whether a passkey was revoked.
	 */
	public function revoke( int $user_id, int $id ) : bool {
		return 1 === $this->db->update( TableName::USER_PASSKEYS->table(), [
			'revoked_at' => gmdate( 'Y-m-d H:i:s' ),
		], [ 'id' => $id, 'user_id' => $user_id ] );
	}

	/**
	 * Begin query building.
	 *
	 * @return SQLBuilder
	 */
	private function query() : SQLBuilder {
		return \smliserQueryBuilder( $this->db->get_driver() );
	}
}
