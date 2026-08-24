<?php
/**
 * Specialized Exception for Database Layer Failures.
 *
 * This exception abstracts database-related failures including connection
 * failures, adapter configuration, query execution, constraint violations,
 * transaction issues, migrations, and CRUD operation failures.
 *
 * It is intended for use throughout the database abstraction and persistence
 * layers, including database adapters, registries, repositories, query
 * builders, migrations, and persistence services.
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
     * Each key represents a shorthand error slug used across the DB layer.
     *
     * @var array<string, array{code: string, message: string}>
     */
    protected $error_map = [

        /*
        |-----------------------
        | ADAPTER / REGISTRY
        |-----------------------
        */
        'adapter_not_found' => [
            'code'    => 'DB_ADAPTER_NOT_FOUND',
            'message' => 'The requested database adapter was not found.',
        ],
        'adapter_not_registered' => [
            'code'    => 'DB_ADAPTER_NOT_REGISTERED',
            'message' => 'The requested database adapter is not registered.',
        ],
        'adapter_invalid' => [
            'code'    => 'DB_ADAPTER_INVALID',
            'message' => 'The specified database adapter is invalid.',
        ],
        'adapter_class_not_found' => [
            'code'    => 'DB_ADAPTER_CLASS_NOT_FOUND',
            'message' => 'The specified database adapter class does not exist.',
        ],
        'adapter_interface_invalid' => [
            'code'    => 'DB_ADAPTER_INTERFACE',
            'message' => 'The specified class does not implement the required database adapter interface.',
        ],
        'adapter_id_invalid' => [
            'code'    => 'DB_ADAPTER_ID_INVALID',
            'message' => 'The specified database adapter ID is invalid.',
        ],
        'adapter_id_conflict' => [
            'code'    => 'DB_ADAPTER_ID_CONFLICT',
            'message' => 'The specified database adapter ID is already registered.',
        ],
        'core_adapter_override' => [
            'code'    => 'DB_CORE_ADAPTER_OVERRIDE',
            'message' => 'A core database adapter cannot be overridden.',
        ],
        'no_adapter_for_engine' => [
            'code'    => 'DB_NO_ADAPTER_ENGINE',
            'message' => 'No database adapter is registered for the specified database engine.',
        ],
        'adapter_engine_mismatch' => [
            'code'    => 'DB_ADAPTER_ENGINE_MISMATCH',
            'message' => 'The specified database adapter is not registered for the requested database engine.',
        ],
        'default_adapter_not_found' => [
            'code'    => 'DB_DEFAULT_ADAPTER_NOT_FOUND',
            'message' => 'No default database adapter is configured for the specified database engine.',
        ],
        'default_adapter_invalid' => [
            'code'    => 'DB_DEFAULT_ADAPTER_INVALID',
            'message' => 'The configured default database adapter is not registered for the specified database engine.',
        ],

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
        'unsupported_engine' => [
            'code'    => 'DB_ENGINE_UNSUPPORTED',
            'message' => 'The specified database engine is not supported.',
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
        |-----------------------
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
     * @param mixed       $custom_data Optional metadata.
     */
    public function __construct(
        string $error_slug,
        ?string $custom_message = null,
        $custom_data = []
    ) {
        $has_map = isset( $this->error_map[ $error_slug ] );

        $default_data = $has_map
            ? $this->error_map[ $error_slug ]
            : $this->error_map['unknown_db_error'];

        $message = $custom_message ?? $default_data['message'];

        $data = array_merge(
            [
                'error_code' => $default_data['code'],
            ],
            (array) $custom_data
        );

        parent::__construct( $error_slug, $message, $data );
    }
}