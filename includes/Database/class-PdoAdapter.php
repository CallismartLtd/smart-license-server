<?php
/**
 * PDO Database Adapter
 *
 * Implements the DatabaseAdapterInterface for environments using PDO (PHP Data Objects).
 * This is the preferred adapter for non-framework pure PHP environments.
 *
 * @package SmartLicenseServer\Database
 */

namespace SmartLicenseServer\Database;

use PDO;
use PDOException;

defined( 'SMLISER_PATH' ) || exit;

/**
 * Adapter for PDO database access.
 */
class PdoAdapter implements DatabaseAdapterInterface {

    /**
     * The PDO connection instance.
     *
     * @var PDO|null
     */
    protected $pdo;

    /**
     * Configuration settings for the PDO connection.
     *
     * @var array
     */
    protected $config;

    /**
     * Last inserted ID.
     *
     * @var int|null
     */
    protected $insert_id = null;

    /**
     * Last executed error message.
     *
     * @var string|null
     */
    protected $last_error = null;

    /**
     * Constructor.
     *
     * @param array $config Database connection configuration.
     */
    public function __construct( array $config ) {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Establish a database connection.
     *
     * @return bool True on success, false on failure.
     */
    public function connect() {
        if ( $this->pdo ) {
            return true;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['host'] ?? 'localhost',
                $this->config['database'] ?? '',
                $this->config['charset'] ?? 'utf8mb4'
            );

            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? 'root',
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return true;
        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Close the active database connection.
     *
     * @return void
     */
    public function close() {
        $this->pdo = null;
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function begin_transaction() {
        if ( $this->pdo && ! $this->pdo->inTransaction() ) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        if ( $this->pdo && $this->pdo->inTransaction() ) {
            $this->pdo->commit();
        }
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() {
        if ( $this->pdo && $this->pdo->inTransaction() ) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * Uses positional placeholders (?) for parameter binding.
     *
     * @param string $query  The SQL query with ? placeholders.
     * @param array  $params Optional. The bound values for placeholders.
     *
     * @return mixed The native statement object, or false on failure.
     */
    public function query( $query, array $params = [] ) {
        if ( ! $this->pdo ) {
            $this->last_error = 'No active PDO connection.';
            return false;
        }

        try {
            $stmt = $this->pdo->prepare( $query );

            if ( ! empty( $params ) ) {
                foreach ( $params as $i => $param ) {
                    $type = $this->get_param_type( $param );
                    // PDO uses 1-based indices for positional placeholders
                    $stmt->bindValue( $i + 1, $param, $type );
                }
            }

            $stmt->execute();

            // Store insert ID for INSERT queries
            $this->insert_id = $this->pdo->lastInsertId() ?: null;
            
            return $stmt;

        } catch ( PDOException $e ) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Determine the PDO parameter type for a value.
     *
     * @param mixed $param The parameter value.
     * @return int PDO::PARAM_* constant.
     */
    protected function get_param_type( $param ) {
        if ( is_null( $param ) ) {
            return PDO::PARAM_NULL;
        } elseif ( is_bool( $param ) ) {
            return PDO::PARAM_BOOL;
        } elseif ( is_int( $param ) ) {
            return PDO::PARAM_INT;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * Retrieve a single row as an associative array.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return array|null Associative array of the row, or null if not found.
     */
    public function get_row( $query, array $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return null;
        }
        
        $row = $stmt->fetch( PDO::FETCH_ASSOC );
        return $row ?: null;
    }

    /**
     * Retrieve multiple rows as an array of associative arrays.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return array List of associative arrays representing result rows.
     */
    public function get_results( $query, array $params = [] ) {
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return [];
        }
        
        return $stmt->fetchAll( PDO::FETCH_ASSOC );
    }

    /**
     * Retrieve a single scalar value.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return mixed|null The first column of the first row, or null if none.
     */
    public function get_var( $query, array $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return null;
        }
        
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : null;
    }

    /**
     * Retrieve a single column of values.
     *
     * @param string $query  SQL query with ? placeholders.
     * @param array  $params Optional. Bound values for placeholders.
     *
     * @return array List of column values, or empty array if none found.
     */
    public function get_col( $query, array $params = [] ) {
        $stmt = $this->query( $query, $params );
        
        if ( false === $stmt ) {
            return [];
        }
        
        return $stmt->fetchAll( PDO::FETCH_COLUMN, 0 );
    }

    /**
     * Insert a record into the database.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     *
     * @return int|false The inserted record ID on success, false on failure.
     */
    public function insert( $table, array $data ) {
        if ( empty( $data ) ) {
            $this->last_error = 'Insert data cannot be empty.';
            return false;
        }

        $columns = array_keys( $data );
        $placeholders = array_fill( 0, count( $data ), '?' );

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode( ', ', $columns ),
            implode( ', ', $placeholders )
        );

        $params = array_values( $data );
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        return $this->get_insert_id();
    }

    /**
     * Update existing records.
     *
     * @param string $table Table name.
     * @param array  $data  Associative array of column => value.
     * @param array  $where Associative array for WHERE conditions.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function update( $table, array $data, array $where ) {
        if ( empty( $data ) || empty( $where ) ) {
            $this->last_error = 'Update data and WHERE condition cannot be empty.';
            return false;
        }

        // Build SET clause with ? placeholders
        $set_clauses = array_map( function( $column ) {
            return "$column = ?";
        }, array_keys( $data ) );

        // Build WHERE clause with ? placeholders
        $where_clauses = array_map( function( $column ) {
            return "$column = ?";
        }, array_keys( $where ) );

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode( ', ', $set_clauses ),
            implode( ' AND ', $where_clauses )
        );

        // Merge data and where values for positional binding
        $params = array_merge( array_values( $data ), array_values( $where ) );
        
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Delete records from the database.
     *
     * @param string $table Table name.
     * @param array  $where Associative array for WHERE conditions.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function delete( $table, array $where ) {
        if ( empty( $where ) ) {
            $this->last_error = 'Delete WHERE condition cannot be empty.';
            return false;
        }

        // Build WHERE clause with ? placeholders
        $where_clauses = array_map( function( $column ) {
            return "$column = ?";
        }, array_keys( $where ) );

        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode( ' AND ', $where_clauses )
        );

        $params = array_values( $where );
        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Retrieve the last inserted ID.
     *
     * @return int|null The last inserted ID, or null if not available.
     */
    public function get_insert_id() {
        return $this->insert_id;
    }

    /**
     * Retrieve the last database error.
     *
     * @return string|null The last error message, or null if none.
     */
    public function get_last_error() {
        return $this->last_error;
    }
}