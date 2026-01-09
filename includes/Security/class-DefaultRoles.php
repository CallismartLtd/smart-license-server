<?php
namespace SmartLicenseServer\Security;

defined( 'SMLISER_ABSPATH' ) || exit;

final class DefaultRoles {

    /**
     * System default role definitions.
     *
     * @var array<string, array{
     *     label: string,
     *     capabilities: string[]
     * }>
     */
    protected static array $roles = [

        'owner' => [
            'label'        => 'Owner',
            'capabilities' => [
                // Full access
                'security.owner.create',
                'security.organization.create',

                'security.role.create',
                'security.role.update',
                'security.role.delete',
                'security.capability.assign',

                'hosted_apps.create',
                'hosted_apps.update',
                'hosted_apps.delete',
                'hosted_apps.change_status',
                'hosted_apps.upload_assets',
                'hosted_apps.access_files',

                'monetization.pricing.create',
                'monetization.pricing.update',
                'monetization.pricing.delete',

                'monetization.license.create',
                'monetization.license.update',
                'monetization.license.revoke',
                'monetization.license.issue',
                'monetization.license.delete',

                'repository.view',
                'repository.download',

                'analytics.view',
                'messaging.send_bulk',
            ],
        ],

        'manager' => [
            'label'        => 'Manager',
            'capabilities' => [
                'hosted_apps.create',
                'hosted_apps.update',
                'hosted_apps.upload_assets',

                'monetization.pricing.create',
                'monetization.pricing.update',

                'monetization.license.create',
                'monetization.license.update',
                'monetization.license.issue',

                'repository.view',
                'analytics.view',
            ],
        ],

        'viewer' => [
            'label'        => 'Viewer',
            'capabilities' => [
                'repository.view',
                'repository.download',
                'analytics.view',
            ],
        ],
    ];

    private function __construct() {}

    /**
     * Get all default role definitions.
     */
    public static function all() : array {
        return self::$roles;
    }

    /**
     * Check if a default role exists.
     */
    public static function exists( string $name ) : bool {
        return isset( self::$roles[ $name ] );
    }

    /**
     * Get a default role definition.
     */
    public static function get( string $name ) : array {
        if ( ! self::exists( $name ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown default role "%s".', $name )
            );
        }

        return self::$roles[ $name ];
    }
}
