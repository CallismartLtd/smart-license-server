<?php
/**
 * Core runtime class file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Environments\Application;

use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Environment;

/**
 * Smart License Server running as a standalone PHP application.
 */
class ApplicationEnvironment extends Environment {
    /**
     * Private constructor
    */
    private function __construct() {
        $this->bind_instance();
        $this->prepare_db_config();

        $this->setup([
            'identity_provider' => new IdentityProvider()
        ]);
        
    }

    /**
     * {@inheritdoc}
     */
    protected function bind_instance() : void {
        static::$envProvider = $this;
    }
    
    protected function prepare_db_config() {
        $this->dbConfig = new DBConfigDTO([
            'driver'    => $_ENV['SMLISER_DB_DRIVER'] ?? '',
            'host'      => $_ENV['SMLISER_DB_HOST'] ?? '',
            'port'      => $_ENV['SMLISER_DB_PORT'] ?? '',
            'dbname'    => $_ENV['SMLISER_DB_NAME'] ?? '',
            'username'  => $_ENV['SMLISER_DB_USER'] ?? '',
            'password'  => $_ENV['SMLISER_DB_PASSWORD'] ?? '',
            'charset'   => $_ENV['SMLISER_DB_CHARSET'] ?? '',
            'prefix'    => $_ENV['SMLISER_DB_PREFIX'] ?? '',
            'path'      => $_ENV['SMLISER_DB_PATH'] ?? '', // Auto use sqlite if set in driver.

        ]);

        if ( 'sqlite' === $this->dbConfig->driver && ! empty( $_ENV['SMLISER_SQLITE_ENCRYPTION_KEY'] ) ) {
            $this->dbConfig->encryption_key = $_ENV['SMLISER_SQLITE_ENCRYPTION_KEY'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function boot() : static {
        if ( ! isset( static::$envProvider ) ) {
            new static();
        }

        return static::$envProvider;
    }
   
   /**
    * {@inheritdoc}
    */
    public static function url( string $path = '', array $q = [] ) : URL {
        $url = (string)  $_ENV['SMLISER_APP_URL'] ?? '';
        
        return URL::from( $url )
            ->append_path( $path )
            ->add_query_params( $q );
    }

   /**
    * {@inheritdoc}
    */
    public static function assetsUrl( string $path = '', array $q = [] ) : URL {
        return static::url( '/assets/', $q )
        ->append_path( $path );
    }

    /**
     * {@inheritdoc}
     */
    public static function adminUrl( string $path = '', array $q = [] ) : URL {
        return static::url( '/admin/', $q )
        ->append_path( $path );
    }

    /**
     * {@inheritdoc}
     */
    public static function restAPIUrl( string $path = '', array $q = [] ) : URL {
        return static::url( '/api/', $q )
        ->append_path( $path );
    }

    /**
     * {@inheritdoc}
     */
    public function route_register() : void {}

    /**
     * {@inheritdoc}
     */
    public function check_filesystem_errors(): void {}
}