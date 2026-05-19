<?php
/**
 * Smart License Server WordPress Plugin Bootstrap Generator
 *
 * Interactive CLI script to generate the WordPress plugin bootstrap file.
 * Prompts for WordPress path and creates the main plugin file.
 *
 * Usage:
 *   php make-smliser-wordpress.php
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Setup
 * @since   0.2.0
 */

// Guard — must be run from CLI only.
if ( 'cli' !== PHP_SAPI ) {
	function_exists( 'http_response_code' ) && http_response_code( 403 );
	exit( "This script can only be run from the command line." . PHP_EOL );
}

/**
 * Interactive WordPress plugin bootstrap generator for SmartLicenseServer.
 *
 * Prompts for WordPress installation path and generates the main plugin file.
 */
class WordPressBootstrapGenerator {
	private const string VERSION = '0.2.0';

	/**
	 * Checks if the terminal supports colors.
	 *
	 * @return bool True if colors are supported, false otherwise.
	 */
	private function supports_color(): bool {
		// Check for NO_COLOR environment variable (standard).
		if ( getenv( 'NO_COLOR' ) !== false ) {
			return false;
		}

		// Check for FORCE_COLOR environment variable.
		if ( getenv( 'FORCE_COLOR' ) !== false ) {
			return true;
		}

		// On Windows, check for ANSICON or ConEmu.
		if ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
			return false !== getenv( 'ANSICON' ) || false !== getenv( 'ConEmuANSI' );
		}

		// Unix-like systems typically support colors.
		return true;
	}

	/**
	 * Wraps text with ANSI color codes.
	 *
	 * @param string $text Text to colorize.
	 * @param string $color Color name (red, green, yellow, blue, cyan, white).
	 * @return string Colored text or plain text if colors unsupported.
	 */
	private function colorize( string $text, string $color ): string {
		if ( ! $this->supports_color() ) {
			return $text;
		}

		$colors = [
			'red'    => "\033[31m",
			'green'  => "\033[32m",
			'yellow' => "\033[33m",
			'blue'   => "\033[34m",
			'cyan'   => "\033[36m",
			'white'  => "\033[37m",
			'reset'  => "\033[0m",
		];

		$color_code = $colors[ $color ] ?? $colors['white'];
		return $color_code . $text . $colors['reset'];
	}

	/**
	 * Prints an empty line.
	 *
	 * @return void
	 */
	private function print_line(): void {
		echo PHP_EOL;
	}

	/**
	 * Prints a success message.
	 *
	 * @param string $message The message to print.
	 * @return void
	 */
	private function print_success( string $message ): void {
		echo "✅ " . $this->colorize( $message, 'green' ) . PHP_EOL;
	}

	/**
	 * Prints an error message.
	 *
	 * @param string $message The message to print.
	 * @return void
	 */
	private function print_error( string $message ): void {
		echo "  ❌ " . $this->colorize( $message, 'red' ) . PHP_EOL;
	}

	/**
	 * Prints a warning message.
	 *
	 * @param string $message The message to print.
	 * @return void
	 */
	private function print_warning( string $message ): void {
		echo "  ⚠️  " . $this->colorize( $message, 'yellow' ) . PHP_EOL;
	}

	/**
	 * Prints an info message.
	 *
	 * @param string $message The message to print.
	 * @return void
	 */
	private function print_info( string $message ): void {
		echo "   " . $this->colorize( $message, 'cyan' ) . PHP_EOL;
	}

	/**
	 * Prints a section header.
	 *
	 * @param string $title The section title.
	 * @return void
	 */
	private function print_section( string $title ): void {
		$this->print_line();
		echo "╔════════════════════════════════════════════════════════════════╗" . PHP_EOL;
		echo "║   " . str_pad( $title, 59 ) . "║" . PHP_EOL;
		echo "╚════════════════════════════════════════════════════════════════╝" . PHP_EOL;
	}

	/**
	 * Prompts the user for a yes/no confirmation.
	 *
	 * @param string $question The prompt text.
	 * @param bool $default Default value if user presses enter (true for yes, false for no).
	 * @return bool User's choice.
	 */
	private function confirm( string $question, bool $default = true ): bool {
		$prompt = $default ? '[Y/n]' : '[y/N]';
		$question_with_prompt = $question . ' ' . $prompt . ':' . PHP_EOL . '➜ ';

		while ( true ) {
			// Use readline if available, otherwise fall back to fgets.
			if ( function_exists( 'readline' ) ) {
				$input = readline( $question_with_prompt );
				if ( $input === false ) {
					$this->print_line();
					exit( 1 );
				}
			} else {
				echo $question_with_prompt;
				$input = trim( fgets( STDIN ) );
			}

			$input = strtolower( trim( $input ) );

			// Empty input uses default.
			if ( empty( $input ) ) {
				return $default;
			}

			// Accept y/yes or n/no.
			if ( 'y' === $input || 'yes' === $input ) {
				return true;
			}

			if ( 'n' === $input || 'no' === $input ) {
				return false;
			}

			$this->print_error( 'Please enter y, n, yes, no, or press enter for default.' );
		}
	}

	/**
	 * Prompts the user for input with a question and optional validation.
	 *
	 * @param string $question The prompt text.
	 * @param callable|null $validator Optional validation callback.
	 * @return string The user's input.
	 */
	private function prompt_user( string $question, ?callable $validator = null ): string {
		while ( true ) {
			// Use readline if available, otherwise fall back to fgets.
			if ( function_exists( 'readline' ) ) {
				$input = readline( $question );
				if ( $input === false ) {
					$this->print_line();
					exit( 1 );
				}
			} else {
				echo $question;
				$input = trim( fgets( STDIN ) );
			}

			$input = trim( $input );

			if ( empty( $input ) ) {
				$this->print_error( 'Input cannot be empty. Please try again.' );
				continue;
			}

			if ( $validator && ! call_user_func( $validator, $input ) ) {
				$this->print_error( 'Invalid input. Please try again.' );
				continue;
			}

			return $input;
		}
	}

	/**
	 * Validates that a path exists and is a directory.
	 *
	 * @param string $path The path to validate.
	 * @return bool True if valid directory, false otherwise.
	 */
	private function is_valid_directory( string $path ): bool {
		return is_dir( $path ) && is_readable( $path );
	}

	/**
	 * Validates that WordPress is installed in a directory.
	 *
	 * @param string $path The path to validate.
	 * @return bool True if WordPress is found, false otherwise.
	 */
	private function is_wordpress_root( string $path ): bool {
		return $this->is_valid_directory( $path ) && file_exists( $path . 'wp-load.php' );
	}

	/**
	 * Normalizes a path by removing trailing slashes and adding a single slash.
	 *
	 * @param string $path The path to normalize.
	 * @return string The normalized path.
	 */
	private function normalize_path( string $path ): string {
		return rtrim( $path, '/\\' ) . '/';
	}

	/**
	 * Generates the WordPress plugin bootstrap file content.
	 *
	 * @return string The PHP file content.
	 */
	private function generate_plugin_content(): string {

		$php_template = <<<'PHP'
        <?php
        /*
        * Plugin Name:         Smart License Server
        * Plugin URI:          https://callismart.com.ng/smart-license-server
        * Description:         Private plugin, themes and software repository with licensing and monetization feature.
        * Author:              Callistus Nwachukwu
        * Author URI:          https://callismart.com.ng/callistus
        * Version:             %s
        * Requires at least:   6.8
        * Requires PHP:        8.4
        */

        use SmartLicenseServer\Environments\WordPress\WordPressEnvironment;

        defined( 'ABSPATH' ) || exit;
        if ( defined( 'SMLISER_ROOT' ) ) return;

        $config = [
            'app_root'      => ABSPATH,
            'base_dir'      => __DIR__,
            'base_dir_url'  => plugin_dir_url( __FILE__ ),
            'src_dir'       => __DIR__ . '/src/',
            'index_file'    => __FILE__
        ];

        require_once $config['src_dir'] . 'bootstrap.php';

        WordPressEnvironment::boot();
        PHP;

		return sprintf( $php_template, self::VERSION );
	}

	/**
	 * Displays the welcome banner.
	 *
	 * @return void
	 */
	private function display_banner(): void {
		$this->print_section( 'Smart License Server WordPress Plugin Generator' );
		echo "   Version " . self::VERSION . PHP_EOL;
		$this->print_line();
	}

	/**
	 * Prompts for WordPress installation path and plugin directory.
	 *
	 * @return array Configuration array with paths.
	 */
	private function gather_paths(): array {
		// Prompt for WordPress root directory.
		$wp_root = $this->prompt_user(
			"Enter the absolute path to your WordPress installation:" . PHP_EOL . "➜ ",
			[ $this, 'is_wordpress_root' ]
		);
		$wp_root = $this->normalize_path( $wp_root );

		// Prompt for plugin directory name.
		$plugin_dir_name = $this->prompt_user(
			PHP_EOL . "Enter the plugin directory name (e.g., smart-license-server):" . PHP_EOL . "➜ "
		);
		$plugin_dir_name = $this->sanitize_dirname( $plugin_dir_name );

		// Derive plugin base directory.
		$plugin_base_dir = $wp_root . 'wp-content/plugins/' . $plugin_dir_name . '/';

		// Check if plugin directory exists.
		if ( ! $this->is_valid_directory( $plugin_base_dir ) ) {
			$this->print_error( "Plugin directory not found at: $plugin_base_dir" );
			$this->print_info( "Please ensure the plugin directory exists before running this script." );
			exit( 1 );
		}

		// Derive src_dir from plugin base_dir.
		$src_dir = $plugin_base_dir . 'src/';
		if ( ! $this->is_valid_directory( $src_dir ) ) {
			$this->print_error( "src directory not found at: $src_dir" );
			$this->print_info( "Please ensure the src directory exists before running this script." );
			exit( 1 );
		}

		return [
			'wp_root'           => $wp_root,
			'plugin_dir_name'   => $plugin_dir_name,
			'base_dir'          => $plugin_base_dir,
			'src_dir'           => $src_dir,
		];
	}

	/**
	 * Sanitizes a directory name using only safe characters.
	 *
	 * @param string $name The directory name to sanitize.
	 * @return string Sanitized directory name.
	 */
	private function sanitize_dirname( string $name ): string {
		$name = strtolower( trim( $name ) );
		$name = preg_replace( '/[^a-z0-9\-_]/', '', $name );
		return trim( $name, '-_' );
	}

	/**
	 * Displays the configuration summary.
	 *
	 * @param array $config Configuration array.
	 * @return void
	 */
	private function display_config_summary( array $config ): void {
		$this->print_section( 'Configuration Summary' );
		$this->print_info( "WordPress Root:   " . $config['wp_root'] );
		$this->print_info( "Plugin Directory: " . $config['base_dir'] );
		$this->print_info( "Source Directory: " . $config['src_dir'] );
		$this->print_line();
	}

	/**
	 * Writes the plugin bootstrap file.
	 *
	 * @param array $config Configuration array.
	 * @param string $content Plugin file content.
	 * @return string|false Output file path on success, false on failure.
	 */
	private function write_plugin_file( array $config, string $content ): string|false {
		$output_file = $config['base_dir'] . 'smart-license-server.php';

		if ( file_put_contents( $output_file, $content ) === false ) {
			$this->print_line();
			$this->print_error( "Failed to write plugin file to $output_file" );
			return false;
		}

		// Set permissions.
		chmod( $output_file, 0644 );

		return $output_file;
	}

	/**
	 * Displays the success message.
	 *
	 * @param string $output_file Output file path.
	 * @return void
	 */
	private function display_success( string $output_file ): void {
		$this->print_line();
		$this->print_success( "WordPress plugin bootstrap file created successfully!" );
		$this->print_info( "Location: $output_file" );
		$this->print_line();
		echo "Next steps:" . PHP_EOL;
		$this->print_info( "1. Ensure your source code is in the src/ directory" );
		$this->print_info( "2. Activate the plugin in WordPress admin" );
		$this->print_info( "3. The plugin will be initialized on WordPress load" );
		$this->print_line();
	}

	/**
	 * Runs the interactive generator.
	 *
	 * @return int Exit code.
	 */
	public function run(): int {
		$this->display_banner();

		// Gather paths from user.
		$config = $this->gather_paths();

		// Display summary.
		$this->display_config_summary( $config );

		// Confirm generation.
		if ( ! $this->confirm( 'Generate plugin bootstrap file?', true ) ) {
			echo "Aborted." . PHP_EOL;
			return 0;
		}

		// Generate content.
		$content = $this->generate_plugin_content();

		// Write plugin file.
		$output_file = $this->write_plugin_file( $config, $content );
		if ( $output_file === false ) {
			return 1;
		}

		// Display success.
		$this->display_success( $output_file );

		return 0;
	}
}

// Instantiate and run.
$generator = new WordPressBootstrapGenerator();
exit( $generator->run() );