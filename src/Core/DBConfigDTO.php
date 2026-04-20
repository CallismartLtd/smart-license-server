<?php
/**
 * Database configuration data transfer object file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Core;

use LogicException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Database configuration data transfer object.
 *
 * Provides a unified configuration container for all supported
 * database adapters including MySQL, PDO, WordPress wpdb,
 * Laravel database manager, and SQLite.
 *
 * Sensitive credentials are automatically protected from
 * exposure through debugging or serialization.
 */
final class DBConfigDTO extends DTO {

    /**
     * Allowed configuration keys.
     *
     * Supports both network databases and file-based SQLite databases.
     *
     * @return string[]
     */
    protected function allowed_keys(): array {
        return [
            'driver',      // mysql | sqlite | pgsql | etc
            'host',
            'port',
            'database',
            'username',
            'password',
            'charset',
            'collation',
            'prefix',
            'socket',
            'path',        // SQLite database file
            'dsn',         // Optional DSN override
            'flags',       // PDO options
        ];
    }

    /**
     * Sensitive configuration keys.
     *
     * @return string[]
     */
    protected function sensitive_keys(): array {
        return [
            'password',
        ];
    }

    /**
     * Cast values to expected types where appropriate.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    protected function cast( string $key, mixed $value ): mixed {
        if ( 'driver' === $key ) {

            $allowed = [ 'mysql', 'sqlite', 'pdo', 'wpdb', 'laravel' ];

            if ( ! in_array( $value, $allowed, true ) ) {
                throw new \InvalidArgumentException(
                    sprintf( 'Unsupported database driver "%s".', $value )
                );
            }
        }
        
        return match ( $key ) {

            'port'  => is_null( $value ) ? null : (int) $value,

            'flags' => is_array( $value ) ? $value : [],

            default => $value,
        };
    }

    /**
     * Return a masked array representation of the configuration.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {

        $data = parent::to_array();

        foreach ( $this->sensitive_keys() as $key ) {

            if ( array_key_exists( $key, $data ) ) {
                $data[ $key ] = '******';
            }
        }

        return $data;
    }

    /**
     * JSON serialization handler.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed {
        return $this->to_array();
    }

    /**
     * Debug handler for var_dump().
     *
     * Prevents credential leakage.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array {

        return [
            'class' => static::class,
            'count' => $this->count(),
            'props' => $this->to_array(),
        ];
    }

    /**
     * Prevent cloning.
     *
     * @throws LogicException
     */
    public function __clone() {
        throw new LogicException(
            'Cloning DBConfigDTO is not allowed.'
        );
    }

    /**
     * Prevent serialization.
     *
     * @throws LogicException
     */
    public function __serialize(): array {
        throw new LogicException(
            'Serialization of DBConfigDTO is not allowed.'
        );
    }

    /**
     * Prevent unserialization.
     *
     * @param array<string,mixed> $data
     * @throws LogicException
     */
    public function __unserialize( array $data ): void {
        throw new LogicException(
            'Unserialization of DBConfigDTO is not allowed.'
        );
    }
}