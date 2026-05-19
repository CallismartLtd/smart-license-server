<?php
/**
 * The Database Abstraction file.
 *
 * @package SmartLicenseServer\Database\Adapters
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database;

use SmartLicenseServer\Database\Adapters\Contracts\DatabaseAdapterInterface;

/**
 * Database abstraction API.
 *
 * Database singleton class for Smart License Server.
 *
 * Acts as a proxy to the environment-specific database adapter.
 * 
 * @todo Use the inspection API for system report.
 *
 * @method array|null get_row( string $query, array $params = [] ) Retrieve a single row as an associative array.
 * @method array get_results( string $query, array $params = [] ) Retrieve multiple rows as an array of associative arrays.
 * @method mixed|null get_var( string $query, array $params = [] ) Retrieve a single scalar value.
 * @method array get_col( string $query, array $params = [] ) Retrieve a single column of values.
 * @method int|false insert( string $table, array $data ) Insert a record into the database.
 * @method int|false update( string $table, array $data, array $where ) Update existing records.
 * @method int|false delete( string $table, array $where ) Delete records from the database.
 *
 * @method void begin_transaction() Begin a database transaction.
 * @method void commit() Commit the current transaction.
 * @method void rollback() Roll back the current transaction.
 *
 * @method string|null get_last_error() Get last database error.
 * @method string get_last_query() Get last executed query string.
 * @method int|null get_insert_id() Get the last insertion ID.
 *
 * @method bool exec(string $query) Execute a raw SQL query without prepared statements.
 * @method bool execute( string $query, array $params = [] ) Execute a parameterized query and return the number of affected rows.
 *
 * @method string get_server_version() Get the database server version.
 * @method string get_driver() Get the engine type (mysql, sqlite, etc).
 * @method string|null get_host_info() Get connection host information.
 * @method string|int|null get_protocol_version() Get the database protocol version.
 * @method \SmartLicenseServer\Database\DBConfigDTO get_config() Get the database protocol version.
 *
 * @method bool is_connected() Check whether the database connection is alive.
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
    protected DatabaseAdapterInterface $adapter;

    /**
     * Class constructor.
     *
     * @param DatabaseAdapterInterface $adapter Database adapter instance.
     */
    public function __construct( DatabaseAdapterInterface $adapter ) {
        $this->adapter      = $adapter;
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

        $backtrace  = \debug_backtrace( \DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $file       = $backtrace[0]['file'] ?? null;
        $line       = $backtrace[0]['line'] ?? null;
        $message    = sprintf(
            'Method %s::%s does not exist.', 
            get_class( $this ),
            $method
        );

        throw new \ErrorException( $message, 0, 1, $file, $line );
    }

    /**
     * Execute a set of queries within a transaction block.
     *
     * @param callable $callback A function containing the database logic.
     * @return mixed Returns the result of the callback on success, or throws Exception on fail.
     * @throws \Throwable
     */
    public function transactional( callable $callback ) : mixed {
        $result = null;
        try {
            $this->begin_transaction();
            $result = $callback();

            $this->commit();
            
        } catch( \Throwable $th ) {
            $this->rollback();
            throw $th;
        }

        return $result;
    }

    /**
     * Calculate query offset from page and limit.
     * * @param int $page The current pagination number.
     * @param int $limit The result limit for the current request.
     * @return int Calculated offset.
     */
    public static function calculate_query_offset( int $page, int $limit ) {
        $page   = max( 1, $page );
        $limit  = $limit;
        return max( 0, ( $page - 1 ) * $limit );
    }
    
    /**
     * Get the charset and collation string for table creation.
     *
     * @return string SQL fragment for charset and collation.
     */
    public function get_charset_collate() {
        // @todo: Will use the schema inspection API as some relayed method calls
        // to the adapter interface has been removed
        
        if ( 'mysql' !== $this->get_driver() ) {
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
        // @todo: Will use the schema inspection API as some relayed method calls
        // to the adapter interface has been removed
        return [
            'engine'           => $this->get_driver(),
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

    /**
     * Get the underly adapter.
     */
    public function get_adapter() : DatabaseAdapterInterface {
        return $this->adapter;
    }
}