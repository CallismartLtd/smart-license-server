<?php
/**
 * Common database query trait file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

namespace SmartLicenseServer\Utils;

\defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Common reusable database query.
 */
trait CommonQueryTrait {
    use SanitizeAwareTrait;
    /**
     * Get self by ID
     * 
     * @param int $id
     * @param string $table The database table name
     * @return self|null
     */
    protected static function get_self_by_id( $id, $table ) {
        $db     = smliser_dbclass();
        $id     = self::sanitize_int( $id );

        if ( empty( $id ) ) {
            return null;
        }

        $sql    = "SELECT * FROM {$table} WHERE `id` = ?";
        $result = $db->get_row( $sql, [$id] );

        if ( $result ) {
            return self::from_array( $result );
        }

        return null;
    }
}