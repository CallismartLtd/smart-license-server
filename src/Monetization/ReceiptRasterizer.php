<?php
/**
 * ReceiptRasterizer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Monetization
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Turns a PDF receipt into PNG images of its pages.
 *
 * Browsers draw PDFs with different viewers, and many phones draw none, so
 * reviewers see a PDF receipt as images of its pages: the same in every
 * browser, with no viewer toolbar, and with nothing in the PDF able to run.
 *
 * Rendering needs the Imagick extension with Ghostscript, which ImageMagick
 * uses to read PDFs. Whether a server can do it is learnt by trying: Imagick
 * lists PDF among its formats even when ImageMagick's security policy
 * forbids reading it. Without it, nothing is rendered and the reviewer gets
 * the browser's own PDF viewer instead.
 *
 * Ghostscript reads a copy in the system temporary directory, never the
 * stored receipt, because it may be confined there (see copy_to_system_temp()).
 *
 * The pages are written next to the receipt, in "<receipt>.pages/": page-1.png,
 * page-2.png, … at RESOLUTION DPI, at most MAX_PAGES of them. Rendering runs
 * in a background job (RasterizeReceiptJob), never in the buyer's request.
 */
class ReceiptRasterizer {

	/**
	 * Most pages rendered. A receipt is a page or two; the rest is not drawn.
	 *
	 * @var int
	 */
	public const MAX_PAGES = 5;

	/**
	 * Rendering resolution, in dots per inch: an A4 page is 1240 × 1754 pixels.
	 *
	 * @var int
	 */
	public const RESOLUTION = 150;

	/**
	 * Most memory ImageMagick may use for one receipt, in bytes.
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

	/**
	 * Whether the Imagick extension is loaded and lists the PDF format.
	 *
	 * Necessary, not sufficient: ImageMagick's policy may still refuse PDFs,
	 * which only rasterize() finds out.
	 *
	 * @return bool
	 */
	public static function has_imagick() : bool {
		return extension_loaded( 'imagick' ) && [] !== \Imagick::queryFormats( 'PDF' );
	}

	/**
	 * The directory a receipt's page images are written to.
	 *
	 * @param string $receipt_path Absolute path of the PDF receipt.
	 * @return string
	 */
	public static function pages_dir( string $receipt_path ) : string {
		return $receipt_path . '.pages/';
	}

	/**
	 * Render the pages of a PDF receipt to PNG images.
	 *
	 * Replaces images rendered before. On failure nothing is left behind.
	 *
	 * @param string $receipt_path Absolute path of the PDF receipt.
	 * @return int|Exception The number of pages written, or why none were.
	 */
	public function rasterize( string $receipt_path ) : int|Exception {
		if ( ! static::has_imagick() ) {
			return new Exception( 'rasterizer_unavailable', 'The Imagick extension with PDF support is not available.' );
		}

		if ( ! $this->fs->is_file( $receipt_path ) ) {
			return new Exception( 'receipt_missing', 'The receipt file does not exist.' );
		}

		$dir  = static::pages_dir( $receipt_path );
		$copy = $this->copy_to_system_temp( $receipt_path );

		if ( $copy instanceof Exception ) {
			return $copy;
		}

		$this->clear( $dir );

		try {
			\Imagick::setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, self::MEMORY_LIMIT );

			$pdf = new \Imagick();
			$pdf->setResolution( self::RESOLUTION, self::RESOLUTION );
			// "[0-N]" makes ImageMagick read only the first pages: a long PDF costs no more than a short one.
			$pdf->readImage( sprintf( '%s[0-%d]', $copy, self::MAX_PAGES - 1 ) );

			if ( ! $this->fs->is_dir( $dir ) && ! $this->fs->mkdir( $dir, \SMLISER_DIR_PERMISSION ) ) {
				throw new \RuntimeException( 'The page image directory could not be created.' );
			}

			$count = 0;

			foreach ( $pdf as $page ) {
				// PDFs have no background: paint the pages white, as they print.
				$page->setImageBackgroundColor( 'white' );
				$page->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
				$page->setImageFormat( 'png' );

				++$count;

				if ( ! $this->fs->put_contents( FileSystemHelper::join_path( $dir, sprintf( 'page-%d.png', $count ) ), $page->getImageBlob() ) ) {
					throw new \RuntimeException( sprintf( 'Page %d could not be saved.', $count ) );
				}
			}

			$pdf->clear();
		} catch ( \Throwable $e ) {
			$this->clear( $dir );

			return new Exception( 'rasterize_failed', sprintf( 'The PDF could not be rendered: %s', $e->getMessage() ) );
		} finally {
			$this->fs->delete( $copy );
		}

		if ( 0 === $count ) {
			$this->clear( $dir );

			return new Exception( 'rasterize_failed', 'The PDF has no pages to render.' );
		}

		return $count;
	}

	/**
	 * Copy a receipt into the system temporary directory for Ghostscript.
	 *
	 * Ghostscript may be confined to a few directories: Ubuntu's AppArmor
	 * profile for "gs" lets it read the system temporary directory, not the
	 * application's storage (e.g. under /var/www). So it reads a copy, never
	 * the stored receipt; rasterize() deletes the copy afterwards. The
	 * application's own temporary directory is inside its storage, so it
	 * would not do.
	 *
	 * @param string $receipt_path Absolute path of the PDF receipt.
	 * @return string|Exception The copy's path, or why it could not be made.
	 */
	protected function copy_to_system_temp( string $receipt_path ) : string|Exception {
		$copy = FileSystemHelper::join_path( sys_get_temp_dir(), sprintf( 'smliser-receipt-%s.pdf', bin2hex( random_bytes( 8 ) ) ) );

		if ( '' === $copy || ! $this->fs->copy( $receipt_path, $copy ) ) {
			return new Exception( 'rasterize_failed', 'The receipt could not be copied to the system temporary directory for rendering.' );
		}

		return $copy;
	}

	/**
	 * The page images of a PDF receipt, in page order.
	 *
	 * @param string $receipt_path Absolute path of the PDF receipt.
	 * @return list<string> Absolute paths; empty when none were rendered.
	 */
	public function get_pages( string $receipt_path ) : array {
		$dir   = static::pages_dir( $receipt_path );
		$pages = [];

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$path = FileSystemHelper::join_path( $dir, sprintf( 'page-%d.png', $page ) );

			if ( ! $this->fs->is_file( $path ) ) {
				break;
			}

			$pages[] = $path;
		}

		return $pages;
	}

	/**
	 * Remove a receipt's page images.
	 *
	 * @param string $dir The pages directory.
	 * @return void
	 */
	protected function clear( string $dir ) : void {
		if ( $this->fs->is_dir( $dir ) ) {
			$this->fs->rmdir( $dir, true );
		}
	}
}