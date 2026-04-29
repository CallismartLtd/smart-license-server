<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class LicenseSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_LICENSE_TABLE;
    }

    public static function get_columns() : array {
        return [
            [
                'name' => 'id',
                'type' => 'bigint',
                'unsigned' => true,
                'auto_increment' => true,
                'nullable' => false,
            ],
            [
                'name' => 'licensee_fullname',
                'type' => 'varchar',
                'length' => 512,
                'nullable' => true,
            ],
            [
                'name' => 'license_key',
                'type' => 'varchar',
                'length' => 300,
                'nullable' => false,
            ],
            [
                'name' => 'service_id',
                'type' => 'varchar',
                'length' => 300,
                'nullable' => false,
            ],
            [
                'name' => 'app_prop',
                'type' => 'varchar',
                'length' => 600,
                'nullable' => true,
            ],
            [
                'name' => 'max_allowed_domains',
                'type' => 'mediumint',
                'nullable' => true,
            ],
            [
                'name' => 'status',
                'type' => 'varchar',
                'length' => 30,
                'nullable' => true,
            ],
            [
                'name' => 'start_date',
                'type' => 'datetime',
                'nullable' => true,
            ],
            [
                'name' => 'end_date',
                'type' => 'datetime',
                'nullable' => true,
            ],
            [
                'name' => 'created_at',
                'type' => 'datetime',
                'nullable' => true,
            ],
            [
                'name' => 'updated_at',
                'type' => 'datetime',
                'nullable' => false,
            ],
        ];
    }

    public static function get_constraints() : array {
        return [
            [
                'type' => 'primary',
                'columns' => ['id'],
            ],
            [
                'type' => 'unique',
                'columns' => ['license_key'],
            ],
            [
                'type' => 'index',
                'name' => 'service_id_index',
                'columns' => ['service_id'],
            ],
            [
                'type' => 'index',
                'name' => 'status_index',
                'columns' => ['status'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Licenses';
    }

    public static function get_description() : string {
        return 'Stores license records with keys, service identifiers, and validity dates.';
    }
}