<?php
/**
 * Storage exception class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Exceptions
 */

namespace SmartLicenseServer\Exceptions;

/**
 * Thrown by an AppStorageInterface implementation when an operation fails.
 *
 * Every instance carries exactly one error code from the closed set below,
 * so callers branch on get_error_code() instead of parsing messages. The
 * error data always holds the HTTP status for that code under 'status',
 * plus the 'app_type', 'slug' and 'file' the operation was given.
 */
class StorageException extends Exception {

	/**
	 * The app or file does not exist.
	 */
	public const NOT_FOUND = 'storage_not_found';

	/**
	 * The target already exists and the operation never overwrites it.
	 */
	public const ALREADY_EXISTS = 'storage_already_exists';

	/**
	 * The slug or file key breaks the key rules, or would reach outside
	 * the app.
	 */
	public const INVALID_PATH = 'storage_invalid_path';

	/**
	 * A file was given where a directory is required, or the reverse.
	 */
	public const WRONG_TYPE = 'storage_wrong_type';

	/**
	 * A directory still holds entries and the operation is not recursive.
	 */
	public const NOT_EMPTY = 'storage_not_empty';

	/**
	 * The storage backend refused or failed the operation.
	 */
	public const IO_ERROR = 'storage_io_error';

	/**
	 * HTTP status for each error code.
	 *
	 * @var array<string, int>
	 */
	protected const STATUS = [
		self::NOT_FOUND      => 404,
		self::ALREADY_EXISTS => 409,
		self::INVALID_PATH   => 400,
		self::WRONG_TYPE     => 409,
		self::NOT_EMPTY      => 409,
		self::IO_ERROR       => 500,
	];

	/**
	 * Construct the exception.
	 *
	 * @param string $code One of the class constants.
	 * @param string $message Human-readable message.
	 * @param string $app_type The storage's app type.
	 * @param string $slug The app slug the operation was given, or ''.
	 * @param string $file The file key the operation was given, or ''.
	 * @param \Throwable|null $previous Optional previous exception.
	 * @throws \InvalidArgumentException When $code is not one of the constants.
	 */
	public function __construct(
		string $code,
		string $message,
		string $app_type = '',
		string $slug = '',
		string $file = '',
		?\Throwable $previous = null
	) {
		if ( ! isset( static::STATUS[ $code ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown storage error code "%s".', $code ) );
		}

		parent::__construct(
			$code,
			$message,
			[
				'status'   => static::STATUS[ $code ],
				'app_type' => $app_type,
				'slug'     => $slug,
				'file'     => $file,
			],
			$previous
		);
	}
}