<?php
/**
 * Specialized Exception for Database Layer Failures.
 *
 * This exception abstracts all database-related errors including:
 * connection failures, query execution errors, constraint violations,
 * transaction issues, and CRUD operation failures.
 *
 * It is intended for use inside repositories, query builders,
 * and persistence services.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus
 */

namespace SmartLicenseServer\Exceptions;

use SmartLicenseServer\Exceptions\Exception;

/**
 * Class DatabaseException
 */
class DatabaseException extends Exception {

    /**
     * Database error map.
     *
     * Each key represents a shorthand error code used across the DB layer.
     */
    protected static $error_map = [

        /*
        |-----------------------
        | CONNECTION ERRORS
        |-----------------------
        */
        'connection_failed' => [
            'code'    => 'DB_CONN_FAIL',
            'message' => 'Failed to establish database connection.',
        ],
        'connection_lost' => [
            'code'    => 'DB_CONN_LOST',
            'message' => 'Database connection was lost during operation.',
        ],
        'invalid_dsn' => [
            'code'    => 'DB_INVALID_DSN',
            'message' => 'The database DSN configuration is invalid.',
        ],

        /*
        |---------------------------
        | QUERY / EXECUTION ERRORS
        |---------------------------
        */
        'query_failed' => [
            'code'    => 'DB_QUERY_FAIL',
            'message' => 'The database query failed to execute.',
        ],
        'query_syntax_error' => [
            'code'    => 'DB_QUERY_SYNTAX',
            'message' => 'There is a syntax error in the database query.',
        ],
        'query_timeout' => [
            'code'    => 'DB_QUERY_TIMEOUT',
            'message' => 'The database query timed out.',
        ],

        /*
        |-----------------------
        | CRUD OPERATIONS
        |-----------------------
        */
        'insert_failed' => [
            'code'    => 'DB_INSERT_FAIL',
            'message' => 'Failed to insert record into the database.',
        ],
        'update_failed' => [
            'code'    => 'DB_UPDATE_FAIL',
            'message' => 'Failed to update the database record.',
        ],
        'delete_failed' => [
            'code'    => 'DB_DELETE_FAIL',
            'message' => 'Failed to delete the database record.',
        ],
        'select_failed' => [
            'code'    => 'DB_SELECT_FAIL',
            'message' => 'Failed to retrieve data from the database.',
        ],

        /*
        |-----------------------
        | TRANSACTIONS
        |-----------------------
        */
        'transaction_begin_failed' => [
            'code'    => 'DB_TX_BEGIN_FAIL',
            'message' => 'Failed to start database transaction.',
        ],
        'transaction_commit_failed' => [
            'code'    => 'DB_TX_COMMIT_FAIL',
            'message' => 'Failed to commit database transaction.',
        ],
        'transaction_rollback_failed' => [
            'code'    => 'DB_TX_ROLLBACK_FAIL',
            'message' => 'Failed to rollback database transaction.',
        ],

        /*
        |-----------------------
        | CONSTRAINT / INTEGRITY
        | -----------------------
        */
        'duplicate_entry' => [
            'code'    => 'DB_DUPLICATE',
            'message' => 'A duplicate record already exists.',
        ],
        'foreign_key_violation' => [
            'code'    => 'DB_FK_VIOLATION',
            'message' => 'Foreign key constraint failed.',
        ],
        'not_null_violation' => [
            'code'    => 'DB_NOT_NULL',
            'message' => 'A required database field is missing (NOT NULL constraint).',
        ],
        'data_too_long' => [
            'code'    => 'DB_DATA_TOO_LONG',
            'message' => 'Data exceeds allowed column size.',
        ],

        /*
        |-----------------------
        | MIGRATION / SCHEMA
        |-----------------------
        */
        'migration_failed' => [
            'code'    => 'DB_MIGRATION_FAIL',
            'message' => 'Database migration failed.',
        ],
        'schema_mismatch' => [
            'code'    => 'DB_SCHEMA_MISMATCH',
            'message' => 'Database schema does not match expected structure.',
        ],

        /*
        |-----------------------
        | GENERIC FALLBACK
        |-----------------------
        */
        'unknown_db_error' => [
            'code'    => 'DB_UNKNOWN',
            'message' => 'An unknown database error occurred.',
        ],
    ];

    /**
     * DatabaseException constructor.
     *
     * @param string      $error_slug Known DB error key.
     * @param string|null $custom_message Optional override message.
     * @param mixed       $custom_data Optional metadata (query, bindings, etc).
     */
    public function __construct( string $error_slug, ?string $custom_message = null, $custom_data = [] ) {

        $has_map = isset( static::$error_map[ $error_slug ] );

        $default_data = $has_map
            ? static::$error_map[ $error_slug ]
            : static::$error_map['unknown_db_error'];

        $resolved_slug = $error_slug;

        $message = $custom_message ?? $default_data['message'];

        $data = array_merge(
            [
                'error_code' => $default_data['code'],
            ],
            (array) $custom_data
        );

        parent::__construct( $resolved_slug, $message, $data );
    }
}