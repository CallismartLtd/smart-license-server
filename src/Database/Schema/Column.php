<?php
/**
 * Database Column class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Schema
 * @since 0.2.0
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a single column in a database table schema.
 * 
 * Provides a fluent API for defining portable column metadata.
 * 
 * @since 0.2.0
 */
class Column {
    /** 
     * @var string $name Column name. 
     */
    public string $name;

    /** 
     * @var string $type Logical data type.
     */
    public string $type;

    /** 
     * @var int|null $length Maximum length for strings/integers. 
     */
    public ?int $length = null;

    /** 
     * @var int|null $precision Total number of digits in decimals. 
     */
    public ?int $precision = null;

    /** 
     * @var int|null $scale Number of digits after decimal point.
     */
    public ?int $scale = null;

    /** 
     * @var bool $unsigned Whether the numeric type is unsigned. 
     */
    public bool $unsigned = false;

    /** 
     * @var bool $nullable Whether the column accepts NULL values.
     */
    public bool $nullable = true;

    /** 
     * @var bool $auto_increment Whether the column increments automatically.
     */
    public bool $auto_increment = false;

    /** 
     * @var mixed $default Default value for the column. 
     */
    public mixed $default = null;

    /** 
     * @var string $comment Column description/comment. 
     */
    public string $comment = '';

    /**
     * Constructor.
     * 
     * @param string $name Column name.
     */
    public function __construct( string $name ) {
        $this->name = $name;
    }

    /**
     * Static factory to start the fluent chain.
     * 
     * @param string $name Column name.
     * @return static
     */
    public static function make( string $name ) : static {
        return new self( $name );
    }

    /**
     * Set the logical type.
     * 
     * @param string $type
     * @return static
     */
    public function type( string $type ) : static {
        $this->type = $type;
        return $this;
    }

    /**
     * Set length or precision/scale.
     * 
     * @param int      $length    Or precision.
     * @param int|null $scale
     * @return static
     */
    public function size( int $length, ?int $scale = null ) : static {
        if ( null === $scale ) {
            $this->length = $length;
        } else {
            $this->precision = $length;
            $this->scale     = $scale;
        }
        return $this;
    }

    /**
     * Mark column as unsigned.
     * 
     * @return static
     */
    public function unsigned() : static {
        $this->unsigned = true;
        return $this;
    }

    /**
     * Mark column as NOT NULL.
     * 
     * @return static
     */
    public function required() : static {
        $this->nullable = false;
        return $this;
    }

    /**
     * Mark column as auto-incrementing.
     * 
     * @return static
     */
    public function auto_increment() : static {
        $this->auto_increment = true;
        return $this;
    }

    /**
     * Set the default value.
     * 
     * @param mixed $value
     * @return static
     */
    public function default( mixed $value ) : static {
        $this->default = $value;
        return $this;
    }

    /**
     * Set the column comment.
     * 
     * @param string $comment
     * @return static
     */
    public function comment( string $comment ) : static {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Export the column definition to a portable array.
     * 
     * Matches the format expected by DatabaseSchemaInterface::get_columns().
     * 
     * @return array{
     *     name: string,
     *     type: string,
     *     length?: int|null,
     *     precision?: int|null,
     *     scale?: int|null,
     *     unsigned?: bool,
     *     nullable?: bool,
     *     auto_increment?: bool,
     *     default?: mixed,
     *     comment?: string
     * }
     */
    public function to_array() : array {
        return array_filter( [
            'name'           => $this->name,
            'type'           => $this->type,
            'length'         => $this->length,
            'precision'      => $this->precision,
            'scale'          => $this->scale,
            'unsigned'       => $this->unsigned,
            'nullable'       => $this->nullable,
            'auto_increment' => $this->auto_increment,
            'default'        => $this->default,
            'comment'        => $this->comment,
        ], fn( $value ) => null !== $value );
    }
}