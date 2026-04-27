<?php
/**
 * SQLite Engine Renderer
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * SQLite-specific SQL renderer.
 *
 * Generates SQLite-compliant SQL from normalized intent.
 * Enforces explicit exceptions for unsupported ALTER TABLE operations.
 *
 * @since 0.2.0
 */
class SQLiteRenderer extends EngineRenderer {

    protected string $engine = 'sqlite';

    /**
     * {@inheritDoc}
     */
    protected function quote_identifier( string $identifier ) : string {
        return '"' . $identifier . '"';
    }

    /**
     * {@inheritDoc}
     */
    protected function normalize_type( string $type ) : string {
        return match ( strtoupper( preg_replace( '/\(.+\)/', '', $type ) ) ) {
            'BIGINT', 'INT', 'INTEGER'      => 'INTEGER',
            'VARCHAR', 'TEXT', 'LONGTEXT'   => 'TEXT',
            'BOOLEAN'                       => 'INTEGER',
            'DATETIME', 'TIMESTAMP', 'DATE' => 'TEXT',
            'JSON', 'JSONB'                 => 'TEXT',
            'FLOAT', 'DOUBLE', 'DECIMAL'    => 'REAL',
            'BLOB', 'LONGBLOB'              => 'BLOB',
            default                         => 'TEXT'
        };
    }

    /**
     * Render constraint for SQLite.
     *
     * @param array $constraint Constraint intent
     *
     * @return string Constraint SQL
     */
    protected function render_constraint( array $constraint ) : string {

        $type = $constraint['type'] ?? '';

        switch ( $type ) {

            case 'PRIMARY KEY':
                $cols = implode(
                    ', ',
                    array_map( [ $this, 'quote_identifier' ], $constraint['columns'] ?? [] )
                );
                return "PRIMARY KEY ({$cols})";

            case 'UNIQUE':
                $cols = implode(
                    ', ',
                    array_map( [ $this, 'quote_identifier' ], $constraint['columns'] ?? [] )
                );
                return "UNIQUE ({$cols})";

            case 'FOREIGN KEY':
                $col = $this->quote_identifier( $constraint['column'] ?? '' );
                $ref_table = $this->quote_identifier( $constraint['ref_table'] ?? '' );
                $ref_col = $this->quote_identifier( $constraint['ref_column'] ?? '' );

                $fk = "FOREIGN KEY ({$col}) REFERENCES {$ref_table} ({$ref_col})";

                if ( ! empty( $constraint['on_delete'] ) ) {
                    $fk .= ' ON DELETE ' . $constraint['on_delete'];
                }

                if ( ! empty( $constraint['on_update'] ) ) {
                    $fk .= ' ON UPDATE ' . $constraint['on_update'];
                }

                return $fk;

            default:
                throw new \Exception( "Unknown constraint type: {$type}" );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function render_alter_table( string $table, array $intent ) : string {

        $table = $this->quote_identifier( $table );

        $operations = $intent['operations'] ?? [];

        if ( ! is_array( $operations ) || empty( $operations ) ) {
            throw new \Exception( 'ALTER TABLE requires operations' );
        }

        if ( count( $operations ) !== 1 ) {
            throw new \Exception(
                'SQLite ALTER TABLE supports one operation per statement.'
            );
        }

        // Normalize first element safely (no assumptions about index type)
        $operation = array_values( $operations )[0];

        return 'ALTER TABLE ' . $table . ' ' . $this->render_alter_operation(
            $table,
            $operation
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function render_alter_operation( string $table, array $operation ) : string|array {

        $op_type = $operation['op'] ?? '';

        return match ( $op_type ) {

            'ADD COLUMN' => $this->render_add_column_operation( $operation ),

            'RENAME TABLE' => 'RENAME TO ' .
                $this->quote_identifier( $operation['new_name'] ?? '' ),

            'DROP COLUMN' => throw new \Exception(
                'SQLite does not support DROP COLUMN safely in this migration layer. Use table recreation strategy.'
            ),

            'RENAME COLUMN' => throw new \Exception(
                'SQLite RENAME COLUMN requires SQLite 3.25+. Use table recreation strategy or version-aware migration.'
            ),

            'MODIFY COLUMN' => throw new \Exception(
                'SQLite does not support MODIFY COLUMN. Use table recreation strategy.'
            ),

            'ADD INDEX' => throw new \Exception(
                'SQLite indexes must be created using standalone CREATE INDEX statements.'
            ),

            'DROP INDEX' => throw new \Exception(
                'SQLite indexes must be dropped using standalone DROP INDEX statements.'
            ),

            default => throw new \Exception(
                "Unknown ALTER operation: {$op_type}"
            )
        };
    }

    /**
     * Render ADD COLUMN operation.
     *
     * @param array $operation
     *
     * @return string
     */
    private function render_add_column_operation( array $operation ) : string {

        $sql =
            $this->quote_identifier( $operation['name'] ?? '' ) . ' ' .
            $this->normalize_type( $operation['type'] ?? '' );

        if ( ! empty( $operation['definition'] ) ) {
            $sql .= ' ' . $operation['definition'];
        }

        return 'ADD COLUMN ' . $sql;
    }
}