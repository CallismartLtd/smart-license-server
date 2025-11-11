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
        $this->pdo->beginTransaction();
    }

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit() {
        $this->pdo->commit();
    }

    /**
     * Roll back the current transaction.
     *
     * @return void
     */
    public function rollback() {
        $this->pdo->rollBack();
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  The SQL query with placeholders (:name or ?).
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

                foreach ( $params as $i => $param ) {
                    $type = PDO::PARAM_STR; // default
                    if ( is_int( $param ) ) {
                        $type = PDO::PARAM_INT;
                    } elseif ( is_bool( $param ) ) {
                        $type = PDO::PARAM_BOOL;
                    } elseif ( is_null( $param ) ) {
                        $type = PDO::PARAM_NULL;
                    }
                    // PDO uses 1-based indices for bindValue with ?
                    $stmt->bindValue( $i + 1, $param, $type );
                }

                $stmt->execute();

                $this->insert_id = $this->pdo->lastInsertId() ?: null;
                return $stmt;

            } catch ( \PDOException $e ) {
                $this->last_error = $e->getMessage();
                return false;
            }
        }


    /**
     * Retrieve a single row as an associative array.
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
     * @return mixed|null The first column of the first row, or null if none.
     */
    public function get_var( $query, array $params = [] ) {
        $stmt = $this->query( $query, $params );
        if ( false === $stmt ) {
            return null;
        }
        return $stmt->fetchColumn();
    }

    /**
     * Retrieve a single column of values.
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
     * @return int|false The inserted record ID on success, false on failure.
     */
    public function insert( $table, array $data ) {
        if ( empty( $data ) ) {
            $this->last_error = 'Insert data cannot be empty.';
            return false;
        }

        $fields = array_keys( $data );
        $placeholders = array_map( fn($f) => ":$f", $fields );

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $result = $this->query( $query, $data );

        if ( false === $result ) {
            return false;
        }

        return $this->get_insert_id();
    }

    /**
     * Update existing records.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function update( $table, array $data, array $where ) {
        if ( empty( $data ) || empty( $where ) ) {
            $this->last_error = 'Update data and WHERE condition cannot be empty.';
            return false;
        }

        $set_clauses = array_map( fn($k) => "$k = :set_$k", array_keys( $data ) );
        $where_clauses = array_map( fn($k) => "$k = :where_$k", array_keys( $where ) );

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set_clauses),
            implode(' AND ', $where_clauses)
        );

        $params = [];
        foreach ( $data as $k => $v ) $params[":set_$k"] = $v;
        foreach ( $where as $k => $v ) $params[":where_$k"] = $v;

        $stmt = $this->query( $query, $params );

        if ( false === $stmt ) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Delete records from the database.
     *
     * @return int|false Number of affected rows, or false on failure.
     */
    public function delete( $table, array $where ) {
        if ( empty( $where ) ) {
            $this->last_error = 'Delete WHERE condition cannot be empty.';
            return false;
        }

        $where_clauses = array_map( fn($k) => "$k = :where_$k", array_keys( $where ) );
        $query = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $where_clauses)
        );

        $params = [];
        foreach ( $where as $k => $v ) $params[":where_$k"] = $v;

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