<?php
/**
 * Role entity.
 *
 * A Role is a named collection of capabilities owned by an Owner.
 * Roles are assigned to principals (User, ServiceAccount)
 * within an ownership scope.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use const SMLISER_ROLES_TABLE;
use function is_json, json_decode, defined, smliser_dbclass, smliser_safe_json_encode;

defined( 'SMLISER_ABSPATH' ) || exit;

class Role {

    use SanitizeAwareTrait, CommonQueryTrait;

    /**
     * Role ID.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * Owner ID this role belongs to.
     *
     * @var int
     */
    protected int $owner_id = 0;

    /**
     * Role machine name (slug).
     *
     * Immutable identifier.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * Human-readable role label.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * Capabilities assigned to this role.
     *
     * @var string[]
     */
    protected array $capabilities = [];

    /**
     * Role constructor.
     *
     * Intentionally empty.
     * Hydration is done via setters or factory methods.
     */
    public function __construct() {}

    /*
    |-----------
    | GETTERS
    |-----------
    */

    /**
     * Get role ID.
     *
     * @return int
     */
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get owner ID.
     *
     * @return int
     */
    public function get_owner_id() : int {
        return $this->owner_id;
    }

    /**
     * Get role name (slug).
     *
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Get role label.
     *
     * @return string
     */
    public function get_label() : string {
        return $this->label;
    }

    /**
     * Get role capabilities.
     *
     * @return string[]
     */
    public function get_capabilities() : array {
        return $this->capabilities;
    }

    /*
    |-----------
    | SETTERS
    |-----------
    */

    /**
     * Set role ID.
     *
     * @param int $id
     * @return static
     */
    public function set_id( $id ) : static {
        $this->id = self::sanitize_int( $id );
        return $this;
    }

    /**
     * Set owner ID.
     *
     * @param int $owner_id
     * @return static
     */
    public function set_owner_id( $owner_id ) : static {
        $this->owner_id = self::sanitize_int( $owner_id );
        return $this;
    }

    /**
     * Set role machine name.
     *
     * This value should be immutable once persisted.
     *
     * @param string $name
     * @return static
     */
    public function set_name( $name ) : static {
        if ( $this->id > 0 ) {
            return $this;
        }

        $name = self::sanitize_text( $name );

        if ( '' === $name ) {
            return $this;
        }

        $this->name = strtolower( $name );
        return $this;
    }


    /**
     * Set role label.
     *
     * @param string $label
     * @return static
     */
    public function set_label( $label ) : static {
        $this->label = self::sanitize_text( $label );
        return $this;
    }

    /**
     * Replace all capabilities.
     *
     * @param string|string[] $capabilities
     * @return static
     */
    public function set_capabilities( array|string $capabilities ) : static {
        if ( is_json( $capabilities ) ) {
            $capabilities = json_decode( $capabilities, true );
        }

        $this->capabilities = [];

        foreach ( (array) $capabilities as $capability ) {
            $this->add_capability( $capability );
        }

        return $this;
    }

    /*
    |----------------
    | CAPABILITIES
    |----------------
    */

    /**
     * Assign a capability to this role.
     *
     * @param string $capability
     * @return static
     */
    public function add_capability( $capability ) : static {
        $capability = self::sanitize_text( $capability );

        if ( '' === $capability ) {
            return $this;
        }

        Capability::assert_exists( $capability );

        if ( ! in_array( $capability, $this->capabilities, true ) ) {
            $this->capabilities[] = $capability;
        }

        return $this;
    }

    /**
     * Remove a capability.
     *
     * @param string $capability
     * @return static
     */
    public function remove_capability( $capability ) : static {
        $capability = self::sanitize_text( $capability );

        $this->capabilities = array_values(
            array_filter(
                $this->capabilities,
                static fn( $cap ) => $cap !== $capability
            )
        );

        return $this;
    }

    /**
     * Check whether role has a capability.
     *
     * @param string $capability
     * @return bool
     */
    public function has_capability( $capability ) : bool {
        $capability = self::sanitize_text( $capability );

        return in_array( $capability, $this->capabilities, true );
    }

    /**
     * Check whether role has all capabilities.
     *
     * @param string[] $capabilities
     * @return bool
     */
    public function has_all_capabilities( array $capabilities ) : bool {
        foreach ( $capabilities as $capability ) {
            if ( ! $this->has_capability( $capability ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether role has any capability.
     *
     * @param string[] $capabilities
     * @return bool
     */
    public function has_any_capability( array $capabilities ) : bool {
        foreach ( $capabilities as $capability ) {
            if ( $this->has_capability( $capability ) ) {
                return true;
            }
        }

        return false;
    }

    /*
    |----------------
    | UTILITY
    |----------------
    */

    /**
     * Check whether role belongs to an owner.
     *
     * @param int $owner_id
     * @return bool
     */
    public function belongs_to_owner( $owner_id ) : bool {
        return $this->owner_id === self::sanitize_int( $owner_id );
    }

    /**
     * Hydrate role from array.
     *
     * @param array $data
     * @return static
     */
    public static function from_array( array $data ) : static {
        $self = new static();

        foreach ( $data as $key => $value ) {
            $method = "set_{$key}";

            if ( is_callable( [ $self, $method ] ) ) {
                $self->$method( $value );
            }
        }

        return $self;
    }

    /*
    |----------------
    | CRUD METHODS
    |----------------
    */

    /**
     * Save role to database.
     *
     * @return bool
     */
    public function save() : bool {
        if ( ! $this->owner_id || '' === $this->name ) {
            return false;
        }

        $db     = smliser_dbclass();
        $table  = SMLISER_ROLES_TABLE;

        $capabilities = array_values( array_unique( $this->get_capabilities() ) );

        $data = [
            'owner_id'      => $this->get_owner_id(),
            'name'          => $this->get_name(),
            'label'         => $this->get_label(),
            'capabilities'  => smliser_safe_json_encode( $capabilities ),
            'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
        ];

        if ( $this->get_id() ) {
            unset( $data['owner_id'], $data['name'] );

            $result = $db->update( $table, $data, [ 'id' => $this->get_id() ] );
        } else {
            $existing = static::get_by_name( $this->get_owner_id(), $this->get_name() );

            if ( $existing ) {
                return false;
            }

            $data['created_at'] = gmdate( 'Y-m-d H:i:s' );

            $result = $db->insert( $table, $data );
            $this->set_id( $db->get_insert_id() );
            
        }

        return false !== $result;
    }

    /**
     * Delete role.
     *
     * @return bool
     */
    public function delete() : bool {
        if ( ! $this->get_id() ) {
            return false;
        }

        $db     = smliser_dbclass();
        $table  = SMLISER_ROLES_TABLE;

        $result = $db->delete( $table, [ 'id' => $this->get_id() ] );

        return false !== $result;
    }

    /**
     * Get role by ID.
     *
     * @param int $id
     * @return static|null
     */
    public static function get_by_id( int $id ) : ?static {
        static $roles = [];

        if ( ! array_key_exists( $id, $roles ) ) {
            $roles[ $id ] = static::get_self_by_id( $id, SMLISER_ROLES_TABLE );
        }

        return $roles[ $id ];
    }

    /**
     * Get role by owner and name.
     *
     * @param int    $owner_id
     * @param string $name
     * @return static|null
     */
    public static function get_by_name(
        int $owner_id,
        string $name
    ) : ?static {
        $db     = smliser_dbclass();
        $table  = SMLISER_ROLES_TABLE;

        $row = $db->get_row(
            "SELECT * FROM {$table} WHERE owner_id = ? AND name = ?",
            [ $owner_id, self::sanitize_text( $name ) ]
        );

        return $row ? static::from_array( $row ) : null;
    }

    /**
     * Get all roles for an owner.
     *
     * @param int $owner_id
     * @return static[]
     */
    public static function get_all_by_owner( int $owner_id ) : array {
        $db     = smliser_dbclass();
        $table  = SMLISER_ROLES_TABLE;

        $rows = $db->get_results(
            "SELECT * FROM {$table} WHERE owner_id = ?",
            [ $owner_id ]
        );

        if ( ! $rows ) {
            return [];
        }

        return array_map(
            static fn( $row ) => static::from_array( $row ),
            $rows
        );
    }

}
