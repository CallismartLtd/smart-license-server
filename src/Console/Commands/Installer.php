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
use SmartLicenseServer\Environments\Application\Auth\FolderOwnership;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Environments\Application\Installation\DatabaseSettings;
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

    /**
     * How many times a question is asked again after an invalid answer.
     */
    protected const MAX_ATTEMPTS = 5;

    public function __construct(
        protected AppInstaller $installer,
        protected Guard $guard,
        protected SetupToken $setup_token,
        protected FolderOwnership $ownership,
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
            'run'           => 'Installs step by step, asking only for what is missing. Safe to run again.',
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
            '   General: ',
            '--force, -f        Replace or rebuild what a subcommand would otherwise leave as it is:',
            '                     run            .env (after confirmation), .htaccess and public/assets.',
            '                                    Roles are never reinstalled by run; use make:roles --force.',
            '                     make:dotenv    Replace the existing .env file (asks for confirmation first).',
            '                     make:htaccess  Rewrite the existing .htaccess file.',
            '                     link:assets    Rebuild public/assets: recreate the link, or switch between',
            '                                    link and copy based on what the server allows now.',
            '                     make:roles     Install the default roles again over existing ones.',
            '',
            '   Full installation (run): ',
            '--skip-admin       Do not create the administrator account now.',
            '--app-url and the database connection options below answer those questions in advance;',
            'anything missing is asked. Steps that are already done are skipped.',
            '',
            '   Creating the administrator account (run, make:admin): ',
            '--admin-name       The administrator\'s name.',
            '--admin-email      The administrator\'s email address.',
            '--admin-password   The administrator\'s password (at least ' . AppInstaller::MIN_ADMIN_PASSWORD_LENGTH . ' characters).',
            'Note: While no account exists, only the user that owns the application folder can create the',
            '      first administrator. After that, make:admin requires a signed-in system administrator.',
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
            '   Connection options (test:db --manual, and run): ',
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
            '--path, -l             Database folder (sqlite). Required for sqlite; not applicable to mysql/pgsql.',
            '--dsn, -D              Raw DSN string that overrides the discrete host/port/socket/path options above.',
            '--flags, -F            Driver connection attributes as a JSON object.',
            '--ssl, -S              SSL options and certificate paths as a JSON object.',
            '--sslmode, -M          SSL enforcement tier. Used by mysql/pgsql; not applicable to sqlite.',
            '--encryption-key, -k   At-rest encryption key. Used by mysql (TDE) and sqlite; not applicable to pgsql.',
            '--strict, -t           Enable strict SQL mode enforcement.',
            '--persistent, -e       Reuse a persistent connection instead of opening a new one.',
            '--timeout, -T          Connection timeout in seconds.',
            '--read, -r             Read replica settings as a JSON object.',
            '--write, -w            Write primary settings as a JSON object.',
            '--sticky, -K           Read from the primary after a write in the same request.',
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
     * Install the application step by step, asking only for what is missing.
     *
     * Every step first checks whether its work is already done and skips it
     * if so, so the command can be stopped and run again at any time. The
     * database settings are asked for, tested and saved when the .env file
     * has none that work, and the administrator account is asked for when no
     * account exists. Ends by recording the installation as complete.
     *
     * @param CommandInput $input
     * @return int
     */
    public function run_wizard( CommandInput $input ) : int {
        $timer = new Stopwatch();
        $timer->start();

        $this->output->info( sprintf( 'Installing %s.', SMLISER_APP_NAME ) );
        $this->output->writeln( 'Steps that are already done are checked and skipped, so you can stop and run this command again at any time.' );

        $steps = array(
            'Checking the server'                         => fn () : int => $this->handle_checks( $input ),
            'Creating folders'                            => fn () : int => $this->make_directories( $input ),
            'Preparing the configuration file (.env)'     => fn () : int => $this->make_dot_env( $input ),
            'Connecting to the database'                  => fn () : int => $this->setup_database( $input ),
            'Creating database tables and default roles'  => fn () : int => $this->make_db_tables( $input ) ?: $this->make_roles( $input, false ),
            'Publishing web files (.htaccess and assets)' => fn () : int => $this->make_dot_htaccess( $input ) ?: $this->link_assets( $input ),
            'Creating the administrator account'          => fn () : int => $this->admin_step( $input ),
        );

        $number = 0;

        foreach ( $steps as $title => $step ) {
            $number++;

            $this->output->newline();
            $this->output->info( sprintf( 'Step %d/%d: %s', $number, count( $steps ), $title ) );

            try {
                $code = $step();
            } catch ( \Throwable $e ) {
                // Anything a step did not handle itself still gets explained and logged.
                $this->report_failure( sprintf( 'Step %d (%s) failed', $number, $title ), $e );
                $code = 1;
            }

            if ( 0 !== $code ) {
                $this->output->newline();
                $this->output->error( sprintf( 'Installation stopped at step %d (%s).', $number, $title ) );
                $this->output->info( sprintf( 'Fix the problem above, then run `%s` again. Completed steps are skipped.', $this->command_line( 'run' ) ) );
                return $code;
            }

            // Visitors see the installation notice from now until the installation is recorded.
            if ( 1 === $number && ! $this->installer->is_installed() ) {
                try {
                    $this->installer->begin_installation();
                } catch ( \RuntimeException $e ) {
                    $this->output->error( sprintf( 'Installation stopped: %s', $e->getMessage() ) );
                    return 1;
                }
            }
        }

        $this->output->newline();
        $this->output->info( 'Finishing' );

        try {
            $has_users = $this->installer->has_users();
        } catch ( DatabaseException $e ) {
            $this->output->error( sprintf( 'Could not check the user accounts: %s', $e->getMessage() ) );
            return 1;
        }

        if ( ! $has_users ) {
            $this->output->warning( 'The installation is not complete until an administrator account exists.' );
            $this->output->info( sprintf( 'Create it with `%s`, or run `%s` again.', $this->command_line( 'make:admin' ), $this->command_line( 'run' ) ) );
            return 0;
        }

        if ( 0 !== $this->mark_installed( $input ) ) {
            $this->output->error( sprintf( 'Resolve the issues above, then run `%s` again.', $this->command_line( 'run' ) ) );
            return 1;
        }

        // The web installer is closed now; its claim token is no longer needed.
        try {
            $this->setup_token->delete();
        } catch ( \Throwable $e ) {
            $this->output->warning( sprintf( 'Could not delete the web installer token: %s', $e->getMessage() ) );
        }

        $this->output->newline();
        $this->output->success( sprintf( '%s is installed (%.1fs).', SMLISER_APP_NAME, $timer->elapsed() ) );

        $app_url = $this->installer->read_app_url();

        if ( null !== $app_url ) {
            $this->output->writeln( sprintf( '   Site:     %s/', $app_url ) );
            $this->output->writeln( sprintf( '   Sign in:  %s/auth/', $app_url ) );
        }

        return 0;
    }

    /**
     * Connect to the database saved in the .env file, or ask for, test and save a new one.
     *
     * Answers can be given in advance with the connection options (the same
     * ones test:db accepts); anything missing is asked. A connection that
     * fails is explained and can be retried with the previous answers as
     * defaults. The settings are written to the .env file only once the
     * connection works.
     *
     * @param CommandInput $input
     * @return int
     */
    protected function setup_database( CommandInput $input ) : int {
        if ( $this->installer->has_database_connection() ) {
            $this->output->success( 'The database is already connected.' );
            return 0;
        }

        $values         = array();
        $saved_password = null;

        try {
            $saved          = $this->installer->read_database_config();
            $values         = DatabaseSettings::values( $saved );
            $saved_password = $saved->password;

            try {
                $this->installer->use_connection( $this->installer->test_db_connection( $saved ) );
                $this->output->success( sprintf( 'Connected to the %s database saved in the .env file.', $this->driver_label( (string) $saved->driver ) ) );
                return 0;
            } catch ( DatabaseException $e ) {
                $this->output->warning( 'The database saved in the .env file could not be reached. ' . DatabaseSettings::explain_error( $e->getMessage(), (string) $saved->driver ) );
                $this->output->writeln( '   ' . $e->getMessage() );
                $this->output->writeln( 'Enter the database details again; your saved values are the defaults.' );
            }
        } catch ( DatabaseException ) {
            // Nothing configured yet: the normal starting point.
        } catch ( \InvalidArgumentException | \RuntimeException $e ) {
            $this->output->warning( sprintf( 'The database settings in the .env file are not valid (%s). Enter them again.', $e->getMessage() ) );
        }

        $given  = $this->database_options( $input );
        $values = array_merge( $values, $given );

        // With the type and name given as options, try them before asking anything.
        $ask = ! isset( $given['driver'], $given['dbname'] );

        if ( $ask ) {
            $this->output->writeln( 'Enter the details of the database this site will use. Press Enter to accept the value in [brackets].' );
        }

        for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
            if ( $ask ) {
                $values = $this->ask_database( $values, null !== $saved_password && '' !== $saved_password );
            }

            $ask = true;

            // An empty password keeps the saved one.
            if ( '' === ( $values['password'] ?? '' ) && null !== $saved_password ) {
                $values['password'] = $saved_password;
            }

            try {
                $config = DatabaseSettings::normalize( $values );
            } catch ( \InvalidArgumentException $e ) {
                $this->output->error( $e->getMessage() );

                if ( null !== $e->getPrevious() ) {
                    $this->output->writeln( '   ' . $e->getPrevious()->getMessage() );
                }

                continue;
            }

            $this->output->writeln( 'Testing the connection...' );

            try {
                $adapter = $this->installer->test_db_connection( $config );
            } catch ( DatabaseException $e ) {
                $this->output->error( 'Could not connect to the database. ' . DatabaseSettings::explain_error( $e->getMessage(), (string) $config->driver ) );
                $this->output->writeln( '   ' . $e->getMessage() );

                if ( $attempt < self::MAX_ATTEMPTS && ! $this->io->confirm( 'Try again?', true ) ) {
                    break;
                }

                continue;
            }

            try {
                $this->installer->make_dot_env_file();
                $this->installer->write_database_config( $config );
                $this->installer->generate_app_secrets();
            } catch ( \RuntimeException $e ) {
                $adapter->close();
                $this->output->error( sprintf( 'The connection works, but the settings could not be saved to the .env file: %s', $e->getMessage() ) );
                return 1;
            }

            $this->installer->use_connection( $adapter );
            $this->output->success( sprintf( 'Connected to the %s database. The settings were saved to the .env file.', $this->driver_label( (string) $config->driver ) ) );

            return 0;
        }

        $this->output->error( 'No working database connection.' );
        $this->output->info( sprintf( 'Check the details with your host, then run `%s` again.', $this->command_line( 'run' ) ) );

        return 1;
    }

    /**
     * Ask for the database settings, offering the given values as defaults.
     *
     * Only the questions that apply to the chosen database type are asked.
     * The table prefix and character set are only asked when the person
     * chooses to change them.
     *
     * @param array<string, string> $values         Current values keyed by DatabaseSettings::FIELDS.
     * @param bool                  $password_saved Whether an empty password answer keeps a saved password.
     * @return array<string, string> The answers.
     */
    protected function ask_database( array $values, bool $password_saved = false ) : array {
        $labels   = array_map( static fn ( array $driver ) : string => "{$driver['label']}. {$driver['help']}", DatabaseSettings::DRIVERS );
        $previous = (string) ( $values['driver'] ?? '' );
        $current  = isset( DatabaseSettings::DRIVERS[ $previous ] ) ? $previous : 'mysql';

        $this->output->newline();

        $choice = $this->io->choice( sprintf( 'Database type [%s]', $current ), $labels, $current );
        $driver = match ( true ) {
            is_string( $choice ) && isset( $labels[ $choice ] ) => $choice,
            false !== array_search( $choice, $labels, true )    => (string) array_search( $choice, $labels, true ),
            default                                             => $current,
        };

        // Defaults of one engine do not fit another.
        if ( $driver !== $previous ) {
            unset( $values['port'], $values['charset'], $values['path'] );
        }

        $values['driver'] = $driver;

        if ( 'sqlite' === $driver ) {
            $values['dbname'] = $this->ask( 'Database file name', $values['dbname'] ?? '' ?: 'smliser' );
            $values['path']   = $this->ask( 'Folder for the database file', $values['path'] ?? '' ?: DatabaseSettings::default_sqlite_dir() );

            return $values;
        }

        $values['host']   = $this->ask( 'Server address', $values['host'] ?? '' ?: DatabaseSettings::DEFAULT_HOST );
        $values['port']   = $this->ask( 'Port', $values['port'] ?? '' ?: (string) DatabaseSettings::DRIVERS[ $driver ]['port'] );
        $values['dbname'] = $this->ask( 'Database name', $values['dbname'] ?? '', true );

        $values['username'] = $this->ask( 'Username', $values['username'] ?? '' );
        $values['password'] = $this->io->secret( $password_saved ? 'Password (press Enter to keep the saved one): ' : 'Password: ' );

        $prefix  = $values['prefix'] ?? '' ?: 'smliser_';
        $charset = $values['charset'] ?? '' ?: (string) DatabaseSettings::DRIVERS[ $driver ]['charset'];

        if ( $this->io->confirm( sprintf( 'Change the table prefix (%s) or character set (%s)? Most sites keep them.', $prefix, $charset ), false ) ) {
            $prefix  = $this->ask( 'Table prefix', $prefix );
            $charset = $this->ask( 'Character set', $charset );
        }

        $values['prefix']  = $prefix;
        $values['charset'] = $charset;

        return $values;
    }

    /**
     * Ask one question with a default answer.
     *
     * The input prompt shows the default itself, in brackets.
     *
     * @param string $label    Question, without punctuation.
     * @param string $default  Answer used when Enter is pressed.
     * @param bool   $required Ask again (a few times) while the answer is empty.
     * @return string The trimmed answer.
     */
    protected function ask( string $label, string $default = '', bool $required = false ) : string {
        $question = "{$label}: ";

        for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
            $answer = trim( $this->io->prompt( $question, $default ) );

            if ( '' !== $answer || ! $required ) {
                return $answer;
            }

            $this->output->error( sprintf( '%s is required.', $label ) );
        }

        return '';
    }

    /**
     * Database answers given as command options.
     *
     * @param CommandInput $input
     * @return array<string, string> Values keyed by DatabaseSettings::FIELDS; options not given are left out.
     */
    protected function database_options( CommandInput $input ) : array {
        $options = array(
            'driver'         => array( 'db-driver', 'd' ),
            'host'           => array( 'host', 'h' ),
            'port'           => array( 'port', 'P' ),
            'dbname'         => array( 'dbname', 'n' ),
            'username'       => array( 'username', 'u' ),
            'password'       => array( 'password', 'p' ),
            'prefix'         => array( 'prefix', 'x' ),
            'charset'        => array( 'charset', 'c' ),
            'path'           => array( 'path', 'l' ),
            'encryption_key' => array( 'encryption-key', 'k' ),
        );

        $values = array();

        foreach ( $options as $field => [ $long, $short ] ) {
            $value = $input->get_option( $long ) ?? $input->get_option( $short );

            if ( is_string( $value ) || is_int( $value ) ) {
                $values[ $field ] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Create the administrator account during run, unless an account already exists.
     *
     * @param CommandInput $input
     * @return int
     */
    protected function admin_step( CommandInput $input ) : int {
        try {
            if ( $this->installer->has_users() ) {
                $this->output->success( 'An account already exists; skipped.' );
                return 0;
            }
        } catch ( DatabaseException $e ) {
            $this->output->error( sprintf( 'Could not check the user accounts: %s', $e->getMessage() ) );
            return 1;
        }

        if ( $this->is_flag_set( $input, 'skip-admin' ) ) {
            $this->output->info( 'Skipped (--skip-admin).' );
            return 0;
        }

        return $this->make_admin( $input );
    }

    /**
     * Readable name of a database driver.
     *
     * @param string $driver Driver ID.
     * @return string
     */
    protected function driver_label( string $driver ) : string {
        return DatabaseSettings::DRIVERS[ $driver ]['label'] ?? $driver;
    }

    /**
     * How to type one of this command's subcommands in the current context.
     *
     * Inside the interactive shell the script name is omitted.
     *
     * @param string $subcommand Subcommand.
     * @return string
     */
    protected function command_line( string $subcommand ) : string {
        $prefix = \is_interactive_shell() ? static::name() : $this->script_name . ' ' . static::name();

        return "{$prefix} {$subcommand}";
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
        $force      = $this->is_forced( $input );

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
     * @param CommandInput $input
     * @return int
     */
    public function link_assets( CommandInput $input ) : int {
        $this->start_timer();

        $force = $this->is_forced( $input );

        try {
            $result = $this->installer->link_public_assets( $force );
        } catch ( \RuntimeException $e ) {
            $this->output->error( $e->getMessage() );
            return 1;
        }

        $source = $this->installer->assets_source_dir();
        $target = $this->installer->assets_public_dir();

        switch ( $result ) {
            case AppInstaller::ASSETS_LINKED:
                $this->output->success( sprintf( '%s %s -> %s', $force ? 'Relinked' : 'Linked', $target, $source ) );
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
                $this->output->info( 'Remove or rename it, then run this command again to use the bundled assets. --force does not remove it.' );
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
        $force      = $this->is_forced( $input );

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
     * @param bool|null         $force Overrides --force/-f; the wizard passes false so it never reinstalls roles.
     * @return int
     */
    public function make_roles( ?CommandInput $input = null, ?bool $force = null ): int {
        $this->start_timer();

        if ( ! $this->ensure_database_connection() ) {
            return 1;
        }

        $force  = $force ?? $this->is_forced( $input );
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
     * Answers can be given with --admin-name, --admin-email and
     * --admin-password; anything missing or invalid is asked. The same rules
     * as the web installer apply (AppInstaller::validate_admin()).
     *
     * Who may run it: while no account exists, the user that owns the
     * application folder (the first administrator is created during
     * installation, before anyone can sign in); after that, a signed-in
     * system administrator.
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

        try {
            $first = ! $this->installer->has_users();
        } catch ( DatabaseException $e ) {
            $this->output->error( sprintf( 'Could not check the user accounts: %s', $e->getMessage() ) );
            return 1;
        }

        if ( ! $this->may_create_admin( $first ) ) {
            return 1;
        }

        $name     = $this->string_option( $input, 'admin-name' );
        $email    = $this->string_option( $input, 'admin-email' );
        $password = $this->string_option( $input, 'admin-password', false );

        for ( $attempt = 1; ; $attempt++ ) {
            if ( $attempt > self::MAX_ATTEMPTS ) {
                $this->output->error( 'Too many invalid answers. No account was created.' );
                return 1;
            }

            if ( '' === $name ) {
                $name = trim( $this->io->prompt( 'Your name: ' ) );
            }

            if ( '' === $email ) {
                $email = trim( $this->io->prompt( 'Your email address: ' ) );
            }

            if ( '' === $password ) {
                // Only trailing line-ending artifacts from terminal input are
                // removed; an intentional space stays part of the password.
                $password = rtrim( $this->io->secret( sprintf( 'Password (at least %d characters): ', AppInstaller::MIN_ADMIN_PASSWORD_LENGTH ) ), "\r\n" );
                $confirm  = rtrim( $this->io->secret( 'Type the password again: ' ), "\r\n" );

                if ( '' === $password ) {
                    $this->output->error( 'Enter a password.' );
                    continue;
                }

                if ( ! hash_equals( $password, $confirm ) ) {
                    $this->output->error( 'The two passwords do not match. Type them again.' );
                    $password = '';
                    continue;
                }
            }

            $errors = $this->installer->validate_admin( $name, $email, $password );

            if ( ! isset( $errors['name'] ) && str_contains( strtolower( $name ), 'admin' ) ) {
                $errors['name'] = 'The name must not contain the word "admin".';
            }

            // No account can use the address before the first one exists.
            if ( ! isset( $errors['email'] ) && ! $first && User::email_exists( $email ) ) {
                $errors['email'] = 'An account with this email address already exists.';
            }

            if ( array() !== $errors ) {
                foreach ( $errors as $field => $message ) {
                    $this->output->error( $message );

                    match ( $field ) {
                        'name'  => $name = '',
                        'email' => $email = '',
                        default => $password = '',
                    };
                }

                continue;
            }

            // DNS lookup: the address may not be able to receive mail.
            if ( ! is_email( $email, true ) ) {
                $this->output->warning( sprintf( 'No mail server was found for %s, so it may not receive email.', $email ) );

                if ( ! $this->io->confirm( 'Use this email address anyway?', false ) ) {
                    $email = '';
                    continue;
                }
            }

            break;
        }

        try {
            $admin = $this->installer->create_admin( name: $name, email: $email, password: $password );

            $role = ContextServiceProvider::get_principal_role( $admin );

            $this->output->success(
                sprintf( 'Admin account for %s has been created successfully.', $admin->get_display_name() )
            );

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
                sprintf( 'A welcome email is queued for %s.', $admin->get_email() )
            );

            return 0;
        } catch ( \Throwable $e ) {
            $this->report_failure( 'The administrator account could not be created', $e );
            return 1;
        }
    }

    /**
     * Explain a failure on screen and write the full details to the error log.
     *
     * The screen gets the cause in one line, plus where it happened when
     * that is not obvious; the log gets the exception class, location and
     * stack trace, so a failure can be investigated after the session ends.
     *
     * @param string     $what What was being done, e.g. "The administrator account could not be created".
     * @param \Throwable $e    The failure.
     * @return void
     */
    protected function report_failure( string $what, \Throwable $e ) : void {
        $this->output->error( sprintf( '%s: %s', $what, $e->getMessage() ) );

        $where = sprintf( '%s:%d', $e->getFile(), $e->getLine() );

        // Expected failures carry a complete message; anything else also gets its location.
        if ( ! $e instanceof DatabaseException && ! $e instanceof \InvalidArgumentException ) {
            $this->output->writeln( sprintf( '   %s in %s', get_class( $e ), $where ) );
        }

        \smliser_log_error(
            sprintf( "[installer] %s: %s (%s in %s)\n%s", $what, $e->getMessage(), get_class( $e ), $where, $e->getTraceAsString() )
        );

        $log = (string) ini_get( 'error_log' );

        $this->output->writeln(
            '' !== $log
                ? sprintf( '   The full details were written to %s', $log )
                : '   The full details were written to the PHP error log.'
        );
    }

    /**
     * Whether the person running this command may create an administrator.
     *
     * A signed-in system administrator always may. While no account exists,
     * so does the user that owns the application folder: nobody can be
     * signed in yet, and that user already controls the installation.
     *
     * @param bool $first Whether this would be the first account.
     * @return bool
     */
    protected function may_create_admin( bool $first ) : bool {
        if ( $this->guard->has_principal() && $this->guard->get_principal()?->is( 'system_admin' ) ) {
            return true;
        }

        if ( ! $first ) {
            $this->output->error( 'You must be logged in as a system admin to perform this action' );
            $this->output->info( 'A system admin must own the app root directory and the CLI credential set to `root`.' );
            return false;
        }

        if ( $this->ownership->is_owner( \SMLISER_ROOT ) ) {
            return true;
        }

        $owner = $this->ownership->owner_name( \SMLISER_ROOT );

        $this->output->error(
            sprintf(
                'The first administrator can only be created by the user that owns the application folder%s.',
                null === $owner ? '' : " ({$owner})"
            )
        );

        $this->output->info(
            null !== $owner && '\\' !== \DIRECTORY_SEPARATOR
                ? sprintf( 'Run it as that user, e.g. `sudo -u %s %s`, or create the administrator in the web installer.', $owner, $this->script_name . ' ' . static::name() . ' make:admin' )
                : 'Run it as that user, or create the administrator in the web installer.'
        );

        return false;
    }

    /**
     * A text option, empty when not given.
     *
     * @param CommandInput $input
     * @param string       $name Option name.
     * @param bool         $trim Trim the value (not for passwords).
     * @return string
     */
    protected function string_option( CommandInput $input, string $name, bool $trim = true ) : string {
        $value = $input->get_option( $name );

        if ( ! is_string( $value ) ) {
            return '';
        }

        return $trim ? trim( $value ) : $value;
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
        $this->output->info( sprintf( 'Enter it at /install on your site (or /?install if that page is not found). It expires %s UTC.', gmdate( 'Y-m-d H:i', (int) $token->expires_at() ) ) );

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
     * Whether --force or its short form -f was given.
     *
     * @param CommandInput|null $input
     * @return bool
     */
    private function is_forced( ?CommandInput $input ) : bool {
        return $this->is_flag_set( $input, 'force', 'f' );
    }

    /**
     * Whether a flag option was given.
     *
     * A bare flag counts as given; an explicit false-like value
     * (--flag=false, --flag=0, --flag=no) does not.
     *
     * @param CommandInput|null $input
     * @param string            $long  Long option name.
     * @param string|null       $short Short option name.
     * @return bool
     */
    private function is_flag_set( ?CommandInput $input, string $long, ?string $short = null ) : bool {
        if ( null === $input ) {
            return false;
        }

        $value = $input->get_option( $long ) ?? ( null === $short ? null : $input->get_option( $short ) );

        if ( null === $value || false === $value ) {
            return false;
        }

        if ( true === $value || '' === $value ) {
            return true;
        }

        return false !== filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
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

        $this->output->success( sprintf( 'Connected to the %s database.', $this->driver_label( (string) $db_config->driver ) ) );

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
                'path'           => $input->get_option( 'path' )           ?? $input->get_option( 'l' ),
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