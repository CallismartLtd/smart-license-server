<?php
/**
 * Options API class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI\Providers;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\SQLBuilder;
use SmartLicenseServer\Utils\Format;

/**
 * Core option class that uses our custom settings table in the database to manage user options.
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
     * Constructor for the Options class.
     *
     * @param Database $db The instance of the current environment DB adapter @see `\Callismart\DBPrism\Adapters\Database`.
     */
    public function __construct( protected Database $db ) {}

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
        $sql    = smliserQueryBuilder( $this->db->get_driver() )
            ->select( 'option_value' )->from( static::TABLE_NAME )
            ->where( 'option_name', '=', $key )
            ->limit( 1 );

        $result = $this->db->get_var( $sql->build(), $sql->get_bindings() );

        if ( null === $result ) {
            return $default;
        }

        $value = Format::decode( $result );

        return $value;
    }

    /**
     * Concrete implementation for storing or updating a setting in the custom options table.
     *
     * @since 0.2.0
     *
     * @param string $key   The unique identifier/name of the setting.
     * @param mixed  $value The data to be stored.
     * @return bool True on successful storage/update, false otherwise.
     */
    protected function do_set( string $key, $value ): bool {
        return $this->db->transactional( function() use( $key, $value ) {
            $value_to_store = Format::encode( $value, Format::ENCODING_PHP );
            $option = array(
                'option_name'   => $key,
                'option_value'  => $value_to_store
            );

            $lock_sql   = $this->query()
                ->select( 'option_id' )->from( static::TABLE_NAME )
                ->where( 'option_name', '=', $key )
                ->limit(1)->lock_for_update();

            $id = (int) $this->db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );

            if ( $id ) {
                $result = $this->db->update( static::TABLE_NAME, $option, ['option_name' => $key, 'option_id' => $id] );
            } else {
                $result = $this->db->insert( static::TABLE_NAME, $option );
            }
            
            // Insert returns ID or false.
            return false !== $result;
        });
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
        $result = $this->db->delete( static::TABLE_NAME, [ 'option_name' => $key ] );

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
        $sql    = smliserQueryBuilder( $this->db->get_driver() )
            ->select( '1' )->from( static::TABLE_NAME )
            ->where( 'option_name', '=', $key )
            ->limit(1);

        $result = $this->db->get_var( $sql->build(), $sql->get_bindings() );

        return ! empty( $result );
    }

    /**
     * Retrieve paginated settings from the custom options table.
     *
     * @since 0.2.0
     */
    protected function do_all( int $page, int $limit ): array {
        $offset = $this->db->calculate_query_offset( $page, $limit );

        $sql = smliserQueryBuilder( $this->db->get_driver() )
            ->select( 'option_name', 'option_value' )
            ->from( static::TABLE_NAME )
            ->limit( $limit )
            ->offset( $offset )
            ->order_by( 'option_id', 'ASC' );

        $rows = $this->db->get_results(
            $sql->build(),
            $sql->get_bindings()
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $results = [];

        foreach ( $rows as $row ) {
            $results[ $row['option_name'] ] = Format::decode( $row['option_value'] );
        }

        return $results;
    }

    /**
     * Search settings in the custom options table.
     *
     * @since 0.2.0
     */
    protected function do_search( string $query, int $page, int $limit ): array {
        $offset = $this->db->calculate_query_offset( $page, $limit );

        $sql = smliserQueryBuilder( $this->db->get_driver() )
            ->select( 'option_name', 'option_value' )
            ->from( static::TABLE_NAME )
            ->where_contains( 'option_name', $query )
            ->limit( $limit )
            ->offset( $offset )
            ->order_by( 'option_id', 'ASC' );

        $rows = $this->db->get_results(
            $sql->build(),
            $sql->get_bindings()
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $results = [];

        foreach ( $rows as $row ) {
            $results[ $row['option_name'] ] = Format::decode( $row['option_value'] );
        }

        return $results;
    }

    protected function query() : SQLBuilder {
        return \smliserQueryBuilder( $this->db->get_driver() );
    }
}