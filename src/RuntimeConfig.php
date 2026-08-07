<?php
/**
 * Runtime configuration class file.
 * 
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.3.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer;

use Callismart\DTO\DTO;

/**
 * Runtime configuration class.
 * 
 * Provides a centralized, immutable configuration object that environment providers
 * can pass to the core application.
 * 
 * @property-read string $app_root Absolute path to the root directory.
 * @property-read string $storage_dir Absolute path to the storage directory.
 * @property-read string $runtime_dir Absolute path to the runtime directory.
 * @property-read string $index_file Absolute path to the index file.
 * @property-read bool $debug_mode Whether debug mode is enabled.
 * @property-read bool $display_errors Whether to display errors.
 * @property-read bool $log_errors Whether to log errors.
 * @property-read string $secret Application secret key.
 * @property-read string $salt Application salt key.
 * @property-read string $db_table_prefix Database table prefix.
 */
class RuntimeConfig extends DTO {
    /**
     * {@inheritdoc}
     */
    protected function allowed_keys() : array {
        return [
            'app_root',
            'storage_dir',
            'runtime_dir',
            'index_file',
            'debug_mode',
            'display_errors',
            'log_errors',
            'secret',
            'salt',
            'db_table_prefix'
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function sensitive_keys() : array {
        return ['secret', 'salt'];
    }

    /**
     * {@inheritdoc}
     */
    protected function cast( string $key, mixed $value ) : mixed {
        return match ( $key ) {
            'debug_mode', 'display_errors', 'log_errors' => (bool) $value,
            default => (string) $value
        };
    }

    /**
     * Instanciate with default configuration
     * 
     * @return static
     */
    public static function defaults() : static {
        return new static([
            'app_root'      => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'storage_dir'   => '',
            'runtime_dir'   => '',
            'index_file'    => __FILE__,
            'debug_mode'        => false,
            'display_errors'    => false,
            'log_errors'        => false,
            'secret'    => '',
            'salt'      => '',
            'db_table_prefix'   => 'smliser_'

        ]);
    }
}