<?php
/**
 * App storage facade class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\Storage
 */

namespace SmartLicenseServer\HostedApps\Storage;

use RuntimeException;
use SmartLicenseServer\Exceptions\StorageException;

/**
 * The one entry point for hosted app files.
 *
 * Proxies every call to the adapter the site admin chose: a default adapter
 * for all types, plus optional per-type overrides. It implements the same
 * contract as the adapters, so callers never know or care which adapter
 * answers.
 *
 * Memoization: answers to reads (has_app, get_manifest, get_document,
 * list_artifacts, list_assets, stat, list_trashed) are kept until a write
 * through this facade changes the same app, or flush() is called. Loading
 * one app asks for the same readme and asset listing many times; with a
 * remote adapter each ask would be a round trip. Changes made behind the
 * facade's back (another process, the shell) are not seen until flush(), so
 * long-running processes (queue workers, the interactive shell) call
 * flush() between units of work. Failures are never memoized.
 */
final class AppStorage implements AppStorageAdapterInterface {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Memoized answers, keyed by app ("type/slug") or by type ("type/"),
	 * then by question.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $memo = [];

	/**
	 * Construct the facade.
	 *
	 * @param AppStorageAdapterInterface $default Adapter for every type
	 *                                            without an override.
	 * @param array<string, AppStorageAdapterInterface> $overrides Adapter for
	 *                                                             particular types.
	 */
	private function __construct(
		private readonly AppStorageAdapterInterface $default,
		private readonly array $overrides,
	) {}

	/**
	 * Get the facade, building it on the first call.
	 *
	 * @param AppStorageAdapterInterface|null $default Required on the first
	 *                                                 call; ignored after.
	 * @param array<string, AppStorageAdapterInterface> $overrides Optional
	 *        per-type adapters, used on the first call only.
	 * @return self
	 * @throws RuntimeException When first called without a default adapter.
	 * @throws \InvalidArgumentException When an override's key is not a
	 *                                   valid type.
	 */
	public static function instance( ?AppStorageAdapterInterface $default = null, array $overrides = [] ) : self {
		if ( null === self::$instance ) {
			if ( null === $default ) {
				throw new RuntimeException( 'App storage must be called with a default adapter early.' );
			}

			foreach ( array_keys( $overrides ) as $type ) {
				if ( ! AppFile::is_identifier( (string) $type ) ) {
					throw new \InvalidArgumentException( sprintf( 'Invalid app storage override for type "%s".', $type ) );
				}
			}

			self::$instance = new self( $default, $overrides );
		}

		return self::$instance;
	}

	/**
	 * Forget the singleton, so the next instance() call builds a new one.
	 *
	 * @return void
	 */
	public static function reset_instance() : void {
		self::$instance = null;
	}

	/**
	 * Get the adapter that serves a type.
	 *
	 * @param string $type App type.
	 * @return AppStorageAdapterInterface
	 */
	public function adapter( string $type ) : AppStorageAdapterInterface {
		return $this->overrides[ $type ] ?? $this->default;
	}

	/**
	 * Forget every memoized answer.
	 *
	 * @return void
	 */
	public function flush() : void {
		$this->memo = [];
	}

	/*
	|--------
	| Apps
	|--------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function has_app( string $type, string $slug ) : bool {
		return $this->remember( $type . '/' . $slug, 'has_app', fn() => $this->adapter( $type )->has_app( $type, $slug ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function trash_app( string $type, string $slug ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->trash_app( $type, $slug );
	}

	/**
	 * {@inheritDoc}
	 */
	public function restore_app( string $type, string $slug ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->restore_app( $type, $slug );
	}

	/**
	 * {@inheritDoc}
	 */
	public function purge_app( string $type, string $slug ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->purge_app( $type, $slug );
	}

	/**
	 * {@inheritDoc}
	 */
	public function list_trashed( string $type ) : array {
		return $this->remember( $type . '/', 'list_trashed', fn() => $this->adapter( $type )->list_trashed( $type ) );
	}

	/*
	|-----------
	| Package
	|-----------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function put_package( string $type, string $slug, string $local_path ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->put_package( $type, $slug, $local_path );
	}

	/*
	|------------
	| Manifest
	|------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function get_manifest( string $type, string $slug ) : ?array {
		return $this->remember( $type . '/' . $slug, 'manifest', fn() => $this->adapter( $type )->get_manifest( $type, $slug ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_manifest( string $type, string $slug, array $manifest ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->put_manifest( $type, $slug, $manifest );
	}

	/*
	|-------------
	| Artifacts
	|-------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function list_artifacts( string $type, string $slug ) : array {
		return $this->remember( $type . '/' . $slug, 'artifacts', fn() => $this->adapter( $type )->list_artifacts( $type, $slug ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_artifact( string $type, string $slug, string $name, string $local_path, bool $overwrite = false ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->put_artifact( $type, $slug, $name, $local_path, $overwrite );
	}

	/**
	 * {@inheritDoc}
	 */
	public function rename_artifact( string $type, string $slug, string $from, string $to ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->rename_artifact( $type, $slug, $from, $to );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_artifact( string $type, string $slug, string $name ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->delete_artifact( $type, $slug, $name );
	}

	/*
	|----------
	| Assets
	|----------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function list_assets( string $type, string $slug ) : array {
		return $this->remember( $type . '/' . $slug, 'assets', fn() => $this->adapter( $type )->list_assets( $type, $slug ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_asset( string $type, string $slug, string $name, string $local_path ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->put_asset( $type, $slug, $name, $local_path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_asset( string $type, string $slug, string $name ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->delete_asset( $type, $slug, $name );
	}

	/*
	|-------------
	| Documents
	|-------------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function get_document( string $type, string $slug, string $name ) : ?string {
		return $this->remember( $type . '/' . $slug, 'document:' . $name, fn() => $this->adapter( $type )->get_document( $type, $slug, $name ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function put_document( string $type, string $slug, string $name, string $contents ) : void {
		$this->changed( $type, $slug );
		$this->adapter( $type )->put_document( $type, $slug, $name, $contents );
	}

	/*
	|-----------
	| Serving
	|-----------
	*/

	/**
	 * {@inheritDoc}
	 */
	public function stat( AppFile $file ) : array {
		return $this->remember(
			$file->type . '/' . $file->slug,
			'stat:' . $file->kind . ':' . $file->name,
			fn() => $this->adapter( $file->type )->stat( $file )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function read_stream( AppFile $file, int $offset = 0, ?int $length = null ) : mixed {
		return $this->adapter( $file->type )->read_stream( $file, $offset, $length );
	}

	/**
	 * {@inheritDoc}
	 */
	public function local_path( AppFile $file ) : ?string {
		return $this->adapter( $file->type )->local_path( $file );
	}

	/**
	 * {@inheritDoc}
	 */
	public function temporary_url( AppFile $file, int $ttl ) : ?string {
		return $this->adapter( $file->type )->temporary_url( $file, $ttl );
	}

	/*
	|--------------------------
	| Repository browser
	|--------------------------
	*/

	/**
	 * {@inheritDoc}
	 *
	 * The top level merges every adapter's types, each type listed by the
	 * adapter that serves it here.
	 */
	public function browse( string $type = '', string $slug = '' ) : array {
		if ( '' !== $type ) {
			return $this->adapter( $type )->browse( $type, $slug );
		}

		if ( '' !== $slug ) {
			throw new StorageException( StorageException::INVALID_PATH, 'Browsing an app needs its type.', '', $slug );
		}

		$adapters = [ $this->default ];

		foreach ( $this->overrides as $adapter ) {
			if ( ! in_array( $adapter, $adapters, true ) ) {
				$adapters[] = $adapter;
			}
		}

		$entries = [];

		foreach ( $adapters as $adapter ) {
			foreach ( $adapter->browse() as $entry ) {
				if ( $this->adapter( $entry['name'] ) === $adapter ) {
					$entries[ $entry['path'] ] = $entry;
				}
			}
		}

		ksort( $entries, \SORT_STRING );

		return array_values( $entries );
	}

	/*
	|-----------
	| Helpers
	|-----------
	*/

	/**
	 * Return a memoized answer, asking the adapter the first time.
	 *
	 * @template T
	 * @param string $scope "type/slug" for an app, "type/" for a type.
	 * @param string $question What is asked.
	 * @param callable(): T $ask Asks the adapter.
	 * @return T
	 */
	private function remember( string $scope, string $question, callable $ask ) : mixed {
		if ( isset( $this->memo[ $scope ] ) && array_key_exists( $question, $this->memo[ $scope ] ) ) {
			return $this->memo[ $scope ][ $question ];
		}

		$answer = $ask();

		$this->memo[ $scope ][ $question ] = $answer;

		return $answer;
	}

	/**
	 * Forget what is memoized about an app and its type's trash.
	 *
	 * Called before a write, so a write that fails halfway is never hidden
	 * behind an old answer.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 */
	private function changed( string $type, string $slug ) : void {
		unset( $this->memo[ $type . '/' . $slug ], $this->memo[ $type . '/' ] );
	}
}