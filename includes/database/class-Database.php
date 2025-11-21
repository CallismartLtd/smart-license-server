<?php
/**
 * The Database Manager class file.
 *
 * @package SmartLicenseServer\Database
 * @since 1.0.0
 */

namespace SmartLicenseServer\Database;

use PDO;

defined( 'SMLISER_PATH' ) || exit;

/**
 * Provides a unified database access layer across different environments.
 *
 * Database singleton class for Smart License Server.
 *
 * Acts as a proxy to the environment-specific database adapter.
 *
 * @method array get_row(string $query, array $params = [])
 * @method array get_results(string $query, array $params = [])
 * @method mixed get_var(string $query, array $params = [])
 * @method array get_col(string $query, array $params = [])
 * @method int|false insert(string $table, array $data)
 * @method int|false update(string $table, array $data, array $where)
 * @method int|false delete(string $table, array $where)
 * @method void begin_transaction()
 * @method void commit()
 * @method void rollback()
 * @method string|null get_last_error() Get last database error.
 * @method int|null get_insert_id() Get the last insertion ID
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
    public static function instance() {
        if ( null === self::$instance ) {
            $adapter = self::detect_environment();
            self::$instance = new self( $adapter );
        }
        return self::$instance;
    }

    /**
     * Detect the environment and return a suitable adapter.
     *
     * @return DatabaseAdapterInterface
     * @throws \Exception If no supported adapter can be initialized.
     */
    protected static function detect_environment() {
        // --- 1. WordPress Environment (Highest Priority) ---
        if ( defined( 'ABSPATH' ) && class_exists( '\wpdb' ) && isset( $GLOBALS['wpdb'] ) ) {
            return new WPAdapter( $GLOBALS['wpdb'] );
        }

        // --- 2. Laravel Environment ---
        if ( class_exists( 'Illuminate\Support\Facades\DB' ) ) {
            return new LaravelAdapter();
        }

        // Configuration setup for standard PHP environments (requires constants to be defined)
        $config = [
            'host'     => defined('DB_HOST') ? DB_HOST : 'localhost',
            'username' => defined('DB_USER') ? DB_USER : 'root',
            'password' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
            'database' => defined('DB_NAME') ? DB_NAME : '',
            'charset'  => 'utf8mb4',
        ];

        // --- 3. PDO Adapter (Preferred Standard PHP Fallback) ---
        if ( class_exists( 'PDO' ) && in_array( 'mysql', PDO::getAvailableDrivers() ) ) {
            include_once \SMLISER_PATH . 'includes/database/class-PdoAdapter.php';
            return new PdoAdapter( $config );
        }

        // --- 4. MySQLi Adapter (Basic Standard PHP Fallback) ---
        if ( class_exists( 'mysqli' ) ) {
            include_once \SMLISER_PATH . 'includes/database/class-MysqliAdapter.php';
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
	 * Calcualte query offset from page and limit.
	 * 
	 * @param int $page The current pagination number.
	 * @param int $limit The result limit for the current request.
	 * @return int $offset Calculated offset
	 */
	public static function calculate_query_offset( $page, $limit ) {
		$page	= max( 1, $page );

		return absint( max( 0, ( $page - 1 ) * $limit ) );
	}
}
