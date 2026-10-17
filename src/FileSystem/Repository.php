<?php
/**
 * Repository class for hosted application files.
 *
 * @author  Callistus Nwachukwu
 * @since   0.0.6
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\UploadedFileCollection;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileSystemException;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\HostedApps\Storage\AppFile;
use SmartLicenseServer\HostedApps\Storage\AppStorage;
use SmartLicenseServer\Utils\MDParser;
use ZipArchive;

/**
 * The rules of one hosted app type, applied to files kept in AppStorage.
 *
 * Philosophy: a repository decides what an app type's files mean and which
 * ones are allowed; AppStorage decides where they are kept. A repository
 * never sees a storage path. It reads and writes the package, manifest,
 * artifacts, assets and documents through the AppStorage facade, so every
 * app type works on whatever storage the site admin chose.
 *
 * This base class holds what every type shares: package uploads, asset
 * uploads, artifacts, the trash, documents (with a fallback to the copy
 * inside the package) and ZIP inspection. Each type adds its own rules:
 * which documents a package must carry, which asset names are allowed,
 * how assets are grouped, and how its documents and manifest are read.
 */
abstract class Repository {

	/**
	 * Allowed image extensions.
	 *
	 * @var list<string>
	 */
	public const array ALLOWED_IMAGE_EXTENSIONS = [ 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif' ];

	/**
	 * Allowed icon names.
	 *
	 * @var list<string>
	 */
	public const array ALLOWED_ICON_NAMES = [ 'icon-128x128', 'icon-256x256', 'icon' ];

	/**
	 * Constructor.
	 *
	 * @param AppStorage $storage The app storage facade.
	 * @param FileSystem $fs The FileSystem API, for local temporary files.
	 * @param MDParser $mdparser The markdown parser.
	 * @param URLManager $urlmanager The URL manager.
	 */
	public function __construct(
		protected AppStorage $storage,
		protected FileSystem $fs,
		protected MDParser $mdparser,
		protected URLManager $urlmanager,
	) {}

	/*
	|-----------------
	| ABSTRACT METHODS
	|-----------------
	*/

	/**
	 * Get the app type this repository serves.
	 *
	 * @return string The normalized app type, e.g. "plugin".
	 */
	abstract public function app_type() : string;

	/**
	 * Read the documents a package must or may carry.
	 *
	 * Called on an upload before it is stored. Returns the documents to
	 * keep next to the package; throws when a required one is missing.
	 *
	 * @param ZipArchive $zip The open package.
	 * @param string $root The package's top folder.
	 * @return array<string, string> Document contents keyed by document name.
	 * @throws Exception 400 when a required document is missing.
	 */
	abstract protected function package_documents( ZipArchive $zip, string $root ) : array;

	/**
	 * Check an uploaded asset and choose the name to store it under.
	 *
	 * @param UploadedFile $file The uploaded asset.
	 * @param string $type The asset type, e.g. "icon" or "screenshots".
	 * @param string $slug The app slug.
	 * @return Exception|string The asset name, or the reason it is refused.
	 */
	abstract public function validate_app_asset( UploadedFile $file, string $type, string $slug ) : Exception|string;

	/**
	 * Get the assets of one asset type, as the app type presents them.
	 *
	 * @param string $slug The app slug.
	 * @param string $type The asset type.
	 * @return mixed
	 */
	abstract public function get_assets( string $slug, string $type );

	/**
	 * Get the manifest, building and storing it when missing or broken.
	 *
	 * @param \SmartLicenseServer\HostedApps\AbstractHostedApp $app
	 * @return array<mixed>
	 */
	abstract public function get_app_dot_json( \SmartLicenseServer\HostedApps\AbstractHostedApp $app );

	/**
	 * Build the manifest and store it.
	 *
	 * @param \SmartLicenseServer\HostedApps\AbstractHostedApp $app
	 * @return array<mixed>
	 */
	abstract public function regenerate_app_dot_json( \SmartLicenseServer\HostedApps\AbstractHostedApp $app ) : array;

	/*
	|-----------
	| PACKAGE
	|-----------
	*/

	/**
	 * Make the slug for a new app from a name.
	 *
	 * Kept exactly as the repository has always made slugs, so new apps
	 * get the same kind of slug existing apps have: the name is cleaned
	 * like a file name, then cut at its first ".".
	 *
	 * @param string $name A name, e.g. "My Plugin" or "my-plugin.zip".
	 * @return string The slug; "" when nothing usable is left.
	 */
	public static function slug_from( string $name ) : string {
		$clean = FileSystemHelper::sanitize_filename( $name );
		$dot   = strpos( $clean, '.' );

		return false === $dot ? $clean : substr( $clean, 0, $dot );
	}

	/**
	 * Get the reference to an app's main package.
	 *
	 * @param string $slug The app slug.
	 * @return AppFile
	 * @throws StorageException INVALID_PATH for an invalid slug.
	 */
	public function package_file( string $slug ) : AppFile {
		return AppFile::package( $this->app_type(), $slug );
	}

	/**
	 * Tell whether an app has a stored package.
	 *
	 * @param string $slug The app slug.
	 * @return bool
	 */
	public function has_package( string $slug ) : bool {
		try {
			$this->storage->stat( $this->package_file( $slug ) );
			return true;
		} catch ( StorageException ) {
			return false;
		}
	}

	/**
	 * Store an uploaded application ZIP as the app's package.
	 *
	 * The upload is checked completely before anything is stored: it must
	 * be a real upload, a valid ZIP, and carry the documents the app type
	 * requires. Only then is it stored, so a bad upload never replaces a
	 * good package. The documents read from it are kept next to it; if
	 * keeping them fails they are read from the package again when needed.
	 *
	 * @param UploadedFile $file The uploaded file.
	 * @param string $name The app slug, or a name to make a new slug from.
	 * @param bool $update Whether this updates an existing app. A new app
	 *                     may not take a slug that is already used, by a
	 *                     live app or by one in the trash: a slug stays
	 *                     taken until its app is purged. An app in the
	 *                     trash cannot be updated until it is restored.
	 *                     Either way a slug never has a live and a trashed
	 *                     copy at once.
	 * @return string|Exception The app slug, or the reason it was refused.
	 */
	public function upload_zip( UploadedFile $file, string $name, bool $update = false ) : string|Exception {
		try {
			if ( ! $file->is_upload_successful() ) {
				throw new Exception( 'invalid_file', 'No file uploaded.', [ 'status' => 400 ] );
			}

			if ( '' === trim( $name ) ) {
				throw new Exception( 'invalid_filename', 'The new filename cannot be empty.', [ 'status' => 400 ] );
			}

			if ( ! $file->is_uploaded_file() ) {
				throw new Exception( 'invalid_temp_file', 'The temporary file is not valid.', [ 'status' => 400 ] );
			}

			if ( 'zip' !== $file->get_canonical_extension() ) {
				throw new Exception( 'invalid_file_type', 'The application archive file must be in ZIP format.', [ 'status' => 400 ] );
			}

			$slug = static::slug_from( $name );
			$type = $this->app_type();

			if ( '' === $slug || ! AppFile::is_segment( $slug ) ) {
				throw new Exception( 'invalid_filename', sprintf( 'No valid slug can be made from "%s".', $name ), [ 'status' => 400 ] );
			}

			if ( ! $update && $this->storage->has_app( $type, $slug ) ) {
				throw new Exception(
					'app_slug_exists',
					sprintf( 'The slug "%s" is not available, you can change the %s name and try again.', $slug, $type ),
					[ 'status' => 400 ]
				);
			}

			if ( ! $this->storage->has_app( $type, $slug ) && $this->is_trashed( $slug ) ) {
				throw $update
					? new Exception(
						'app_in_trash',
						sprintf( 'This %s is in the trash. Restore it before uploading a new version.', $type ),
						[ 'status' => 400 ]
					)
					: new Exception(
						'app_slug_exists',
						sprintf( 'The slug "%s" belongs to a %s in the trash. Restore or permanently delete it, or change the %s name and try again.', $slug, $type, $type ),
						[ 'status' => 400 ]
					);
			}

			$tmp_path  = (string) $file->get_tmp_path();
			$documents = $this->read_package_documents( $tmp_path );

			$this->storage->put_package( $type, $slug, $tmp_path );

			foreach ( $documents as $document => $contents ) {
				try {
					$this->storage->put_document( $type, $slug, $document, $contents );
				} catch ( StorageException ) {
					// read_document() reads it from the package instead.
				}
			}

			return $slug;
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Open a local ZIP and read the documents the app type wants from it.
	 *
	 * @param string $zip_path Absolute path of a local ZIP.
	 * @return array<string, string> Document contents keyed by document name.
	 * @throws Exception 400 when it is not a valid ZIP or a required
	 *                   document is missing.
	 */
	protected function read_package_documents( string $zip_path ) : array {
		$zip = new ZipArchive();

		if ( true !== $zip->open( $zip_path ) ) {
			throw new Exception( 'zip_invalid', 'Uploaded ZIP could not be opened.', [ 'status' => 400 ] );
		}

		try {
			return $this->package_documents( $zip, static::zip_root( $zip ) );
		} finally {
			$zip->close();
		}
	}

	/**
	 * Check whether the given file is a valid zip file.
	 *
	 * @param string $path Absolute local path.
	 * @return bool
	 */
	public function is_valid_zip( string $path ) : bool {
		if ( '' === $path ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( true === $zip->open( $path ) ) {
			$zip->close();
			return true;
		}

		return false;
	}

	/**
	 * Get a ZIP's top folder, the folder an app's files sit in.
	 *
	 * @param ZipArchive $zip An open ZIP.
	 * @return string The first entry's first segment.
	 */
	protected static function zip_root( ZipArchive $zip ) : string {
		return explode( '/', (string) $zip->getNameIndex( 0 ) )[0];
	}

	/**
	 * Read one file from an app's top folder in a ZIP.
	 *
	 * @param ZipArchive $zip An open ZIP.
	 * @param string $root The ZIP's top folder.
	 * @param string $name The file name; matched case-insensitively.
	 * @return string|null The contents, or null when it is not there.
	 */
	protected static function zip_entry( ZipArchive $zip, string $root, string $name ) : ?string {
		$index = $zip->locateName( $root . '/' . $name, ZipArchive::FL_NOCASE );

		if ( false === $index ) {
			return null;
		}

		$contents = $zip->getFromIndex( $index );

		return false === $contents ? null : $contents;
	}

	/**
	 * Run a callback on an app's stored package opened as a ZIP.
	 *
	 * When the storage keeps the package on this server it is opened in
	 * place; otherwise it is copied to SMLISER_TMP_DIR through a stream and
	 * the copy is deleted afterwards.
	 *
	 * @template T
	 * @param string $slug The app slug.
	 * @param callable(ZipArchive, string): T $callback Receives the open ZIP
	 *                                                  and its top folder.
	 * @return T|null The callback's result, or null when the app has no
	 *                package or it cannot be opened.
	 */
	protected function with_package_zip( string $slug, callable $callback ) : mixed {
		$temp = null;

		try {
			$package = $this->package_file( $slug );
			$path    = $this->storage->local_path( $package );

			if ( null === $path ) {
				$temp = @tempnam( SMLISER_TMP_DIR, 'smliser_pkg_' );

				if ( false === $temp ) {
					return null;
				}

				$stream = $this->storage->read_stream( $package );
				$copied = $this->fs->write_stream( $temp, $stream );
				fclose( $stream );

				if ( ! $copied ) {
					return null;
				}

				$path = $temp;
			}
		} catch ( StorageException ) {
			return null;
		}

		$zip = new ZipArchive();

		try {
			if ( true !== $zip->open( $path ) ) {
				return null;
			}

			try {
				return $callback( $zip, static::zip_root( $zip ) );
			} finally {
				$zip->close();
			}
		} finally {
			if ( null !== $temp ) {
				$this->fs->delete( $temp );
			}
		}
	}

	/*
	|-------------
	| DOCUMENTS
	|-------------
	*/

	/**
	 * Read a document, falling back to the copy inside the package.
	 *
	 * A document read from the package is stored for the next time.
	 *
	 * @param string $slug The app slug.
	 * @param string $name The document name, e.g. "readme.txt".
	 * @return string The contents; "" when neither the storage nor the
	 *                package has it.
	 */
	protected function read_document( string $slug, string $name ) : string {
		$type = $this->app_type();

		try {
			$contents = $this->storage->get_document( $type, $slug, $name );
		} catch ( StorageException ) {
			return '';
		}

		if ( null !== $contents ) {
			return $contents;
		}

		$contents = $this->with_package_zip(
			$slug,
			static fn( ZipArchive $zip, string $root ) : ?string => static::zip_entry( $zip, $root, $name )
		);

		if ( null === $contents ) {
			return '';
		}

		try {
			$this->storage->put_document( $type, $slug, $name, $contents );
		} catch ( StorageException ) {
			// Served from the package again next time.
		}

		return $contents;
	}

	/*
	|----------
	| ASSETS
	|----------
	*/

	/**
	 * Get the reference to one of an app's assets.
	 *
	 * @param string $slug The app slug.
	 * @param string $filename The asset name; cleaned like a file name.
	 * @return AppFile
	 * @throws StorageException INVALID_PATH when no valid name is left.
	 */
	public function asset_file( string $slug, string $filename ) : AppFile {
		return AppFile::asset( $this->app_type(), $slug, FileSystemHelper::sanitize_filename( $filename ) );
	}

	/**
	 * Get the public URL of an asset.
	 *
	 * @param string $slug The app slug.
	 * @param string $name The asset name.
	 * @return URL
	 */
	public function asset_url( string $slug, string $name ) : URL {
		return $this->urlmanager->app_asset_url( $this->app_type(), $slug, $name );
	}

	/**
	 * Get the names of an app's assets.
	 *
	 * @param string $slug The app slug.
	 * @return list<string> In byte order; empty when the app has none or
	 *                      does not exist.
	 */
	protected function asset_names( string $slug ) : array {
		try {
			return array_column( $this->storage->list_assets( $this->app_type(), $slug ), 'name' );
		} catch ( StorageException ) {
			return [];
		}
	}

	/**
	 * Find assets whose names start with a prefix and end in an extension.
	 *
	 * Matches the way the repository has always found assets: grouped by
	 * extension in the order given, each group in byte order.
	 *
	 * @param string $slug The app slug.
	 * @param string $prefix The start of the name, e.g. "banner-772x250."
	 *                       or "screenshot-".
	 * @param string[] $extensions Extensions, without the dot.
	 * @return list<string> Matching asset names.
	 */
	protected function find_assets( string $slug, string $prefix, array $extensions ) : array {
		$names   = $this->asset_names( $slug );
		$matches = [];

		foreach ( $extensions as $ext ) {
			foreach ( $names as $name ) {
				if ( str_starts_with( $name, $prefix ) && str_ends_with( $name, '.' . $ext ) && ! in_array( $name, $matches, true ) ) {
					$matches[] = $name;
				}
			}
		}

		return $matches;
	}

	/**
	 * Find the first asset named exactly "{basename}.{ext}".
	 *
	 * @param string $slug The app slug.
	 * @param string $basename The name without extension, e.g. "cover".
	 * @param string[] $extensions Extensions to try, in order.
	 * @return string|null The asset name, or null when none exists.
	 */
	protected function find_asset( string $slug, string $basename, array $extensions ) : ?string {
		$names = $this->asset_names( $slug );

		foreach ( $extensions as $ext ) {
			if ( in_array( $basename . '.' . $ext, $names, true ) ) {
				return $basename . '.' . $ext;
			}
		}

		return null;
	}

	/**
	 * Find the next free screenshot name.
	 *
	 * @param string $slug The app slug.
	 * @return string "screenshot-{n}", n being one above the highest in use.
	 */
	public function find_next_screenshot_name( string $slug ) : string {
		$indexes = [];

		foreach ( $this->find_assets( $slug, 'screenshot', static::ALLOWED_IMAGE_EXTENSIONS ) as $name ) {
			if ( preg_match( '/^screenshot-(\d+)\./', $name, $m ) ) {
				$indexes[] = (int) $m[1];
			}
		}

		return sprintf( 'screenshot-%d', [] === $indexes ? 1 : max( $indexes ) + 1 );
	}

	/**
	 * Get the name an uploaded asset is stored under.
	 *
	 * The extension always comes from the file's detected content, never
	 * from the name chosen for it, as UploadedFile::move() has always done.
	 *
	 * @param string $asset_name The name validate_app_asset() chose.
	 * @param UploadedFile $file The uploaded asset.
	 * @return string The name to store.
	 */
	protected static function stored_asset_name( string $asset_name, UploadedFile $file ) : string {
		return sprintf( '%s.%s', FileSystemHelper::remove_extension( $asset_name ), $file->get_canonical_extension() );
	}

	/**
	 * Safely upload application assets, never replacing an existing one.
	 *
	 * @param string $slug The application slug.
	 * @param UploadedFileCollection $files The uploaded files.
	 * @param string $asset_type The asset type (screenshots, icon, banners...).
	 * @return array{
	 *     uploaded: array<string, array{app_slug: string, app_type: string, asset_name: string, asset_url: string, asset_type: string}>,
	 *     failed: array<string, mixed>
	 * }
	 */
	public function safe_assets_upload( string $slug, UploadedFileCollection $files, string $asset_type ) : array {
		$result = [
			'uploaded' => [],
			'failed'   => [],
		];

		$type = $this->app_type();

		try {
			if ( ! $this->storage->has_app( $type, $slug ) ) {
				throw new StorageException( StorageException::NOT_FOUND, sprintf( 'The %s "%s" does not exist.', $type, $slug ), $type, $slug );
			}
		} catch ( StorageException $e ) {
			$result['failed']['system'] = $e->get_error_message();

			return $result;
		}

		foreach ( $files->all() as $file ) {
			$has_error = false;

			if ( ! $file->is_upload_successful() ) {
				$result['failed'][ $file->get_name() ]['partial_upload'] = 'Upload was not success.';
				$has_error = true;
			}

			if ( ! $file->is_uploaded_file() ) {
				$result['failed'][ $file->get_name() ]['invalid_http_upload'] = 'File is not a valid HTTP uploaded file.';
				$has_error = true;
			}

			if ( ! $file->is_moveable() ) {
				$result['failed'][ $file->get_name() ]['filesystem_error'] = 'Asset cannot be reliably moved to the repository.';
				$has_error = true;
			}

			$asset_name = $this->validate_app_asset( $file, $asset_type, $slug );

			if ( $asset_name instanceof Exception ) {
				$result['failed'][ $file->get_name() ][ $asset_name->get_error_code() ] = $asset_name->get_error_message();
				$has_error = true;
			}

			if ( $has_error ) {
				continue;
			}

			$stored = static::stored_asset_name( $asset_name, $file );

			try {
				if ( in_array( $stored, $this->asset_names( $slug ), true ) ) {
					throw new Exception( 'asset_exists', sprintf( 'An asset named "%s" already exists.', $stored ), [ 'status' => 409 ] );
				}

				$this->storage->put_asset( $type, $slug, $stored, (string) $file->get_tmp_path() );

				$result['uploaded'][ $file->get_client_name() ] = $this->asset_result( $slug, $stored, $asset_type );
			} catch ( Exception $e ) {
				$result['failed'][ $file->get_client_name() ][] = $e->get_error_message();
			}
		}

		return $result;
	}

	/**
	 * Put or replace one asset.
	 *
	 * Uploading "screenshot-{n}" removes any other "screenshot-{n}.*", so a
	 * screenshot replaced with a different image type does not leave the
	 * old one behind.
	 *
	 * @param string $app_slug The app slug.
	 * @param UploadedFile $file The uploaded asset.
	 * @param string $asset_type The asset type.
	 * @return array{app_slug: string, app_type: string, asset_name: string, asset_url: string, asset_type: string}|Exception
	 */
	public function put_app_asset( string $app_slug, UploadedFile $file, string $asset_type ) : array|Exception {
		$type = $this->app_type();

		try {
			if ( ! $this->storage->has_app( $type, $app_slug ) ) {
				throw new StorageException( StorageException::NOT_FOUND, sprintf( 'The %s "%s" does not exist.', $type, $app_slug ), $type, $app_slug );
			}

			if ( ! $file->is_moveable() ) {
				throw new FileSystemException( $file->get_error_message() );
			}

			$asset_name = $this->validate_app_asset( $file, $asset_type, $app_slug );

			if ( $asset_name instanceof Exception ) {
				throw $asset_name;
			}

			$stored = static::stored_asset_name( $asset_name, $file );

			if ( preg_match( '#screenshots?#i', $asset_type ) ) {
				$base = (string) $file->get_name( false );

				if ( str_starts_with( $base, 'screenshot-' ) ) {
					foreach ( $this->find_assets( $app_slug, $base . '.', static::ALLOWED_IMAGE_EXTENSIONS ) as $identical ) {
						if ( $identical !== $stored ) {
							$this->storage->delete_asset( $type, $app_slug, $identical );
						}
					}
				}
			}

			$this->storage->put_asset( $type, $app_slug, $stored, (string) $file->get_tmp_path() );

			return $this->asset_result( $app_slug, $stored, $asset_type );
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Delete an app asset.
	 *
	 * @param string $slug The app slug.
	 * @param string $filename The asset name.
	 * @return true|Exception
	 */
	public function delete_asset( string $slug, string $filename ) : true|Exception {
		try {
			$file = $this->asset_file( $slug, $filename );
			$this->storage->delete_asset( $file->type, $file->slug, $file->name );

			return true;
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Build the description of a stored asset that uploads return.
	 *
	 * @param string $slug The app slug.
	 * @param string $name The stored asset name.
	 * @param string $asset_type The asset type.
	 * @return array{app_slug: string, app_type: string, asset_name: string, asset_url: string, asset_type: string}
	 * @throws StorageException When the asset cannot be described.
	 */
	protected function asset_result( string $slug, string $name, string $asset_type ) : array {
		$mtime = $this->storage->stat( AppFile::asset( $this->app_type(), $slug, $name ) )['mtime'];

		return [
			'app_slug'   => $slug,
			'app_type'   => $this->app_type(),
			'asset_name' => $name,
			'asset_url'  => $this->asset_url( $slug, $name )->add_query_param( 'ver', $mtime )->url(),
			'asset_type' => $asset_type,
		];
	}

	/**
	 * Validate an image file.
	 *
	 * @param string $filename Absolute local path to the image file.
	 * @return bool|Exception
	 */
	protected function is_valid_image( string $filename ) : bool|Exception {
		$ext = FileSystemHelper::get_canonical_extension( $filename );

		if ( ! $ext ) {
			return new Exception( 'repo_error', 'The extension for the for this image could not be trusted.', [ 'status' => 400 ] );
		}

		if ( ! in_array( $ext, static::ALLOWED_IMAGE_EXTENSIONS, true ) ) {
			return new Exception(
				'file_ext_error',
				sprintf( 'Icon file must be one of: %s', implode( ', ', static::ALLOWED_IMAGE_EXTENSIONS ) ),
			);
		}

		$image_content = (string) $this->fs->get_contents( $filename );
		$is_malicious  = (bool) preg_match( '/<(script|php|eval|iframe|object|embed|form|input|button|link|style)[^>]*>/i', $image_content );

		if ( $is_malicious ) {
			return new Exception( 'malicious_content', 'Potentially malicious content detected in the image.' );
		}

		return true;
	}

	/*
	|-----------------------
	| ARTIFACTS OPERATIONS.
	|-----------------------
	*/

	/**
	 * Get all artifact files for an application.
	 *
	 * The main package comes first (slug "main"), then the artifacts,
	 * newest first.
	 *
	 * @param string $slug Application slug.
	 * @return list<array{slug: string, file: AppFile, size: int, mtime: int, mime_type: string, filename: string}>
	 */
	public function get_artifacts( string $slug ) : array {
		$type = $this->app_type();

		try {
			$artifacts = $this->storage->list_artifacts( $type, $slug );
		} catch ( StorageException ) {
			return [];
		}

		$files = [];

		foreach ( $artifacts as $info ) {
			$files[] = [
				'slug'      => FileSystemHelper::remove_extension( $info['name'] ),
				'file'      => AppFile::artifact( $type, $slug, $info['name'] ),
				'size'      => $info['size'],
				'mtime'     => $info['mtime'],
				'mime_type' => $info['mime_type'],
				'filename'  => $info['name'],
			];
		}

		usort( $files, static fn( array $a, array $b ) : int => $b['mtime'] <=> $a['mtime'] );

		try {
			$package = $this->package_file( $slug );
			$info    = $this->storage->stat( $package );

			array_unshift(
				$files,
				[
					'slug'      => 'main',
					'file'      => $package,
					'size'      => $info['size'],
					'mtime'     => $info['mtime'],
					'mime_type' => $info['mime_type'],
					'filename'  => $info['name'],
				]
			);
		} catch ( StorageException ) {
			// No package yet.
		}

		return $files;
	}

	/**
	 * Get a single artifact, the main package included.
	 *
	 * @param string $app_slug Application slug.
	 * @param string $artifact_filename Artifact file name.
	 * @return array{slug: string, file: AppFile, size: int, mtime: int, mime_type: string, filename: string}|null
	 */
	public function get_artifact( string $app_slug, string $artifact_filename ) : ?array {
		foreach ( $this->get_artifacts( $app_slug ) as $data ) {
			if ( $data['filename'] === $artifact_filename ) {
				return $data;
			}
		}

		return null;
	}

	/**
	 * Upload a new artifact or replace an existing one.
	 *
	 * The artifact is stored under `new_filename` exactly as given; the
	 * file's detected type never changes its name. When replacing an
	 * existing artifact (`overwrite` is `true`), the upload is stored first
	 * and the old name is removed afterwards, so a failed upload leaves the
	 * existing artifact as it was.
	 *
	 * @param array{
	 *     app_slug: string,
	 *     file: UploadedFile,
	 *     new_filename?: string,
	 *     overwrite?: bool,
	 *     filename?: string
	 * } $data {
	 *     Upload data.
	 *
	 *     @type string       $app_slug     Application slug.
	 *     @type UploadedFile $file         Uploaded artifact file.
	 *     @type string       $new_filename Optional. Name to store the artifact under. Default the client filename.
	 *     @type bool         $overwrite    Optional. Whether to replace an existing artifact. Default false.
	 *     @type string       $filename     Optional. Existing artifact filename when replacing an artifact.
	 * }
	 *
	 * @return array{filename: string, slug: string, size: int, mime_type: string, mtime: int}|Exception
	 */
	public function upload_artifact( array $data ) : array|Exception {
		try {
			$app_slug = (string) ( $data['app_slug'] ?? '' );

			if ( '' === $app_slug ) {
				throw new FileSystemException( 'App slug is required to upload artifact.' );
			}

			$file = $data['file'] ?? null;

			if ( ! $file || ! $file->is_upload_successful() ) {
				throw new FileSystemException( 'Artifact file was not uploaded' );
			}

			if ( ! $file->is_moveable() ) {
				throw new FileSystemException( $file->get_error_message() );
			}

			// The artifact is stored under the name the admin chose, exactly.
			// Detected MIME types and canonical extensions are not consulted:
			// "app-1.2.0.zip.sha256" stays ".sha256", not ".txt".
			$new_filename = $this->validate_artifact_filename(
				(string) ( $data['new_filename'] ?? $file->get_client_name() )
			);

			$overwrite = (bool) ( $data['overwrite'] ?? false );
			$filename  = '';
			$type      = $this->app_type();
			$existing  = array_column( $this->storage->list_artifacts( $type, $app_slug ), 'name' );

			if ( $overwrite ) {
				// We are dealing with file edit: the upload replaces $filename,
				// and may give it a new name at the same time.
				$filename = $this->validate_artifact_filename( (string) ( $data['filename'] ?? '' ) );

				if ( ! in_array( $filename, $existing, true ) ) {
					throw new Exception(
						'resource_not_found',
						sprintf( 'The artifact "%s" does not exist.', $filename ),
						[ 'status' => 404 ]
					);
				}
			}

			// Never replace a different artifact that already has the name.
			if ( $new_filename !== $filename && in_array( $new_filename, $existing, true ) ) {
				throw new Exception(
					'artifact_exists',
					sprintf( 'An artifact named "%s" already exists. Edit that artifact to replace it, or choose another name.', $new_filename ),
					[ 'status' => 409 ]
				);
			}

			$this->storage->put_artifact( $type, $app_slug, $new_filename, (string) $file->get_tmp_path(), $new_filename === $filename );

			if ( '' !== $filename && $new_filename !== $filename ) {
				try {
					$this->storage->delete_artifact( $type, $app_slug, $filename );
				} catch ( StorageException ) {
					// The new artifact is stored; the old name stays until deleted.
				}
			}

			$info = $this->storage->stat( AppFile::artifact( $type, $app_slug, $new_filename ) );

			return [
				'filename'  => $new_filename,
				'slug'      => FileSystemHelper::remove_extension( $new_filename ),
				'size'      => $info['size'],
				'mime_type' => $info['mime_type'],
				'mtime'     => $info['mtime'],
			];
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Rename an artifact file.
	 *
	 * @param string $app_slug The app slug.
	 * @param string $artifact_filename The current artifact file name.
	 * @param string $new_filename The new artifact file name.
	 * @return array{filename: string, slug: string, size: int, mime_type: string, mtime: int}|Exception
	 */
	public function rename_artifact( string $app_slug, string $artifact_filename, string $new_filename ) : array|Exception {
		try {
			$artifact = $this->get_artifact( $app_slug, $artifact_filename );

			if ( ! $artifact ) {
				throw new Exception(
					'resource_not_found',
					sprintf( 'The artifact file with name "%s" cannot be renamed to "%s" because it does not exist.', $artifact_filename, $new_filename ),
					[ 'status' => 404 ]
				);
			}

			// The main package is listed with the artifacts but managed by upload_zip().
			if ( AppFile::PACKAGE === $artifact['file']->kind ) {
				throw new Exception( 'invalid_input', 'The main application package cannot be renamed as an artifact.', [ 'status' => 400 ] );
			}

			// The name is used exactly as given, extension included.
			$new_filename = $this->validate_artifact_filename( $new_filename );

			if ( $new_filename !== $artifact_filename && null !== $this->get_artifact( $app_slug, $new_filename ) ) {
				throw new Exception(
					'artifact_exists',
					sprintf( 'An artifact named "%s" already exists.', $new_filename ),
					[ 'status' => 409 ]
				);
			}

			$type = $this->app_type();

			$this->storage->rename_artifact( $type, $app_slug, $artifact_filename, $new_filename );

			$info = $this->storage->stat( AppFile::artifact( $type, $app_slug, $new_filename ) );

			return [
				'filename'  => $new_filename,
				'slug'      => FileSystemHelper::remove_extension( $new_filename ),
				'size'      => $info['size'],
				'mime_type' => $info['mime_type'],
				'mtime'     => $info['mtime'],
			];
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Check an admin-chosen artifact filename and return it unchanged.
	 *
	 * The name is never rewritten (no extension is added, removed or
	 * swapped), and any extension is accepted: artifacts are only ever sent
	 * as downloads, never executed. A name is refused only when it is a
	 * path instead of a name, a dot file (an .htaccess or .user.ini could
	 * change how a server treats the directory), or contains control
	 * characters.
	 *
	 * @param string $filename The filename to check.
	 * @return string The same filename, trimmed of surrounding whitespace.
	 * @throws Exception 400 when the name is not acceptable.
	 */
	protected function validate_artifact_filename( string $filename ) : string {
		$filename = trim( $filename );
		$reject   = static function ( string $reason ) : never {
			throw new Exception( 'invalid_input', $reason, [ 'status' => 400 ] );
		};

		if ( '' === $filename ) {
			$reject( 'The artifact filename must not be empty.' );
		}

		if ( \strlen( $filename ) > 255 ) {
			$reject( 'The artifact filename must not be longer than 255 characters.' );
		}

		if ( \str_contains( $filename, '/' ) || \str_contains( $filename, '\\' ) || \str_contains( $filename, '..' ) ) {
			$reject( 'The artifact filename must be a name, not a path.' );
		}

		if ( \str_starts_with( $filename, '.' ) ) {
			$reject( 'The artifact filename must not start with a dot.' );
		}

		if ( \preg_match( '/[\x00-\x1F\x7F]/', $filename ) ) {
			$reject( 'The artifact filename contains invalid characters.' );
		}

		return $filename;
	}

	/**
	 * Delete an artifact file.
	 *
	 * @param string $app_slug Application slug.
	 * @param string $artifact_filename Artifact filename.
	 * @return true|Exception True on success, otherwise an Exception.
	 */
	public function delete_artifact( string $app_slug, string $artifact_filename ) : true|Exception {
		try {
			$artifact = $this->get_artifact( $app_slug, $artifact_filename );

			if ( ! $artifact ) {
				throw new Exception(
					'resource_not_found',
					sprintf( 'The artifact file "%s" does not exist.', $artifact_filename ),
					[ 'status' => 404 ]
				);
			}

			if ( AppFile::PACKAGE === $artifact['file']->kind ) {
				throw new Exception( 'invalid_input', 'The main application package cannot be deleted as an artifact.', [ 'status' => 400 ] );
			}

			$this->storage->delete_artifact( $this->app_type(), $app_slug, $artifact_filename );

			return true;
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/*
	|---------
	| TRASH
	|---------
	*/

	/**
	 * Move an app's files to the trash.
	 *
	 * @param string $slug The app slug.
	 * @return true|Exception
	 */
	public function trash( string $slug ) : true|Exception {
		try {
			$this->storage->trash_app( $this->app_type(), $slug );

			return true;
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Bring an app's files back from the trash.
	 *
	 * @param string $slug The app slug.
	 * @return true|Exception
	 */
	public function restore_from_trash( string $slug ) : true|Exception {
		try {
			$this->storage->restore_app( $this->app_type(), $slug );

			return true;
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Permanently delete an app's files, live and trashed.
	 *
	 * @param string $slug The app slug.
	 * @return true|Exception
	 */
	public function purge( string $slug ) : true|Exception {
		try {
			$this->storage->purge_app( $this->app_type(), $slug );

			return true;
		} catch ( Exception $e ) {
			return $e;
		}
	}

	/**
	 * Tell whether an app is in the trash.
	 *
	 * @param string $slug The app slug.
	 * @return bool True when this type's trash holds the slug.
	 */
	public function is_trashed( string $slug ) : bool {
		return in_array( $slug, array_column( $this->list_trashed(), 'slug' ), true );
	}

	/**
	 * List this type's trashed apps.
	 *
	 * @return list<array{slug: string, trashed_at: int}>
	 */
	public function list_trashed() : array {
		try {
			return $this->storage->list_trashed( $this->app_type() );
		} catch ( StorageException ) {
			return [];
		}
	}
}