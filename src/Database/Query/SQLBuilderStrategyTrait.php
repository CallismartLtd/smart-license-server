<?php
/**
 * SQL Builder strategy trait file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Database\Query;

trait SQLBuilderStrategyTrait {
    /**
     * The SQL builder instance.
     * 
     * @var SQLBuilder $builder
     */
    private SQLBuilder $builder;
    
    /**
     * Build query.
     * 
     * @return string
     */
    public function build() : string {
        return $this->builder->build();
    }

    /**
     * Build the raw sql with the parameters.
     * * @return string
     */
    public function build_raw(): string {
        $sql      = $this->build();
        $bindings = $this->get_bindings();

        foreach ( $bindings as $value ) {
            $escapedValue = is_string( $value ) ? "'" . addslashes( $value ) . "'" : (string) $value;
            
            $pos = strpos( $sql, '?' );
            if ($pos !== false) {
                $sql = substr_replace( $sql, $escapedValue, $pos, 1 );
            }
        }

        return $sql;
    }
}