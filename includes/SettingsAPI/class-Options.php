<?php
/**
 * Options API.
 *
 * Concrete adapter class that uses our custom settings table in the database
 * to manage user options across all supported host environments.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

use SmartLicenseServer\Database\Database;

/**
 * Concrete class that uses our custom settings table in the database to manage user options.
 *
 * This class implements the abstract do_* methods from AbstractSettings by using
 * the injected DatabaseAdapterInterface implementation.
 *
 * @since 0.2.0
 */
class Options extends AbstractSettings {

    /**
     * Name of the custom settings database table.
     *
     * @var string
     */
    const TABLE_NAME = SMLISER_OPTIONS_TABLE;

    /**
     * Instance of the database adapter used to execute queries.
     *
     * @var Database
     */
    private $db;

    /**
     * Constructor for the Options class.
     *
     * @param Database $db The instance of the current environment DB adapter @see `\SmartLicenseServer\Database\Database`.
     */
    public function __construct( Database $db ) {
        $this->db = $db;
    }

    /**
     * Concrete implementation for retrieving a setting from the options table.
     *
     * @since 0.2.0
     *
     * @param string $key     The unique identifier/name of the setting.
     * @param mixed  $default The value to return if the key is not found.
     * @return mixed The stored setting value.
     */
    protected function do_get( string $key, $default = null ) {
        $table          = self::TABLE_NAME;

        $sql = "SELECT `option_value` FROM `{$table}` WHERE `option_name` = ?";

        $result = $this->db->get_var( $sql, [ $key ] );

        if ( null === $result || false === $result ) {
            return $default;
        }

        $value = \is_serialized( $result ) ? unserialize( $result ) : $result;

        return $value;
    }

    /**
     * Concrete implementation for storing or updating a setting in the custom options table.
     *
     * We use a SELECT + UPDATE/INSERT strategy, or an explicit UPSERT if the adapter supports it.
     * Since the interface provides UPDATE and INSERT, we'll use them.
     *
     * @since 0.2.0
     *
     * @param string $key   The unique identifier/name of the setting.
     * @param mixed  $value The data to be stored.
     * @return bool True on successful storage/update, false otherwise.
     */
    protected function do_set( string $key, $value ): bool {
        $table          = self::TABLE_NAME;

        $value_to_store = \maybe_serialize( $value );
        
        $option = array(
            'option_name'   => $key,
            'option_value'  => $value_to_store
        );
        
        $old_value      = $this->do_get( $key, null );

        if ( $old_value ) {
            // Update mode.
            if ( \maybe_serialize( $old_value ) === $value_to_store ) {
                return false; // No changes.
            }

            $result = $this->db->update( $table, $option, ['option_name' => $key] );
        } else {
            $result = $this->db->insert( $table, $option );
        }

        // Insert returns ID or false.
        return false !== $result;
    }

    /**
     * Concrete implementation for removing a setting from the options table.
     *
     * @since 0.2.0
     *
     * @param string $key The unique identifier/name of the setting to delete.
     * @return bool True on successful deletion, false otherwise.
     */
    protected function do_delete( string $key ): bool {
        $table        = self::TABLE_NAME;

        $result = $this->db->delete( $table, [ 'option_name' => $key ] );

        return false !== $result;
    }

    /**
     * Concrete implementation for checking existence in the options table.
     *
     * @since 0.2.0
     *
     * @param string $key The unique identifier/name of the setting.
     * @return bool True if the key exists, false otherwise.
     */
    protected function do_has( string $key ): bool {
        $table          = self::TABLE_NAME;

        $sql            = "SELECT 1 FROM `{$table}` WHERE `option_name` = ?";

        $result         = $this->db->get_var( $sql, [ $key ] );

        return ! empty( $result );
    }
}