<?php
/**
 * ReleaseCheck class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Release
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Release;

/**
 * Result of ReleaseManifest::verify().
 */
final readonly class ReleaseCheck {

	/**
	 * @param string[] $missing  Listed files that do not exist.
	 * @param string[] $modified Listed files whose content differs from the release.
	 * @param int      $checked  Number of files listed in the manifest.
	 */
	public function __construct(
		public array $missing,
		public array $modified,
		public int $checked
	) {}

	/**
	 * Whether every listed file is present and unchanged.
	 *
	 * @return bool
	 */
	public function passed(): bool {
		return empty( $this->missing ) && empty( $this->modified );
	}

	/**
	 * Plain-language problems, at most $limit names per kind.
	 *
	 * @param int $limit Names listed per kind before "and N more".
	 * @return string[]
	 */
	public function messages( int $limit = 10 ): array {
		$messages = array();

		if ( ! empty( $this->missing ) ) {
			$messages[] = sprintf( '%d file(s) are missing: %s', count( $this->missing ), self::names( $this->missing, $limit ) );
		}

		if ( ! empty( $this->modified ) ) {
			$messages[] = sprintf( '%d file(s) do not match the release: %s', count( $this->modified ), self::names( $this->modified, $limit ) );
		}

		return $messages;
	}

	/**
	 * A short list of names.
	 *
	 * @param string[] $names Names.
	 * @param int      $limit Maximum listed.
	 * @return string
	 */
	private static function names( array $names, int $limit ): string {
		$listed = implode( ', ', array_slice( $names, 0, $limit ) );
		$more   = count( $names ) - $limit;

		return $more > 0 ? sprintf( '%s and %d more', $listed, $more ) : $listed;
	}
}