<?php
/**
 * Role entity.
 *
 * A Role is a named collection of capabilities owned by an Owner.
 * Roles are assigned to principals (User, ServiceAccount) within an ownership scope.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

class Role {

    /**
     * Role ID.
     *
     * @var int|null
     */
    protected ?int $id = null;

    /**
     * Owner ID this role belongs to.
     *
     * @var int
     */
    protected int $owner_id;

    /**
     * Role machine name (slug).
     *
     * @var string
     */
    protected string $name;

    /**
     * Human-readable label.
     *
     * @var string
     */
    protected string $label;

    /**
     * Capabilities assigned to this role.
     *
     * @var string[]
     */
    protected array $capabilities = [];

    /**
     * Role constructor.
     *
     * @param int    $owner_id
     * @param string $name
     * @param string $label
     */
    public function __construct( int $owner_id, string $name, string $label ) {
        $this->owner_id = $owner_id;
        $this->name     = $name;
        $this->label    = $label;
    }

    /**
     * Get role ID.
     *
     * @return int|null
     */
    public function get_id() : ?int {
        return $this->id;
    }

    /**
     * Set role ID.
     *
     * @param int $id
     * @return self
     */
    public function set_id( int $id ) : self {
        $this->id = $id;
        return $this;
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
     * Get role name.
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
     * Set role label.
     *
     * @param string $label
     * @return self
     */
    public function set_label( string $label ) : self {
        $this->label = $label;
        return $this;
    }

    /**
     * Get capabilities assigned to this role.
     *
     * @return string[]
     */
    public function get_capabilities() : array {
        return $this->capabilities;
    }

    /**
     * Assign a capability to the role.
     *
     * @param string $capability
     * @return self
     */
    public function add_capability( string $capability ) : self {
        Capability::assert_exists( $capability );

        if ( ! in_array( $capability, $this->capabilities, true ) ) {
            $this->capabilities[] = $capability;
        }

        return $this;
    }

    /**
     * Remove a capability from the role.
     *
     * @param string $capability
     * @return self
     */
    public function remove_capability( string $capability ) : self {
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
    public function has_capability( string $capability ) : bool {
        return in_array( $capability, $this->capabilities, true );
    }

    /**
     * Replace all capabilities.
     *
     * @param string[] $capabilities
     * @return self
     */
    public function set_capabilities( array $capabilities ) : self {
        $this->capabilities = [];

        foreach ( $capabilities as $capability ) {
            $this->add_capability( $capability );
        }

        return $this;
    }
}
