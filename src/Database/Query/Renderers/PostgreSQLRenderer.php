<?php
/**
 * PostgreSQL Engine Renderer
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\Renderers
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query\Renderers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * PostgreSQL-specific SQL renderer.
 *
 * Generates PostgreSQL-compliant SQL from normalized intent.
 * Handles PostgreSQL-specific ALTER COLUMN syntax.
 *
 * @since 0.2.0
 */
class PostgreSQLRenderer extends AbstractQueryRenderer {

    protected string $engine = 'pgsql';

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
            'BIGINT'   => 'BIGINT',
            'INT'      => 'INTEGER',
            'VARCHAR'  => 'VARCHAR',
            'TEXT'     => 'TEXT',
            'DATETIME' => 'TIMESTAMP',
            'BOOLEAN'  => 'BOOLEAN',
            'JSON'     => 'JSONB',
            default    => $type
        };
    }

    /**
     * Render constraint for PostgreSQL.
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
                return 'PRIMARY KEY (' . $cols . ')';

            case 'UNIQUE':
                $cols = implode( ', ', array_map( [ $this, 'quote_identifier' ], $constraint['columns'] ) );
                return 'CONSTRAINT ' . $this->quote_identifier( $constraint['name'] ) .
                    ' UNIQUE (' . $cols . ')';

            case 'FOREIGN KEY':
                $col       = $this->quote_identifier( $constraint['column'] );
                $ref_table = $this->quote_identifier( $constraint['ref_table'] );
                $ref_col   = $this->quote_identifier( $constraint['ref_column'] );

                $fk = 'FOREIGN KEY (' . $col . ') REFERENCES ' . $ref_table . ' (' . $ref_col . ')';

                if ( ! empty( $constraint['on_delete'] ) ) {
                    $fk .= ' ON DELETE ' . $constraint['on_delete'];
                }

                if ( ! empty( $constraint['on_update'] ) ) {
                    $fk .= ' ON UPDATE ' . $constraint['on_update'];
                }

                return $fk;

            default:
                throw new \Exception( 'Unknown constraint type: ' . $type );
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
     * Render a single ALTER TABLE operation for PostgreSQL.
     *
     * @param string $table
     * @param array  $operation
     *
     * @return string|array
     */
    protected function render_alter_operation( string $table, array $operation ) : string|array {
        $op = $operation['op'];

        switch ( $op ) {

            case 'ADD COLUMN':
                $column = $this->quote_identifier( $operation['name'] );
                $type   = $this->normalize_type( $operation['type'] );

                $sql = 'ALTER TABLE ' . $table .
                    ' ADD COLUMN ' . $column . ' ' . $type;

                if ( ! empty( $operation['definition'] ) ) {
                    $sql .= ' ' . $operation['definition'];
                }

                return $sql;

            case 'DROP COLUMN':
                return 'ALTER TABLE ' . $table .
                    ' DROP COLUMN ' . $this->quote_identifier( $operation['name'] );

            case 'RENAME COLUMN':
                return 'ALTER TABLE ' . $table .
                    ' RENAME COLUMN ' . $this->quote_identifier( $operation['old_name'] ) .
                    ' TO ' . $this->quote_identifier( $operation['new_name'] );

            case 'MODIFY COLUMN':
                return $this->render_modify_column( $table, $operation );

            case 'RENAME TABLE':
                return 'ALTER TABLE ' . $table .
                    ' RENAME TO ' . $this->quote_identifier( $operation['new_name'] );

            case 'ADD INDEX':
                return $this->render_create_index( $table, $operation );

            case 'DROP INDEX':
                return 'DROP INDEX IF EXISTS ' . $this->quote_identifier( $operation['name'] );

            default:
                throw new \Exception( 'Unknown ALTER operation: ' . $op );
        }
    }

    /**
     * Modify column (PostgreSQL multi-step safe form)
     *
     * @return string|array
     */
    private function render_modify_column( string $table, array $operation ) : string|array {
        $column = $this->quote_identifier( $operation['name'] );
        $type   = $this->normalize_type( $operation['type'] );
        $def    = $operation['definition'] ?? '';

        $sql = [];

        $sql[] = 'ALTER TABLE ' . $table .
            ' ALTER COLUMN ' . $column .
            ' TYPE ' . $type;

        if ( stripos( $def, 'NOT NULL' ) !== false ) {
            $sql[] = 'ALTER TABLE ' . $table .
                ' ALTER COLUMN ' . $column . ' SET NOT NULL';

        } elseif ( preg_match( '/\bNULL\b/i', $def ) ) {
            $sql[] = 'ALTER TABLE ' . $table .
                ' ALTER COLUMN ' . $column . ' DROP NOT NULL';
        }

        if ( preg_match( '/DEFAULT\s+(.+)/i', $def, $m ) ) {
            $sql[] = 'ALTER TABLE ' . $table .
                ' ALTER COLUMN ' . $column .
                ' SET DEFAULT ' . $m[1];
        }

        return $sql;
    }

    /**
     * Create index (PostgreSQL)
     */
    private function render_create_index( string $table, array $operation ) : string {
        $cols = implode(
            ', ',
            array_map( [ $this, 'quote_identifier' ], $operation['columns'] )
        );

        $type = ! empty( $operation['type'] )
            ? strtoupper( $operation['type'] ) . ' '
            : '';

        return 'CREATE ' . $type . 'INDEX ' .
            $this->quote_identifier( $operation['name'] ) .
            ' ON ' . $table . ' (' . $cols . ')';
    }
}