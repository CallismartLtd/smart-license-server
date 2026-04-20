<?php
namespace SmartLicenseServer\Database;

trait PostgresCompatibilityTrait {
    protected function translate_mysql_to_postgres(string $sql): string {
        // 1. Identifiers (Backticks to Double Quotes)
        $sql = str_replace('`', '"', $sql);

        // 2. Date Math: DATE_SUB(NOW(), INTERVAL ? DAY) 
        // -> NOW() - ( ? || ' days')::interval
        $sql = preg_replace_callback(
            '/DATE_SUB\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+([\?\w\d]+)\s+DAY\s*\)/i',
            fn($m) => "NOW() - ( {$m[1]} || ' days')::interval",
            $sql
        );

        // 3. DATE(created_at) -> created_at::date
        $sql = preg_replace('/\bDATE\s*\(\s*([\w"\.]+)\s*\)/i', '$1::date', $sql);

        // 4. IFNULL -> COALESCE
        $sql = str_ireplace('IFNULL(', 'COALESCE(', $sql);

        return $sql;
    }
}