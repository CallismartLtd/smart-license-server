<?php
/**
 * Theme Repository
 *
 * Handles theme-specific business logic within the repository.
 * Files are kept in AppStorage; this class only knows what they mean.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\URL;
use ZipArchive;
use SmartLicenseServer\Exceptions\Exception;

use function is_smliser_error, in_array, sprintf, is_string, preg_match,
trim, str_replace;

class ThemeRepository extends Repository {
	use WPRepoUtils, RepoFilesAwareTrait;

	/**
	 * Allowed additional screenshots file extensions.
	 *
	 * @var list<string>
	 */
	const array ALLOWED_THEME_SCREENSHOT_EXTS = ['png', 'gif', 'avif', 'webp', 'jpg', 'jpeg'];

	/**
	 * {@inheritdoc}
	 */
	public function app_type() : string {
		return 'theme';
	}

	/**
	 * {@inheritdoc}
	 *
	 * A theme package must carry a style.css, and may carry a
	 * changelog.txt and an installation.txt.
	 */
	protected function package_documents( ZipArchive $zip, string $root ) : array {
		$style_css = static::zip_entry( $zip, $root, 'style.css' );

		if ( null === $style_css ) {
			throw new Exception( 'style_missing', 'The theme zip file must contain a style.css file', [ 'status' => 400 ] );
		}

		$documents = [ 'style.css' => $style_css ];

		foreach ( [ 'changelog.txt', 'installation.txt' ] as $optional ) {
			$contents = static::zip_entry( $zip, $root, $optional );

			if ( null !== $contents && '' !== $contents ) {
				$documents[ $optional ] = $contents;
			}
		}

		return $documents;
	}

	/**
	 * {@inheritDoc}
	 */
	public function asset_types() : array {
		return [
			'screenshot'  => 'Main screenshot',
			'screenshots' => 'Additional screenshot',
		];
	}

	/**
	 * Validate names and types of asset that can be uploaded for a theme.
	 * 
	 * @param UploadedFile $file The uploaded file instance.
	 * @param string $type       The asset type.
	 * @param string $slug       The theme slug.
	 * @return Exception|string On error, file name otherwise.
	 */
	public function validate_app_asset( UploadedFile $file, string $type, string $slug ) : Exception|string {
		$ext        = $file->get_canonical_extension();
		$validation = $this->is_valid_image( $file->get_tmp_path() );

		if ( is_smliser_error( $validation ) ) {
			return $validation;
		}

		switch ( $type ) {
			case 'screenshot':
				if ( ! in_array( $ext, static::ALLOWED_THEME_SCREENSHOT_EXTS, true ) ) {
					return new Exception( 'invalid_type', sprintf( 'Main theme screenshot must be one of: %s', implode( ', ', static::ALLOWED_THEME_SCREENSHOT_EXTS ) ) );
				}

				return sprintf( 'screenshot.%s', $ext );
			case 'screenshots':
				if ( preg_match( '/screenshot-(\d+)/', $file->get_name(), $m ) ) {
					$screenshot = sprintf( 'screenshot-%d.%s', $m[1], $ext );
					
				} else {
					// Auto-generate next index.
					$screenshot = sprintf( '%s.%s', $this->find_next_screenshot_name( $slug ), $ext );
				}

				return $screenshot;

			default:
				return new Exception( 'invalid_type', 'Themes only supports screenshot and screenshots as asset type.', [ 'status' => 400 ] );
		}

	}

	/**
	 * Abstract Implementation: Get theme assets as URLs.
	 *
	 * Theme assets includes screenshots (screenshot.png, etc.).
	 *
	 * @param string $slug Theme slug.
	 * @param string $type Asset type (e.g., 'screenshots').
	 * @return URL[]|URL URLs of assets.
	 */
	public function get_assets( string $slug, string $type ) : array|URL|string {
		if ( [] === $this->asset_names( $slug ) ) {
			return ( 'screenshot' === $type ) ? URL::from( '' ) : [];
		}

		switch ( $type ) {
			case 'screenshot':
				// screenshot.{ext}.
				$screenshot = $this->find_asset( $slug, 'screenshot', static::ALLOWED_THEME_SCREENSHOT_EXTS );

				return null !== $screenshot ? $this->asset_url( $slug, $screenshot ) : URL::from( '' );

			case 'screenshots':
				// screenshot-{index}.{ext}.
				$screenshots = $this->find_assets( $slug, 'screenshot-', static::ALLOWED_IMAGE_EXTENSIONS );

				usort( $screenshots, 'strnatcmp' );

				$urls = [];

				foreach ( $screenshots as $name ) {
					$url = $this->asset_url( $slug, $name );

					if ( preg_match( '/screenshot-(\d+)\./i', $name, $m ) ) {
						$urls[ (int) $m[1] ] = $url;
					} else {
						$urls[] = $url;
					}
				}

				ksort( $urls );

				return $urls;

			default:
				return [];
		}
	}

	/**
	 * Get main theme screenshot URL.
	 * 
	 * @return URL|string URL of the main screenshot or empty string if not found.
	 */
	public function get_screenshot( string $slug ) : URL|string {
		return $this->get_assets( $slug, 'screenshot' );
	}

	/**
	 * Get the theme descriptions.
	 * 
	 * @return string The theme description in HTML format.
	 */
	public function get_description( string $slug ) : string {
		$metadata = $this->get_metadata( $slug );

		if ( ! isset( $metadata['description'] ) ) {
			return '';
		}

		return $this->mdparser->parse( $metadata['description'] );
	}

	/**
	 * Get short description.
	 * 
	 * @return string $slug The theme slug.
	 */
	public function get_short_description( string $slug ) : string {
		return \substr( $this->get_description( $slug ), 0, 800 );
	}

	/**
	 * Get the content of the theme style.css file.
	 * 
	 * @param string $slug The theme slug.
	 * @return string Style.css contents or empty string if not found.
	 */
	public function get_style_css( string $slug ) : string {
		return $this->read_document( $slug, 'style.css' );
	}
	
	/**
	 * Get theme metadata from the style.css header.
	 *
	 * This method would parse the style.css file for theme headers (Theme Name, Version, etc.)
	 *
	 * @param string $slug The theme slug.
	 * @return array<string, mixed>
	 */
	
	public function get_metadata( string $slug ) : array {
		$style_contents = $this->get_style_css( $slug );

		if ( ! $style_contents ) {
			return [];
		}

		$metadata = [];
		$lines    = preg_split( '/\r\n|\r|\n/', $style_contents );

		/**
		 * Expected header block looks like:
		 *
		 * /*
		 * Theme Name: My Theme
		 * Description: Example theme
		 * Version: 1.0
		 * Tags: blog, clean, minimal
		 * * /
		 */

		$header_started = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Detect beginning of comment block.
			if ( ! $header_started && preg_match( '/^\/\*/', $line ) ) {
				$header_started = true;
				continue;
			}

			// Stop when comment block closes.
			if ( $header_started && preg_match( '/\*\//', $line ) ) {
				break;
			}

			if ( ! $header_started || '' === $line ) {
				continue;
			}

			/*
			* Parse key: value fields
			* Example:
			*   Theme Name: Smart Portal
			*   Version: 0.2.0
			*   Tags: dashboard, smart-woo
			*/
			if ( preg_match( '/^([^:]+):\s*(.+)$/', $line, $matches ) ) {

				$raw_key = trim( $matches[1] );
				$value   = trim( $matches[2] );

				$normalized_key = strtolower(
					str_replace( ' ', '_', $raw_key )
				);

				switch ( $normalized_key ) {
					case 'tags':
						$metadata[ $normalized_key ] = array_map(
							'trim',
							explode( ',', $value )
						);
						break;

					default:
						$metadata[ $normalized_key ] = $value;
				}
			}
		}

		return $metadata;
	}

}