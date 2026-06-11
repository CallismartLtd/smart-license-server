<?php
/**
 * Role entity.
 *
 * A Role is a named groupings of capabilities that are resolved for a principal 
 * within a specific owner context.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security\Permission;

use Callismart\DBPrism\Database;
use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\Format;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use const SMLISER_ROLES_TABLE, SMLISER_ROLE_CAPABILITIES_TABLE;
use function is_json, json_decode, defined, smliser_db, smliser_safe_json_encode,
get_object_vars;

/**
 * Classical representation of a role.
 */
class Role {
    use SanitizeAwareTrait, CommonQueryTrait;

    /**
     * Role ID.
     *
     * @var int
     */
    protected int $id = 0;

    /**
     * Role machine slug.
     *
     * Immutable identifier.
     *
     * @var string
     */
    protected string $slug = '';

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
     * Whether this role is a default system role.
     * 
     * @var bool $is_canonical
     */
    protected bool $is_canonical = false;

    /**
     * Tell wether the principal is acting for self.
     */
    public readonly bool $is_owner_default;

    /**
     * Fillable props through mass assignment.
     *
     * @var array 
     */
    protected static array $fillable = [
        'id',
        'slug',
        'label',
        'capabilities',
        'is_canonical',
    ];

    /**
     * Role constructor.
     *
     * Intentionally light.
     * Hydration is done via setters or factory methods.
     */
    public function __construct( bool $is_owner_default = false ) {
        $this->is_owner_default = $is_owner_default;
    }

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
     * Get role slug.
     *
     * @return string
     */
    public function get_slug() : string {
        return $this->slug;
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

    /**
     * Get the is_canonical property
     * 
     * @return bool
     */
    public function get_is_canonical() : bool {
        return $this->is_canonical;
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
     * Set role machine slug.
     *
     * This value should be immutable once persisted.
     *
     * @param string $slug
     * @return static
     */
    public function set_slug( $slug ) : static {
        $slug = self::sanitize_text( $slug );

        $this->slug = strtolower( str_replace( [' ', '-'], ['_', '_'], $slug ) );
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
     * @throws \SmartLicenseServer\Exceptions\Exception
     */
    public function set_capabilities( array|string $capabilities ) : static {
        if ( is_string( $capabilities ) ) {
            if ( ! is_json( $capabilities ) ) {
                throw new Exception(
                    'role_caps_invalid',
                    'Capabilities must be valid JSON or array'
                );
            }

            $capabilities = json_decode( $capabilities, true );
        }

        $this->capabilities = [];

        foreach ( (array) $capabilities as $capability ) {
            $this->add_capability( $capability );
        }

        return $this;
    }


    /**
     * Set the value of is_canonical property
     * 
     * @param bool $is_canonical
     */
    public function set_is_canonical( bool $is_canonical ) : static {
        $this->is_canonical = $is_canonical;

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

    /**
     * Load the capabilities from the database
     * 
     * @return static
     */
    public function load_capabilities() : static {
        $db     = smliser_db();
        $table  = SMLISER_ROLE_CAPABILITIES_TABLE;

        $sql    = static::query()
            ->select( 'capabilities' )->from( $table )
            ->where( 'role_id', '=', $this->get_id() );

        $caps   = $db->get_row( $sql->build(), $sql->get_bindings() );

        try {
            $this->set_capabilities( $caps['capabilities'] ?? [] );
        } catch ( Exception ) {}

        return $this;
    }

    /*
    |----------------
    | UTILITY
    |----------------
    */

    /**
     * Hydrate role from array.
     *
     * @param array $data
     * @return static
     */
    public static function from_array( array $data ) : static {
        $static   = static::from_array_helper( SMLISER_ROLES_TABLE, $data );

        if ( $static->get_id() ) {
            $static->load_capabilities();
        }

        return $static;
    }

    /**
     * Convert roles to array.
     * 
     * array{id: int, slug: string, label: string, capabilities: string[], is_canonical: bool}
     */
    public function to_array() : array {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'label'         => $this->label,
            'capabilities'  => $this->capabilities,
            'is_canonical'  => $this->is_canonical,
        ];
    }


    /**
     * Tells whether this role exists in the database.
     * 
     * @return true
     */
    public function exists() : bool {
        return  $this->id > 0;
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
     * @throws Exception
     * @throws DatabaseException
     */
    public function save() : bool {
        if ( ! $this->get_slug() ) {
            throw new Exception( 'role_save_error', 'Role slug must be set' );
        }

        $result = (bool) smliser_db()->transactional( function ( Database $db ) {
            $roles_table    = SMLISER_ROLES_TABLE;
            $caps_table     = SMLISER_ROLE_CAPABILITIES_TABLE;
            $lock_sql       = static::query()
                ->select( 'id' )->from( SMLISER_ROLES_TABLE )
                ->where( 'slug', '=', $this->get_slug() )
                ->limit( 1 )->lock_for_update();

            $id             = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
            $capabilities   = array_values( array_unique( $this->get_capabilities() ) );
            $now            = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

            if ( ! $id ) {
                $roles_data = [
                    'is_canonical'  => $this->get_is_canonical(),
                    'slug'          => $this->get_slug(),
                    'label'         => $this->get_label(),
                    'updated_at'    => $now->format( 'Y-m-d H:i:s' ),
                    'created_at'    => $now->format( 'Y-m-d H:i:s' ),
                ];

                $result = $db->insert( $roles_table, $roles_data );

                if ( ! $result ) {
                    throw new DatabaseException(
                        'insert_failed',  
                        sprintf( 'Inserting roles failed with error: %s', $db->get_last_error() )    
                    );
                }

                $this->set_id( $db->get_insert_id() );

                $caps_data  = [
                    'role_id'       => $this->get_id(),
                    'capabilities'  => Format::encode( $capabilities, Format::ENCODING_JSON )
                ];

                if ( ! $db->insert( $caps_table, $caps_data ) ) {
                    throw new DatabaseException(
                        'insert_failed',
                        sprintf(
                            'Inserting role capabilities failed with error: %s',
                            $db->get_last_error()
                        )
                    );
                }

                return true;
            }

            $caps_data  = [
                'capabilities' => Format::encode( $capabilities, Format::ENCODING_JSON )
            ];

            $cap_id_sql = static::query()->select( 'id' )->from( $caps_table )
                ->where( 'role_id', '=', $id )->limit( 1 );

            $cap_id = (int) $db->get_var( $cap_id_sql->build(), $cap_id_sql->get_bindings() );

            if ( $cap_id ) {
                $result = $db->update( $caps_table, $caps_data, ['id' => $cap_id] );

                if ( false === $result ) {
                    throw new DatabaseException(
                        'update_failed',
                        sprintf(
                            'Updating capabilities failed with error: %s',
                            $db->get_last_error()
                        )
                    );
                }
            } else {
                $caps_data['role_id'] = $this->get_id();
                $result = $db->insert( $caps_table, $caps_data );

                if ( false === $result ) {
                    throw new DatabaseException(
                        'insert_failed',
                        sprintf(
                            'Inserting capabilities failed with error: %s',
                            $db->get_last_error()
                        )
                    );
                }
            }

            return true;
        });



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
     * Get role by slug.
     *
     * @param string $slug
     * @return static|null
     */
    public static function get_by_slug( string $slug ) : ?static {
        return static::get_self_by( 'slug', $slug, SMLISER_ROLES_TABLE );
    }

    /**
     * Get all available roles from the database
     * 
     * @param bool $to_array Whether to run the static::to_array method on each record.
     * @return array{
     *  id: int,
     *  slug: string,
     *  label: string,
     *  capabilities: string[],
     *  is_canonical: bool
     * }|static[] An array of role objects or array of roles if return param is true.
     */
    public static function all( bool $to_array = false ) : array {
        $db         = smliser_db();
        $table      = SMLISER_ROLES_TABLE;
        $sql        = static::query()->select( '*' )->from( $table );
        $results    = $db->get_results( $sql->build(), $sql->get_bindings() );

        if ( empty( $results ) ) {
            return [];
        }
        
        /** @var self[] $roles */
        $roles  = array_map( [__CLASS__, 'from_array'], $results );
        if ( $to_array ) {
            foreach ( $roles as $i => $role ) {
                $roles[$role->get_slug()]   = $role->to_array();
                unset( $roles[$i] );
            }
            /** @var array{id: int, slug: string, label: string, capabilities: string[], is_canonical: bool} $roles */
        }

        return $roles;
    }

}
