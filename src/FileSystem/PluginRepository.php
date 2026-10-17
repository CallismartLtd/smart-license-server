<?php
/**
 * Plugin Repository
 *
 * Handles plugin-specific business logic within the repository.
 * Files are kept in AppStorage; this class only knows what they mean.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use ZipArchive;

/**
 * Plugin repository class provides filesystem APIs to interact with 
 * hosted plugins in the repository.
 * 
 * Note: it does not represent a single hosted plugin @see \SmartLicenseServer\HostedApps\Plugin
 */
class PluginRepository extends Repository {
	use WPRepoUtils;

	/**
	 * Allowed plugin banner names.
	 *
	 * @var array{low: string, high: string}
	 */
	const array ALLOWED_BANNER_NAMES = [ 'low' => 'banner-772x250', 'high' => 'banner-1544x500' ];

	/**
	 * {@inheritdoc}
	 */
	public function app_type() : string {
		return 'plugin';
	}

	/**
	 * {@inheritdoc}
	 *
	 * A plugin package must carry a readme.txt.
	 */
	protected function package_documents( ZipArchive $zip, string $root ) : array {
		$readme = static::zip_entry( $zip, $root, 'readme.txt' );

		if ( null === $readme ) {
			throw new Exception( 'readme_missing', 'The plugin ZIP file must contain a readme.txt file.', [ 'status' => 400 ] );
		}

		return [ 'readme.txt' => $readme ];
	}

	/**
	 * Validate names and types of asset that can be uploaded for a plugin.
	 * 
	 * @param UploadedFile $file The uploaded file instance.
	 * @param string $type       The asset type.
	 * @param string $slug       The plugin slug.
	 * @return Exception|string On error, file name otherwise.
	 */
	public function validate_app_asset( UploadedFile $file, string $type, string $slug ) : Exception|string {
		$ext        = $file->get_canonical_extension();
		$validation = $this->is_valid_image( $file->get_tmp_path() );

		if ( is_smliser_error( $validation ) ) {
			return $validation;
		}

		switch ( $type ) {
			case 'banner':
			case 'banners':

				if ( ! in_array( $file->get_name( false ), static::ALLOWED_BANNER_NAMES, true ) ) {
					return new Exception( 'filename_error', sprintf( 'Banner name must be one of: %s', implode( ', ', static::ALLOWED_BANNER_NAMES ) ) );
				}
		
				return $file->get_name();

			case 'icon':
				$allowed = in_array( $file->get_name( false ), static::ALLOWED_ICON_NAMES, true );
				if ( ! $allowed ) {
					return new Exception(
						'filename_error',
						sprintf( 'Icon name must be one of: %s', implode( ', ', static::ALLOWED_ICON_NAMES ) )
					);
				}

				return $file->get_name();

			case 'screenshots':
			case 'screenshot':
				if ( preg_match( '/screenshot-(\d+)/', $file->get_name(), $m ) ) {
					$screenshot = sprintf( 'screenshot-%d.%s', $m[1], $ext );
					
				} else {
					// Auto-generate next index.
					$screenshot = sprintf( '%s.%s', $this->find_next_screenshot_name( $slug ), $ext );
				}

				return $screenshot;

			default:
				return new Exception( 'invalid_type', 'Plugins only supports banners, icon, screenshots as asset type.', [ 'status' => 400 ] );
		}
		
	}

	/**
	 * Get plugin assets as URLs.
	 *
	 * @param string $slug Plugin slug.
	 * @param string<'banners', 'icons', 'screenshots'> $type Asset type.
	 * @return URL[]
	 *  |array{
	 *      'low': URL|string,
	 *      'high': URL|string
	 *  }
	 *  |array{
	 *      '1x': URL|string,
	 *      '2x': URL|string
	 *  }
	 */
	public function get_assets( string $slug, string $type ) : array {
		if ( [] === $this->asset_names( $slug ) ) {
			return [];
		}

		switch ( $type ) {
			case 'banners':
				$urls = [];

				foreach ( static::ALLOWED_BANNER_NAMES as $key => $basename ) {
					$match        = $this->find_assets( $slug, $basename . '.', static::ALLOWED_IMAGE_EXTENSIONS )[0] ?? null;
					$urls[ $key ] = null !== $match ? $this->asset_url( $slug, $match ) : '';
				}

				return $urls;

			case 'icons':
				$urls = [];

				// Check the usual sized icons.
				foreach ( [ '1x' => 'icon-128x128', '2x' => 'icon-256x256' ] as $key => $basename ) {
					$match        = $this->find_assets( $slug, $basename . '.', static::ALLOWED_IMAGE_EXTENSIONS )[0] ?? null;
					$urls[ $key ] = null !== $match ? $this->asset_url( $slug, $match ) : '';
				}

				// Use a universal icon.* as the 1x fallback when no 128x128 exists.
				$icon = $this->find_assets( $slug, 'icon.', static::ALLOWED_IMAGE_EXTENSIONS )[0] ?? null;

				if ( empty( $urls['1x'] ) && null !== $icon ) {
					$urls['1x'] = $this->asset_url( $slug, $icon );
				}

				return $urls;

			case 'screenshots':
				$files = $this->find_assets( $slug, 'screenshot-', static::ALLOWED_IMAGE_EXTENSIONS );

				usort( $files, 'strnatcmp' );

				$indexed = [];
				foreach ( $files as $basename ) {
					$url = $this->asset_url( $slug, $basename );

					if ( preg_match( '/screenshot-(\d+)\./i', $basename, $m ) ) {
						$indexed[ (int) $m[1] ] = $url;
					} else {
						$indexed[] = $url;
					}
				}

				ksort( $indexed );
				return $indexed;
			default:
				return [];
		}
	}

	/**
	 * Get plugin description from the readme.txt file.
	 * 
	 * @param string $slug The plugin slug.
	 * @return string The plugin description.
	 */
	public function get_description( $slug ) {
		$readme_contents = $this->get_readme_txt( $slug );

		if ( ! $readme_contents ) {
			return '';
		}

		// Normalize line endings.
		$readme_contents = str_replace( ["\r\n", "\r"], "\n", $readme_contents );
		
		// Step 1: Start from Description block
		if ( preg_match( '/==\s*Description\s*==\s*(.+)/si', $readme_contents, $matches ) ) {
			$description = trim( $matches[1] );

			// Step 2: Remove official sections that might appear afterwards.
			$official_sections = ['Installation', 'Changelog', 'Frequently Asked Questions', 'Screenshots', 'Upgrade Notice'];
			foreach ( $official_sections as $section ) {
				$pattern     = '/==\s*' . preg_quote( $section, '/' ) . '\s*==.*?(?=^==\s*\w.*==|\z)/msi';
				$description = preg_replace( $pattern, '', $description );
			}

			// Step 3: Optional cleanup of stray metadata lines
			$lines   = explode( "\n", $description );
			$exclude = ['Contributors:', 'Tags:', 'Stable tag:', 'Requires PHP:', 'License:', 'License URI:', 'Requires at least:', 'Tested up to:', 'WooCommerce tested up to:'];
			$cleaned = [];
			foreach ( $lines as $line ) {
				$line = trim( $line );

				if ( '' === $line ) continue;

				$skip = false;

				foreach ( $exclude as $meta ) {
					if ( false !== stripos( $line, $meta ) ) {
						$skip = true;
						break;
					}
				}
				
				if ( ! $skip ) {
					$cleaned[] = $line;
				}
			}

			$final_text = implode( "\n", $cleaned ) ;
			return $this->mdparser->parse( $final_text );
		}

		return '';
	}

	/**
	 * Get plugin short description.
	 * 
	 * According to WordPress.org standards, the short description is the 
	 * first non-empty text line following the header metadata block 
	 * and preceding any section header (== Section ==).
	 * 
	 * @param string $slug The plugin slug.
	 * @return string The plugin short description or empty string if absent.
	 */
	public function get_short_description( string $slug ) : string {
		$readme_contents = $this->get_readme_txt( $slug );

		if ( ! $readme_contents ) {
			return '';
		}

		$lines = preg_split( '/\r\n|\r|\n/', $readme_contents );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines or the top Plugin Title (e.g., === My Plugin ===)
			if ( '' === $line || preg_match( '/^===\s*.+\s*===$/', $line ) ) {
				continue;
			}

			// Skip standard WordPress header metadata lines (Key: Value)
			if ( $this->is_readme_meta_key( $line ) ) {
				continue;
			}

			// Stop if we hit ANY section header (== Description ==, == ChangeLog ==, etc.)
			// If we reach a section header before finding text, this readme has no short description.
			if ( preg_match( '/^==\s*[^=]+\s*==$/', $line ) ) {
				break;
			}

			// First valid text line encountered is our short description
			return $line;
		}

		return '';
	}

	/**
	 * Check if a line matches a standard WordPress header metadata key.
	 * 
	 * @param string $line
	 * @return bool
	 */
	private function is_readme_meta_key( string $line ) : bool {
		return (bool) preg_match(
			'/^([^:]+):\s*(.+)$/',
			$line
		);
	}

	/**
	 * Get plugin changelog text
	 * 
	 * @param string $slug The plugin slug
	 * @return string The parsed changelog HTML
	 */
	public function get_changelog( string $slug ) : string {
		$readme_contents = $this->get_readme_txt( $slug );

		if ( ! $readme_contents ) {
			return '';
		}

		// Match "== Changelog ==" up to the next main section "== Header ==" or EOF.
		// Negative lookahead/lookbehind ensures we only match exactly 2 equals signs (==),
		// preserving version subheadings like "= 2.5.4 =" or "=== 2.5.4 ===".
		$pattern = '/==\s*Changelog\s*==\s*(.*?)(?=\n\s*(?<!=)==(?!=)[^=]+==(?!=)|$)/is';

		if ( preg_match( $pattern, $readme_contents, $matches ) ) {
			$changelog_md = trim( $matches[1] );

			return '' !== $changelog_md ? $this->mdparser->parse( $changelog_md ) : '';
		}

		return '';
	}

	/**
	 * Get the installation text.
	 * 
	 * @param string $slug The plugin slug.
	 * @return string The parsed installation HTML.
	 */
	public function get_installation( string $slug ) : string {
		$readme_contents = $this->get_readme_txt( $slug );

		if ( ! $readme_contents ) {
			return '';
		}

		// Match "== Installation ==" up to the next main section "== Header ==" or EOF.
		// Strictly matches exactly 2 equals signs (==) for section boundaries,
		// preserving subheadings like "= Step 1 =" or "=== Manual Upload ===".
		$pattern = '/==\s*Installation\s*==\s*(.*?)(?=\n\s*(?<!=)==(?!=)[^=]+==(?!=)|$)/is';

		if ( preg_match( $pattern, $readme_contents, $matches ) ) {
			$installation_md = trim( $matches[1] );

			return '' !== $installation_md ? $this->mdparser->parse( $installation_md ) : '';
		}

		return '';
	}

	/**
	 * Get plugin FAQ (Frequently Asked Questions) section.
	 * 
	 * @param string $slug The plugin slug.
	 * @return string The parsed FAQ HTML.
	 */
	public function get_faq( string $slug ) : string {
		$readme_contents = $this->get_readme_txt( $slug );

		if ( ! $readme_contents ) {
			return '';
		}

		// Match "== Frequently Asked Questions ==", "== FAQ ==", or "== F.A.Q. =="
		// up to the next main section header "== Section ==" or EOF.
		$pattern = '/==\s*(?:Frequently\s+Asked\s+Questions|FAQ|F\.A\.Q\.)\s*==\s*(.*?)(?=\n\s*(?<!=)==(?!=)[^=]+==(?!=)|$)/is';

		if ( preg_match( $pattern, $readme_contents, $matches ) ) {
			$faq_md = trim( $matches[1] );

			return '' !== $faq_md ? $this->mdparser->parse( $faq_md ) : '';
		}

		return '';
	}

	/**
	 * Extract screenshot captions from a plugin's readme.txt file.
	 *
	 * @param string $slug Plugin slug.
	 * @return string[]
	 */
	public function get_screenshot_captions( string $slug ) : array {
		$content = $this->get_readme_txt( $slug );

		if ( empty( $content ) ) {
			return [];
		}

		$lines    = preg_split( "/(\r?\n)/", $content );
		$captions = [];
		$capture  = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			// Section starts
			if ( preg_match( '/^==\s*Screenshots\s*==$/i', $trimmed ) ) {
				$capture = true;
				continue;
			}

			// Section ends (next == Section == heading)
			if ( $capture && preg_match( '/^==.+==$/', $trimmed ) ) {
				break;
			}

			// Numbered caption lines like "1. Some caption"
			if ( $capture && preg_match( '/^(\d+)\.\s*(.+)$/', $trimmed, $m ) ) {
				$captions[ (int) $m[1] ] = $m[2];
			}
		}

		return $captions;
	}

	/**
	 * Get plugin screenshots in WordPress.org-style format.
	 *
	 * @param string $slug Plugin slug.
	 * @return array{src: string, caption: string}[]
	 */
	public function get_screenshots( string $slug ) : array {
		/** @var URL[] */
		$assets = $this->get_assets( $slug, 'screenshots' );

		if ( empty( $assets ) ) {
			return [];
		}

		// Get captions from readme.txt
		$captions = $this->get_screenshot_captions( $slug );

		$screenshots = [];
		foreach ( $assets as $i => $url ) {
			$screenshots[ $i ] = [
				'src'     => $url->url(),
				'caption' => $captions[ $i ] ?? '',
			];
		}

		return $screenshots;
	}

	/**
	 * Get plugin icons.
	 * 
	 * @return array{'1x': URL|string, '2x': URL|string}
	 */
	public function get_icons( string $slug ) {
		return $this->get_assets( $slug, 'icons' );
	}

	/**
	 * Get plugin banners.
	 * 
	 * @return array{'low': URL|string, 'high': URL|string}
	 */
	public function get_banners( string $slug ) {
		return $this->get_assets( $slug, 'banners' );
	}
	
	/**
	 * Get plugin screenshots as a formatted HTML ordered list.
	 *
	 * This method mimics the HTML structure found in the `sections -> screenshots`
	 * property of the WordPress.org plugin information API.
	 *
	 * @param string $slug Plugin slug.
	 * @return string HTML of the screenshots ordered list.
	 */
	public function get_screenshot_html( string $slug ) : string {
		// Get the structured screenshot data from your existing method.
		$screenshots = $this->get_screenshots( $slug );

		if ( empty( $screenshots ) ) {
			return '';
		}

		$html = '<ol>';

		foreach ( $screenshots as $i => $screenshot ) {
			$src     = $screenshot['src'];
			$caption = $screenshot['caption'];

			// This is the structure you see in the API response's sections property.
			// It includes a link to the full-size image and a caption.
			$html .= '<li>';
			$html .= '<a href="' . $src . '">';
			$html .= '<img src="' . $src . '" alt="' . $caption . '">';
			$html .= '</a>';
			if ( ! empty( $caption ) ) {
				$html .= '<p>' . $caption . '</p>';
			}
			$html .= '</li>';
		}

		$html .= '</ol>';

		return $html;
	}

	/**
	 * Get the contents of the readme file.
	 * 
	 * @param string $slug The plugin slug.
	 * @return string The readme.txt content, or empty string.
	 */
	public function get_readme_txt( $slug ) {
		return $this->read_document( (string) $slug, 'readme.txt' );
	}

	/**
	 * Get plugin metadata from the readme.txt header.
	 *
	 * @param string $slug The plugin slug.
	 * @return array<string, mixed>
	 */
	public function get_metadata( $slug ) : array {
		$readme_contents = $this->get_readme_txt( $slug );

		if ( ! $readme_contents ) {
			return [];
		}

		$metadata             = [];
		$lines                = preg_split( '/\r\n|\r|\n/', $readme_contents );
		$first_line_processed = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Stop when the first section header begins (Description, Installation, etc).
			if ( preg_match( '/^==\s*[\w\s]+\s*==$/', $line ) ) {
				break;
			}

			// Skip empty lines until we've captured the title and/or headers.
			if ( '' === $line ) {
				continue;
			}

			/*
			* Detect plugin title.
			* Valid formats:
			*   === Plugin Name ===
			*   == Plugin Name ==
			*   # Plugin Name
			*   Plugin Name (H1 fallback)
			*/
			if ( ! $first_line_processed ) {
				if ( preg_match( '/^={1,3}\s*(.+?)\s*={1,3}$/', $line, $m ) ) {
					$metadata['plugin_name'] = trim( $m[1] );
					$first_line_processed    = true;
					continue;
				}
				if ( preg_match( '/^#\s*(.+)$/', $line, $m ) ) {
					$metadata['plugin_name'] = trim( $m[1] );
					$first_line_processed    = true;
					continue;
				}

				// Fallback: treat the first non-empty line as title
				$metadata['plugin_name'] = $line;
				$first_line_processed    = true;
				continue;
			}

			/*
			* Parse key: value fields
			* Example:
			*   Requires at least: 6.0
			*   Tested up to: 6.7
			*   Contributors: callistus, john
			*/
			if ( preg_match( '/^([^:]+):\s*(.+)$/', $line, $matches ) ) {

				$raw_key = trim( $matches[1] );
				$value   = trim( $matches[2] );

				// Normalize key -> stable_tag, tested_up_to, etc.
				$normalized_key = strtolower(
					str_replace( ' ', '_', $raw_key )
				);

				switch ( $normalized_key ) {
					case 'contributors':
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