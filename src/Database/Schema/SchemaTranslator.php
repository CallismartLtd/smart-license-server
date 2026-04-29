<?php
/**
 * SchemaTranslator
 *
 * Bridges DatabaseSchemaInterface metadata into SQLBuilder intent.
 * Pure intent mapping layer (NO SQL syntax, NO engine rules).
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Schema;

use SmartLicenseServer\Database\Query\SQLBuilder;

defined( 'SMLISER_ABSPATH' ) || exit;

class SchemaTranslator {

    /**
     * Translate schema metadata into CREATE TABLE intent.
     */
    public function create_table(
        DatabaseSchemaInterface $schema,
        SQLBuilder $builder
    ) : SQLBuilder {

        $builder->create_table( $schema::get_table_name() );

        $this->add_columns(
            $builder,
            $schema::get_columns()
        );

        $this->add_constraints(
            $builder,
            $schema::get_constraints()
        );

        $this->apply_options(
            $builder,
            $schema::get_options()
        );

        return $builder;
    }

    /**
     * Add schema columns (INTENT ONLY).
     */
    private function add_columns(
        SQLBuilder $builder,
        array $columns
    ) : void {

        foreach ( $columns as $column ) {

            $builder->column(
                $column['name'],
                $column['type'],
                [
                    'length'         => $column['length'] ?? null,
                    'precision'      => $column['precision'] ?? null,
                    'scale'          => $column['scale'] ?? null,
                    'unsigned'       => $column['unsigned'] ?? false,
                    'nullable'       => $column['nullable'] ?? true,
                    'auto_increment' => $column['auto_increment'] ?? false,
                    'default'        => $column['default'] ?? null,
                    'comment'        => $column['comment'] ?? null,
                ]
            );
        }
    }

    /**
     * Add schema constraints (INTENT ONLY).
     */
    private function add_constraints(
        SQLBuilder $builder,
        array $constraints
    ) : void {

        foreach ( $constraints as $constraint ) {

            $type = strtolower( $constraint['type'] );

            switch ( $type ) {

                case 'primary':
                    $builder->primary_key(
                        $constraint['columns'] ?? []
                    );
                    break;

                case 'unique':
                    $builder->unique(
                        $constraint['name'] ?? '',
                        $constraint['columns'] ?? []
                    );
                    break;

                case 'foreign':
                    $this->add_foreign_key($builder, $constraint);
                    break;

                case 'index':
                    $builder->index(
                        $constraint['name'] ?? '',
                        $constraint['columns'] ?? []
                    );
                    break;

                case 'fulltext':
                    $builder->add_index(
                        $constraint['name'] ?? '',
                        $constraint['columns'] ?? [],
                        'FULLTEXT'
                    );
                    break;
            }
        }
    }

    /**
     * Foreign key (INTENT ONLY).
     */
    private function add_foreign_key(
        SQLBuilder $builder,
        array $constraint
    ) : void {

        $local_columns = $constraint['columns'] ?? [];
        $ref_columns   = $constraint['references_columns'] ?? [];

        if ( empty( $local_columns ) || empty( $ref_columns ) ) {
            return;
        }

        $builder->foreign_key(
            $local_columns[0],
            $constraint['references_table'],
            $ref_columns[0],
            $constraint['on_delete'] ?? '',
            $constraint['on_update'] ?? ''
        );
    }

    /**
     * Apply schema options (INTENT ONLY).
     */
    private function apply_options(
        SQLBuilder $builder,
        array $options
    ) : void {

        if ( ! empty( $options['engine'] ) ) {
            $builder->engine( $options['engine'] );
        }

        if ( ! empty( $options['charset'] ) ) {
            $builder->charset(
                $options['charset'],
                $options['collation'] ?? ''
            );
        }
    }

    /**
     * Column definition builder (PURE INTENT STRING FRAGMENT).
     *
     * IMPORTANT:
     * - NO AUTO_INCREMENT handling
     * - NO engine-specific keywords
     * - ONLY neutral structural intent
     */
    private function column_definition( array $column ) : string {

        $parts = [];

        // Length / precision
        $length = $this->length_segment( $column );
        if ( $length ) {
            $parts[] = $length;
        }

        // UNSIGNED (kept as neutral intent flag)
        if ( ! empty( $column['unsigned'] ) ) {
            $parts[] = 'UNSIGNED';
        }

        // NULLABILITY (neutral)
        if ( isset( $column['nullable'] ) && $column['nullable'] === false ) {
            $parts[] = 'NOT NULL';
        }

        // DEFAULT (neutral literal expression, renderer decides final correctness)
        if ( array_key_exists( 'default', $column ) ) {
            $parts[] = 'DEFAULT ' . $this->normalize_default(
                $column['default']
            );
        }

        return implode( ' ', $parts );
    }

    /**
     * Length / precision segment (neutral).
     */
    private function length_segment(
        array $column
    ) : string {

        if ( isset( $column['length'] ) ) {
            return '(' . $column['length'] . ')';
        }

        if (
            isset( $column['precision'] ) &&
            isset( $column['scale'] )
        ) {
            return '(' . $column['precision'] . ',' . $column['scale'] . ')';
        }

        return '';
    }

    /**
     * Normalize default value (neutral literal formatting).
     *
     * NOTE:
     * - No engine-specific SQL assumptions
     * - Only literal normalization
     */
    private function normalize_default( $value ) : string {

        if ( null === $value ) {
            return 'NULL';
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }

        $upper = strtoupper( (string) $value );

        if ( in_array( $upper, [ 'CURRENT_TIMESTAMP', 'NULL' ], true ) ) {
            return $upper;
        }

        return "'" . str_replace( "'", "''", (string) $value ) . "'";
    }
}