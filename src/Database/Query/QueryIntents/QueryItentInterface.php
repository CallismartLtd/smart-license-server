<?php
/**
 * Query intent constract file
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Database\Query\QueryIntents;

interface QueryItentInterface {
    /**
     * Reconstruct a new self using existing factory methods
     */
    public function new_instance() : static;

    /**
     * Build query.
     * 
     * @return string
     */
    public function build() : string;

    /**
     * Build the raw sql with the parameters.
     *
     * @return string
     */
    public function build_raw(): string;
}