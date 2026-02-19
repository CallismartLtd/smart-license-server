<?php
/**
 * The Database Manager class file.
 *
 * @package SmartLicenseServer\Database
 * @since 1.0.0
 */

namespace SmartLicenseServer\Database;

use PDO;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides a unified database access layer across different environments.
 *
 * Database singleton class for Smart License Server.
 *
 * Acts as a proxy to the environment-specific database adapter.
 *
 * @method array|null get_row(string $query, array $params = [])
 * @method array get_results(string $query, array $params = [])
 * @method mixed|null get_var(string $query, array $params = [])
 * @method array get_col(string $query, array $params = [])
 * @method int|false insert(string $table, array $data)
 * @method int|false update(string $table, array $data, array $where)
 * @method int|false delete(string $table, array $where)
 * @method void begin_transaction()
 * @method void commit()
 * @method void rollback()
 * @method string|null get_last_error() Get last database error.
 * @method string get_last_query() Get last executed query string.
 * @method int|null get_insert_id() Get the last insertion ID.
 * @method mixed query(string $query, array $params = []) Execute a raw SQL query.
 * @method string get_server_version() Get the database server version.
 * @method string get_engine_type() Get the engine type (mysql, sqlite, etc).
 * @method string|null get_host_info() Get connection host information.
 * @method string|int|null get_protocol_version() Get the database protocol version.
 */
class Database {

    /**
     * The singleton instance.
     *
     * @var Database|null
     */
    protected static $instance = null;

    /**
     * The active adapter instance.
     *
     * @var DatabaseAdapterInterface
     */
    protected $adapter;

    /**
     * Private constructor.
     *
     * @param DatabaseAdapterInterface $adapter Database adapter instance.
     */
    private function __construct( DatabaseAdapterInterface $adapter ) {
        $this->adapter = $adapter;
    }

    /**
     * Initialize and get the database instance.
     *
     * Automatically detects the current environment and chooses the appropriate adapter.
     *
     * @return Database
     */
    public static function instance( ?DatabaseAdapterInterface $db = null ) {
        if ( is_null( self::$instance ) ) {
            $adapter            = $db ?? static::detect_environment();
            static::$instance   = new static( $adapter );
        }

        return static::$instance;
    }

    /**
     * Detect the environment and return a suitable adapter.
     *
     * @return DatabaseAdapterInterface
     * @throws \Exception If no supported adapter can be initialized.
     */
    protected static function detect_environment() {

        if ( class_exists( 'Illuminate\Support\Facades\DB' ) ) {
            return new LaravelAdapter();
        }

        // Common configuration for standard PHP environments
        $config = [
            'host'      => defined('DB_HOST') ? DB_HOST : 'localhost',
            'username'  => defined('DB_USER') ? DB_USER : 'root',
            'password'  => defined('DB_PASSWORD') ? DB_PASSWORD : '',
            'database'  => defined('DB_NAME') ? DB_NAME : '',
            'charset'   => function_exists('smliser_settings_adapter') ? \smliser_settings_adapter()->get( 'db_charset', 'utf8mb4' ) : 'utf8mb4',
        ];

        if ( defined( 'DB_TYPE' ) && 'sqlite' === constant( 'DB_TYPE' ) && class_exists( 'SQLite3' ) ) {
            return new SqliteAdapter( [
                'database' => defined( 'DB_FILE' ) ? constant( 'DB_TYPE' )  : $config['database']
            ] );
        }

        if ( class_exists( PDO::class ) && in_array( 'mysql', PDO::getAvailableDrivers() ) ) {
            return new PdoAdapter( $config );
        }

        if ( class_exists( 'mysqli' ) ) {
            return new MysqliAdapter( $config );
        }
        
        throw new \Exception( 'No supported database adapter found or initialized.' );
    }

    /**
     * Get the underlying adapter instance.
     *
     * @return DatabaseAdapterInterface
     */
    public function get_adapter() {
        return $this->adapter;
    }

    /**
     * Proxy calls to the adapter methods.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     *
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call( $method, $args ) {
        if ( method_exists( $this->adapter, $method ) ) {
            return call_user_func_array( [ $this->adapter, $method ], $args );
        }

        throw new \BadMethodCallException(
            sprintf( 'Method %s::%s does not exist.', get_class( $this->adapter ), $method )
        );
    }

    /**
     * Calculate query offset from page and limit.
     * * @param int $page The current pagination number.
     * @param int $limit The result limit for the current request.
     * @return int Calculated offset.
     */
    public static function calculate_query_offset( $page, $limit ) {
        $page = max( 1, (int) $page );
        $limit = (int) $limit;
        return max( 0, ( $page - 1 ) * $limit );
    }
    
    /**
     * Get the charset and collation string for table creation.
     *
     * @return string SQL fragment for charset and collation.
     */
    public function get_charset_collate() {
        // SQLite doesn't use Charset/Collation in the same way as MySQL
        if ( $this->get_engine_type() === 'sqlite' ) {
            return '';
        }

        $charset = 'utf8mb4';
        $collate = 'utf8mb4_unicode_ci';

        if ( isset( $this->adapter->config['charset'] ) ) {
            $charset = $this->adapter->config['charset'];
        }

        return sprintf( 'DEFAULT CHARSET=%s COLLATE=%s', $charset, $collate );
    }

    /**
     * Generate a comprehensive report of the current database environment.
     *
     * Useful for system health checks, debug logs, or support dashboards.
     *
     * @return array {
     * @type string $engine           The driver name (mysql, sqlite, etc).
     * @type string $server_version   The database version.
     * @type string $protocol         The connection protocol version.
     * @type string $host             Host or file path information.
     * @type string|null $last_error  The most recent error message.
     * @type bool   $connection_alive Whether the connection is currently active.
     * }
     */
    public function get_system_report() {
        return [
            'engine'           => $this->get_engine_type(),
            'server_version'   => $this->get_server_version(),
            'protocol_version' => $this->get_protocol_version(),
            'host_info'        => $this->get_host_info(),
            'last_error'       => $this->get_last_error(),
            'connection_alive' => $this->adapter !== null,
            'php_extension'    => get_class( $this->adapter ),
        ];
    }

    /**
     * Get a formatted string representation of the database system report.
     *
     * Useful for CLI output, error logs, or "Copy to Clipboard" support features.
     *
     * @return string The formatted report string.
     */
    public function print_system_report() {
        $report = $this->get_system_report();
        
        $output = "### Database System Report" . PHP_EOL . PHP_EOL;
        $output .= "| Metric | Value |" . PHP_EOL;
        $output .= "| :--- | :--- |" . PHP_EOL; // Alignment row

        foreach ( $report as $key => $value ) {
            $label = ucwords( str_replace( '_', ' ', $key ) );
            if ( is_bool( $value ) ) { $value = $value ? 'Yes' : 'No'; }
            $value = $value ?? 'N/A';

            $output .= "| **$label** | $value |" . PHP_EOL;
        }

        return $output;
    }
}