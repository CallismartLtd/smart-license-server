<?php
/**
 * User settings class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 */
declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

use Callismart\DBPrism\Database;
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
     * Cached user settings, invalidated on delete.
     * 
     * @var array<string, mixed>
     */
    protected array $settings_cache = [];

    /**
     * Holds reference to the current data to be inserted or updated.
     * 
     * @var array{name: string, value: mixed}
     */
    protected array $current_data   = [];

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

        $sql    = smliserQueryBuilder()
            ->select( 'option_key', 'option_value' )->from( SMLISER_USER_OPTIONS_TABLE )
            ->where( 'user_id', '=', $this->user->get_id() );

        $results = $this->database->get_results( $sql->build(), $sql->get_bindings() );

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
        $this->current_data['name']     = $name;
        $this->current_data['value']    = $value;

        return (bool) $this->database->transactional( [$this, 'insert_or_update'] );
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
    
    /**
     * Transaction callback for inserting current user data.
     * 
     * @access private
     * @return bool
     */
    public function insert_or_update( Database $db ) : bool {
        $table      = SMLISER_USER_OPTIONS_TABLE;
        $user_id    = $this->user->get_id();

        $name       = $this->current_data['name'];
        $value      = $this->current_data['value'];

        $this->reset_current_data();

        $encoded    = Format::encode( $value );

        $exists_sql = smliserQueryBuilder()
            ->select( 'id' )->from( $table )
            ->where( 'user_id', '=', $user_id )
            ->where( 'option_key', '=', $name )
            ->lock_for_update();

        $id = $db->get_var( $exists_sql->build(), $exists_sql->get_bindings() );

        if ( ! $id ) {
            $inserted   = $db->insert( $table, [
                'user_id'      => $user_id,
                'option_key'   => $name,
                'option_value' => $encoded,
            ]);

            if ( $inserted !== false ) {
                $this->settings_cache[$name] = $value;
                return true;
            }

            return false;
        }

        
        $updated = $this->database->update(
            $table,
            [ 'option_value' => $encoded ],
            [ 'id'  => $id ]
        );

        if ( $updated !== false ) {
            $this->settings_cache[$name] = $value;
            return true;
        }

        return false;
    }

    protected function reset_current_data() : void {
        $this->current_data = [];
    }
}