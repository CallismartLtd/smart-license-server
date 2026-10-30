<?php
/**
 * Boot keyring trait file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

namespace SmartLicenseServer\Utils;

use LogicException;

/**
 * Keys for models that are not built by the container.
 *
 * A model (a DataStore class, created with `new static()`) cannot receive the
 * application secret through its constructor. Like DataStore::set_database(),
 * the secret and salt are given once at boot with set_keys(), and the model
 * derives a key per purpose from them. Each class that uses this trait has
 * its own keyring, so each is given its keys at boot.
 */
trait BootKeyringTrait {

	/**
	 * The application secret and salt keys are derived from.
	 *
	 * @var array{secret: string, salt: string}
	 */
	private static array $keyring = [ 'secret' => '', 'salt' => '' ];

	/**
	 * Set the application secret and salt keys are derived from.
	 *
	 * @param string $secret The application secret.
	 * @param string $salt   The application salt.
	 * @return void
	 */
	public static function set_keys( #[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $salt ) : void {
		self::$keyring = [ 'secret' => $secret, 'salt' => $salt ];
	}

	/**
	 * Derive the key for a purpose from the application secret (HKDF-SHA256).
	 *
	 * Each purpose gives an unrelated key.
	 *
	 * @param string $context The purpose of the key.
	 * @return string 32-byte key.
	 * @throws LogicException When set_keys() was not called at boot.
	 */
	private static function keyring_key( string $context ) : string {
		if ( '' === self::$keyring['secret'] ) {
			throw new LogicException( sprintf( '%s::set_keys() must be called at boot before keys are derived.', self::class ) );
		}

		return hash_hkdf( 'sha256', self::$keyring['secret'], 32, $context, self::$keyring['salt'] );
	}
}
