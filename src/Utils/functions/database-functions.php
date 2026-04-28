<?php
/**
 * Database related utility functions.
 */

use SmartLicenseServer\Database\Query\CompositeSQLBuilder;
use SmartLicenseServer\Database\Query\SQLBuilder;

/**
 * Get the database API instance.
 *
 * @return \SmartLicenseServer\Database\Database Singleton instance of the Database class.
 */
function smliser_db() : \SmartLicenseServer\Database\Database {
    return smliser_envProvider()->database();
}

/**
 * Get the query builder instance.
 * 
 * @return SQLBuilder Instance of the SQLBuilder class.
 */
function smliserQueryBuilder() : SQLBuilder{
    return new SQLBuilder( smliser_db()->get_engine_type() );
}

/**
 * Get the composite query builder instance.
 */
function smliserCompositeQueryBuilder() : CompositeSQLBuilder {
    return new CompositeSQLBuilder( smliser_db()->get_engine_type() );
}