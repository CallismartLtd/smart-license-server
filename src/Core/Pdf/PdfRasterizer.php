<?php
/**
 * PdfRasterizer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Pdf
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core\Pdf;

use SmartLicenseServer\Core\Pdf\Rasterizers\GhostscriptRasterizer;
use SmartLicenseServer\Core\Pdf\Rasterizers\ImagickRasterizer;
use SmartLicenseServer\Core\Pdf\Rasterizers\PdfRasterizerInterface;
use SmartLicenseServer\Core\Pdf\Rasterizers\PopplerRasterizer;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Draws the first pages of a PDF as PNG images: page-1.png, page-2.png, ….
 *
 * Pages drawn this way look the same in every browser, and nothing in the
 * PDF can run where they are shown. Drawing needs one of, in this order:
 * the Imagick extension, Ghostscript, or Poppler's pdftoppm. The first that
 * is available and succeeds is used; when one fails, the next is tried.
 * Without any, rasterize() says so and callers show the PDF another way.
 *
 * Work happens in the system temporary directory, never next to the PDF:
 * Ghostscript may be confined to it (Ubuntu's AppArmor profile for "gs"
 * forbids /var/www, for example). The PDF is copied in, drawn there, and
 * the pages are moved to their directory. The work directory is always
 * removed. Drawing can take seconds: run it in a background job.
 */
class PdfRasterizer {

	/**
	 * The rasterizers, in the order they are tried.
	 *
	 * @var list<PdfRasterizerInterface>
	 */
	protected array $rasterizers;

	/**
	 * @param FileSystem            $fs          The filesystem API.
	 * @param ImagickRasterizer     $imagick     Imagick extension.
	 * @param GhostscriptRasterizer $ghostscript Ghostscript program.
	 * @param PopplerRasterizer     $poppler     Poppler's pdftoppm program.
	 */
	public function __construct(
		protected FileSystem $fs,
		ImagickRasterizer $imagick,
		GhostscriptRasterizer $ghostscript,
		PopplerRasterizer $poppler
	) {
		$this->rasterizers = [ $imagick, $ghostscript, $poppler ];
	}

	/**
	 * The rasterizers this server can use, by ID, in the order they are tried.
	 *
	 * @return list<string> E.g. [ "ghostscript", "poppler" ]; empty when PDFs cannot be drawn.
	 */
	public function get_available() : array {
		return array_values(
			array_map(
				static fn ( PdfRasterizerInterface $rasterizer ) : string => $rasterizer::get_id(),
				array_filter( $this->rasterizers, static fn ( PdfRasterizerInterface $rasterizer ) : bool => $rasterizer->is_available() )
			)
		);
	}

	/**
	 * Draw the first pages of a PDF into a directory, replacing pages drawn there before.
	 *
	 * @param string $pdf_path  Absolute path of the PDF.
	 * @param string $pages_dir Directory the pages are written to; created when missing.
	 * @param int    $max_pages Most pages drawn.
	 * @param int    $dpi       Resolution, in dots per inch.
	 * @return int|Exception The number of pages written, or why none were (each rasterizer's reason).
	 */
	public function rasterize( string $pdf_path, string $pages_dir, int $max_pages = 5, int $dpi = 150 ) : int|Exception {
		if ( ! $this->fs->is_file( $pdf_path ) ) {
			return new Exception( 'pdf_missing', 'The PDF does not exist.' );
		}

		$available = array_filter( $this->rasterizers, static fn ( PdfRasterizerInterface $rasterizer ) : bool => $rasterizer->is_available() );

		if ( [] === $available ) {
			return new Exception( 'rasterizer_unavailable', 'No PDF renderer is available: install the Imagick extension, Ghostscript or Poppler (pdftoppm).' );
		}

		$work = FileSystemHelper::join_path( sys_get_temp_dir(), sprintf( 'smliser-pdf-%s', bin2hex( random_bytes( 8 ) ) ) );
		$copy = FileSystemHelper::join_path( $work, 'document.pdf' );
		$out  = FileSystemHelper::join_path( $work, 'pages' );

		try {
			if ( '' === $work || ! $this->fs->mkdir( $work, 0700 ) || ! $this->fs->copy( $pdf_path, $copy ) ) {
				return new Exception( 'rasterize_failed', 'The PDF could not be copied to the system temporary directory.' );
			}

			$reasons = [];

			foreach ( $available as $rasterizer ) {
				$this->clear( $out );

				if ( ! $this->fs->mkdir( $out, 0700 ) ) {
					return new Exception( 'rasterize_failed', 'The temporary page directory could not be created.' );
				}

				$count = $rasterizer->rasterize( $copy, $out, max( 1, $max_pages ), max( 36, $dpi ) );

				if ( $count instanceof Exception ) {
					$reasons[] = sprintf( '%s: %s', $rasterizer::get_id(), $count->get_error_message() );
					continue;
				}

				return $this->publish( $out, $pages_dir, $count );
			}

			return new Exception( 'rasterize_failed', sprintf( 'The PDF could not be drawn. %s', implode( ' | ', $reasons ) ) );
		} finally {
			$this->clear( $work );
		}
	}

	/**
	 * The pages drawn into a directory, in page order.
	 *
	 * @param string $pages_dir The pages directory.
	 * @param int    $max_pages Most pages looked for.
	 * @return list<string> Absolute paths; empty when none were drawn.
	 */
	public function get_pages( string $pages_dir, int $max_pages = 5 ) : array {
		$pages = [];

		for ( $page = 1; $page <= $max_pages; $page++ ) {
			$path = FileSystemHelper::join_path( $pages_dir, sprintf( 'page-%d.png', $page ) );

			if ( ! $this->fs->is_file( $path ) ) {
				break;
			}

			$pages[] = $path;
		}

		return $pages;
	}

	/**
	 * Move drawn pages from the work directory to their own, replacing what was there.
	 *
	 * @param string $from  The work page directory.
	 * @param string $to    The pages directory.
	 * @param int    $count Pages drawn.
	 * @return int|Exception
	 */
	protected function publish( string $from, string $to, int $count ) : int|Exception {
		$this->clear( $to );

		if ( ! $this->fs->mkdir( $to, \SMLISER_DIR_PERMISSION ) ) {
			return new Exception( 'rasterize_failed', 'The page directory could not be created.' );
		}

		for ( $page = 1; $page <= $count; $page++ ) {
			$name = sprintf( 'page-%d.png', $page );

			if ( ! $this->fs->move( FileSystemHelper::join_path( $from, $name ), FileSystemHelper::join_path( $to, $name ) ) ) {
				$this->clear( $to );

				return new Exception( 'rasterize_failed', sprintf( 'Page %d could not be saved.', $page ) );
			}
		}

		return $count;
	}

	/**
	 * Remove a directory and what is in it, if it exists.
	 *
	 * @param string $dir
	 * @return void
	 */
	protected function clear( string $dir ) : void {
		if ( '' !== $dir && $this->fs->is_dir( $dir ) ) {
			$this->fs->rmdir( $dir, true );
		}
	}
}
