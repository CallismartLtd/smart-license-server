<?php
/**
 * Application environment class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Contracts\URLManagerInterface;
use SmartLicenseServer\Environments\Application\Boot\BootManager;
use SmartLicenseServer\Environments\Application\Boot\BootMode;
use SmartLicenseServer\Environments\Application\Boot\BootModeResolver;
use SmartLicenseServer\Environments\Application\Boot\InstallationState;
use SmartLicenseServer\Environments\Application\Boot\MaintenanceFlag;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Environments\Application\Installation\InstallerSession;
use SmartLicenseServer\Environments\Application\Installation\SetupToken;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Environments\Application\Boot\DowntimeBootstrapper;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\CoreURLManager;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environment;
use SmartLicenseServer\Environments\Application\Auth\IdentityService;
use SmartLicenseServer\Environments\Application\Boot\CLIBootstrapper;
use SmartLicenseServer\Environments\Application\Boot\WebBootstrapper;
use SmartLicenseServer\Environments\Application\Middlewares\CallableResolver;
use SmartLicenseServer\Environments\Application\Web\RestAPIProvider;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\RESTAPI\RESTProviderInterface;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Authentication\PasswordResetToken;
use SmartLicenseServer\Security\Authentication\Session\SessionManager;
use SmartLicenseServer\Security\CSRF\CSRF;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Class ApplicationEnvironment
 *
 * Smart License Server running as a standalone PHP application environment.
 * Orchestrates container bindings and boot sequences for both HTTP/Web and CLI modes
 * using context-specific bootstrappers.
 *
 * @package SmartLicenseServer\Environments\Application
 * @since 0.2.0
 */
class ApplicationEnvironment extends Environment {

    /**
     * Boot manager instance for handling environment bootstrappers.
     *
     * @var BootManager
     */
    protected BootManager $bootManager;

    /**
     * Boot mode resolver for the current request or command.
     *
     * @var BootModeResolver
     */
    protected BootModeResolver $bootModeResolver;

    /**
     * {@inheritdoc}
     */
    protected function registerDependencies() : void {
        $this->container->singleton( ApplicationEnvironment::class, $this );

        /*
         * Resolve the boot mode first, from files only (no database access).
         */
        $fs = $this->container->get( FileSystem::class );

        $this->bootModeResolver = BootModeResolver::from_runtime( $fs );
        $this->container->singleton( BootModeResolver::class, $this->bootModeResolver );
        $this->container->singleton( InstallationState::class, $this->bootModeResolver->state() );
        $this->container->singleton( MaintenanceFlag::class, $this->bootModeResolver->flag() );
        $this->container->singleton( SetupToken::class, fn () : SetupToken => SetupToken::from_runtime( $fs ) );
        $this->container->singleton( InstallerSession::class, fn () : InstallerSession => InstallerSession::from_runtime( $fs ) );

        $this->adoptExistingInstallation();

        $this->container->set(
            URLManagerInterface::class,
            fn( Container $c ) : URLManagerInterface => new CoreURLManager(
                settings: $c->get( Settings::class ),
                app_url: url(),
                admin_base_url: url(),
                assets_url: url( '/assets/' ),
            )
        );

        $this->container->singleton(
            CSRF::class,
            fn ( Container $c ) : CSRF => new CSRF(
                $this->runtime->secret,
                $this->runtime->salt,
                $c->get( SessionManager::class )
            )
        );

        $this->container->singleton(
            PasswordResetToken::class,
            fn ( Container $c ) : PasswordResetToken => new PasswordResetToken(
                $this->runtime->secret,
                $this->runtime->salt,
                $c->get( Cache::class )
            )
        );

        $this->container->singleton(
            CallableResolver::class,
            fn( Container $c ) : CallableResolver => new CallableResolver( $c )
        );

        $this->container->alias(
            PasswordIdentityProviderInterface::class,
            IdentityService::class
        );

        $this->container->alias(
            RESTProviderInterface::class,
            RestAPIProvider::class
        );

        /*
         * Initialize BootManager and attach context bootstrappers.
         *
         * During downtime the web bootstrapper is replaced, so downtime
         * requests set up no routes, sessions or identity services.
         * The CLI bootstrapper is always attached.
         */
        $this->bootManager = new BootManager( $this->container );
        $this->bootManager->add( new CLIBootstrapper() );

        if ( $this->bootMode()->blocks_web() ) {
            $this->bootManager->add( new DowntimeBootstrapper( $this->bootModeResolver ) );
        } else {
            $this->bootManager->add( new WebBootstrapper() );
        }

        $this->bootManager->registerAll();
    }

    /**
     * Record an installation that predates the installation state file.
     *
     * Runs only when the state file is missing, no installation has been
     * started (an installer finishes its own run), the .env file exists and
     * a database is configured, so it costs nothing once the state exists.
     * The .env file is checked on disk because $_ENV can outlive a deleted
     * .env (DotEnv::load() also calls putenv(), which persists in long-lived
     * workers such as PHP-FPM). If every installation
     * requirement is met, the state file is written and the mode re-resolved;
     * otherwise the application stays in Installation mode. A database that
     * cannot be reached fails closed to Maintenance, so an outage never
     * exposes the installer on a live site.
     *
     * @return void
     */
    protected function adoptExistingInstallation() : void {
        $resolver = $this->bootModeResolver;

        if (
            BootMode::Installation !== $resolver->resolve()
            || $resolver->installation_in_progress()
            || null !== $resolver->state()->read()
            || ! $this->container->get( FileSystem::class )->is_file( \SMLISER_ROOT . '.env' )
            || null === $this->databaseConfig()
        ) {
            return;
        }

        try {
            $installer = $this->container->get( AppInstaller::class );

            if ( array() !== $installer->installation_issues() ) {
                return;
            }

            $installer->mark_installed();
            $resolver->refresh();
        } catch ( \Throwable $e ) {
            $resolver->fail_closed( 'Could not verify an existing installation: ' . $e->getMessage() );
        }
    }

    /**
     * Get the boot mode for the current request or command.
     *
     * @return BootMode
     */
    public function bootMode() : BootMode {
        return $this->bootModeResolver->resolve();
    }

    /**
     * {@inheritdoc}
     */
    public function boot() : void {
        $mode = $this->bootMode();

        /*
         * Not installed: no database, cache, settings or users exist yet.
         * Only the shared Database is registered, so models use the real
         * connection once the installer swaps the adapter in. Applies to
         * the CLI too, so `smliser installer` can run.
         */
        if ( BootMode::Installation === $mode ) {
            // Auto provision when available.
            try {
                DataStore::set_database(
                    $this->container->get( Database::class )
                );

                DataStore::set_cache(
                    $this->container->get( Cache::class )
                );
            } catch ( \Throwable ) {}

            $this->bootManager->bootAll();
            return;
        }

        /*
         * Web requests during downtime only need the downtime handler.
         * Skip data stores, registries and authentication, which may
         * query a schema that is mid-upgrade.
         */
        if ( $mode->blocks_web() && ! \is_cli() ) {
            $this->bootManager->bootAll();
            return;
        }

        DataStore::set_database(
            $this->container->get( Database::class )
        );

        DataStore::set_cache(
            $this->container->get( Cache::class )
        );

        DataStore::set_urlmanager(
            $this->container->get( URLManager::class )
        );

        // Shadow boot hosted app registry.
        $this->container->get( HostedAppsRegistry::class );

        /*
         * Boot environment bootstrappers (routing, assets, terminal inputs, etc.).
         */
        $this->bootManager->bootAll();

        /*
         * Trigger context identity authentication.
         */
        $this->container->get( IdentityService::class )->authenticate();
    }

    /**
     * {@inheritdoc}
     */
    protected function createDatabaseConfig() : ?DBConfigDTO {
        return \smliser_db_config_from_env( $_ENV );
    }
}