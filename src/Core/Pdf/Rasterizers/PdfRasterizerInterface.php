<?php
/**
 * PdfRasterizerInterface file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core\Pdf\Rasterizers
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core\Pdf\Rasterizers;

use SmartLicenseServer\Exceptions\Exception;

/**
 * One way of drawing PDF pages as PNG images (an extension or a program).
 *
 * A rasterizer only reads and writes in the work directory PdfRasterizer
 * gives it, which is in the system temporary directory: programs such as
 * Ghostscript may be confined there (e.g. by AppArmor).
 */
interface PdfRasterizerInterface {

	/**
	 * Short identifier, e.g. "ghostscript".
	 *
	 * @return string
	 */
	public static function get_id() : string;

	/**
	 * Whether this server can use it (the extension is loaded, or the program is installed and can be run).
	 *
	 * @return bool
	 */
	public function is_available() : bool;

	/**
	 * Draw the first pages of a PDF as page-1.png, page-2.png, … on a white background.
	 *
	 * @param string $pdf       The PDF, inside the work directory.
	 * @param string $out_dir   Existing, empty directory for the images, inside the work directory.
	 * @param int    $max_pages Most pages drawn.
	 * @param int    $dpi       Resolution, in dots per inch.
	 * @return int|Exception The number of pages written, or why none were.
	 */
	public function rasterize( string $pdf, string $out_dir, int $max_pages, int $dpi ) : int|Exception;
}
