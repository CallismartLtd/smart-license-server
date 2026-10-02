<?php
/**
 * Installer class file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Console\Commands;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\DatabaseInfoDTO;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\DBPrism\Inspection\Inspector;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Environments\Application\Installation\SetupToken;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Handles installation processes through the console.
 */
class Installer extends AbstractCommand {

    public function __construct(
        protected AppInstaller $installer,
        protected Guard $guard,
        protected SetupToken $setup_token,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        return parent::__construct( $io, $output, $script_name );
    }

    /**
     * {@inheritdoc}
     */
    public static function name() : string {
        return 'installer';
    }

    /**
     * {@inheritdoc}
     */
    public function description() : string {
        return 'Handle installation.';
    }

    public function help() : string {
        $command_name   = static::name();
        $app_name       = SMLISER_APP_NAME;

        $commands = [
            'run'           => 'Executes full automated installation wizard.',
            'check'         => 'Performs environment sanity checks.',
            'test:db'       => 'Tests the database connection set in .env (or given options with --manual).',
            'make:dir'      => 'Creates all required directories.',
            'make:dotenv'   => 'Create a .env file if missing, generate empty application secrets and set the site URL.',
            'make:tables'   => 'Creates all registered database tables.',
            'make:roles'    => 'Install default roles.',
            'make:admin'    => 'Create a human administrator account.',
            'make:htaccess' => 'Creates or updates the .htaccess file.',
            'link:assets'   => 'Publishes system/assets at public/assets (symlink, or a copy where links are unavailable).',
            'mark:installed' => 'Verifies the installation and records it as complete.',
            'token'         => 'Prints the setup token that claims the web installer.',
            'help'          => 'Displays this help message.',
        ];

        $command_width = max( array_map( 'strlen', array_keys( $commands ) ) );
        $command_format = "{$this->script_name} {$command_name} %-{$command_width}s %s";

        $lines = [];

        foreach ( $commands as $command => $description ) {
            $lines[] = sprintf( $command_format, $command, $description );
        }

        return \implode( PHP_EOL, array_merge( $lines, [
            '',
            'OPTIONS: ',
            '',
            '   Full Installation: ',
            '--skip-admin       Skip interactive administrator account creation step.',
            '--force            Force overwrite existing configuration files (asks before replacing an existing .env).',
            '',
            '   Creating admin account: ',
            '--name             The administrator\'s name.',
            '--email            The administrator\'s email address.',
            '--password         The administrator\'s password.',
            'Note: Creating administrator account requires special authentication (usually done automatically).',
            '',
            '   Creating .env file: ',
            '--dotenv-example-path      The absolute path to the .env.example file. The file will be searched for in',
            '                           the parent directory and the runtime directory.',
            '--app-url                  The public URL of this installation, e.g. https://licenses.example.com.',
            '                           Asked for interactively when SMLISER_APP_URL is empty and this is not given.',
            "Note: The .env file is required to bootstrap {$app_name}.",
            'Note: Empty SMLISER_SECRET and SMLISER_SALT values are generated automatically; existing values are kept.',
            '',
            '   Creating .htaccess file: ',
            '--htaccess-example-path    The absolute path to the .htaccess.example file. The file will be searched for in',
            '                           the parent directory and the runtime directory.',
            '',
            '   Testing a database connection (test:db): ',
            'By default, tests the SMLISER_DB_* values currently in the .env file.',
            '--manual, -m           Test the connection options below instead of the .env values.',
            '',
            '   Connection options (require --manual): ',
            '--db-driver, -d        (required) Database driver: mysql, pgsql, or sqlite.',
            '--dbname, -n           (required) Target database or schema name.',
            '--host, -h             Server hostname or IP. Used by mysql/pgsql; not applicable to sqlite.',
            '--port, -P             Server port. Used by mysql/pgsql; not applicable to sqlite.',
            '--username, -u         Authentication username. Used by mysql/pgsql; not applicable to sqlite.',
            '--password, -p         Authentication password. Used by mysql/pgsql; not applicable to sqlite.',
            '--charset, -c          Connection character encoding. Used by mysql; not applicable to sqlite.',
            '--collation, -C        Connection collation. Used by mysql; not applicable to sqlite.',
            '--prefix, -x           Table name prefix, applied regardless of driver.',
            '--socket, -s           Unix socket path, as an alternative to --host/--port. Used by mysql/pgsql.',
            '--path, -f             Database file path. Required for sqlite; not applicable to mysql/pgsql.',
            '--dsn, -D              Raw DSN string that overrides the discrete host/port/socket/path options above.',
            '--sslmode, -M          SSL enforcement tier. Used by mysql/pgsql; not applicable to sqlite.',
            '--encryption-key, -k   At-rest encryption key. Used by mysql (TDE) and sqlite; not applicable to pgsql.',
            '--strict, -t           Enable strict SQL mode enforcement.',
            '--persistent, -e       Reuse a persistent connection instead of opening a new one.',
            '--timeout, -T          Connection timeout in seconds.',
        ] ) );
    }

    public function get_subcommands() : array {
        return [
            'run'           => [$this, 'run_wizard'],
            'help'          => [$this, 'handle_help'],
            'check'         => [$this, 'handle_checks'],
            'test:db'       => [$this, 'test_db'],
            'make:dir'      => [$this, 'make_directories'],
            'make:dotenv'   => [$this, 'make_dot_env'],
            'make:tables'   => [$this, 'make_db_tables'],
            'make:roles'    => [$this, 'make_roles'],
            'make:admin'    => [$this, 'make_admin'],
            'make:htaccess' => [$this, 'make_dot_htaccess'],
            'link:assets'   => [$this, 'link_assets'],
            'mark:installed' => [$this, 'mark_installed'],
            'token'         => [$this, 'print_setup_token'],
        ];
    }

    /**
     * Interactive setup wizard running all installation steps sequentially.
     *
     * @param CommandInput $input
     * @return int
     */
    public function run_wizard( CommandInput $input ) : int {
        $timer  = new Stopwatch();

        $timer->start();

        $this->output->info( sprintf( 'Starting automated installation wizard for %s...', SMLISER_APP_NAME ) );
        $this->output->writeln( '' );

        // Step 1: Sanity Checks.
        $this->output->info( '--- Step 1/7: Checking Environment ---' );

        sleep(1);

        $code = $this->handle_checks( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Environment sanity checks failed.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Visitors see the installation notice until the final step completes.
        if ( ! $this->installer->is_installed() ) {
            try {
                $this->installer->begin_installation();
            } catch ( \RuntimeException $e ) {
                $this->output->error( sprintf( 'Installation aborted: %s', $e->getMessage() ) );
                return 1;
            }
        }

        // Step 2: Directories.
        $this->output->info( '--- Step 2/7: Creating Directories ---' );

        sleep(1);

        $code = $this->make_directories( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Directory creation failed.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 3: Environment File.
        $this->output->info( '--- Step 3/7: Bootstrapping .env File ---' );

        sleep(1);

        $code = $this->make_dot_env( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Failed to create .env file.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 4: Public web files (.htaccess and assets).
        $this->output->info( '--- Step 4/7: Writing Apache Web Rules (.htaccess) & Publishing Assets ---' );

        sleep(1);

        $code = $this->make_dot_htaccess( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Failed to create .htaccess file.' );
            return $code;
        }

        $code = $this->link_assets( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Failed to publish the public assets.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 5: Database Connection.
        $this->output->info( '--- Step 5/7: Connecting to the Database ---' );

        sleep(1);

        if ( ! $this->ensure_database_connection() ) {
            $this->output->error( 'Installation paused: No working database connection.' );
            $this->output->info( 'Fill in the SMLISER_DB_* values in your .env file, then run the installer again. Completed steps are skipped.' );
            return 1;
        }
        $this->output->writeln( '' );

        // Step 6: Database Schema & Default Roles.
        $this->output->info( '--- Step 6/7: Migrating Database Schema & Roles ---' );

        sleep(1);

        $code = $this->make_db_tables( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Table creation failed.' );
            return $code;
        }

        $code = $this->make_roles( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Default role installation failed.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 7: Administrator Account Creation.
        $this->output->info( '--- Step 7/7: Administrator Account Setup ---' );

        sleep(1);

        $skip_admin = (bool) $input->get_option( 'skip-admin', false );

        if ( $skip_admin ) {
            $this->output->info( 'Skipped admin creation via --skip-admin flag.' );
        } else {
            $code = $this->make_admin( $input );
            if ( 0 !== $code ) {
                $this->output->error( 'Installation incomplete: Administrator creation failed.' );
                return $code;
            }
        }

        $this->output->writeln( '' );

        if ( 0 !== $this->mark_installed( $input ) ) {
            $this->output->error( 'Installation incomplete: Resolve the issues above, then run `installer mark:installed`.' );
            return 1;
        }

        $this->output->success(
            sprintf( '%s installation completed successfully in %fs!', SMLISER_APP_NAME, $timer->elapsed() )
        );

        return 0;
    }

    public function run( CommandInput $input ) : int {
        $this->output->info(
            \sprintf(
                'Start or fix the installation of %s',
                SMLISER_APP_NAME
            )
            
        );

        $prefix = \is_interactive_shell() ? static::name() : $this->script_name . ' ' . static::name();

        $this->output->info(
            sprintf(
                "Run `{$prefix} check` to know whether %s can run on this server.",
                SMLISER_APP_NAME
            )
            
        );
        
        $this->output->info( "Run `{$prefix} help` to see available subcommands." );
        $this->output->writeln('');

        return 0;
    }

    /**
     * Run environment sanity checks and render diagnostic output.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function handle_checks( ?CommandInput $input = null ) : int {
        $this->start_timer();
        $this->output->info( 'Performing system sanity checks...' );
        $this->output->writeln( '' );

        $results   = [];
        $installer = $this->installer;

        $record = function( string $check, string $status, string $message ) use ( &$results ) {
            $results[] = [ $check, $status, $message ];
        };

        $report = $installer->verify_environment_sanity( $record, $record );

        $this->output->table(
            [ 'Check Point', 'Status', 'Diagnostic / Recommendation' ],
            $results
        );

        $this->output->writeln( '' );

        if ( ! $report['passed'] ) {
            $this->output->error(
                sprintf(
                    'Environment check failed with %d critical error(s). Please fix the issues above before running %s.',
                    count( $report['errors'] ),
                    SMLISER_APP_NAME
                )
            );
            return 1;
        }

        if ( ! empty( $report['warnings'] ) ) {
            $this->output->warning(
                sprintf( 'Environment check passed with %d warning(s). Review recommendations above for security and performance.', count( $report['warnings'] ) )
            );
        } else {
            $this->output->success( 'Environment check passed with zero critical errors!' );
        }

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );

        return 0;
    }

    /**
     * Create the required directories.
     * 
     * @param CommandInput|null $input
     * @return int
     */
    public function make_directories( ?CommandInput $input = null ) : int {
        $this->start_timer();
        $results    = [];
        $installer  = $this->installer;

        $callback   = function( $name, $dir, $message ) use ( &$results ) {
            $this->output->progress_update_label( sprintf( 'Creating %s', $name ) );

            $results[] = [$name, $dir, $message];

            usleep(80000);
            $this->output->progress_advance();
        };

        $this->output->progress_start(
            count( $installer->get_required_directories() ),
            'Creating required directories...'
        );

        $installer->create_required_directories( $callback, $callback );

        $this->output->progress_finish( 'Directory creation complete.' );

        $this->output->writeln('');
        $this->output->table(
            ['Directory Name', 'Path', 'Message'],
            $results
        );

        $this->output->writeln( '' );

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );  

        return 0;      
    }

    /**
     * Create the .env file from .env.example file.
     * 
     * @param CommandInput $input
     * @return int
     */
    public function make_dot_env( CommandInput $input ) : int {
        $this->start_timer();
        $path_to_eg = $input->get_option( 'dotenv-example-path', null );
        $force      = (bool) $input->get_option( 'force', false );

        // Replacing an existing .env erases its database credentials and
        // secrets; new secrets would invalidate every existing session.
        if ( $force && file_exists( \SMLISER_ROOT . '.env' ) ) {
            $this->output->warning( 'The existing .env file will be replaced. Its database credentials and application secrets will be lost, and new secrets will sign out every user.' );

            if ( ! $this->io->confirm( 'Replace the existing .env file?', false ) ) {
                $this->output->info( 'Kept the existing .env file.' );
                $force = false;
            }
        }

        try {
            $env_file   = $this->installer->make_dot_env_file( $path_to_eg, $force );

            $this->output->success( 'The env file is ready.' );
            $this->output->writeln(
                implode( \PHP_EOL, [
                    'Path to .env file: ',
                    "   {$env_file}"
                ])
            );

            $generated = $this->installer->generate_app_secrets();

            if ( ! empty( $generated ) ) {
                $this->output->success( sprintf( 'Generated application secrets: %s', implode( ', ', $generated ) ) );
            }

            if ( 0 !== $this->ensure_app_url( $input ) ) {
                return 1;
            }

        } catch ( \RuntimeException $e ) {
            $this->output->error( $e->getMessage() );

            return 1;
        }

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );
        
        return 0;
    }

    /**
     * Set SMLISER_APP_URL when it is empty, from --app-url or by asking.
     *
     * An existing value is kept unless --app-url is given.
     *
     * @param CommandInput $input
     * @return int
     */
    protected function ensure_app_url( CommandInput $input ) : int {
        $given = $input->get_option( 'app-url', null );

        if ( ( null === $given || '' === $given ) && null !== $this->installer->read_app_url() ) {
            return 0;
        }

        for ( $attempt = 0; $attempt < 3; $attempt++ ) {
            $url = is_string( $given ) && '' !== $given
                ? $given
                : (string) $this->io->prompt( 'Site URL (e.g. https://licenses.example.com): ' );

            try {
                $saved = $this->installer->write_app_url( $url );
                $this->output->success( sprintf( 'Site URL set to %s', $saved ) );
                return 0;
            } catch ( \InvalidArgumentException $e ) {
                $this->output->error( $e->getMessage() );

                // A bad --app-url is not retried with the same value.
                $given = null;
            }
        }

        $this->output->error( sprintf( 'No valid site URL was given. Set %s in the .env file, or run this command again with --app-url.', AppInstaller::APP_URL_KEY ) );

        return 1;
    }

    /**
     * Publish system/assets at public/assets.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function link_assets( ?CommandInput $input = null ) : int {
        $this->start_timer();

        try {
            $result = $this->installer->link_public_assets();
        } catch ( \RuntimeException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        }

        $source = $this->installer->assets_source_dir();
        $target = $this->installer->assets_public_dir();

        switch ( $result ) {
            case AppInstaller::ASSETS_LINKED:
                $this->output->success( sprintf( 'Linked %s -> %s', $target, $source ) );
                break;

            case AppInstaller::ASSETS_COPIED:
                $this->output->warning( 'Symlinks are not available on this server, so the assets were copied.' );
                $this->output->success( sprintf( 'Copied %s to %s', $source, $target ) );
                $this->output->info( 'Run this command again after every update to refresh the copy.' );
                break;

            case AppInstaller::ASSETS_UNCHANGED:
                $this->output->success( sprintf( '%s is already linked to %s', $target, $source ) );
                break;

            default:
                $this->output->warning( sprintf( '%s already exists and was not created by the installer, so it was left as it is.', $target ) );
                $this->output->info( 'Remove or rename it, then run this command again to use the bundled assets.' );
                break;
        }

        $this->output->success( sprintf( 'Completed in %fs', $this->stop_timer() ) );

        return 0;
    }

    /**
     * Create or update the .htaccess file.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function make_dot_htaccess( ?CommandInput $input = null ) : int {
        $this->start_timer();
        $path_to_eg = $input ? $input->get_option( 'htaccess-example-path', null ) : null;
        $force      = $input ? (bool) $input->get_option( 'force', false ) : false;

        try {
            $htaccess_file = $this->installer->make_htaccess_file( $path_to_eg, $force );

            $this->output->success( 'The .htaccess file has been created or updated successfully.' );
            $this->output->writeln(
                implode( PHP_EOL, [
                    'Path to .htaccess file: ',
                    "   {$htaccess_file}"
                ])
            );
        } catch ( \RuntimeException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        }

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );

        return 0;
    }

    /**
     * Create database tables
     * 
     * @param CommandInput $input
     * @return int
     */
    public function make_db_tables( CommandInput $input ) : int {
        $this->start_timer();
        $results    = [];

        $callback   = function( $db_name, $message ) use ( &$results ) {
            $results[]  = [$db_name, $message];
            \usleep(80000);
            $this->output->progress_advance();
        };

        if ( ! $this->ensure_database_connection() ) {
            return 1;
        }

        $this->output->progress_start(
            count( SchemaRegistry::instance()->all() ),
            'Creating database tables...'
        );

        $this->installer->create_tables( $callback, $callback );

        $this->output->writeln( '' );
        $this->output->table(
            ['Table Name', 'Message'],
            $results
        );

        $this->output->writeln( '' );

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );

        return 0;
    }

    /**
     * Install default permission roles.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function make_roles( ?CommandInput $input = null ): int {
        $this->start_timer();

        if ( ! $this->ensure_database_connection() ) {
            return 1;
        }

        $force  = $input ? (bool) $input->get_option( 'force', false ) : false;
        $rows   = [];

        $callback   = function( $role_name, $message ) use ( &$rows ) {
            $rows[] = [$role_name, $message];

            usleep(80000);
            $this->output->progress_advance();
        };

        $this->output->progress_start(
            count( DefaultRoles::all() ),
            'Installing default roles...'
        );

        usleep(80000);

        $this->installer->install_default_roles( $callback, $callback, $force );

        $this->output->table( [ 'Roles', 'Result' ], $rows );
        $this->output->writeln( '' );

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );
        return 0;
    }

    /**
     * Create a human administrator account.
     *
     * @param CommandInput $input
     * @return int
     */
    public function make_admin( CommandInput $input ) : int {
        // Check before prompting: email lookups against a placeholder
        // database would silently report every address as available.
        if ( ! $this->ensure_database_connection() ) {
            return 1;
        }

        if ( ! $this->guard->has_principal() || ! $this->guard->get_principal()?->is( 'system_admin' ) ) {
            $this->output->error(
                'You must be logged in as a system admin to perform this action'
            );
            $this->output->info(
                'A system admin must own the app root directory and the CLI credential set to `root`.'
            );

            return 1;
        }

        $name           = $input->get_argument( 'name', null );
        $email          = $input->get_argument( 'email', null );
        $email_is_valid = false;
        $password       = $input->get_argument( 'password', null );
        $confirmed_pwd  = false;
        $error_counter  = 0;

        $filled_all = ! empty( $name ) && ! empty( $email ) && ! empty( $password );
        $terminated = false;

        while ( true ) {
            if ( $filled_all && $confirmed_pwd ) {
                break;
            }

            if ( $error_counter >= 5 ) {
                $terminated = true;
            }

            if ( $terminated ) {
                break;
            }

            if ( ! $name ) {
                $entered_name   = (string) $this->io->prompt( 'Enter Admin Name: ' );
                $contains_admin = str_contains( strtolower( $entered_name ), 'admin' );

                if ( empty( $entered_name ) || $contains_admin ) {
                    $name = '';

                    $error_counter++;
                    $this->output->error( 'Please enter a valid admin name.' );

                    if ( $contains_admin ) {
                        $this->output->error( 'Admin name must not contain the word `admin`' );
                    }

                    continue;
                }

                $name = $entered_name;
            }

            if ( ! $email ) {
                $email   = $this->io->prompt( 'Enter Admin Email: ' );

                if ( empty( $email ) || ! is_email( $email ) ) {
                    $email = '';

                    $error_counter++;
                    $this->output->error( 'Please enter a valid email address.' );
                    continue;
                }
            }

            if ( User::email_exists( $email ) ) {
                $email = '';

                $this->output->error( 'Sorry the provided email is not available.' );
                continue;
            }

            if ( ! $email_is_valid && ! is_email( $email, true ) ) {
                // DNS record not found for the email?
                $this->output->warning( 'The system detected that the provided email address cannot be reached!' );
                if ( ! $this->io->confirm( 'Do you still want to use this email?', false ) ) {
                    $email = '';
                    continue;
                } else {
                    $email_is_valid = true;
                }
            }

            if ( empty( $password ) ) {
                $password = $this->io->secret( 'Enter Admin Password: ' );
                if ( empty( $password ) ) {
                    $password = '';

                    $error_counter++;
                    $this->output->error( 'Please enter a valid password.' );
                    continue;
                }

                continue;
            }

            if ( ! $confirmed_pwd ) {
                $pwd           = $this->io->secret( 'Confirm Admin Password: ' );
                // Strip only trailing line-ending artifacts from terminal input,
                // not arbitrary whitespace — an intentional space in the
                // password shouldn't be able to falsely "match".
                $confirmed_pwd = rtrim( $pwd, "\r\n" ) === rtrim( $password, "\r\n" );

                if ( ! $confirmed_pwd ) {
                    $this->output->error( 'Password mismatch.' );
                }
            }

            $filled_all = ! empty( $name ) && ! empty( $email ) && ! empty( $password );
        }

        if ( $terminated ) {
            $this->output->error( 'Operation cancelled due to multiple errors!' );
            return 1;
        }

        try {
            $admin = $this->installer->create_admin( name: $name, email: $email, password: $password );

            $role = ContextServiceProvider::get_principal_role( $admin );

            $this->output->success(
                sprintf( 'Admin account for %s has been created successfully.', $admin->get_display_name() )
            );

            $this->output->info( 'See admin account details here...' );
            $this->output->table(
                ['Name', 'Value'],
                [
                    ['Account Name', $admin->get_display_name()],
                    ['Email', $admin->get_email()],
                    ['Role', $role?->get_label() ?? 'Unknown'],
                    ['Password', '**********'],
                ]
            );

            $this->output->writeln(
                sprintf(
                    'A welcome email has been sent to %s.', $admin->get_email()
                )
            );

            return 0;
        } catch ( \InvalidArgumentException|DatabaseException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        }
    }

    /**
     * Print the setup token that claims the web installer, creating it when needed.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function print_setup_token( ?CommandInput $input = null ) : int {
        if ( $this->installer->is_installed() ) {
            $this->output->error( sprintf( '%s is already installed; the web installer is closed.', SMLISER_APP_NAME ) );
            return 1;
        }

        $token = $this->setup_token;

        try {
            $value = $token->get();
        } catch ( \RuntimeException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        }

        $this->output->writeln( $value );
        $this->output->writeln( '' );
        $this->output->info( sprintf( 'Enter it on your site at /install. It expires %s UTC.', gmdate( 'Y-m-d H:i', (int) $token->expires_at() ) ) );

        return 0;
    }

    /**
     * Verify the installation and record it as complete.
     *
     * For installations made before the installation state file existed,
     * and as the final step of the installation wizard.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function mark_installed( ?CommandInput $input = null ) : int {
        $this->start_timer();

        if ( ! $this->ensure_database_connection() ) {
            return 1;
        }

        try {
            $issues = $this->installer->installation_issues();
        } catch ( DatabaseException $e ) {
            $this->output->error( sprintf( 'Could not verify the installation: %s', $e->getMessage() ) );
            return 1;
        }

        if ( ! empty( $issues ) ) {
            $this->output->error( 'The installation is not complete:' );

            foreach ( $issues as $issue ) {
                $this->output->writeln( "   - {$issue}" );
            }

            return 1;
        }

        $was_installed = $this->installer->is_installed();

        try {
            $this->installer->mark_installed();
        } catch ( \RuntimeException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        }

        $this->output->success(
            $was_installed
                ? 'Installation verified; the installation state has been updated.'
                : 'Installation verified and recorded as complete.'
        );

        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );

        return 0;
    }

    /**
     * Test a database connection.
     *
     * Tests the values currently in the .env file by default, or the
     * connection options given on the command line with --manual (-m).
     * The connection is only tested; it is never activated.
     *
     * @param CommandInput $input
     * @return int
     */
    public function test_db( CommandInput $input ): int {
        $this->start_timer();

        $manual = (bool) ( $input->get_option( 'manual' ) ?? $input->get_option( 'm' ) ?? false );

        $db_config = $manual
            ? $this->db_config_from_options( $input )
            : $this->db_config_from_env_file( $input );

        if ( null === $db_config ) {
            return 1;
        }

        $this->output->info(
            sprintf( 'Testing database configuration from %s...', $manual ? 'command options' : 'the .env file' )
        );
        $this->output->writeln( '' );

        try {
            $adapter = $this->installer->test_db_connection( $db_config );

            $this->output->success( 'Database configuration passed!' );
            $this->output->writeln( '' );

            // A test only: inspect through a private Database, never the shared one.
            $inspector = new Inspector( new Database( $adapter ) );
            $info      = $inspector->get_database_info();

            $this->render_database_info( $info );

            $adapter->close();

        } catch ( DatabaseException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        } catch ( \Throwable $e ) {
            $this->output->error(
                sprintf(
                    'Database configuration test failed: %s',
                    $e->getMessage()
                )
            );
            return 1;
        }

        $this->output->writeln( '' );
        $this->output->success(
            sprintf( 'Completed in %fs', $this->stop_timer() )
        );

        return 0;
    }

    /**
     * Make sure the application has a working database connection.
     *
     * When only the placeholder adapter is active, the .env file is read as
     * it is now, and its connection is tested and activated. This lets a
     * user fill in .env and continue in the same process.
     *
     * @return bool True when a connection is active.
     */
    private function ensure_database_connection() : bool {
        if ( $this->installer->has_database_connection() ) {
            return true;
        }

        try {
            $db_config = $this->installer->read_database_config();
        } catch ( DatabaseException $e ) {
            // Not configured yet: missing .env, or no driver / database name.
            $this->output->error( $e->getMessage() );
            $this->output->info( 'Fill in the SMLISER_DB_* values in your .env file, then run this command again.' );
            return false;
        } catch ( \InvalidArgumentException $e ) {
            $this->output->error( sprintf( 'Invalid database configuration in the .env file: %s', $e->getMessage() ) );
            return false;
        } catch ( \RuntimeException $e ) {
            $this->output->error( sprintf( 'Unable to read the .env file: %s', $e->getMessage() ) );
            return false;
        }

        try {
            $this->installer->use_connection(
                $this->installer->test_db_connection( $db_config )
            );
        } catch ( DatabaseException $e ) {
            $this->output->error( sprintf( 'Could not connect to the database: %s', $e->getMessage() ) );
            $this->output->info( 'Check the SMLISER_DB_* values in your .env file.' );
            return false;
        }

        $this->output->success( sprintf( 'Connected to the %s database.', $db_config->driver ) );

        return true;
    }

    /**
     * Build the database configuration from the .env file as it is now.
     *
     * @param CommandInput $input
     * @return DBConfigDTO|null Null when the .env file has no database driver.
     */
    private function db_config_from_env_file( CommandInput $input ) : ?DBConfigDTO {
        if ( null !== ( $input->get_option( 'db-driver' ) ?? $input->get_option( 'd' ) ) ) {
            $this->output->warning( 'Connection options are ignored without --manual (-m); testing the .env values.' );
        }

        try {
            return $this->installer->read_database_config();
        } catch ( DatabaseException $e ) {
            // Not configured yet: missing .env, or no driver / database name.
            $this->output->error( $e->getMessage() );
            $this->output->info( 'Fill in the SMLISER_DB_* values in your .env file, or test explicit values with --manual (-m).' );
        } catch ( \InvalidArgumentException $e ) {
            $this->output->error( sprintf( 'Invalid database configuration in the .env file: %s', $e->getMessage() ) );
        } catch ( \RuntimeException $e ) {
            $this->output->error( sprintf( 'Unable to read the .env file: %s', $e->getMessage() ) );
        }

        return null;
    }

    /**
     * Build the database configuration from command options (--manual).
     *
     * @param CommandInput $input
     * @return DBConfigDTO|null Null when a required option is missing or invalid.
     */
    private function db_config_from_options( CommandInput $input ) : ?DBConfigDTO {
        $driver = $input->get_option( 'db-driver' ) ?? $input->get_option( 'd' );

        if ( empty( $driver ) ) {
            $this->output->error( 'Option --db-driver (-d) is required with --manual.' );
            return null;
        }

        $db_name = $input->get_option( 'dbname' ) ?? $input->get_option( 'n' );

        if ( empty( $db_name ) ) {
            $this->output->error( 'Option --dbname (-n) is required with --manual.' );
            return null;
        }

        try {
            return new DBConfigDTO( [
                'dbname'         => $db_name,
                'driver'         => $driver,
                'host'           => $input->get_option( 'host' )           ?? $input->get_option( 'h' ),
                'port'           => $input->get_option( 'port' )           ?? $input->get_option( 'P' ),
                'username'       => $input->get_option( 'username' )       ?? $input->get_option( 'u' ),
                'password'       => $input->get_option( 'password' )       ?? $input->get_option( 'p' ),
                'charset'        => $input->get_option( 'charset' )        ?? $input->get_option( 'c' ),
                'collation'      => $input->get_option( 'collation' )      ?? $input->get_option( 'C' ),
                'prefix'         => $input->get_option( 'prefix' )         ?? $input->get_option( 'x' ),
                'socket'         => $input->get_option( 'socket' )         ?? $input->get_option( 's' ),
                'path'           => $input->get_option( 'path' )           ?? $input->get_option( 'f' ),
                'dsn'            => $input->get_option( 'dsn' )            ?? $input->get_option( 'D' ),
                'flags'          => $input->get_option( 'flags' )          ?? $input->get_option( 'F' ),
                'ssl'            => $input->get_option( 'ssl' )            ?? $input->get_option( 'S' ),
                'sslmode'        => $input->get_option( 'sslmode' )        ?? $input->get_option( 'M' ),
                'encryption_key' => $input->get_option( 'encryption-key' ) ?? $input->get_option( 'k' ),
                'strict'         => $input->get_option( 'strict' )         ?? $input->get_option( 't' ),
                'persistent'     => $input->get_option( 'persistent' )     ?? $input->get_option( 'e' ),
                'timeout'        => $input->get_option( 'timeout' )        ?? $input->get_option( 'T' ),
                'read'           => $input->get_option( 'read' )           ?? $input->get_option( 'r' ),
                'write'          => $input->get_option( 'write' )          ?? $input->get_option( 'w' ),
                'sticky'         => $input->get_option( 'sticky' )         ?? $input->get_option( 'K' ),
            ] );
        } catch ( \Throwable $e ) {
            $this->output->error( sprintf( 'Invalid database options: %s', $e->getMessage() ) );
            return null;
        }
    }

    /**
     * Render DatabaseInfoDTO data cleanly in key-value sections.
     *
     * @param DatabaseInfoDTO|array $info
     * @return void
     */
    protected function render_database_info( DatabaseInfoDTO|array $info ): void {
        $data = $info instanceof DatabaseInfoDTO ? $info->to_array() : $info;

        $groups = array(
            'System' => array(
                'Product'          => $data['product'] ?? null,
                'Version'          => $data['version'] ?? null,
                'Engine'           => $data['engine'] ?? null,
                'Protocol Version' => $data['protocol_version'] ?? null,
                'OS'               => $data['server_os'] ?? null,
                'Architecture'     => $data['server_architecture'] ?? null,
            ),
            'Connection & Transport' => array(
                'Database'  => $data['database'] ?? null,
                'Schema'    => $data['schema'] ?? null,
                'Server'    => $data['server'] ?? null,
                'Port'      => $data['port'] ?? null,
                'Transport' => $data['transport'] ?? null,
                'Socket'    => $data['socket'] ?? null,
                'Path'      => $data['path'] ?? null,
                'SSL/TLS'   => isset( $data['ssl'] ) ? ( $data['ssl'] ? 'Enabled' : 'Disabled' ) : null,
            ),
            'Localization & Encoding' => array(
                'Charset'   => $data['charset'] ?? null,
                'Collation' => $data['collation'] ?? null,
                'Timezone'  => $data['timezone'] ?? null,
                'Locale'    => $data['locale'] ?? null,
            ),
        );

        // 'features' and 'runtime' vary by engine, so their labels are derived
        // from the keys themselves instead of being hardcoded like the groups
        // above.
        foreach ( array( 'features' => 'Features', 'runtime' => 'Runtime' ) as $data_key => $group_title ) {
            if ( empty( $data[ $data_key ] ) || ! is_array( $data[ $data_key ] ) ) {
                continue;
            }

            $labelled = [];

            foreach ( $data[ $data_key ] as $sub_key => $sub_val ) {
                $labelled[ $this->humanize_key( (string) $sub_key ) ] = $sub_val;
            }

            $groups[ $group_title ] = $labelled;
        }

        foreach ( $groups as $group_title => $items ) {
            $filtered = array_filter(
                $items,
                static function ( mixed $val ): bool {
                    return null !== $val && '' !== $val;
                }
            );

            if ( empty( $filtered ) ) {
                continue;
            }

            $this->output->info( sprintf( '[ %s ]', $group_title ) );

            foreach ( $filtered as $label => $val ) {
                $this->output->writeln(
                    sprintf( '  %-22s : %s', $label, $this->format_display_value( $val ) )
                );
            }

            $this->output->writeln( '' );
        }

        if ( ! empty( $data['capabilities'] ) && is_array( $data['capabilities'] ) ) {
            $caps = [];

            foreach ( $data['capabilities'] as $cap => $supported ) {
                // null means "couldn't be determined" (e.g. unknown storage
                // engine) — that is not the same claim as "no", so it's
                // omitted rather than shown as unsupported.
                if ( null === $supported ) {
                    continue;
                }

                $caps[] = sprintf( '%s: %s', $this->humanize_key( (string) $cap ), $supported ? 'yes' : 'no' );
            }

            if ( ! empty( $caps ) ) {
                $this->output->info( '[ Capabilities ]' );
                $this->output->writeln( sprintf( '  %s', implode( '  |  ', $caps ) ) );
                $this->output->writeln( '' );
            }
        }
    }

    /**
     * Format a raw value for console display.
     *
     * @param mixed $val
     * @return string
     */
    private function format_display_value( mixed $val ): string {
        if ( is_bool( $val ) ) {
            return $val ? 'Yes' : 'No';
        }

        if ( is_array( $val ) ) {
            return (string) json_encode( $val, JSON_UNESCAPED_SLASHES );
        }

        return (string) $val;
    }

    /**
     * Convert a snake_case data key into a human-readable label.
     *
     * @param string $key
     * @return string
     */
    private function humanize_key( string $key ): string {
        return ucwords( str_replace( '_', ' ', $key ) );
    }
}