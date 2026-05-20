<?php
/**
 * Database related utility functions.
 */

use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Query\CompositeSQLBuilder;
use Callismart\DBPrism\Query\SQLBuilder;

/**
 * Get the DBAL instance.
 *
 * @return \Callismart\DBPrism\Database Singleton instance of the Database class.
 */
function smliser_db() : \Callismart\DBPrism\Database {
    return smliser_envProvider()->database();
}

/**
 * Get the query builder instance.
 * 
 * @return SQLBuilder Instance of the SQLBuilder class.
 */
function smliserQueryBuilder() : SQLBuilder{
    return new SQLBuilder( smliser_db()->get_driver() );
}

/**
 * Get the composite query builder instance.
 */
function smliserCompositeQueryBuilder() : CompositeSQLBuilder {
    return new CompositeSQLBuilder( smliser_db()->get_driver() );
}

/**
 * Get the database table prefix.
 * 
 * @return string The database table prefix.
 */
function smliser_db_prefix() : string {
    return smliser_envProvider()->db_prefix();
}

/**
 * Get the database schema inpection instance
 */
function smliserDBSchemaInspection() : Inspector {
    return new Inspector( smliser_db() );
}