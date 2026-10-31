<?php
/**
 * Site theme command.
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
use SmartLicenseServer\Environments\Application\Theming\Theme;
use SmartLicenseServer\Environments\Application\Theming\ThemeException;
use SmartLicenseServer\Environments\Application\Theming\ThemeInstaller;
use SmartLicenseServer\Environments\Application\Theming\ThemeManager;
use SmartLicenseServer\FileSystem\FileSystem;

/** Manage the themes that render this installation's public pages. */
final class ThemeCommand extends AbstractModuleCommand {

	public function __construct(
		private ThemeManager $themes,
		ThemeInstaller $installer,
		FileSystem $fs,
		InputInterface $io,
		OutputInterface $output,
		ScriptName $script_name
	) {
		parent::__construct( $themes, $installer, $fs, $io, $output, $script_name );
	}

	public static function name() : string {
		return 'theme';
	}

	protected function noun() : string {
		return 'theme';
	}

	protected function result_key() : string {
		return 'theme';
	}

	protected function remove_module( string $slug ) : array {
		return $this->installer->remove_theme( $slug );
	}

	protected function module_status( Module $module ) : string {
		if ( ! $module instanceof Theme ) {
			return '';
		}

		$status = $module->slug === $this->themes->active()->slug ? 'active' : '';
		if ( ! $module->supports( \SMLISER_VER ) ) {
			$status = sprintf( 'needs app %s', $module->requires );
		}
		if ( null !== $module->parent && ThemeManager::DEFAULT_SLUG !== $module->parent ) {
			$status = trim( $status . ' child of ' . $module->parent );
		}
		return $status;
	}

	protected function prepend_builtin_rows( array &$rows ) : void {
		$default = $this->themes->default_theme();
		$rows[] = [ $default->slug, $default->name, $default->version, 'Built in', $default->slug === $this->themes->active()->slug ? 'active' : '' ];
	}

	protected function list_notice() : void {
		if ( $this->themes->active_slug() !== $this->themes->active()->slug ) {
			$this->output->warning( sprintf( 'The recorded theme "%s" cannot be used, so the default theme renders the pages; see the error log.', $this->themes->active_slug() ) );
		}
	}

	protected function after_install_option() : ?string {
		return 'activate';
	}

	protected function type_subcommands() : array {
		return [ 'activate' => [ $this, 'handle_activate' ] ];
	}

	protected function type_help() : array {
		return [
			'  activate <slug>       Use a theme for this site ("default" restores the built-in theme).',
			'',
			sprintf( 'Marketplace themes are published on the update server as %s apps, by theme slug.', ThemeInstaller::MARKETPLACE_TYPE ),
		];
	}

	protected function application_hint( Module $module ) : string {
		return sprintf( 'Run `%s activate %s` to use it.', $this->command_prefix(), $module->slug );
	}

	protected function after_install( string $slug ) : int {
		return $this->activate( $slug );
	}

	public function handle_activate( CommandInput $input ) : int {
		$slug = (string) $input->get_argument( 0, '' );
		if ( '' === $slug ) {
			$this->output->error( sprintf( 'Usage: %s <slug>', $this->command_prefix( 'activate' ) ) );
			return 1;
		}
		return $this->activate( $slug );
	}

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
}
