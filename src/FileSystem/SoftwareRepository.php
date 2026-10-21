<?php
/**
 * Software Repository
 *
 * Handles software-specific business logic within the repository.
 * Files are kept in AppStorage; this class only knows what they mean.
 *
 * @author  Callistus Nwachukwu
 * @since   0.2.0
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\HostedApps\Software;
use ZipArchive;

use function sprintf, in_array, is_smliser_error, implode;

/**
 * Software repository class provides the software type's rules for hosted software in the repository.
 * 
 * Note: it does not represent a single hosted software @see \SmartLicenseServer\HostedApps\Software
 */
class SoftwareRepository extends Repository {
	use RepoFilesAwareTrait;

	/**
	 * {@inheritdoc}
	 */
	public function app_type() : string {
		return 'software';
	}

	/**
	 * {@inheritdoc}
	 *
	 * A software package must carry a readme.md.
	 */
	protected function package_documents( ZipArchive $zip, string $root ) : array {
		$readme = static::zip_entry( $zip, $root, 'readme.md' );

		if ( null === $readme ) {
			throw new Exception( 'readme_missing', 'The uploaded software ZIP file is missing the required readme.md file.', [ 'status' => 400 ] );
		}

		return [ 'readme.md' => $readme ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function asset_types() : array {
		return [
			'icon'       => 'Icon',
			'cover'      => 'Cover',
			'screenshot' => 'Screenshot',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function asset_type_of( string $name ) : ?string {
		$stem = FileSystemHelper::remove_extension( $name );

		return match ( true ) {
			in_array( $stem, static::ALLOWED_ICON_NAMES, true )  => 'icon',
			'cover' === $stem                                    => 'cover',
			1 === preg_match( '/^screenshot-\d+$/', $stem )      => 'screenshot',
			default                                              => null,
		};
	}

	/**
	 * Validate names and types of asset that can be uploaded for a software.
	 * 
	 * @param UploadedFile $file The uploaded file instance.
	 * @param string $type       The asset type.
	 * @param string $slug       The software slug.
	 * @return Exception|string On error, file name otherwise.
	 */
	public function validate_app_asset( UploadedFile $file, string $type, string $slug ) : Exception|string {
		$ext        = $file->get_canonical_extension();
		$validation = $this->is_valid_image( $file->get_tmp_path() );

		if ( is_smliser_error( $validation ) ) {
			return $validation;
		}

		// Only icon, cover and screenshots are allowed.
		switch ( $type ) {
			case 'icon':
				$allowed = in_array( $file->get_name( false ), static::ALLOWED_ICON_NAMES, true );
				if ( ! $allowed ) {
					return new Exception(
						'filename_error',
						sprintf( 'Icon name must be one of: %s', implode( ', ', static::ALLOWED_ICON_NAMES ) )
					);
				}

				return $file->get_name();
			case 'cover':
				return sprintf( 'cover.%s', $ext );
			case 'screenshot':
			case 'screenshots':
				if ( preg_match( '/screenshot-(\d+)/', $file->get_name(), $m ) ) {
					$screenshot = sprintf( 'screenshot-%d.%s', $m[1], $ext );
				} else {
					// Auto-generate next index.
					$screenshot = sprintf( '%s.%s', $this->find_next_screenshot_name( $slug ), $ext );
				}

				return $screenshot;

			default:
				return new Exception( 'invalid_type', 'Software only supports icon, cover and screenshots as asset type.', [ 'status' => 400 ] );
		}
	}

	/**
	 * Get software assets as URL.
	 * 
	 * @param string $slug Software slug.
	 * @param string $type Asset type (e.g., cover, screenshots).
	 * @return URL[]|URL Asset URLs or Exception on failure.
	 */
	public function get_assets( string $slug, string $type ) : URL|array {
		if ( [] === $this->asset_names( $slug ) ) {
			return ( 'cover' === $type ) ? URL::from( '' ) : [];
		}

		$possible_exts = static::ALLOWED_IMAGE_EXTENSIONS;

		switch ( $type ) {
			case 'icons':
				$icons = [];
				$names = $this->asset_names( $slug );

				foreach ( static::ALLOWED_ICON_NAMES as $icon_name ) {
					foreach ( $possible_exts as $ext ) {
						if ( in_array( $icon_name . '.' . $ext, $names, true ) ) {
							$icons[] = $this->asset_url( $slug, $icon_name . '.' . $ext );
						}
					}
				}

				return $icons;

			case 'cover':
				$cover = $this->find_asset( $slug, 'cover', $possible_exts );

				return null !== $cover ? $this->asset_url( $slug, $cover ) : URL::from( '' );

			case 'screenshots':
				$screenshots = [];

				foreach ( $this->find_assets( $slug, 'screenshot-', $possible_exts ) as $screenshot ) {
					$screenshots[] = $this->asset_url( $slug, $screenshot );
				}

				return $screenshots;

			default:
				return [];
		}
	}

	/**
	 * Get the app.json metadata for a software.
	 *
	 * A missing or broken app.json is rebuilt and stored.
	 * 
	 * @param Software $software
	 * @return array
	 */
	public function get_app_dot_json( $software ) : array {
		try {
			$manifest = $this->storage->get_manifest( $this->app_type(), $software->get_slug() );
		} catch ( StorageException ) {
			$manifest = null;
		}

		return $manifest ?? $this->regenerate_app_dot_json( $software );
	}

	/**
	 * Build app.json file.
	 *
	 * The manifest is stored when the software exists in storage; it is
	 * returned either way.
	 * 
	 * @param Software $software
	 * @return array
	 */
	public function regenerate_app_dot_json( $software ) : array {

		$defaults = [
			'name'              => $software->get_name(),
			'slug'              => $software->get_slug(),
			'version'           => $software->get_version(),
			'short_description' => $software->get_short_description(),
		];

		$current = $software->get_manifest();

		// Allow overrides and additional fields.
		$manifest = array_merge( $defaults, $current );

		// Enforce canonical identity fields.
		foreach ( [ 'name', 'slug', 'version' ] as $key ) {
			$manifest[ $key ] = $defaults[ $key ];
		}

		try {
			if ( $this->storage->has_app( $this->app_type(), $software->get_slug() ) ) {
				$this->storage->put_manifest( $this->app_type(), $software->get_slug(), $manifest );
			}
		} catch ( StorageException ) {
			// Rebuilt again on the next read.
		}

		return $manifest;
	}
	
	/**
	 * Get description from readm.md file.
	 * 
	 * @param string $slug
	 * @return string
	 */
	public function get_description( $slug ) {
		return $this->get_readme( $slug );
	}

	/**
	 * Get the value of faq key in the app.json file
	 *
	 * @param Software $software
	 * @return string HTML markup of frequently asked questions.
	 */
	public function get_faq( Software $software ) : string {
		$app_json = $this->get_app_dot_json( $software );
		$faq      = $app_json['faq'] ?? [];

		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return '';
		}

		$html = '<dl>';

		foreach ( $faq as $question => $answer ) {
			if ( ! is_string( $question ) || ! is_string( $answer ) ) {
				continue;
			}

			$html .= '<dt>';
			$html .= escHtml( $question );
			$html .= '</dt>';

			$html .= '<dd>';
			$html .= escHtml( $answer );
			$html .= '</dd>';
		}

		$html .= '</dl>';

		return $html;
	}

	/**
	 * Get short description.
	 * 
	 * @param Software $software
	 * @return string
	 */
	public function get_short_description( Software $software ) : string {
		$app_json = $this->get_app_dot_json( $software );

		return $app_json['short_description'] ?? '';
	}
}
