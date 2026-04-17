<?php
namespace SmartLicenseServer\Database\Adapters;

use PDO;

/**
 * Postgres Adapter extending the generic PDO Adapter.
 */
class PostgresAdapter extends PdoAdapter {
    use PostgresCompatibilityTrait;

    /**
     * Override connect to build the Postgres-specific DSN.
     */
    public function connect() {
        if ($this->pdo) return true;

        $dsn = sprintf(
            "pgsql:host=%s;port=%d;dbname=%s",
            $this->config->host,
            $this->config->port ?? 5432,
            $this->config->database
        );

        try {
            $this->pdo = new PDO($dsn, $this->config->username, $this->config->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            return true;
        } catch (\PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /**
     * Override query to intercept and translate SQL.
     */
    public function query($query, array $params = []) {
        $query = $this->translate_mysql_to_postgres($query);
        return parent::query($query, $params);
    }

    public function get_engine_type() {
        return 'pgsql';
    }
}