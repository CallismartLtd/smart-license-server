<?php
/**
 * User settings class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 */
declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

use SmartLicenseServer\Database\Database;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Utils\Format;

/**
 * User settings API.
 */
class UserSettings {

    const PWD_RESET_NAME                    = 'password_reset_key';
    const DASHBOARD_THEME_NAME              = 'theme';
    const DASHBOARD_SIDEBAR_COLLAPSED_NAME  = 'sidebar_collapsed';
    const LOCALE                            = 'locale';

    /**
     * @var array<string, mixed>
     */
    protected array $settings_cache = [];

    public function __construct(
        private User $user,
        private Database $database
    ) {}

    public static function for( User $user ) : static {
        return new static( $user, \smliser_db() );
    }

    /**
     * Get all settings for the user.
     */
    public function all() : array {
        if ( ! empty( $this->settings_cache ) ) {
            return $this->settings_cache;
        }

        $table  = SMLISER_USER_OPTIONS_TABLE;
        $sql    = "SELECT option_key, option_value FROM {$table} WHERE user_id = ?";

        $results = $this->database->get_results(
            $sql,
            [ $this->user->get_id() ]
        );

        if ( ! \is_array( $results ) || $results === [] ) {
            return $this->settings_cache = [];
        }

        $cache = [];

        foreach ( $results as $row ) {
            $key = $row['option_key'];
            $cache[$key] = Format::decode( $row['option_value'] );
        }

        return $this->settings_cache = $cache;
    }

    /**
     * Get a single option.
     */
    public function get( string $name, mixed $default = null ) : mixed {
        $cache = $this->all();
        return $cache[$name] ?? $default;
    }

    /**
     * Set (insert or update) a user option.
     */
    public function set( string $name, mixed $value ) : bool {
        $table   = SMLISER_USER_OPTIONS_TABLE;
        $user_id = $this->user->get_id();
        $encoded = Format::encode( $value );

        $inserted = $this->database->insert( $table, [
            'user_id'      => $user_id,
            'option_key'   => $name,
            'option_value' => $encoded,
        ]);

        if ( $inserted !== false ) {
            $this->settings_cache[$name] = $value;
            return true;
        }

        $error = $this->database->get_last_error();

        if ( $error !== null ) {
            $updated = $this->database->update(
                $table,
                [ 'option_value' => $encoded ],
                [
                    'user_id'    => $user_id,
                    'option_key' => $name,
                ]
            );

            if ( $updated !== false ) {
                $this->settings_cache[$name] = $value;
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a single option.
     */
    public function delete( string $name ) : bool {
        $table      = SMLISER_USER_OPTIONS_TABLE;
        $user_id    = $this->user->get_id();

        $deleted = $this->database->delete( $table, [
            'user_id'    => $user_id,
            'option_key' => $name,
        ]);

        if ( $deleted > 0 ) {
            unset( $this->settings_cache[$name] );
        }

        return (bool) $deleted;
    }

    /**
     * Delete all options for the user.
     */
    public function delete_all() : bool {
        $table   = SMLISER_USER_OPTIONS_TABLE;
        $user_id = $this->user->get_id();

        $deleted = (int) $this->database->delete( $table, [
            'user_id' => $user_id
        ]);

        if ( $deleted > 0 ) {
            $this->settings_cache = [];
        }

        return (bool) $deleted;
    }
}