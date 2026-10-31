<?php
/**
 * Shared command for installing and managing site modules.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use RuntimeException;
use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Environments\Application\Modules\Module;
use SmartLicenseServer\Environments\Application\Modules\ModuleInstaller;
use SmartLicenseServer\Environments\Application\Modules\ModuleManager;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Common package and marketplace operations for site themes and extensions.
 * Type-specific behavior (activation, enabled state, and removal constraints)
 * stays in the concrete commands and installers.
 */
abstract class AbstractModuleCommand extends AbstractCommand {

	public function __construct(
		protected ModuleManager $modules,
		protected ModuleInstaller $installer,
		protected FileSystem $fs,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $io, $output, $script_name );
	}

	/** Singular noun used in user-facing messages. */
	abstract protected function noun() : string;

	/** Result key returned by the installer (theme or extension). */
	abstract protected function result_key() : string;

	/** Remove this type through its type-specific installer API. */
	abstract protected function remove_module( string $slug ) : array;

	/** State shown in the list table. */
	abstract protected function module_status( Module $module ) : string;

	/** Optional action subcommands such as activate or enable/disable. */
	protected function type_subcommands() : array {
		return [];
	}

	/** Extra options and examples for the command help. */
	protected function type_help() : array {
		return [];
	}

	/** Option that applies the type after installation (activate/enable). */
	protected function after_install_option() : ?string {
		return null;
	}

	/** Apply this module after a successful installation when requested. */
	protected function after_install( string $slug ) : int {
		return 0;
	}

	/** Command to run to activate or enable a module after installation. */
	protected function application_hint( Module $module ) : string {
		return sprintf( 'Run `%s` to apply this %s.', $this->command_prefix(), $this->noun() );
	}

	public function description() : string {
		return sprintf( 'Install and manage this site\'s %ss.', $this->noun() );
	}

	public function synopsis() : string {
		return sprintf( '%s [list|install|update|check|remove|publish] [options]', $this->command_prefix() );
	}

	public function help() : string {
		$prefix = $this->command_prefix();
		$lines = [
			'Subcommands:',
			sprintf( '  list                  List installed %ss (the default).', $this->noun() ),
			sprintf( '  install <slug>        Install or update a %s from the marketplace.', $this->noun() ),
			'  install <file.zip>    Install a local package; its .sha256 and .sha256.sig files',
			'                        must be beside it unless --trust-unsigned is used.',
			sprintf( '  update <slug>|--all   Update %ss installed from the marketplace.', $this->noun() ),
			'  check                 Show available marketplace updates.',
			sprintf( '  remove <slug>         Remove an installed %s.', $this->noun() ),
			'  publish [--force]     Publish installed module assets in public/ again.',
			'  help                  Show this help message.',
			'',
			'Options:',
			'  --trust-unsigned      Accept a local package without a valid signature.',
			'  --replace             Replace an installed module with a local package.',
			'  --yes, -y             Do not ask for confirmation when removing.',
		];

		if ( null !== ( $option = $this->after_install_option() ) ) {
			$lines[] = sprintf( '  --%s             Apply the %s after installation.', $option, $this->noun() );
		}

		$lines = array_merge( $lines, $this->type_help(), [
			'',
			'Examples:',
			sprintf( '  %s install example-%s', $prefix, $this->noun() ),
			sprintf( '  %s install ./example-1.0.0.zip', $prefix ),
			sprintf( '  %s update --all', $prefix ),
		] );

		return implode( PHP_EOL, $lines );
	}

	public function get_subcommands() : array {
		return [
			'list'     => [ $this, 'handle_list' ],
			'install'  => [ $this, 'handle_install' ],
			'update'   => [ $this, 'handle_update' ],
			'check'    => [ $this, 'handle_check' ],
			'remove'   => [ $this, 'handle_remove' ],
			'publish'  => [ $this, 'handle_publish' ],
			'help'     => [ $this, 'handle_help' ],
		] + $this->type_subcommands();
	}

	public function run( CommandInput $input ) : int {
		return $this->handle_list( $input );
	}

	public function handle_list( CommandInput $input ) : int {
		$rows = [];
		$this->prepend_builtin_rows( $rows );

		foreach ( $this->modules->installed() as $slug => $module ) {
			if ( ! $module instanceof Module ) {
				$rows[] = [ $slug, '—', '—', '—', 'broken: ' . $module->getMessage() ];
				continue;
			}

			$rows[] = [ $module->slug, $module->name, $module->version, ModuleInstaller::describe_source( $this->installer->record( $slug ) ), $this->module_status( $module ) ];
		}

		$this->output->table( [ 'Slug', 'Name', 'Version', 'Source', 'Status' ], $rows );
		$this->list_notice();

		return 0;
	}

	/** Add special built-in entries (for example the default theme). */
	protected function prepend_builtin_rows( array &$rows ) : void {}

	/** Print a type-specific warning after the list, when needed. */
	protected function list_notice() : void {}

	public function handle_install( CommandInput $input ) : int {
		$target  = (string) $input->get_argument( 0, '' );
		$trust   = true === $input->get_option( 'trust-unsigned' );
		$replace = true === $input->get_option( 'replace' );

		if ( '' === $target ) {
			$this->output->error( sprintf( 'Usage: %s <slug>|<file.zip> [--trust-unsigned] [--replace]', $this->command_prefix( 'install' ) ) );
			return 1;
		}

		$is_file = str_ends_with( strtolower( $target ), '.zip' );

		if ( ! $is_file && ( $trust || $replace ) ) {
			$this->output->error( '--trust-unsigned and --replace only apply to a package file. Marketplace packages are always verified; installing an installed module updates it.' );
			return 1;
		}

		if ( $trust ) {
			$this->output->warning( '--trust-unsigned: a package without a valid signature will be accepted. Use this only for a package you built or received directly.' );
		}

		try {
			$result = $is_file
				? $this->installer->install_zip( $target, $this->sibling( $target, '.sha256' ), $this->sibling( $target, '.sha256.sig' ), $trust, $replace, $this->progress() )
				: $this->installer->install_from_marketplace( $target, $this->progress() );
		} catch ( RuntimeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$option = $this->after_install_option();
		$apply  = null !== $option && true === $input->get_option( $option );
		$slug   = null === $result ? $target : $result[ $this->result_key() ]->slug;

		if ( null === $result ) {
			$this->output->success( sprintf( 'The %s "%s" is already the latest version.', $this->noun(), $target ) );
		} else {
			$this->report_result( $result, ! $apply );
		}

		return $apply ? $this->after_install( $slug ) : 0;
	}

	public function handle_update( CommandInput $input ) : int {
		$slug  = (string) $input->get_argument( 0, '' );
		$slugs = true === $input->get_option( 'all' ) ? array_keys( $this->installer->check_updates() ) : ( '' === $slug ? [] : [ $slug ] );

		if ( '' === $slug && true !== $input->get_option( 'all' ) ) {
			$this->output->error( sprintf( 'Usage: %s <slug>|--all', $this->command_prefix( 'update' ) ) );
			return 1;
		}

		if ( [] === $slugs ) {
			$this->output->info( sprintf( 'No installed %s comes from the marketplace.', $this->noun() ) );
			return 0;
		}

		$failed = 0;
		foreach ( $slugs as $module_slug ) {
			try {
				$result = $this->installer->update( $module_slug, $this->progress() );
			} catch ( RuntimeException $e ) {
				$this->output->error( sprintf( '%s: %s', $module_slug, $e->getMessage() ) );
				++$failed;
				continue;
			}

			if ( null === $result ) {
				$this->output->success( sprintf( 'The %s "%s" is up to date.', $this->noun(), $module_slug ) );
			} else {
				$this->report_result( $result );
			}
		}

		return $failed > 0 ? 1 : 0;
	}

	public function handle_check( CommandInput $input ) : int {
		$results = $this->installer->check_updates();
		if ( [] === $results ) {
			$this->output->info( sprintf( 'No installed %s comes from the marketplace.', $this->noun() ) );
			return 0;
		}

		$rows = [];
		foreach ( $results as $slug => $result ) {
			$rows[] = [ $slug, $result['installed'] ?? '—', $result['latest'] ?? '—', null !== $result['error'] ? 'error: ' . $result['error'] : ( $result['update'] ? 'update available' : 'up to date' ) ];
		}

		$this->output->table( [ 'Slug', 'Installed', 'Latest', 'Status' ], $rows );
		return 0;
	}

	public function handle_remove( CommandInput $input ) : int {
		$slug = (string) $input->get_argument( 0, '' );
		$yes  = true === $input->get_option( 'y' ) || true === $input->get_option( 'yes' );
		if ( '' === $slug ) {
			$this->output->error( sprintf( 'Usage: %s <slug> [--yes]', $this->command_prefix( 'remove' ) ) );
			return 1;
		}

		if ( ! $yes && ! $this->io->confirm( sprintf( 'Remove the %s "%s" and its files?', $this->noun(), $slug ), false ) ) {
			$this->output->info( 'Nothing was changed.' );
			return 0;
		}

		try {
			$warnings = $this->remove_module( $slug );
		} catch ( RuntimeException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		foreach ( $warnings as $warning ) {
			$this->output->warning( $warning );
		}
		$this->output->success( sprintf( 'The %s "%s" was removed.', $this->noun(), $slug ) );
		return 0;
	}

	public function handle_publish( CommandInput $input ) : int {
		$results = $this->installer->publish_all( true === $input->get_option( 'force' ) );
		if ( [] === $results ) {
			$this->output->info( sprintf( 'No %s is installed.', $this->noun() ) );
			return 0;
		}

		$rows = [];
		foreach ( $results as $slug => $result ) {
			$rows[] = [ $slug, $result ];
		}
		$this->output->table( [ ucfirst( $this->noun() ), 'Assets' ], $rows );
		return 0;
	}

	/** Report what an install or update did. */
	protected function report_result( array $result, bool $hint = true ) : void {
		foreach ( $result['warnings'] as $warning ) {
			$this->output->warning( $warning );
		}
		$module = $result[ $this->result_key() ];
		$this->output->success( null === $result['previous']
			? sprintf( 'Installed %s (%s) %s.', $module->name, $module->slug, $module->version )
			: sprintf( 'Updated %s (%s) from %s to %s.', $module->name, $module->slug, $result['previous'], $module->version ) );
		$this->output->info( sprintf( 'SHA-256:   %s', (string) ( $result['record']['sha256'] ?? '' ) ) );
		$this->output->info( sprintf( 'Signed by: %s', $result['record']['signed_by'] ?? 'nobody (trusted unsigned)' ) );
		if ( $hint ) {
			$this->output->info( $this->application_hint( $module ) );
		}
	}

	protected function sibling( string $zip, string $suffix ) : ?string {
		$path = preg_replace( '/\.zip$/i', '', $zip ) . $suffix;
		if ( ! $this->fs->is_file( $path ) ) {
			return null;
		}
		$contents = $this->fs->get_contents( $path );
		return is_string( $contents ) ? $contents : null;
	}

	protected function progress() : callable {
		return function ( string $step, string $status, string $message ) : void {
			$line = sprintf( '[%s] %s', $step, $message );
			match ( $status ) {
				ModuleInstaller::STATUS_OK      => $this->output->success( $line ),
				ModuleInstaller::STATUS_WARNING => $this->output->warning( $line ),
				default                        => $this->output->info( $line ),
			};
		};
	}
}
