<?php
/**
 * Installer class file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Environments\Application\Installation\AppInstaller;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

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
        return "smliser {$name} <subcommands> [arguments]";
    }

    public static function help() : string {
        $name       = static::name();
        $app_name   = SMLISER_APP_NAME;

        return \implode( PHP_EOL, [
            "smliser {$name}  check                 Performs environment sanity checks.",
            "smliser {$name}  make:dir              Creates all required directories.",
            "smliser {$name}  make:dotenv           Create a .env file if missing.",
            "smliser {$name}  make:tables           Creates all registered database tables.",
            "smliser {$name}  make:roles            Install default roles.",
            "smliser {$name}  make:admin            Create a human administrator account.",
            "smliser {$name}  make:htaccess         Creates or updates the .htaccess file.",
            '',
            'OPTIONS: ',
            '',
            '   Creating admin account: ',
            '--name             The administrator\'s name.',
            '--email            The administrator\'s email address.',
            '--password         The administrator\'s password.',
            'Note: Creating administrator account requires special authentication (usually done automatically).',
            '',
            '   Creating .env file: ',
            '--dotenv-example-path      The absolute path to the .env.example file. The file will searched for in',
            '                           the parent directory and the runtime directory.',
            "Note: The .env file is required to bootstrap {$app_name}."
        ]);
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

    public function get_subcommands() : array {
        return [
            'help'          => [$this, 'handle_help'],
            'check'         => [$this, 'handle_checks'],
            'make:dir'      => [$this, 'make_directories'],
            'make:dotenv'   => [$this, 'make_dot_env'],
            'make:tables'   => [$this, 'make_db_tables'],
            'make:roles'    => [$this, 'make_roles'],
            'make:admin'    => [$this, 'make_admin'],
            'make:htaccess' => [$this, 'make_dot_htaccess'],
        ];
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
     */
    public function make_admin( CommandInput $input ) : int {
        if ( ! Guard::has_principal() || ! Guard::get_principal()?->is( 'system_admin' ) ) {
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

        $filled_all     = ! empty( $name ) && ! empty( $email ) && ! empty( $password );
        $terminated = false;

        while( true ) {
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
                $name   = $this->io->prompt( 'Enter Admin Name: ' );

                if ( empty( $name ) || \str_contains( \strtolower( $name ), 'admin' ) ) {
                    $name   = '';

                    $error_counter++;
                    $this->output->error( 'Please enter a valid admin name.' );

                    if ( \str_contains( \strtolower( $name ), 'admin' ) ) {
                        $this->output->error( 'Admin name must not contain the word `admin`' );
                    }
                    
                    continue;
                }
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
                $email  = '';

                $this->output->error( 'Sorry the provided email is not available.' );
                continue;
            }
            
            if ( ! $email_is_valid && ! is_email( $email, true ) ) {
                // DNS record not found for the email?
                $this->output->warning( 'The system detected that the provided email address cannot be reached!' );
                if ( ! $this->io->confirm( 'Do you still want to use this email?', false ) ) {
                    $email  = '';
                    continue;
                } else {
                    $email_is_valid = true;
                }

            }

            if ( empty( $password ) ) {
                $password   = $this->io->secret( 'Enter Admin Password: ' );
                if ( empty( $password ) ) {
                    $password = '';
                    
                    $error_counter++;
                    $this->output->error( 'Please enter a valid password.' );
                    continue;
                }

                continue;
            }

            if ( ! $confirmed_pwd ) {
                $pwd    = $this->io->secret( 'Confirm Admin Password: ' );
                $confirmed_pwd  = trim( $pwd ) === trim( $password );

                if ( ! $confirmed_pwd ) {
                    $this->output->error( 'Password mismatch.' );
                }
            }

            $filled_all    = ! empty( $name ) && ! empty( $email ) && ! empty( $password );
        }

        if ( $terminated ) {
            $this->output->error( 'Operation cancelled due to multiple errors!' );
            return 1;
        }

        try {
            $admin  = $this->get_installer()->create_admin( name: $name, email: $email, password: $password );
            
            $role   = ContextServiceProvider::get_principal_role( $admin );
            
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
}