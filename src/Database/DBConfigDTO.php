<?php
/**
 * Database configuration data transfer object file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Database;

use LogicException;
use Callismart\DTO\DTO;

/**
 * Database configuration data transfer object.
 *
 * Sensitive credentials are automatically protected from
 * exposure through debugging or serialization.
 * 
 * @property string  $driver    The database adapter engine target (e.g., 'mysql', 'sqlite', 'pgsql').
 * @property ?string $host      The database server network hostname or IP address.
 * @property ?string $port      The database server network communication port.
 * @property ?string $database  The name of the target database or schema.
 * @property ?string $username  The connection authentication identity string.
 * @property ?string $password  The connection authentication credential secret.
 * @property ?string $charset   The text character encoding layout specification.
 * @property ?string $collation The text string sorting and comparison criteria rule.
 * @property ?string $prefix    The database engine table namespace identifier prefix.
 * @property ?string $socket    The local system IPC Unix socket connection endpoint path.
 * @property ?string $path      The file-system target path for isolated file-based databases.
 * @property ?string $dsn       A raw engine connection string override used to bypass default parameterization.
 * @property ?array  $flags     Engine-specific runtime connection attributes or configuration options.
    * @property ?array  $ssl        SSL deployment options and authority certificates mapping.
 * @property ?string $sslmode    SSL transmission enforcement tier (PostgreSQL/MySQL).
 * @property ?bool   $strict     Enforcement behavior rule modifier for SQL execution modes.
 * @property ?bool   $persistent Connection reuse strategy persistence indicator flag.
 * @property ?int    $timeout    Temporal boundary restriction constraint for connection limits.
 * @property ?array  $read       High-availability routing configuration metrics for read replicas.
 * @property ?array  $write      High-availability routing configuration metrics for write primaries.
 * @property ?bool   $sticky     Immediate transactional lookup mapping flag for replica routing.
 */
final class DBConfigDTO extends DTO {

    /**
     * Allowed configuration keys.
     *
     * Supports network databases, cluster architectures, and file-based storage.
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
            'path',
            'dsn',
            'flags',
            'ssl',
            'sslmode',
            'strict',
            'persistent',
            'timeout',     
            'read',
            'write',       
            'sticky',
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

            $allowed = [ 'mysql', 'pgsql', 'sqlite' ];

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