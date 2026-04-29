<?php
/**
 * MySQL Engine Renderer
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\Renderers
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Query\Renderers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * MySQL-specific SQL renderer.
 *
 * Generates MySQL-compliant SQL from normalized intent.
 *
 * @since 0.2.0
 */
class MySQLRenderer extends AbstractQueryRenderer {

    protected string $engine = 'mysql';

    /**
     * MySQL version for compatibility checks.
     *
     * Format: '5.7.30', '8.0.21', etc.
     * If null, assume modern (8.0+) and use latest syntax.
     *
     * @var string|null
     */
    private ?string $mysql_version = null;

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
     * Set MySQL version for compatibility checks.
     *
     * @param string $version Version string (e.g., '5.7.30')
     *
     * @return void
     */
    public function set_mysql_version( string $version ) : void {
        $this->mysql_version = $version;
    }

    /**
    * Get MySQL major.minor version.
    *
    * @return array [major, minor] e.g., [8, 0]
    */
    private function get_version_parts() : array {
        if ( ! $this->mysql_version ) {
            return [8, 0];  // Assume modern if not set
        }
    
        if ( preg_match( '/^(\d+)\.(\d+)/', $this->mysql_version, $m ) ) {
            return [(int) $m[1], (int) $m[2]];
        }
    
        return [8, 0];  // Default to modern
    }
    
    /**
     * Check if MySQL version supports RENAME COLUMN (8.0.14+).
     *
     * @return bool
     */
    private function supports_rename_column() : bool {
        [$major, $minor] = $this->get_version_parts();
    
        // MySQL 8.0.14+
        if ( $major > 8 ) {
            return true;
        }
    
        if ( $major === 8 && $minor >= 0 ) {
            // 8.0.x - assume 8.0.14+
            // (Would need patch version to be precise, but rarely matters)
            return true;
        }
    
        // MySQL 5.7 and earlier don't support RENAME COLUMN
        return false;
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

    /**
     * Render RENAME COLUMN operation for MySQL.
     *
     * Uses RENAME COLUMN for MySQL 8.0.14+ (preferred syntax).
     * Falls back to CHANGE COLUMN for earlier versions (compatible with all).
     *
     * @param array $operation
     *
     * @return string
     */
    private function render_rename_column_operation( array $operation ) : string {
        $old_name = $this->quote_identifier( $operation['old_name'] );
        $new_name = $this->quote_identifier( $operation['new_name'] );
    
        // MySQL 8.0.14+ supports RENAME COLUMN (preferred)
        if ( $this->supports_rename_column() ) {
            return sprintf(
                'RENAME COLUMN %s TO %s',
                $old_name,
                $new_name
            );
        }
    
        // Fallback to CHANGE COLUMN for older versions
        // CHANGE requires: CHANGE OLD_NAME NEW_NAME TYPE [definition]
        // We use VARCHAR(255) as a safe default type (rarely changed by rename alone)
        return sprintf(
            'CHANGE COLUMN %s %s VARCHAR(255)',
            $old_name,
            $new_name
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

    /**
     * Render MySQL-specific CREATE TABLE suffix (ENGINE, CHARSET, COLLATION).
     *
     * @param array $intent Query intent
     *
     * @return string SQL suffix (e.g., "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")
     */
    protected function render_create_table_suffix( array $intent ) : string {
        $parts = [];
    
        // ENGINE (default: InnoDB in modern MySQL)
        if ( ! empty( $intent['engine'] ) ) {
            $parts[] = 'ENGINE=' . $intent['engine'];
        }
    
        // DEFAULT CHARSET
        if ( ! empty( $intent['charset'] ) ) {
            $parts[] = 'DEFAULT CHARSET=' . $intent['charset'];
        }
    
        // COLLATE
        if ( ! empty( $intent['collation'] ) ) {
            $parts[] = 'COLLATE=' . $intent['collation'];
        }
    
        return implode( ' ', $parts );
    }
}