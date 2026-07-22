<?php
/**
 * FileOpenMode enum file.
 *
 * @package SmartLicenseServer\Utils
 */

declare(strict_types=1);

namespace SmartLicenseServer\Utils;

/**
 * File opening modes for fopen()-compatible operations.
 *
 * @see https://www.php.net/manual/en/function.fopen.php
 */
enum FileOpenMode: string {

	/**
	 * Open for reading only.
	 * File pointer starts at the beginning of the file.
	 */
	case READ = 'r';

	/**
	 * Open for reading and writing.
	 * File pointer starts at the beginning of the file.
	 */
	case READ_WRITE = 'r+';

	/**
	 * Open for writing only.
	 * Truncates the file to zero length or creates it.
	 */
	case WRITE = 'w';

	/**
	 * Open for reading and writing.
	 * Truncates the file to zero length or creates it.
	 */
	case WRITE_READ = 'w+';

	/**
	 * Open for writing only.
	 * Appends to the end of the file or creates it.
	 */
	case APPEND = 'a';

	/**
	 * Open for reading and writing.
	 * Appends to the end of the file or creates it.
	 */
	case APPEND_READ = 'a+';

	/**
	 * Open for writing only.
	 * Creates the file exclusively; fails if it already exists.
	 */
	case CREATE = 'x';

	/**
	 * Open for reading and writing.
	 * Creates the file exclusively; fails if it already exists.
	 */
	case CREATE_READ = 'x+';

	/**
	 * Open for writing only.
	 * Creates the file if it does not exist.
	 * Does not truncate existing files.
	 * File pointer starts at the beginning.
	 */
	case CREATE_IF_MISSING = 'c';

	/**
	 * Open for reading and writing.
	 * Creates the file if it does not exist.
	 * Does not truncate existing files.
	 * File pointer starts at the beginning.
	 */
	case CREATE_IF_MISSING_READ = 'c+';

	/**
	 * Open for writing only in binary mode.
	 */
	case WRITE_BINARY = 'wb';

	/**
	 * Open for reading only in binary mode.
	 */
	case READ_BINARY = 'rb';

	/**
	 * Open for appending in binary mode.
	 */
	case APPEND_BINARY = 'ab';
}