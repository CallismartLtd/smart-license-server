<?php
/**
 * Site extension command.
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
use SmartLicenseServer\Environments\Application\Extensions\Extension;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionException;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionInstaller;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionManager;
use SmartLicenseServer\Environments\Application\Modules\Module;
use SmartLicenseServer\FileSystem\FileSystem;

/** Manage the extensions installed in this application. */
final class ExtensionCommand extends AbstractModuleCommand {

	public function __construct(
		private ExtensionManager $extensions,
		ExtensionInstaller $installer,
		FileSystem $fs,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $extensions, $installer, $fs, $io, $output, $script_name );
	}

	public static function name() : string {
		return 'extension';
	}

	protected function noun() : string {
		return 'extension';
	}

	protected function result_key() : string {
		return 'extension';
	}

	protected function remove_module( string $slug ) : array {
		return $this->installer->remove_extension( $slug );
	}

	protected function module_status( Module $module ) : string {
		if ( ! $module instanceof Extension ) {
			return '';
		}

		$status = $this->extensions->is_enabled( $module->slug ) ? 'enabled' : 'disabled';
		if ( ! $module->supports( \SMLISER_VER ) ) {
			$status .= sprintf( '; needs app %s', $module->requires );
		}
		return $status;
	}

	protected function after_install_option() : ?string {
		return 'enable';
	}

	protected function type_subcommands() : array {
		return [
			'enable'  => [ $this, 'handle_enable' ],
			'disable' => [ $this, 'handle_disable' ],
		];
	}

	protected function type_help() : array {
		return [
			'  enable <slug>         Enable an installed extension (loads on the next request).',
			'  disable <slug>        Disable an extension that no enabled extension depends on.',
			'  --enable              Install: enable the extension once installed.',
			'',
			'Installing an extension does not enable it. Enable only extensions you trust.',
		];
	}

	protected function application_hint( Module $module ) : string {
		return sprintf( 'Run `%s enable %s` to load it.', $this->command_prefix(), $module->slug );
	}

	protected function after_install( string $slug ) : int {
		return $this->enable( $slug );
	}

	public function handle_enable( CommandInput $input ) : int {
		$slug = (string) $input->get_argument( 0, '' );
		if ( '' === $slug ) {
			$this->output->error( sprintf( 'Usage: %s <slug>', $this->command_prefix( 'enable' ) ) );
			return 1;
		}
		return $this->enable( $slug );
	}

	public function handle_disable( CommandInput $input ) : int {
		$slug = (string) $input->get_argument( 0, '' );
		if ( '' === $slug ) {
			$this->output->error( sprintf( 'Usage: %s <slug>', $this->command_prefix( 'disable' ) ) );
			return 1;
		}
		if ( ! $this->extensions->is_installed( $slug ) ) {
			$this->output->error( sprintf( 'The extension "%s" is not installed.', $slug ) );
			return 1;
		}

		try {
			$this->extensions->disable( $slug );
		} catch ( ExtensionException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( sprintf( 'The extension "%s" is disabled.', $slug ) );
		return 0;
	}

	private function enable( string $slug ) : int {
		try {
			$extension = $this->extensions->enable( $slug );
		} catch ( ExtensionException $e ) {
			$this->output->error( $e->getMessage() );
			return 1;
		}

		$this->output->success( sprintf( 'The extension "%s" is enabled and will load on the next request.', $extension->slug ) );
		return 0;
	}
}
