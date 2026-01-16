<?php
/**
 * Canonical default role definitions.
 *
 * Default roles exist to reduce configuration overhead.
 * They are optional presets and are NOT user-editable.
 *
 * The highest role automatically inherits all registered capabilities.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security;

defined( 'SMLISER_ABSPATH' ) || exit;

final class DefaultRoles {

    /**
     * System default role definitions.
     *
     * @var array<string, array{
     *     label: string,
     *     capabilities: string[]|callable():string[]
     * }>
     */
    protected static array $roles = [

        /**
         * Super Administrator.
         *
         * Full unrestricted access.
         * Automatically inherits ALL registered capabilities.
         */
        'super_admin' => [
            'label'        => 'Super Administrator',
            'capabilities' => [ self::class, 'all_capabilities' ],
        ],

        /**
         * Resource Administrator.
         *
         * Manages applications, monetization, and visibility layers.
         */
        'resource_admin' => [
            'label'        => 'Resource Administrator',
            'capabilities' => [
                // Hosted applications
                'hosted_apps.create',
                'hosted_apps.update',
                'hosted_apps.delete',
                'hosted_apps.change_status',
                'hosted_apps.upload_assets',
                'hosted_apps.access_files',

                // Monetization
                'monetization.pricing.create',
                'monetization.pricing.update',
                'monetization.pricing.delete',

                'monetization.license.create',
                'monetization.license.update',
                'monetization.license.revoke',
                'monetization.license.issue',
                'monetization.license.delete',

                // Visibility
                'repository.view',
                'repository.download',
                'analytics.view',
            ],
        ],

        /**
         * Security Administrator.
         *
         * Identity, access control, and role management.
         */
        'security_admin' => [
            'label'        => 'Security Administrator',
            'capabilities' => [
                'security.owner.create',
                'security.organization.create',
                'security.user.create',
                'security.service_account.create',

                'security.role.create',
                'security.role.update',
                'security.role.delete',

                'security.capability.assign',
            ],
        ],

        /**
         * Application Manager.
         */
        'app_manager' => [
            'label'        => 'Application Manager',
            'capabilities' => [
                'hosted_apps.create',
                'hosted_apps.update',
                'hosted_apps.upload_assets',
                'hosted_apps.change_status',
            ],
        ],

        /**
         * License & Pricing Manager.
         */
        'license_manager' => [
            'label'        => 'License Manager',
            'capabilities' => [
                'monetization.pricing.create',
                'monetization.pricing.update',

                'monetization.license.create',
                'monetization.license.update',
                'monetization.license.issue',
                'monetization.license.revoke',
                'monetization.license.deactivate',
            ],
        ],

        /**
         * Analyst.
         */
        'analyst' => [
            'label'        => 'Analyst',
            'capabilities' => [
                'analytics.view',
            ],
        ],

        /**
         * Viewer.
         */
        'viewer' => [
            'label'        => 'Viewer',
            'capabilities' => [
                'repository.view',
                'repository.download',
            ],
        ],
    ];

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Get all default role definitions.
     *
     * @return array<string, array{label:string, capabilities:string[]}>
     */
    public static function all() : array {
        $roles = [];

        foreach ( self::$roles as $key => $role ) {
            $roles[ $key ] = [
                'label'        => $role['label'],
                'capabilities' => self::resolve_capabilities( $role['capabilities'] ),
            ];
        }

        return $roles;
    }

    /**
     * Resolve role capabilities.
     *
     * @param string[]|callable $caps
     * @return string[]
     */
    protected static function resolve_capabilities( $caps ) : array {
        return is_callable( $caps ) ? (array) call_user_func( $caps ) : $caps;
    }

    /**
     * Get all registered capabilities.
     *
     * Used by the highest-privilege role.
     *
     * @return string[]
     */
    protected static function all_capabilities() : array {
        return array_keys( Capability::all() );
    }

    /**
     * Check if a default role exists.
     */
    public static function exists( string $name ) : bool {
        return isset( self::$roles[ $name ] );
    }

    /**
     * Retrieve a default role definition.
     *
     * @throws \InvalidArgumentException
     */
    public static function get( string $name ) : array {
        if ( ! self::exists( $name ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown default role "%s".', $name )
            );
        }

        return self::all()[ $name ];
    }

    /**
     * Save or update default roles in the database.
     */
    public static function install() {
        $default_roles  = static::all();
        $roles          = [];

        foreach ( $default_roles as $slug => $roledata ) {
            $role   = new Role;

            foreach ( $roledata as $k => $v ) {
                $cap_meth = "set_{$k}";
                $role->$cap_meth( $v );
            }
            $key    = key( $roledata );
            $value  = $roledata[$key];
            
            $method = "set_{$key}";
            $role->set_slug( $slug );
            $role->$method( $value );

            $roles[]    = $role;

        }

        return $roles;
    }
}
