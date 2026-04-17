<?php
/**
 * CLI REST provider stub class file.
 *
 * A minimal no-op implementation of RESTProviderInterface for the CLI
 * environment. The CLI never serves HTTP requests so no route registration
 * or request dispatching is needed. This stub satisfies the Environment
 * bootstrap requirement that a rest_api_provider must be present.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\CLI
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\CLI;

use SmartLicenseServer\RESTAPI\RESTInterface;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * No-op REST provider for the CLI environment.
 *
 * The CLI never serves HTTP requests so route registration,
 * authentication, and HTTPS enforcement are all no-ops.
 * Only namespace() returns a meaningful value — it is used
 * by SetUp::restAPIUrl() to construct REST API URLs when
 * job handlers need to reference API endpoints.
 */
class CLIRESTProvider implements RESTProviderInterface {

    /*
    |--------------------------------------------
    | RESTProviderInterface implementation
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     *
     * No-op — HTTPS enforcement is a web server concern.
     * The CLI never receives HTTP requests.
     *
     * @return null
     */
    public function enforce_https( ...$params ): mixed {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * No-op — there are no routes to register in CLI context.
     */
    public function register_rest_routes(): void {}

    /**
     * {@inheritdoc}
     *
     * No-op — authentication is handled per job handler in CLI context.
     * Background jobs run with the privileges of the CLI process, not
     * an HTTP actor.
     *
     * @return null
     */
    public function authenticate(): mixed {
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * Returns the application REST API namespace.
     * Used by SetUp::restAPIUrl() to construct REST API URLs.
     */
    public function namespace(): string {
        return 'smliser/v1';
    }

    /**
     * {@inheritdoc}
     *
     * Returns a null-object REST version since no routes are served
     * in CLI context. Fulfils the interface contract without registering
     * any routes or loading any route configuration.
     */
    public function restAPIVersion(): RESTInterface {
        return new CLIRESTVersion();
    }
}