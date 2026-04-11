<?php
/**
 * User settings class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 */
declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

use SmartLicenseServer\Security\Actors\User;

/**
 * User settings API
 */
class UserSettings {
    const OPTIONS_KEY   = 'smliser_user_%d_options';

    public function __construct( 
        private User $user,
        private Settings $settings
        
    ) {}

    /**
     * Initialize settings context for a user
     * 
     * @param User $user The user instance.
     * @param Settings $settings The settings API.
     * @return static Fluent
     */
    public static function for( User $user, ?Settings $settings = null ) : static {
        return new static( $user, $settings ?? \smliser_settings() );

    }

    /**
     * Get all settings for the user.
     * 
     * @return array
     */
    public function all() : array {
        $options_key    = $this->make_key();
        $options        = $this->settings->get( $options_key, [] );

        if ( ! is_array( $options ) ) {
            $options    = [];
        }

        return $options;
    }

    /**
     * Get the value of a particular option key.
     * 
     * @param string $name The option name.
     * @param mixed $default The default value to return.
     * @return mixed
     */
    public function get( string $name, mixed $default = null ) : mixed {
        return $this->all()[$name] ?? $default;
    }

    /**
     * Set an option for the user
     * 
     * @param string $name The option name
     * @param mixed $value The option value
     * @return bool
     */
    public function set( string $name, mixed $value ) : bool {
        $all        = $this->all();
        $all[$name] = $value;

        return (bool) $this->settings->set( $this->make_key(), $all );
    }

    /**
     * Delete a single user option
     * 
     * @param string $name The option name
     * @return bool
     */
    public function delete( string $name ) : bool {
        $all = $this->all();

        if ( ! array_key_exists( $name, $all ) ) {
            return true; // nothing to delete
        }

        unset( $all[$name] );

        return (bool) $this->settings->set( $this->make_key(), $all );
    }

    /**
     * Delete all user options
     * 
     * @return bool
     */
    public function delete_all() : bool {
        return (bool) $this->settings->delete( $this->make_key() );
    }

    /*
    |-----------------------
    | HELPERS
    |-----------------------
    */

    /**
     * Make user option key
     */
    public function make_key() : string {
        return sprintf( static::OPTIONS_KEY, $this->user->get_id() );
    }
}