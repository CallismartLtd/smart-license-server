<?php
namespace SmartLicenseServer\Database\Adapters;

use PDO;
use SmartLicenseServer\Database\PostgresCompatibilityTrait;

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
    public function query( $query, array $params = [] ) {
        $query = $this->translate_mysql_to_postgres( $query );
        return parent::query( $query, $params );
    }

    public function get_engine_type() {
        return 'pgsql';
    }

    /**
     * Check if a table exists.
     */
    public function table_exists( string $table ): bool {
        if ( ! $this->pdo ) return false;

        $query = "
            SELECT 1 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = ?
        ";

        return null !== $this->get_var( $query, [$table] );
    }

    /**
     * Check if a column exists.
     */
    public function column_exists(string $table, string $column): bool {
        if ( ! $this->pdo ) return false;

        $query = "
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = ? 
            AND column_name = ?
        ";

        return null !== $this->get_var( $query, [$table, $column] );
    }

    /**
     * Get column type.
     */
    public function get_column_type(string $table, string $column): ?string {
        if ( ! $this->pdo ) return null;

        $query = "
            SELECT data_type 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = ? 
            AND column_name = ?
        ";

        return $this->get_var( $query, [$table, $column] );
    }

    /**
     * Get all columns in a table.
     */
    public function get_columns(string $table): array {
        if ( ! $this->pdo ) return [];

        $query = "
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
            AND table_name = ?
            ORDER BY ordinal_position
        ";

        return $this->get_col( $query, [$table] ) ?? [];
    }

    /**
     * Check connection state.
     */
    public function is_connected(): bool {
        return $this->pdo instanceof \PDO;
    }
}