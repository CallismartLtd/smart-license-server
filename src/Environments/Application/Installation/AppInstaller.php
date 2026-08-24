<?php
/**
 * App installer class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Installation;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\DBPrism\Inspection\Inspector;
use Callismart\DBPrism\Utils\Table;
use SmartLicenseServer\Background\Jobs\Accounts\SignupEmailJob;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Provides the Unified API to install this application on a the server.
 * 
 * This class:
 * - performs environment sanity checks.
 * - creates all required directories.
 * - makes the .env file from the core .env.example file.
 * - handles initial database connection.
 * - creates all the registered tables.
 * - installs the default roles.
 * - creates the administrator account.
 * - creates the .htaccess file from the .htaccess.example file.
 */
class AppInstaller {
    /**
	 * The required directories keyed by readable names.
	 * 
	 * @var array<string, string>
	 */
	protected array $required_directories = array(
		'Application Root'    => \SMLISER_ROOT,
		'Runtime Directory'   => \SMLISER_RUNTIME_DIR,
		'Storage Directory'   => \SMLISER_STORAGE_DIR,
		'Repository Root'     => \SMLISER_REPO_DIR,
		'Plugins Repository'  => \SMLISER_PLUGINS_REPO_DIR,
		'Themes Repository'   => \SMLISER_THEMES_REPO_DIR,
		'Software Repository' => \SMLISER_SOFTWARE_REPO_DIR,
		'Cache Directory'     => \SMLISER_CACHE_DIR,
		'Temporary Directory' => \SMLISER_TMP_DIR,
		'Uploads Directory'   => \SMLISER_UPLOADS_DIR,
		'Logs Directory'      => \SMLISER_LOGS_DIR,
	);

    /**
     * Performs environment sanity checks and evaluates database, cache, and package management requirements.
     *
     * @param callable(string $check, string $status, string $message)|null $success_callback
     * @param callable(string $check, string $status, string $message)|null $failure_callback
     * @return array{passed: bool, errors: array<string, string>, warnings: array<string, string>, recommendations: array<string, string>}
     */
    public function verify_environment_sanity(
        ?callable $success_callback = null,
        ?callable $failure_callback = null
    ): array {
        $results = [
            'passed'          => true,
            'errors'          => [],
            'warnings'        => [],
            'recommendations' => [],
        ];

        $report = function( string $check, bool $is_ok, string $message, string $level = 'CRITICAL' ) use ( &$results, $success_callback, $failure_callback ): void {
            if ( $is_ok && 'CRITICAL' === $level ) {
                $success_callback && $success_callback( $check, 'OK', $message );
                return;
            }

            if ( 'CRITICAL' === $level && ! $is_ok ) {
                $results['passed']           = false;
                $results['errors'][ $check ] = $message;
                $failure_callback && $failure_callback( $check, 'CRITICAL', $message );
            } elseif ( 'WARNING' === $level ) {
                $results['warnings'][ $check ] = $message;
                $failure_callback && $failure_callback( $check, 'WARNING', $message );
            } else {
                $results['recommendations'][ $check ] = $message;
                $success_callback && $success_callback( $check, 'RECOMMENDED', $message );
            }
        };

        // PHP Version
        $report(
            'PHP Version',
            version_compare( PHP_VERSION, '8.4.0', '>=' ),
            version_compare( PHP_VERSION, '8.4.0', '>=' )
                ? sprintf( 'PHP %s installed.', PHP_VERSION )
                : sprintf( 'PHP 8.4.0+ required. Current version: %s', PHP_VERSION ),
            'CRITICAL'
        );

        // Package Management & Updates
        $report(
            'Zip Extension',
            extension_loaded( 'zip' ),
            extension_loaded( 'zip' )
				? 'ZipArchive loaded for software updates, themes, and package extraction.'
				: 'The "zip" extension is missing. Package uploads and updates will fail.',
            'CRITICAL'
        );

        // Database Engine Breakdown
        $db_extensions = [
            'mysqli'     => extension_loaded( 'mysqli' ),
            'sqlite3'    => extension_loaded( 'sqlite3' ),
            'pdo'        => extension_loaded( 'pdo' ),
            'pdo_mysql'  => extension_loaded( 'pdo_mysql' ),
            'pdo_pgsql'  => extension_loaded( 'pdo_pgsql' ),
            'pdo_sqlite' => extension_loaded( 'pdo_sqlite' ),
        ];

        $has_any_db = array_reduce( $db_extensions, fn( $carry, $status ) => $carry || $status, false );

        $report(
            'Database Availability',
            $has_any_db,
            $has_any_db
                ? 'At least one supported database extension is loaded.'
                : 'No supported database drivers found (mysqli, sqlite3, or pdo extensions missing).',
            'CRITICAL'
        );

        // Detailed Database Driver Recommendations & Remarks
        if ( $db_extensions['mysqli'] ) {
            $report( 'DB: mysqli (MySQL)', true, 'Native mysqli driver loaded. Preferred for low-overhead MySQL connections.', 'RECOMMENDATION' );
        } elseif ( $db_extensions['pdo_mysql'] ) {
            $report( 'DB: pdo_mysql', true, 'PDO MySQL loaded. Consider enabling native "mysqli" for better performance and speed.', 'RECOMMENDATION' );
        }

        if ( $db_extensions['sqlite3'] ) {
            $report( 'DB: SQLite3', true, 'Native SQLite3 driver loaded. Preferred for light, zero-config file databases.', 'RECOMMENDATION' );
        } elseif ( $db_extensions['pdo_sqlite'] ) {
            $report( 'DB: pdo_sqlite', true, 'PDO SQLite loaded.', 'RECOMMENDATION' );
        }

        if ( $db_extensions['pdo_pgsql'] ) {
            $report( 'DB: PostgreSQL (PDO)', true, 'pdo_pgsql loaded for PostgreSQL connections.', 'RECOMMENDATION' );
        }

        // Cache Adapters & Security Persistence Check
        $cache_adapters = [
            'redis'     => extension_loaded( 'redis' ),
            'memcached' => extension_loaded( 'memcached' ),
            'apcu'      => extension_loaded( 'apcu' ),
            'sqlite3'   => extension_loaded( 'sqlite3' ),
        ];

        $active_persistent_caches = array_keys( array_filter( $cache_adapters ) );

        if ( ! empty( $active_persistent_caches ) ) {
            $report(
                'Persistent Cache',
                true,
                sprintf( 'Persistent cache available via [%s]. MFA tokens, reset tokens, and brute-force tracking will persist accurately.', implode( ', ', $active_persistent_caches ) ),
                'RECOMMENDATION'
            );
        } else {
            $report(
                'Persistent Cache',
                false,
                'No persistent cache extension (redis, memcached, apcu, sqlite3) found. Defaulting to in-memory array cache. CAUTION: Password resets, MFA tokens, and rate-limiting counters will NOT persist across process restarts!',
                'WARNING'
            );
        }

        return $results;
    }

    /**
     * Creates the required directories.
     * 
     * @param callable(string $type, string $dir, string $message)|null $success_callback
     * @param callable(string $type, string $dir, string $message)|null $failure_callback
     * @return void
     */
    public function create_required_directories(
        ?callable $success_callback = null,
        ?callable $failure_callback = null
        ) : void {

        $fs = \smliser_filesystem();
        foreach ( $this->required_directories as $type => $dir ) {

            if ( $fs->is_dir( $dir ) ) {
                $failure_callback && $failure_callback( $type, $dir, 'Exists' );
                continue;
            }

            if ( ! $fs->mkdir( $dir, SMLISER_DIR_PERMISSION, true ) ) {
                $failure_callback && $failure_callback( $type, $dir, 'mkdir failed' );
                continue;
            }

            $success_callback && $success_callback( $type, $dir, 'Created' );
        }
    }

    /**
     * Make the .env file.
     * 
     * @param string|null $env_example_path  Absolute path to the env.example file, passing null
     * will force us to look up the file in the root directory, the parent directory of the app root or
     * in the runtime directory.
     * 
     * @param bool $overwrite   Overwrite existing file.
     * 
     * @return string|null  Absolute path to the newly created .env file,
     * null if the env.example file was not found.
     * 
     * @throws \RuntimeException If target directory is not writable.
     */
    public function make_dot_env_file( ?string $env_example_path = null, bool $overwrite = false ) : ?string {
        $env_file   = SMLISER_ROOT . '.env';

        if ( file_exists( $env_file ) && ! $overwrite ) {
            return $env_file;
        }

        if ( null === $env_example_path ) {
            $env_example_path   =  SMLISER_ROOT . '.env.example';

            if ( ! file_exists( $env_example_path ) ) {
                $env_example_path   = dirname( \SMLISER_ROOT ) . '/.env.example';
            }

            if ( ! file_exists( $env_example_path ) ) {
                $env_example_path   = SMLISER_RUNTIME_DIR . '.env.example';
            }
        }

        if ( '' === $env_example_path || ! file_exists( $env_example_path ) ) {
            return throw new \RuntimeException(
                "The path \"{$env_example_path}\" to env.example file does not exist."
            );
        }

        EnvFileWriter::create_from_example( $env_example_path, $env_file )
            ->save();

        return $env_file;
       
    }

    /**
     * Make the .htaccess file.
     * 
     * @param string|null $htaccess_example_path Absolute path to the .htaccess.example file, passing null
     * will force us to look up the file in the root directory, the parent directory of the app root or
     * in the runtime directory.
     * 
     * @param bool $overwrite Overwrite existing file.
     * 
     * @return string|null Absolute path to the newly created .htaccess file,
     * null if the .htaccess.example file was not found.
     * 
     * @throws \RuntimeException If target directory is not writable.
     */
    public function make_htaccess_file( ?string $htaccess_example_path = null, bool $overwrite = false ) : ?string {
        $htaccess_file = SMLISER_ROOT . 'public/.htaccess';

        if ( file_exists( $htaccess_file ) && ! $overwrite ) {
            return $htaccess_file;
        }

        if ( null === $htaccess_example_path ) {
            $htaccess_example_path = SMLISER_ROOT . '.htaccess.example';

            if ( ! file_exists( $htaccess_example_path ) ) {
                $htaccess_example_path = dirname( \SMLISER_ROOT ) . '/.htaccess.example';
            }

            if ( ! file_exists( $htaccess_example_path ) ) {
                $htaccess_example_path = SMLISER_RUNTIME_DIR . '.htaccess.example';
            }
        }

        if ( '' === $htaccess_example_path || ! file_exists( $htaccess_example_path ) ) {
            return throw new \RuntimeException(
                "The path \"{$htaccess_example_path}\" to .htaccess.example file does not exist."
            );
        }

        HtaccessWriter::create_from_example( $htaccess_example_path, $htaccess_file )
            ->save();

        return $htaccess_file;
    }

    /**
     * Get all required directories.
     * 
     * @return array<string,string>
     */
    public function get_required_directories() : array {
        return $this->required_directories;
    }

    /**
     * Create the database tables.
     * 
     * @param callable(string $table_name, string $message)|null $success_callback
     * @param callable(string $table_name, string $message)|null $failure_callback
     * @return void
     */
    public function create_tables(
        ?callable $success_callback = null,
        ?callable $failure_callback = null
        ) : void{
        
        $db         = smliser_db();
        $schema     = SchemaRegistry::instance();
        $inspector  = new Inspector( $db );

        foreach ( $schema->get_all_tables() as $table ) {
            if ( $inspector->table_exists( $table->get_name() ) ) {
                $failure_callback && $failure_callback( $table->get_name(), 'Exists' );
                continue;
            }

            if ( ! $this->create_table( $table, $db ) ) {
                $failure_callback && $failure_callback( $table->get_name(), $db->get_last_error() );
                continue;
            }

            $success_callback && $success_callback( $table->get_name(), 'Created' );
        }
    }

    /**
     * Create a single database table from a column definition array.
     *
     * @param Table $table
     * @param Database $db
     * @return bool
     */
    protected function create_table( Table $table, Database $db ): bool {
        $charset_collate = $db->get_charset_collate();

        $query  = \smliserQueryBuilder()
            ->create_table( $table->get_name() )
            ->add_columns( $table->get_columns() )
            ->add_constraints( $table->get_constraints() );
        $sql    = $query->build() . '' . $charset_collate;
        
        usleep( 10000 );

        return $db->exec( $sql );        
    }

    /**
     * Install default roles.
     * 
     * @param callable(string $role_name, string $message)|null $success_callback
     * @param callable(string $role_name, string $message)|null $failure_callback
     * @return void
     */
    public function install_default_roles(
        callable|null $success_callback = null,
        callable|null $failure_callback = null 
        ) : void {
        
        $default_roles = DefaultRoles::all();

        foreach ( $default_roles as $slug => $roledata ) {
            $role = new Role();
            $role->set_capabilities( $roledata['capabilities'] );
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );
            $role->set_slug( $slug );

            try {
                if ( $role->save() ) {
                    $rows[] = [ $slug, '✔ Installed' ];
                    $success_callback && $success_callback( $role->get_label(), 'Installed' );
                } else {
                    $rows[] = [ $slug, '⚠ Skipped — unable to save' ];
                    $failure_callback && $failure_callback( $role->get_label(), smliser_db()->get_last_error() );
                }
            } catch ( \Throwable $e ) {
                $failure_callback && $failure_callback( $role->get_label(), $e->getMessage() );
            }
        }
    }

    /**
     * Create the site administrator.
     * 
     * @return User
     * @throws DatabaseException
     * @throws \InvalidArgumentException
     */
    public function create_admin( string $name, string $email, string $password ) : User {
        $default_role   = DefaultRoles::get( 'system_admin' );
        
        $role           = Role::get_by_slug( $default_role['slug'] );

        if ( ! $role ) {
            $role = new Role();
        }

        $role->set_label( $default_role['label'])
            ->set_slug( $default_role['slug'] )
            ->set_capabilities( $default_role['capabilities'] )
            ->set_is_canonical( $default_role['is_canonical'] );

        $admin  = ( new User() )
            ->set_display_name( $name )
            ->set_email( $email )
            ->set_password_hash( password_hash( $password, PASSWORD_ARGON2ID ) )
            ->set_created_at( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )
            ->set_id(0)
            ->set_status( User::STATUS_ACTIVE );

        if ( ! $admin->save() ) {
            throw new DatabaseException( 'insert_error', smliser_db()->get_last_error() );
        }

        $owner = ContextServiceProvider::get_default_owner( $admin );
        if ( ! $owner ) {
            $owner = new Owner;
        }
        
        if ( ! $owner->exists() ) {
            $owner->set_name( $admin->get_display_name() )
                ->set_status( Owner::STATUS_ACTIVE )
                ->set_subject_id( $admin->get_id() )
                ->set_type( Owner::TYPE_INDIVIDUAL );
            $owner->save();
        }
        
        $owner_subject = ContextServiceProvider::get_owner_subject( $owner );

        ContextServiceProvider::save_actor_role( $admin, $role, $owner_subject );

        \smliser_job_queue()->dispatch( JobDTO::make(
            job_class: SignupEmailJob::class,
            payload: [
                'user_id'   => $admin->get_id(),
                'for_admin' => false,
                'recipient' => $admin->get_email(),
            ]
        ));

        return $admin;
    }

    /**
     * Test the given credentials against a database engine.
     * 
     * @param DBConfigDTO $config
     */
    public function test_db_connection( DBConfigDTO $config ) {
        
    }
}