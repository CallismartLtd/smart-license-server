<?php
/**
 * Site theme command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Environments\Application\Theming\Theme;
use SmartLicenseServer\Environments\Application\Theming\ThemeException;
use SmartLicenseServer\Environments\Application\Theming\ThemeInstaller;
use SmartLicenseServer\Environments\Application\Theming\ThemeManager;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Manage the site themes that render this installation's public pages.
 *
 * Not the `theme` command, which manages the WordPress themes hosted in the
 * repository. See ThemeInstaller for how packages are verified and installed.
 */
class SiteThemeCommand extends AbstractCommand {

	/**
	 * Constructor.
	 *
	 * @param ThemeManager    $themes      The installed themes and the active one.
	 * @param ThemeInstaller  $installer   Installs, updates and removes themes.
	 * @param FileSystem      $fs          Reads the checksums beside a package.
	 * @param InputInterface  $io          CLI input interface stream.
	 * @param OutputInterface $output      CLI output interface stream.
	 * @param ScriptName      $script_name Executable binary alias context.
	 */
	public function __construct(
		protected ThemeManager $themes,
		protected ThemeInstaller $installer,
		protected FileSystem $fs,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $io, $output, $script_name );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function name() : string {
		return 'site-theme';
	}

	/**
	 * {@inheritdoc}
	 */
	public function description() : string {
		return 'Install, activate, update and remove the themes of this site\'s public pages.';
	}

	/**
	 * {@inheritdoc}
	 *
	 * Without a subcommand the installed themes are listed, so the subcommand is optional.
	 */
	public function synopsis() : string {
		return sprintf( '%s [list|install|update|check|activate|remove|publish] [options]', $this->command_prefix() );
	}

	/**
	 * {@inheritdoc}
	 */
	public function help() : string {
		$prefix = $this->command_prefix();

		return implode( PHP_EOL, [
			'Subcommands:',
			'  list                  List the installed themes and the active one (the default).',
			'  install <slug>        Install a theme from the marketplace (signed packages only).',
			'  install <file.zip>    Install a theme package. Its <name>.sha256 and <name>.sha256.sig',
			'                        must be beside it, unless --trust-unsigned.',
			'  update <slug>|--all   Update themes installed from the marketplace.',
			'  check                 Show which marketplace themes have a newer version.',
			'  activate <slug>       Render the public pages with a theme ("default" for the built-in one).',
			'  remove <slug>         Remove an installed theme that is not active and not a parent.',
			'  publish [--force]     Publish every installed theme\'s assets in public/ again.',
			'  help                  Show this help message.',
			'',
			'Options:',
			'  --trust-unsigned      install <file.zip>: accept a package without a valid signature.',
			'                        Only for a package you built or received directly.',
			'  --replace             install <file.zip>: replace an installed theme of the same slug',
			'                        with this package (same or newer version).',
			'  --activate            install: activate the theme once it is installed.',
			'  --yes, -y             Do not ask for confirmation.',
			'',
			sprintf( 'Marketplace themes are published on the update server as %s apps named "%s<slug>".', ThemeInstaller::MARKETPLACE_TYPE, ThemeInstaller::MARKETPLACE_PREFIX ),
			'',
			'Examples:',
			"  {$prefix} install aurora --activate",
			"  {$prefix} install ./smliser-theme-aurora-1.2.0.zip",
			"  {$prefix} update --all",
			"  {$prefix} activate default",
		] );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Handlers must stay public: AbstractCommandRouter invokes them as
	 * callables from outside this class.
	 */
	public function get_subcommands() : array {
		return [
			'list'     => [ $this, 'handle_list' ],
			'install'  => [ $this, 'handle_install' ],
			'update'   => [ $this, 'handle_update' ],
			'check'    => [ $this, 'handle_check' ],
			'activate' => [ $this, 'handle_activate' ],
			'remove'   => [ $this, 'handle_remove' ],
			'publish'  => [ $this, 'handle_publish' ],
			'help'     => [ $this, 'handle_help' ],
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * Reached when no subcommand is given: lists the themes.
	 */
	public function run( CommandInput $input ) : int {
		return $this->handle_list( $input );
	}

	/*
	|-------------
	| SUBCOMMANDS
	|-------------
	*/

	/**
	 * List the installed themes and the active one.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_list( CommandInput $input ) : int {
		$active = $this->themes->active()->slug;
		$rows   = [ $this->row( $this->themes->default_theme(), $active, 'Built in' ) ];

		foreach ( $this->themes->installed() as $slug => $theme ) {
			if ( $theme instanceof ThemeException ) {
				$rows[] = [ $slug, '—', '—', 'broken: ' . $theme->getMessage(), '' ];
				continue;
			}

			$record = $this->installer->record( $slug );
			$rows[] = $this->row( $theme, $active, ThemeInstaller::describe_source( $record ) );
		}

		$this->output->table( [ 'Slug', 'Name', 'Version', 'Source', 'Status' ], $rows );

		if ( $this->themes->active_slug() !== $active ) {
			$this->output->warning( sprintf( 'The recorded theme "%s" cannot be used, so the default theme renders the pages; see the error log.', $this->themes->active_slug() ) );
		}

		return 0;
	}

	/**
	 * Install a theme from the marketplace or from a package file.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_install( CommandInput $input ) : int {
		$target  = (string) $input->get_argument( 0, '' );
		$trust   = true === $input->get_option( 'trust-unsigned' );
		$replace = true === $input->get_option( 'replace' );

		if ( '' === $target ) {
			$this->output->error( sprintf( 'Usage: %s <slug>|<file.zip> [--trust-unsigned] [--replace] [--activate]', $this->command_prefix( 'install' ) ) );
			return 1;
		}

		$is_file = str_ends_with( strtolower( $target ), '.zip' );

		if ( ! $is_file && ( $trust || $replace ) ) {
			$this->output->error( '--trust-unsigned and --replace only apply to a package file. Marketplace packages are always verified, and installing an installed theme updates it.' );
			return 1;
		}

		if ( $trust ) {
			$this->output->warning( '--trust-unsigned: a package without a valid signature will be accepted. Use this only for a package you built or received directly.' );
		}

		try {
			if ( $is_file ) {
				if ( ! $this->fs->is_file( $target ) ) {
					$this->output->error( sprintf( '%s does not exist.', $target ) );
					return 1;
				}

				$result = $this->installer->install_zip( $target, $this->sibling( $target, '.sha256' ), $this->sibling( $target, '.sha256.sig' ), $trust, $replace, $this->progress() );
			} else {
				$result = $this->installer->install_from_marketplace( $target, $this->progress() );
			}
		} catch ( ThemeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$activate = true === $input->get_option( 'activate' );

		if ( null === $result ) {
			$this->output->success( sprintf( 'The theme "%s" is already the latest version.', $target ) );
		} else {
			$this->report_result( $result, ! $activate );
		}

		return $activate ? $this->activate( null === $result ? $target : $result['theme']->slug ) : 0;
	}

	/**
	 * Update one theme, or every theme installed from the marketplace.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_update( CommandInput $input ) : int {
		$slug  = (string) $input->get_argument( 0, '' );
		$slugs = true === $input->get_option( 'all' ) ? array_keys( $this->installer->check_updates() ) : ( '' === $slug ? [] : [ $slug ] );

		if ( '' === $slug && true !== $input->get_option( 'all' ) ) {
			$this->output->error( sprintf( 'Usage: %s <slug>|--all', $this->command_prefix( 'update' ) ) );
			return 1;
		}

		if ( [] === $slugs ) {
			$this->output->info( 'No installed theme comes from the marketplace.' );
			return 0;
		}

		$failed = 0;

		foreach ( $slugs as $theme ) {
			try {
				$result = $this->installer->update( $theme, $this->progress() );
			} catch ( ThemeException $e ) {
				$this->output->error( sprintf( '%s: %s', $theme, $e->getMessage() ) );
				++$failed;
				continue;
			}

			if ( null === $result ) {
				$this->output->success( sprintf( 'The theme "%s" is up to date.', $theme ) );
			} else {
				$this->report_result( $result );
			}
		}

		return $failed > 0 ? 1 : 0;
	}

	/**
	 * Show which marketplace themes have a newer version.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_check( CommandInput $input ) : int {
		$results = $this->installer->check_updates();

		if ( [] === $results ) {
			$this->output->info( 'No installed theme comes from the marketplace.' );
			return 0;
		}

		$rows = [];

		foreach ( $results as $slug => $result ) {
			$rows[] = [
				$slug,
				$result['installed'] ?? '—',
				$result['latest'] ?? '—',
				null !== $result['error'] ? 'error: ' . $result['error'] : ( $result['update'] ? 'update available' : 'up to date' ),
			];
		}

		$this->output->table( [ 'Slug', 'Installed', 'Latest', 'Status' ], $rows );

		return 0;
	}

	/**
	 * Activate a theme.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_activate( CommandInput $input ) : int {
		$slug = (string) $input->get_argument( 0, '' );

		if ( '' === $slug ) {
			$this->output->error( sprintf( 'Usage: %s <slug>', $this->command_prefix( 'activate' ) ) );
			return 1;
		}

		return $this->activate( $slug );
	}

	/**
	 * Remove a theme.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_remove( CommandInput $input ) : int {
		$slug = (string) $input->get_argument( 0, '' );
		$yes  = true === $input->get_option( 'y' ) || true === $input->get_option( 'yes' );

		if ( '' === $slug ) {
			$this->output->error( sprintf( 'Usage: %s <slug> [--yes]', $this->command_prefix( 'remove' ) ) );
			return 1;
		}

		if ( ! $yes && ! $this->io->confirm( sprintf( 'Remove the theme "%s" and its files?', $slug ), false ) ) {
			$this->output->info( 'Nothing was changed.' );
			return 0;
		}

		try {
			$warnings = $this->installer->remove_theme( $slug );
		} catch ( ThemeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		foreach ( $warnings as $warning ) {
			$this->output->warning( $warning );
		}

		$this->output->success( sprintf( 'The theme "%s" was removed.', $slug ) );

		return 0;
	}

	/**
	 * Publish every installed theme's assets again.
	 *
	 * @param CommandInput $input
	 * @return int
	 */
	public function handle_publish( CommandInput $input ) : int {
		$results = $this->installer->publish_all( true === $input->get_option( 'force' ) );

		if ( [] === $results ) {
			$this->output->info( 'No theme is installed; the default theme\'s assets are published with the core assets (`installer link:assets`).' );
			return 0;
		}

		$rows = [];

		foreach ( $results as $slug => $result ) {
			$rows[] = [ $slug, $result ];
		}

		$this->output->table( [ 'Theme', 'Assets' ], $rows );

		return 0;
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Activate a theme and say so.
	 *
	 * @param string $slug
	 * @return int
	 */
	private function activate( string $slug ) : int {
		try {
			$theme = $this->themes->activate( $slug );
		} catch ( ThemeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( sprintf( '%s (%s) now renders the public pages.', $theme->name, $theme->slug ) );

		return 0;
	}

	/**
	 * Print what an install or update did.
	 *
	 * @param array{theme: Theme, previous: ?string, record: array<string, mixed>, warnings: string[]} $result
	 * @param bool $hint Say how to activate the theme when it is not active.
	 * @return void
	 */
	private function report_result( array $result, bool $hint = true ) : void {
		foreach ( $result['warnings'] as $warning ) {
			$this->output->warning( $warning );
		}

		$theme = $result['theme'];

		$this->output->success( null === $result['previous']
			? sprintf( 'Installed %s (%s) %s.', $theme->name, $theme->slug, $theme->version )
			: sprintf( 'Updated %s (%s) from %s to %s.', $theme->name, $theme->slug, $result['previous'], $theme->version ) );
		$this->output->info( sprintf( 'SHA-256:   %s', (string) ( $result['record']['sha256'] ?? '' ) ) );
		$this->output->info( sprintf( 'Signed by: %s', $result['record']['signed_by'] ?? 'nobody (trusted unsigned)' ) );

		if ( $hint && $this->themes->active_slug() !== $theme->slug ) {
			$this->output->info( sprintf( 'Run `%s %s` to use it.', $this->command_prefix( 'activate' ), $theme->slug ) );
		}
	}

	/**
	 * A table row for a theme.
	 *
	 * @param Theme  $theme
	 * @param string $active Slug of the theme rendering the pages.
	 * @param string $source
	 * @return string[]
	 */
	private function row( Theme $theme, string $active, string $source ) : array {
		$status = $theme->slug === $active ? 'active' : '';

		if ( ! $theme->supports( \SMLISER_VER ) ) {
			$status = sprintf( 'needs app %s', $theme->requires );
		}

		if ( null !== $theme->parent && ThemeManager::DEFAULT_SLUG !== $theme->parent ) {
			$status = trim( $status . ' child of ' . $theme->parent );
		}

		return [ $theme->slug, $theme->name, $theme->version, $source, $status ];
	}

	/**
	 * Contents of a file beside a package, e.g. its .sha256.
	 *
	 * @param string $zip    Package path.
	 * @param string $suffix Suffix replacing ".zip".
	 * @return string|null
	 */
	private function sibling( string $zip, string $suffix ) : ?string {
		$path = preg_replace( '/\.zip$/i', '', $zip ) . $suffix;

		if ( ! $this->fs->is_file( $path ) ) {
			return null;
		}

		$contents = $this->fs->get_contents( $path );

		return is_string( $contents ) ? $contents : null;
	}

	/**
	 * Print installer progress.
	 *
	 * @return callable
	 */
	private function progress() : callable {
		return function ( string $step, string $status, string $message ) : void {
			$line = sprintf( '[%s] %s', $step, $message );

			match ( $status ) {
				ThemeInstaller::STATUS_OK      => $this->output->success( $line ),
				ThemeInstaller::STATUS_WARNING => $this->output->warning( $line ),
				default                        => $this->output->info( $line ),
			};
		};
	}
}
