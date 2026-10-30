<?php
/**
 * GhostscriptRasterizer class file.
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
 * Draws PDF pages by running Ghostscript ("gs"; gswin64c on Windows).
 *
 * Runs with -dSAFER, so the PDF cannot read or write files beyond the ones
 * named, and the png16m device, which draws on white.
 */
class GhostscriptRasterizer implements PdfRasterizerInterface {

	/**
	 * Seconds Ghostscript may take for one PDF.
	 *
	 * @var int
	 */
	public const TIMEOUT = 60;

	/**
	 * Program names, most preferred first.
	 *
	 * @var string[]
	 */
	public const PROGRAMS = [ 'gs', 'gswin64c', 'gswin32c' ];

	/**
	 * @param FileSystem $fs The filesystem API.
	 */
	public function __construct(
		protected FileSystem $fs
	) {}

	public static function get_id() : string {
		return 'ghostscript';
	}

	public function is_available() : bool {
		return null !== Process::find_executable( ...self::PROGRAMS );
	}

	public function rasterize( string $pdf, string $out_dir, int $max_pages, int $dpi ) : int|Exception {
		$gs = Process::find_executable( ...self::PROGRAMS );

		if ( null === $gs ) {
			return new Exception( 'rasterizer_unavailable', 'Ghostscript is not installed.' );
		}

		$run = Process::run(
			[
				$gs,
				'-dSAFER',
				'-dBATCH',
				'-dNOPAUSE',
				'-dQUIET',
				'-sDEVICE=png16m',
				'-dTextAlphaBits=4',
				'-dGraphicsAlphaBits=4',
				sprintf( '-r%d', $dpi ),
				'-dFirstPage=1',
				sprintf( '-dLastPage=%d', $max_pages ),
				// "%d" is Ghostscript's page number; FileSystemHelper::join_path() rightly refuses "%" in paths.
				'-sOutputFile=' . rtrim( $out_dir, '/\\' ) . DIRECTORY_SEPARATOR . 'page-%d.png',
				$pdf,
			],
			self::TIMEOUT
		);

		if ( 0 !== $run['exit_code'] ) {
			return new Exception( 'rasterize_failed', self::describe( $run ) );
		}

		$count = 0;

		while ( $count < $max_pages && $this->fs->is_file( FileSystemHelper::join_path( $out_dir, sprintf( 'page-%d.png', $count + 1 ) ) ) ) {
			++$count;
		}

		return $count > 0 ? $count : new Exception( 'rasterize_failed', 'Ghostscript wrote no pages.' );
	}

	/**
	 * Why a run failed, in Ghostscript's own words (it reports on its output as often as on its errors).
	 *
	 * @param array{exit_code: int, output: string, error: string, timed_out: bool} $run
	 * @return string
	 */
	private static function describe( array $run ) : string {
		if ( $run['timed_out'] ) {
			return sprintf( 'Ghostscript took longer than %d seconds.', self::TIMEOUT );
		}

		$said = trim( $run['error'] . "\n" . $run['output'] );

		return sprintf( 'Ghostscript exited with code %d%s', $run['exit_code'], '' === $said ? '.' : ': ' . substr( (string) preg_replace( '/\s+/', ' ', $said ), 0, 500 ) );
	}
}
