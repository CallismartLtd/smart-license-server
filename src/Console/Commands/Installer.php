<?php
/**
 * Installer class file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Console\Commands;

use Callismart\DBPrism\DatabaseInfoDTO;
use Callismart\DBPrism\DBConfigDTO;
use Callismart\DBPrism\Inspection\Inspector;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Utils\Stopwatch;

/**
 * Handles installation processes through the console.
 */
class Installer extends AbstractCommand {

    /**
     * The core installer object.
     * 
     * @var AppInstaller
     */
    protected AppInstaller $installer;

    /**
     * Get the installer object.
     * 
     * @return AppInstaller
     */
    protected function get_installer() : AppInstaller {
        if ( ! isset( $this->installer ) ) {
            $this->installer = new AppInstaller();
        }

        return $this->installer;
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
    public static function description() : string {
        return 'Handle installation.';
    }

    public static function synopsis() : string {
        $name   = static::name();
        return "smliser {$name} <subcommands> [options]";
    }

    public static function help() : string {
        $name     = static::name();
        $app_name = SMLISER_APP_NAME;

        $commands = [
            'run'           => 'Executes full automated installation wizard.',
            'check'         => 'Performs environment sanity checks.',
            'check:db'      => 'Tests a database configuration without installing.',
            'make:dir'      => 'Creates all required directories.',
            'make:dotenv'   => 'Create a .env file if missing.',
            'make:tables'   => 'Creates all registered database tables.',
            'make:roles'    => 'Install default roles.',
            'make:admin'    => 'Create a human administrator account.',
            'make:htaccess' => 'Creates or updates the .htaccess file.',
            'help'          => 'Displays this help message.',
        ];

        $command_width = max( array_map( 'strlen', array_keys( $commands ) ) );
        $command_format = 'smliser ' . $name . '  %-' . $command_width . 's   %s';

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
            '--force            Force overwrite existing configuration files.',
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
            "Note: The .env file is required to bootstrap {$app_name}.",
            '',
            '   Creating .htaccess file: ',
            '--htaccess-example-path    The absolute path to the .htaccess.example file. The file will be searched for in',
            '                           the parent directory and the runtime directory.',
            '',
            '   Testing a database connection (check:db): ',
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
            'check:db'      => [$this, 'check_database'],
            'make:dir'      => [$this, 'make_directories'],
            'make:dotenv'   => [$this, 'make_dot_env'],
            'make:tables'   => [$this, 'make_db_tables'],
            'make:roles'    => [$this, 'make_roles'],
            'make:admin'    => [$this, 'make_admin'],
            'make:htaccess' => [$this, 'make_dot_htaccess'],
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
        $this->output->info( '--- Step 1/6: Checking Environment ---' );

        sleep(1);

        $code = $this->handle_checks( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Environment sanity checks failed.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 2: Directories.
        $this->output->info( '--- Step 2/6: Creating Directories ---' );

        sleep(1);

        $code = $this->make_directories( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Directory creation failed.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 3: Environment File.
        $this->output->info( '--- Step 3/6: Bootstrapping .env File ---' );

        sleep(1);

        $code = $this->make_dot_env( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Failed to create .env file.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 4: Web Server (.htaccess) Configuration.
        $this->output->info( '--- Step 4/6: Writing Apache Web Rules (.htaccess) ---' );

        sleep(1);

        $code = $this->make_dot_htaccess( $input );
        if ( 0 !== $code ) {
            $this->output->error( 'Installation aborted: Failed to create .htaccess file.' );
            return $code;
        }
        $this->output->writeln( '' );

        // Step 5: Database Schema & Default Roles.
        $this->output->info( '--- Step 5/6: Migrating Database Schema & Roles ---' );

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

        // Step 6: Administrator Account Creation.
        $this->output->info( '--- Step 6/6: Administrator Account Setup ---' );

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
        $installer = $this->get_installer();

        $success_callback = function( string $check, string $status, string $message ) use ( &$results ) {
            $results[] = [ $check, "<info>{$status}</info>", $message ];
        };

        $failure_callback = function( string $check, string $status, string $message ) use ( &$results ) {
            $tag       = 'CRITICAL' === $status ? 'error' : 'comment';
            $results[] = [ $check, "<{$tag}>{$status}</{$tag}>", $message ];
        };

        $report = $installer->verify_environment_sanity( $success_callback, $failure_callback );

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
        $installer  = $this->get_installer();

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
        $force      = $input->get_option( 'force', false );

        try {
            $env_file   = $this->get_installer()->make_dot_env_file( $path_to_eg, $force );

            $this->output->success( 'The env file has been created successfully.' );
            $this->output->writeln(
                implode( \PHP_EOL, [
                    'Path to .env file: ',
                    "   {$env_file}"
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
            $htaccess_file = $this->get_installer()->make_htaccess_file( $path_to_eg, $force );

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

        $this->output->progress_start(
            count( SchemaRegistry::instance()->all() ),
            'Creating database tables...'
        );

        $this->get_installer()->create_tables( $callback, $callback );

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

        $this->get_installer()->install_default_roles( $callback, $callback );

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
            $admin = $this->get_installer()->create_admin( name: $name, email: $email, password: $password );

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
     * Test the configured database connection.
     *
     * @param CommandInput|null $input
     * @return int
     */
    public function check_database( ?CommandInput $input = null ): int {
        $this->start_timer();

        if ( ! $input ) {
            $this->output->error( 'Missing command input.' );
            return 1;
        }

        $driver = $input->get_option( 'db-driver' ) ?? $input->get_option( 'd' );

        if ( empty( $driver ) ) {
            $this->output->error( 'Option --db-driver (-d) is required.' );
            return 1;
        }

        $db_name = $input->get_option( 'dbname' ) ?? $input->get_option( 'n' );

        if ( empty( $db_name ) ) {
            $this->output->error( 'Option --dbname (-n) is required.' );
            return 1;
        }

        $this->output->info( 'Testing database configuration...' );
        $this->output->writeln( '' );

        try {
            $db_config = new DBConfigDTO( [
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

            $dbal = $this->get_installer()->test_db_connection( $db_config );

            $this->output->success( 'Database configuration passed!' );
            $this->output->writeln( '' );

            $inspector = new Inspector( $dbal );
            $info      = $inspector->get_database_info();

            $this->render_database_info( $info );

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

            $labelled = array();

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
            $caps = array();

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