<?php

namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

class OwnersSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return SMLISER_OWNERS_TABLE;
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
                'name' => 'subject_id',
                'type' => 'bigint',
                'nullable' => false,
            ],
            [
                'name' => 'type',
                'type' => 'enum',
                'values' => ['individual', 'organization', 'platform'],
                'default' => 'platform',
                'nullable' => false,
            ],
            [
                'name' => 'name',
                'type' => 'varchar',
                'length' => 255,
                'nullable' => false,
            ],
            [
                'name' => 'status',
                'type' => 'enum',
                'values' => ['active', 'suspended', 'disabled'],
                'default' => 'active',
                'nullable' => false,
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
                'type' => 'index',
                'name' => 'smliser_owners_subject_id',
                'columns' => ['subject_id'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_owners_created_at',
                'columns' => ['created_at'],
            ],
            [
                'type' => 'index',
                'name' => 'smliser_owners_updated_at',
                'columns' => ['updated_at'],
            ],
        ];
    }

    public static function get_options() : array {
        return [];
    }

    public static function get_label() : string {
        return 'Owners';
    }

    public static function get_description() : string {
        return 'Stores resource owners.';
    }
}