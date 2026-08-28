<?php
/**
 * Database related utility functions.
 */

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Query\SQLBuilder;

/**
 * Get the query builder instance.
 * 
 * @param string $driver The DB driver.
 * @return SQLBuilder Instance of the SQLBuilder class.
 */
function smliserQueryBuilder( string $driver ) : SQLBuilder{
    return new SQLBuilder( $driver );
}

/**
 * Get the database schema inpection instance
 */
function smliserDBSchemaInspection( Database $db ) : Inspector {
    return new Inspector( $db );
}