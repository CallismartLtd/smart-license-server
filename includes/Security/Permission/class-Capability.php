<?php
/**
 * Capability registry.
 *
 * Canonical definition of everything that can be done in the system.
 * Capabilities are system-defined and MUST NOT be user-generated.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Security\Permission;

use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

final class Capability {

    /**
     * Capability definitions grouped by domain.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $capabilities = [

        /**
         * Hosted Applications (Plugin, Theme, Software).
         */
        'hosted_apps' => [
            'hosted_apps.create'            => 'Create hosted applications',
            'hosted_apps.update'            => 'Update hosted application details',
            'hosted_apps.delete'            => 'Delete hosted applications',
            'hosted_apps.change_status'     => 'Change hosted application status',
            'hosted_apps.upload_assets'     => 'Upload application assets',
            'hosted_apps.edit_assets'       => 'Edit application assets',
            'hosted_apps.delete_assets'     => 'Delete application assets',
            'hosted_apps.access_files'      => 'Access application package files',
        ],

        /**
         * Monetization & Licensing
         */
        'monetization' => [
            'monetization.create'                   => 'Create app monetization',
            'monetization.update'                   => 'Update app monetization',
            'monetization.delete'                   => 'Delete app monetization',
            'monetization.change_status'            => 'Change the status of app monetization',
            'monetization.pricing.create'           => 'Create monetization pricing tiers',
            'monetization.pricing.update'           => 'Update monetization pricing tiers',
            'monetization.pricing.delete'           => 'Delete monetization pricing tiers',

            'monetization.license.create'           => 'Create licenses',
            'monetization.license.update'           => 'Update licenses',
            'monetization.license.revoke'           => 'Revoke licenses',
            'monetization.license.deactivate'       => 'Deactivate licenses',
            'monetization.license.delete'           => 'Delete licenses',
            'monetization.license.issue'            => 'Issue licenses',
            'monetization.license.uninstall_domain' => 'Uninstall domain from license',
        ],

        /**
         * Repository & Downloads
         */
        'repository' => [
            'repository.view'       => 'View repository contents',
            'repository.download'   => 'Download application packages',
        ],

        /**
         * Analytics & Reporting
         */
        'analytics' => [
            'analytics.view'    => 'View analytics data',
        ],

        /**
         * Messaging
         */
        'messaging' => [
            'messaging.send_bulk'   => 'Send bulk messages',
        ],

        /**
         * Security & Identity Management
         */
        'security' => [
            'security.owner.create'             => 'Create owners',
            'security.owner.update'             => 'Update owners',
            'security.owner.delete'             => 'Delete owners',
            'security.owner.view'               => 'View owners',
            
            'security.organization.create'      => 'Create organizations',
            'security.organization.update'      => 'Update organizations',
            'security.organization.delete'      => 'Delete organizations',
            'security.organization.view'        => 'View organizations',
            'security.organization.add_members'  => 'Add members to organizations',
            'security.organization.update_members'  => 'Update organization members',
            'security.organization.remove_members'  => 'Remove members from organizations',

            'security.user.create'              => 'Create users',
            'security.user.update'              => 'Update users',
            'security.user.delete'              => 'Delete users',
            'security.user.view'                => 'View users',
            'security.user.invite'              => 'Invite users',

            'security.service_account.create'   => 'Create service accounts',
            'security.service_account.update'   => 'Update service accounts',
            'security.service_account.delete'   => 'Delete service accounts',
            'security.service_account.view'     => 'View service accounts',

            'security.role.create'              => 'Create roles',
            'security.role.update'              => 'Update roles',
            'security.role.delete'              => 'Delete roles',
            'security.role.assign'              => 'Assign roles',

            'security.capability.assign'        => 'Assign capabilities to roles',
        ],
    ];

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Get all registered capabilities.
     *
     * @return array<string, string> Flat list [ capability => description ]
     */
    public static function all() : array {
        $all = [];

        foreach ( self::$capabilities as $group ) {
            foreach ( $group as $capability => $description ) {
                $all[ $capability ] = $description;
            }
        }

        return $all;
    }

    /**
     * Check whether a capability exists.
     *
     * @param string $capability
     * @return bool
     */
    public static function exists( string $capability ) : bool {
        return array_key_exists( $capability, self::all() );
    }

    /**
     * Assert that a capability exists.
     *
     * @param string $capability
     * @throws InvalidArgumentException
     */
    public static function assert_exists( string $capability ) : void {
        if ( ! self::exists( $capability ) ) {
            throw new InvalidArgumentException(
                sprintf( 'Unknown capability "%s".', $capability )
            );
        }
    }

    /**
     * Get capabilities by domain.
     *
     * @param string $domain
     * @return array<string, string>
     */
    public static function by_domain( string $domain ) : array {
        return self::$capabilities[ $domain ] ?? [];
    }

    /**
     * Get all capabilities grouped by domain.
     *
     * @return array<string, array<string, string>>
     */
    public static function get_caps() : array {
        return self::$capabilities;
    }

    /**
     * Get all capability domains.
     *
     * @return string[]
     */
    public static function domains() : array {
        return array_keys( self::$capabilities );
    }

}
