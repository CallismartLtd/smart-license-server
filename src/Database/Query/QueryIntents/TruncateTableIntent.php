<?php
/**
 * TruncateTable Query Intent class file.
 * * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Query\QueryIntents
 * @since 0.2.0
 * */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Query\QueryIntents;

use SmartLicenseServer\Database\Query\SQLBuilder;
use SmartLicenseServer\Database\Query\SQLBuilderStrategyTrait;

/**
 * Represents an intent to wipe all data from one or more database tables.
 * * Collects behaviors regarding identity sequences and relational cascades.
 * * @since 0.2.0
 */
class TruncateTableIntent {
    use SQLBuilderStrategyTrait;

    /**
     * @var array $tables The name of the table or tables to be truncated.
     */
    private array $tables = [];

    /**
     * @var bool $restart_identity Whether to reset auto-increment/sequence counters.
     */
    private bool $restart_identity = true;

    /**
     * @var bool $cascade Whether to automatically truncate dependent tables.
     */
    private bool $cascade = false;

    /**
     * Constructor.
     * 
     * @param string ...$tables One or more tables to truncate.
     */
    private function __construct( string ...$tables ) {
        $this->tables = $tables;
    }

    /**
     * Instruct the engine to reset auto-increment/sequence counters.
     * 
     * @param bool $restart
     * @return static Fluent
     */
    public function restart_identity( bool $restart = true ) : static {
        $this->restart_identity = $restart;
        return $this;
    }

    /**
     * Instruct the engine to cascade the truncation to child/dependent foreign key tables.
     * 
     * @param bool $cascade
     * @return static Fluent
     */
    public function cascade( bool $cascade = false ) : static {
        $this->cascade = $cascade;
        return $this;
    }

    /**
     * Retrieve the list of tables targetted by this intent.
     * * @return array<string>
     */
    public function get_tables() : array {
        return $this->tables;
    }

    /**
     * Determine if identity/auto-increment sequences should be reset.
     * * @return bool
     */
    public function should_restart_identity() : bool {
        return $this->restart_identity;
    }

    /**
     * Determine if dependent tables should be truncated recursively.
     * * @return bool
     */
    public function should_cascade() : bool {
        return $this->cascade;
    }

    /**
     * Static factory.
     * * @param array      $tables   Array of table names.
     * @param SQLBuilder $builder  The active SQL builder.
     * @return static Fluent
     */
    public static function make( array $tables, SQLBuilder $builder ) : static {
        $static          = new static( ...$tables );
        $static->builder = $builder;

        return $static;
    }
}