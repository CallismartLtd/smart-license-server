<?php
/**
 * PopplerRasterizer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Pdf\Rasterizers
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core\Pdf\Rasterizers;

use SmartLicenseServer\Core\Process\Process;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;

/**
 * Draws PDF pages by running Poppler's pdftoppm (package poppler-utils).
 *
 * pdftoppm numbers its files with as many digits as the PDF has pages
 * (page-1.png, or page-01.png, …); they are renamed to page-1.png, ….
 */
class PopplerRasterizer implements PdfRasterizerInterface {

	/**
	 * Seconds pdftoppm may take for one PDF.
	 *
	 * @var int
	 */
	public const TIMEOUT = 60;

	/**
	 * @param FileSystem $fs The filesystem API.
	 */
	public function __construct(
		protected FileSystem $fs
	) {}

	public static function get_id() : string {
		return 'poppler';
	}

	public function is_available() : bool {
		return null !== Process::find_executable( 'pdftoppm' );
	}

	public function rasterize( string $pdf, string $out_dir, int $max_pages, int $dpi ) : int|Exception {
		$pdftoppm = Process::find_executable( 'pdftoppm' );

		if ( null === $pdftoppm ) {
			return new Exception( 'rasterizer_unavailable', 'Poppler (pdftoppm) is not installed.' );
		}

		$run = Process::run(
			[ $pdftoppm, '-png', '-r', (string) $dpi, '-f', '1', '-l', (string) $max_pages, $pdf, FileSystemHelper::join_path( $out_dir, 'page' ) ],
			self::TIMEOUT
		);

		if ( 0 !== $run['exit_code'] ) {
			$said = trim( (string) preg_replace( '/\s+/', ' ', $run['error'] . ' ' . $run['output'] ) );

			return new Exception(
				'rasterize_failed',
				$run['timed_out']
					? sprintf( 'pdftoppm took longer than %d seconds.', self::TIMEOUT )
					: sprintf( 'pdftoppm exited with code %d%s', $run['exit_code'], '' === $said ? '.' : ': ' . substr( $said, 0, 500 ) )
			);
		}

		// Page number => file name, whatever the zero-padding.
		$pages = [];

		foreach ( (array) $this->fs->list_dir( $out_dir ) as $entry ) {
			$file = (string) ( $entry['path'] ?? '' );

			if ( preg_match( '/^page-(\d+)\.png$/', basename( $file ), $m ) ) {
				$pages[ (int) $m[1] ] = basename( $file );
			}
		}

		ksort( $pages );

		$count = 0;

		foreach ( $pages as $file ) {
			++$count;
			$name = sprintf( 'page-%d.png', $count );

			if ( $file !== $name && ! $this->fs->move( FileSystemHelper::join_path( $out_dir, $file ), FileSystemHelper::join_path( $out_dir, $name ) ) ) {
				return new Exception( 'rasterize_failed', sprintf( 'Page %d could not be renamed.', $count ) );
			}
		}

		return $count > 0 ? $count : new Exception( 'rasterize_failed', 'pdftoppm wrote no pages.' );
	}
}