<?php
namespace SmartLicenseServer\Database\Adapters;

use PDO;
use PDOStatement;
use SmartLicenseServer\Database\PostgresCompatibilityTrait;

/**
 * Postgres Adapter extending the generic PDO Adapter.
 */
class PostgresAdapter extends PdoAdapter {
    use PostgresCompatibilityTrait;

    /**
     * Override query to intercept and translate SQL.
     */
    public function query( $query, array $params = [] ) : PDOStatement|false  {
        $query  = $this->translate_mysql_to_postgres( $query );
        return parent::query( $query, $params );
    }

    public function get_engine_type() : string {
        return 'pgsql';
    }
}