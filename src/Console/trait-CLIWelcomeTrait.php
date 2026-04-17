<?php
/**
 * CLI Welcome & Info Trait
 *
 * Provides reusable methods to print the application info, welcome banner,
 * and a stylized system info block for interactive CLI sessions.
 *
 * @package SmartLicenseServer\Console
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

defined( 'SMLISER_ABSPATH' ) || exit;

trait CLIWelcomeTrait {
    use CLIAwareTrait;

    /**
     * Input tokens that end the session.
     */
    private const EXIT_TOKENS = [ 'exit', 'quit', 'q' ];

    /**
     * ASCII logo for Smart License Server
     */
    public const ASCII_LOGO = <<<ASCII
   _____                      _     _      _                            _____                          
  / ____|                    | |   | |    (_)                          / ____|                         
 | (___  _ __ ___   __ _ _ __| |_  | |     _  ___ ___ _ __  ___  ___  | (___   ___ _ ____   _____ _ __ 
  \___ \| '_ ` _ \ / _` | '__| __| | |    | |/ __/ _ \ '_ \/ __|/ _ \  \___ \ / _ \ '__\ \ / / _ \ '__|
  ____) | | | | | | (_| | |  | |_  | |____| | (_|  __/ | | \__ \  __/  ____) |  __/ |   \ V /  __/ |   
 |_____/|_| |_| |_|\__,_|_|   \__| |______|_|\___\___|_| |_|___/\___| |_____/ \___|_|    \_/ \___|_|   
                                                                                                       
                                                                                                       
ASCII;

    /**
     * Print application info (name, version, author)
     */
    protected function print_info(): void {
        $app_name = \SMLISER_APP_NAME;
        $version  = SMLISER_VER;
        $author   = 'Callistus Nwachukwu';

        $this->line( $this->colorize( static::ANSI_BOLD, $app_name ) . ' v' . $version );
        $this->line( 'Author: ' . $this->colorize( static::ANSI_CYAN, $author ) );
        $this->newline();
        $this->print_system_info();
    }

    /**
     * Print the welcome banner at the start of an interactive session
     */
    private function print_banner(): void {
        $quit_tokens = implode( '", "', self::EXIT_TOKENS );

        echo PHP_EOL;
        echo self::ASCII_LOGO . PHP_EOL;
        echo PHP_EOL;
        echo sprintf( 'Type "help" to list commands. Type "%s" to quit.', $quit_tokens ) . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Print stylized system info (hostname, OS, IP)
     */
    protected function print_system_info(): void {
        $hostname = 'Unknown';
        if ( function_exists( 'gethostname' ) ) {
            $hostname = gethostname() ?: 'Unknown';
        }

        // Safe OS info
        $os = 'Unknown OS';
        if ( function_exists( 'php_uname' ) ) {
            $os = @php_uname('s') . ' ' . @php_uname('r');
        }

        // Safe IP resolution
        $ip = 'Unknown IP';
        if ( function_exists( 'gethostbyname' ) ) {
            $ip = @gethostbyname( $hostname );
        }

        $info = [
            ['Hostname', $hostname],
            ['OS', $os],
            ['IP Addr', $ip],
        ];

        $width = 80;
        $this->line( str_repeat( '=', $width ) );
        $this->line( $this->colorize( static::ANSI_BOLD, 'SYSTEM INFO' ) );
        $this->line( str_repeat( '-', $width ) );

        foreach ( $info as [$label, $value] ) {
            $this->line(
                sprintf(
                    '  %s : %s',
                    $this->colorize( static::ANSI_YELLOW, $label ),
                    $this->colorize( static::ANSI_GREEN, $value )
                )
            );
        }

        $this->line( str_repeat( '=', $width ) );
        $this->newline();
    }
}