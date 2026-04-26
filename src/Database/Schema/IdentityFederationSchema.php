<?php
/**
 * Identity Federation Schema
 */
namespace SmartLicenseServer\Database\Schema;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Stores federated identities.
 */
class IdentityFederationSchema extends AbstractDatabaseSchema {

    public static function get_table_name() : string {
        return 'SMLISER_IDENTITY_FEDERATION_TABLE';
    }

    public static function get_columns() : array {
        return [
            'id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'user_id BIGINT(20) UNSIGNED NOT NULL',
            'issuer VARCHAR(300) NOT NULL',
            'external_id VARCHAR(512) NOT NULL',
            'created_at DATETIME DEFAULT CURRENT_TIMESTAMP',
            'INDEX smliser_idfed_user_id (user_id)',
            'INDEX smliser_idfed_issuer (issuer)',
            'INDEX smliser_idfed_external_id (external_id)',
        ];
    }

    public static function get_label() : string { return 'Identity Federation'; }
    public static function get_description() : string { return 'Stores federated identity mappings.'; }
}