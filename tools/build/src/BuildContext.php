<?php
/**
 * Build context class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Immutable values shared by the builder and every target.
 */
final class BuildContext {

	/**
	 * Constructor.
	 *
	 * @param string      $repo_root   Absolute path to the repository root.
	 * @param string      $out_dir     Absolute path to the build output directory.
	 * @param string      $release_dir Absolute path to the directory the release artifacts are written to.
	 * @param ProjectInfo $project     Name, version and requirements read from the repository.
	 * @param string      $composer    Composer binary.
	 * @param string      $npm         npm binary, used to install the build's Node tools.
	 * @param bool        $minify      Whether to minify assets (targets can still opt out).
	 * @param string      $node        Node.js binary, used to run esbuild.
	 * @param string|null $sign_key    Ed25519 signing key file; null builds an unsigned release.
	 */
	public function __construct(
		public readonly string $repo_root,
		public readonly string $out_dir,
		public readonly string $release_dir,
		public readonly ProjectInfo $project,
		public readonly string $composer,
		public readonly string $npm = 'npm',
		public readonly bool $minify = true,
		public readonly string $node = 'node',
		public readonly ?string $sign_key = null
	) {}

	/**
	 * Absolute path to a repository-relative path.
	 *
	 * @param string $relative Repository-relative path.
	 * @return string
	 */
	public function source( string $relative ): string {
		return self::join( $this->repo_root, $relative );
	}

	/**
	 * Absolute path to a build-relative path.
	 *
	 * @param string $relative Build-relative path.
	 * @return string
	 */
	public function target( string $relative ): string {
		return self::join( $this->out_dir, $relative );
	}

	/**
	 * Join path segments with forward slashes, ignoring empty segments.
	 *
	 * @param string ...$segments Path segments.
	 * @return string
	 */
	public static function join( string ...$segments ): string {
		$parts = array();

		foreach ( $segments as $index => $segment ) {
			$segment = 0 === $index ? rtrim( $segment, '/\\' ) : trim( $segment, '/\\' );

			if ( '' !== $segment ) {
				$parts[] = $segment;
			}
		}

		return implode( '/', $parts );
	}
}