<?php
/**
 * Smart License Server CLI Bootstrap Generator
 *
 * Interactive CLI script to generate the smliser CLI entry point.
 * Prompts for application paths and creates the bootstrap file.
 *
 * Usage:
 *   php make-smliser-cli.php
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
 * Interactive CLI bootstrap generator for SmartLicenseServer.
 *
 * Prompts for paths, generates the bootstrap file, and sets permissions.
 *
 * @since 0.2.0
 */
class CLIBootstrapGenerator {

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
		echo "║   " . str_pad( $title, 61 ) . "║" . PHP_EOL;
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
	 * Normalizes a path by removing trailing slashes and adding a single slash.
	 *
	 * @param string $path The path to normalize.
	 * @return string The normalized path.
	 */
	private function normalize_path( string $path ): string {
		return rtrim( $path, '/\\' ) . '/';
	}

	/**
	 * Generates the CLI bootstrap file content.
	 *
	 * @param array $config Configuration array with base_dir and src_dir.
	 * @return string The PHP file content.
	 */
	private function generate_bootstrap_content( array $config ): string {
		$base_dir = $config['base_dir'];
		$src_dir = $config['src_dir'];

		$php_template = <<<'PHP'
		<?php
		/**
		 * Smart License Server CLI bootstrap file.
		 *
		 * Entry point for running Smart License Server in a command-line
		 * environment. Boots the CLI environment adapter, loads the core
		 * command registry, and delegates dispatch to CLIRunner.
		 *
		 * Usage:
		 *   smliser [command]
		 *   smliser help
		 *
		 * @author  Callistus Nwachukwu
		 * @package SmartLicenseServer\Environments\CLI
		 * @since   0.2.0
		 */

		// Guard — must be run from CLI only.
		if ( 'cli' !== PHP_SAPI ) {
			function_exists( 'http_response_code' ) && http_response_code( 403 );
			exit( 'This script can only be run from the command line.' );
		}

		use SmartLicenseServer\Environments\CLI\CLIEnvironment;

		$config = [
			'app_root'      => __DIR__,
			'base_dir'      => '%s',
			'src_dir'       => '%s',
			'index_file'    => __FILE__
		];

		require_once $config['src_dir'] . 'Environments/bootstrap.php';

		CLIEnvironment::boot();
		PHP;

		return sprintf(
			$php_template,
			$base_dir,
			$src_dir
		);
	}

	/**
	 * Displays the welcome banner.
	 *
	 * @return void
	 */
	private function display_banner(): void {
		$this->print_section( 'Smart License Server CLI Bootstrap Generator' );
		echo "   Version 0.2.0" . PHP_EOL;
		$this->print_line();
	}

	/**
	 * Prompts for application and base directories.
	 *
	 * @return array Configuration array with app_root and base_dir.
	 */
	private function gather_paths(): array {
		// Prompt for application root directory.
		$app_root = $this->prompt_user(
			"Enter the absolute path to the application root directory:" . PHP_EOL . "➜ ",
			[ $this, 'is_valid_directory' ]
		);
		$app_root = $this->normalize_path( $app_root );

		// Prompt for base directory.
		$base_dir = $this->prompt_user(
			PHP_EOL . "Enter the absolute path to the SmartLicenseServer base directory:" . PHP_EOL . "➜ ",
			[ $this, 'is_valid_directory' ]
		);
		$base_dir = $this->normalize_path( $base_dir );

		// Validate that base_dir is under app_root.
		if ( strpos( $base_dir, $app_root ) !== 0 ) {
			$this->print_warning( 'base_dir does not appear to be under app_root.' );
			if ( ! $this->confirm( 'Continue anyway?', false ) ) {
				echo "Aborted." . PHP_EOL;
				exit( 1 );
			}
		}

		// Derive src_dir from base_dir.
		$src_dir = $base_dir . 'src/';
		if ( ! $this->is_valid_directory( $src_dir ) ) {
			$this->print_line();
			$this->print_error( "src directory not found at: $src_dir" );
			exit( 1 );
		}

		return [
			'app_root' => $app_root,
			'base_dir' => $base_dir,
			'src_dir'  => $src_dir,
		];
	}

	/**
	 * Displays the configuration summary.
	 *
	 * @param array $config Configuration array.
	 * @return void
	 */
	private function display_config_summary( array $config ): void {
		$this->print_section( 'Configuration Summary' );
		$this->print_info( "Application Root:  " . $config['app_root'] );
		$this->print_info( "Base Directory:    " . $config['base_dir'] );
		$this->print_info( "Source Directory:  " . $config['src_dir'] );
		$this->print_line();
	}

	/**
	 * Confirms generation with the user.
	 *
	 * @return bool True to proceed, false to abort.
	 */
	private function confirm_generation(): bool {
		return $this->confirm( 'Generate CLI bootstrap file?', true );
	}

	/**
	 * Writes the bootstrap file and sets permissions.
	 *
	 * @param array $config Configuration array with app_root.
	 * @param string $content Bootstrap file content.
	 * @return string|false Output file path on success, false on failure.
	 */
	private function write_and_set_permissions( array $config, string $content ): string|false {
		$output_file = $config['app_root'] . 'smliser';

		if ( file_put_contents( $output_file, $content ) === false ) {
			$this->print_line();
			$this->print_error( "Failed to write file to $output_file" );
			return false;
		}

		// Inherit directory permissions.
		$app_root_perms = fileperms( $config['app_root'] ) & 0777;
		if ( ! chmod( $output_file, $app_root_perms ) ) {
			$this->print_warning( "Could not apply directory permissions to file." );
		}

		// Make executable.
		if ( chmod( $output_file, $app_root_perms | 0111 ) ) {
			$this->print_info( "Permissions: inherited from directory with execute bit set" );
		} else {
			$this->print_warning( "Could not set executable automatically." );
			$this->print_info( "Please run: chmod +x $output_file" );
		}

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
		$this->print_success( "CLI bootstrap file created successfully!" );
		$this->print_info( "Location: $output_file" );
		$this->print_line();
		echo "You can now run your CLI commands:" . PHP_EOL;
		$this->print_info( "$output_file command-name" );
		$this->print_info( "$output_file help" );
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
		$paths = $this->gather_paths();

		// Display summary.
		$this->display_config_summary( $paths );

		// Confirm generation.
		if ( ! $this->confirm_generation() ) {
			echo "Aborted." . PHP_EOL;
			return 0;
		}

		// Generate content.
		$content = $this->generate_bootstrap_content( $paths );

		// Write and set permissions.
		$output_file = $this->write_and_set_permissions( $paths, $content );
		if ( $output_file === false ) {
			return 1;
		}

		// Display success.
		$this->display_success( $output_file );

		return 0;
	}
}

exit( ( new CLIBootstrapGenerator() )->run() );