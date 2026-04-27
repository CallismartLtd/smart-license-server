<?php
/**
 * MySQL Engine Renderer
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * MySQL-specific SQL renderer.
 *
 * Generates MySQL-compliant SQL from normalized intent.
 *
 * @since 0.2.0
 */
class MySQLRenderer extends EngineRenderer {

    protected string $engine = 'mysql';

    /**
     * {@inheritDoc}
     */
    protected function quote_identifier( string $identifier ) : string {
        return '`' . $identifier . '`';
    }

    /**
     * {@inheritDoc}
     */
    protected function normalize_type( string $type ) : string {
        return $type;
    }

    /**
     * Render constraint for CREATE TABLE.
     *
     * @param array $constraint Constraint intent
     *
     * @return string Constraint SQL
     */
    protected function render_constraint( array $constraint ) : string {
        $type = $constraint['type'];

        switch ( $type ) {
            case 'PRIMARY KEY':
                $cols = implode( ', ', array_map( [ $this, 'quote_identifier' ], $constraint['columns'] ) );
                return "PRIMARY KEY ({$cols})";

            case 'UNIQUE':
                $cols = implode( ', ', array_map( [ $this, 'quote_identifier' ], $constraint['columns'] ) );
                return "CONSTRAINT " . $this->quote_identifier( $constraint['name'] ) . " UNIQUE ({$cols})";

            case 'FOREIGN KEY':
                $col = $this->quote_identifier( $constraint['column'] );
                $ref_table = $this->quote_identifier( $constraint['ref_table'] );
                $ref_col = $this->quote_identifier( $constraint['ref_column'] );

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

        if ( empty( $intent['operations'] ) ) {
            throw new \Exception( 'ALTER TABLE requires operations' );
        }

        $statements = [];

        foreach ( $intent['operations'] as $operation ) {
            $rendered = $this->render_alter_operation( $table, $operation );

            if ( is_array( $rendered ) ) {
                $statements = array_merge( $statements, $rendered );
            } else {
                $statements[] = $rendered;
            }
        }

        return implode( '; ', $statements );
    }

    /**
     * {@inheritDoc}
     */
    protected function render_alter_operation( string $table, array $operation ) : string|array {

        $type = $operation['op'] ?? '';

        return match ( $type ) {

            'ADD COLUMN' => $this->render_add_column_operation( $operation ),

            'DROP COLUMN' => 'DROP COLUMN ' .
                $this->quote_identifier( $operation['name'] ),

            'RENAME COLUMN' => $this->render_rename_column_operation( $operation ),

            'MODIFY COLUMN' => $this->render_modify_column_operation( $operation ),

            'RENAME TABLE' => 'RENAME TO ' .
                $this->quote_identifier( $operation['new_name'] ),

            'ADD INDEX' => $this->render_add_index_operation( $operation ),

            'DROP INDEX' => 'DROP INDEX ' .
                $this->quote_identifier( $operation['name'] ),

            default => throw new \Exception(
                "Unknown ALTER operation: {$type}"
            )
        };
    }

    private function render_add_column_operation( array $operation ) : string {

        $sql =
            $this->quote_identifier( $operation['name'] ) . ' ' .
            $this->normalize_type( $operation['type'] );

        if ( ! empty( $operation['definition'] ) ) {
            $sql .= ' ' . $operation['definition'];
        }

        if ( ! empty( $operation['position'] ) ) {
            $sql .= ' ' . $this->validate_position_clause( $operation['position'] );
        }

        return 'ADD COLUMN ' . $sql;
    }

    private function render_modify_column_operation( array $operation ) : string {

        $sql =
            $this->quote_identifier( $operation['name'] ) . ' ' .
            $this->normalize_type( $operation['type'] );

        if ( ! empty( $operation['definition'] ) ) {
            $sql .= ' ' . $operation['definition'];
        }

        return 'MODIFY COLUMN ' . $sql;
    }

    private function render_rename_column_operation( array $operation ) : string {

        return sprintf(
            'RENAME COLUMN %s TO %s',
            $this->quote_identifier( $operation['old_name'] ),
            $this->quote_identifier( $operation['new_name'] )
        );
    }

    private function render_add_index_operation( array $operation ) : string {

        $allowed = [ '', 'UNIQUE', 'FULLTEXT', 'SPATIAL' ];

        $type = strtoupper( trim( $operation['type'] ?? '' ) );

        if ( ! in_array( $type, $allowed, true ) ) {
            throw new \Exception(
                "Unsupported MySQL index type: {$type}"
            );
        }

        $columns = implode(
            ', ',
            array_map( [ $this, 'quote_identifier' ], $operation['columns'] )
        );

        $prefix = $type ? $type . ' ' : '';

        return sprintf(
            'ADD %sINDEX %s (%s)',
            $prefix,
            $this->quote_identifier( $operation['name'] ),
            $columns
        );
    }

    private function validate_position_clause( string $position ) : string {

        $position = trim( $position );

        if ( strtoupper( $position ) === 'FIRST' ) {
            return 'FIRST';
        }

        if ( preg_match( '/^AFTER\s+[a-zA-Z0-9_]+$/i', $position ) ) {
            return $position;
        }

        throw new \Exception(
            "Invalid MySQL column position clause: {$position}"
        );
    }
}