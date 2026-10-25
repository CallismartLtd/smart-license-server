<?php
/**
 * ImagickRasterizer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Pdf\Rasterizers
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core\Pdf\Rasterizers;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Draws PDF pages with the Imagick extension (which uses Ghostscript).
 *
 * Imagick lists PDF among its formats even when ImageMagick's security
 * policy forbids reading it; that shows only when rasterize() fails, and
 * PdfRasterizer then tries the next rasterizer.
 */
class ImagickRasterizer implements PdfRasterizerInterface {

	/**
	 * Most memory ImageMagick may use for one PDF, in bytes.
	 *
	 * @var int
	 */
	public const MEMORY_LIMIT = 256 * 1024 * 1024;

	/**
	 * @param FileSystem $fs The filesystem API.
	 */
	public function __construct(
		protected FileSystem $fs
	) {}

	public static function get_id() : string {
		return 'imagick';
	}

	public function is_available() : bool {
		return extension_loaded( 'imagick' ) && [] !== \Imagick::queryFormats( 'PDF' );
	}

	public function rasterize( string $pdf, string $out_dir, int $max_pages, int $dpi ) : int|Exception {
		$count = 0;

		try {
			\Imagick::setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, self::MEMORY_LIMIT );

			$document = new \Imagick();
			$document->setResolution( $dpi, $dpi );
			// "[0-N]" reads only the first pages: a long PDF costs no more than a short one.
			$document->readImage( sprintf( '%s[0-%d]', $pdf, $max_pages - 1 ) );

			foreach ( $document as $page ) {
				// PDFs have no background: paint the pages white, as they print.
				$page->setImageBackgroundColor( 'white' );
				$page->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
				$page->setImageFormat( 'png' );

				++$count;

				if ( ! $this->fs->put_contents( FileSystemHelper::join_path( $out_dir, sprintf( 'page-%d.png', $count ) ), $page->getImageBlob() ) ) {
					throw new \RuntimeException( sprintf( 'Page %d could not be saved.', $count ) );
				}
			}

			$document->clear();
		} catch ( \Throwable $e ) {
			return new Exception( 'rasterize_failed', $e->getMessage() );
		}

		return $count;
	}
}