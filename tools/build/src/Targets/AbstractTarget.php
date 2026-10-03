<?php
/**
 * Abstract build target class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build\Targets
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build\Targets;

use SmartLicenseServer\Build\BuildConsole;
use SmartLicenseServer\Build\BuildContext;

/**
 * Describes how one environment's build is laid out.
 *
 * The builder does the shared work (copying, Environments filtering,
 * composer.json rewrite, dependency install, writing generated files).
 * A target only declares where things go and which entry files to emit.
 *
 * Runtime layout constraint: the core, templates, assets, composer.json and
 * vendor/ always sit side by side under runtime_path(), because the core
 * Autoloader requires `__DIR__ . '/../vendor/autoload.php'`. That is why the
 * derived path methods below are final.
 */
abstract class AbstractTarget {

	/**
	 * Target name used on the command line.
	 *
	 * @return string
	 */
	abstract public function name(): string;

	/**
	 * The `src/Environments/<name>` subdirectory this target keeps.
	 *
	 * Every other subdirectory of `src/Environments` is excluded; loose files
	 * directly under `src/Environments` are always kept.
	 *
	 * @return string
	 */
	abstract public function environment(): string;

	/**
	 * Build-relative runtime directory. Empty string means the build root.
	 *
	 * @return string
	 */
	abstract public function runtime_path(): string;

	/**
	 * Entry files generated for this target.
	 *
	 * @param BuildContext $context Build context.
	 * @return array<string, array{contents: string, mode: int}> Build-relative path => file.
	 */
	abstract public function generated_files( BuildContext $context ): array;

	/**
	 * Repository files copied as-is, repository-relative => build-relative.
	 *
	 * @return array<string, string>
	 */
	public function root_files(): array {
		return array(
			'LICENSE'      => 'LICENSE',
			'changelog.md' => 'changelog.md',
		);
	}

	/**
	 * Whether to install Composer dependencies into the runtime directory.
	 *
	 * @return bool
	 */
	public function installs_dependencies(): bool {
		return true;
	}

	/**
	 * Whether to write minified copies (*.min.js, *.min.css) of the assets.
	 *
	 * @return bool
	 */
	public function minify_assets(): bool {
		return true;
	}

	/**
	 * Hook run after every shared step has completed.
	 *
	 * @param BuildContext $context Build context.
	 * @param BuildConsole $console Console.
	 */
	public function after_build( BuildContext $context, BuildConsole $console ): void {}

	/**
	 * Build-relative core directory.
	 *
	 * @param BuildContext $context Build context.
	 * @return string
	 */
	final public function core_path( BuildContext $context ): string {
		return BuildContext::join( $this->runtime_path(), $context->core_dir );
	}

	/**
	 * Build-relative templates directory.
	 *
	 * @return string
	 */
	final public function templates_path(): string {
		return BuildContext::join( $this->runtime_path(), 'templates' );
	}

	/**
	 * Build-relative assets directory.
	 *
	 * @return string
	 */
	final public function assets_path(): string {
		return BuildContext::join( $this->runtime_path(), 'assets' );
	}

	/**
	 * Build-relative directory holding composer.json, composer.lock and vendor/.
	 *
	 * @return string
	 */
	final public function composer_path(): string {
		return $this->runtime_path();
	}
}