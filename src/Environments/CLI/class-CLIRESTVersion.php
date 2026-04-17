<?php
/**
 * CLI REST version stub class file.
 *
 * A minimal no-op implementation of RESTInterface for the CLI
 * environment. No routes are served or registered in CLI context —
 * this stub exists solely to satisfy the RESTProviderInterface
 * contract that requires a RESTInterface instance to be returned
 * from restAPIVersion().
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\CLI
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\CLI;

use SmartLicenseServer\RESTAPI\RESTInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * No-op REST version for the CLI environment.
 */
class CLIRESTVersion implements RESTInterface {

    /*
    |--------------------------------------------
    | RESTInterface implementation
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     *
     * No routes are registered in CLI context.
     * Returns the minimum valid structure the interface requires.
     *
     * @return array{namespace: string, routes: array}
     */
    public static function get_routes(): array {
        return [
            'namespace' => 'smliser/v1',
            'routes'    => [],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * No route categories in CLI context.
     *
     * @return array
     */
    public static function get_categories(): array {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * No routes to describe in CLI context.
     *
     * @return array<string, string>
     */
    public static function describe_routes( ?string $category = null ): array {
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * Returns the application REST API namespace.
     * Consistent with CLIRESTProvider::namespace().
     *
     * @return string
     */
    public function namespace(): string {
        return 'smliser/v1';
    }
}