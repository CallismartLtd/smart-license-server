<?php
/**
 * Database Constraint class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a table constraint or index.
 * 
 * Provides a fluent API for defining portable database constraints.
 * 
 * @since 0.2.0
 */
class Constraint {
    /** 
     * @var string $type Constraint type (primary, unique, index, foreign, fulltext).
     */
    public string $type;

    /** 
     * @var string|null $name Optional constraint/index name.
     */
    public ?string $name = null;

    /** 
     * @var array<int, string> $columns Targeted column names.
     */
    public array $columns = [];

    /** 
     * @var string|null $references_table Foreign key referenced table.
     */
    public ?string $references_table = null;

    /** 
     * @var array<int, string> $references_columns Foreign key referenced columns.
     */
    public array $references_columns = [];

    /** 
     * @var string|null $on_delete Referential action on delete.
     */
    public ?string $on_delete = null;

    /** 
     * @var string|null $on_update Referential action on update.
     */
    public ?string $on_update = null;

    /**
     * Constructor.
     * 
     * @param string $type Constraint type.
     */
    public function __construct( string $type ) {
        $this->type = $type;
    }

    /**
     * Static factory to start the fluent chain.
     * 
     * @param string $type Constraint type.
     * @return static
     */
    public static function make( string $type ) : static {
        return new static( $type );
    }

    /**
     * Set the constraint/index name.
     * 
     * @param string $name
     * @return static
     */
    public function name( string $name ) : static {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the columns involved in the constraint.
     * 
     * @param string ...$columns
     * @return static
     */
    public function on( string ...$columns ) : static {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Define foreign key relationship.
     * 
     * @param string $table
     * @param string ...$columns
     * @return static
     */
    public function references( string $table, string ...$columns ) : static {
        $this->references_table   = $table;
        $this->references_columns = $columns;
        return $this;
    }

    /**
     * Set ON DELETE referential action.
     * 
     * @param string $action CASCADE, SET NULL, RESTRICT, etc.
     * @return static
     */
    public function on_delete( string $action ) : static {
        $this->on_delete = $action;
        return $this;
    }

    /**
     * Set ON UPDATE referential action.
     * 
     * @param string $action
     * @return static
     */
    public function on_update( string $action ) : static {
        $this->on_update = $action;
        return $this;
    }

    /**
     * Export the constraint to a portable array.
     * 
     * @return array{
     *     type: string,
     *     name?: string,
     *     columns?: array<int, string>,
     *     references_table?: string,
     *     references_columns?: array<int, string>,
     *     on_delete?: string,
     *     on_update?: string
     * }
     */
    public function to_array() : array {
        return array_filter( [
            'type'               => $this->type,
            'name'               => $this->name,
            'columns'            => $this->columns,
            'references_table'   => $this->references_table,
            'references_columns' => $this->references_columns,
            'on_delete'          => $this->on_delete,
            'on_update'          => $this->on_update,
        ], fn( $value ) => ( null !== $value && [] !== $value ) );
    }
}