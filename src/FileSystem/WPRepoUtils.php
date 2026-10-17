<?php
/**
 * Unified WordPress Repository utilities.
 *
 * Handles app.json resolution, reading, creation, and regeneration
 * for hosted WordPress plugins and themes.
 *
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\Plugin;
use SmartLicenseServer\HostedApps\Theme;
use TypeError;

trait WPRepoUtils {
	private const IMMUTABLE_MANIFEST_KEYS = [
		'name',
		'slug',
		'version',
		'type',
	];

	/**
	 * Get the content of an app's app.json file.
	 *
	 * If the file does not exist or is corrupted, it will be regenerated
	 * from the canonical manifest builder.
	 *
	 * @param Plugin|Theme $app
	 * @return array
	 *
	 * @throws TypeError When $app is not a Plugin or Theme.
	 */
	public function get_app_dot_json( $app ) : array {
		try {
			$manifest = $this->storage->get_manifest( $this->app_type(), $app->get_slug() );
		} catch ( StorageException ) {
			// The app is not stored yet: return the canonical manifest.
			$manifest = null;
		}

		// Missing or corrupted: rebuild it.
		return $manifest ?? $this->regenerate_app_dot_json( $app );
	}

	/**
	 * Regenerate an app.json file from the canonical manifest.
	 *
	 * The manifest is stored whenever the app exists in storage, whatever
	 * the current file holds; it is returned either way.
	 *
	 * @param Plugin|Theme $app
	 * @return array The regenerated manifest content.
	 *
	 * @throws TypeError When $app is not a Plugin or Theme.
	 */
	public function regenerate_app_dot_json( $app ) : array {
		$manifest = $this->build_app_manifest( $app );

		try {
			if ( $this->storage->has_app( $this->app_type(), $app->get_slug() ) ) {
				$this->storage->put_manifest( $this->app_type(), $app->get_slug(), $manifest );
			}
		} catch ( StorageException ) {
			// Rebuilt again on the next read.
		}

		return $manifest;
	}

	/**
	 * Build the canonical app manifest.
	 *
	 * @param Plugin|Theme $app
	 * @return array
	 */
	protected function build_app_manifest( Plugin|Theme $app ) : array {

		$slug             = $app->get_slug();
		$metadata         = $this->get_metadata( $slug );
		$defaults         = $this->default_manifest( $app, $metadata );
		$current_manifest = $app->get_manifest();

		$current_manifest = is_array( $current_manifest )
			? $current_manifest
			: [];

		// Allow overrides
		$manifest = array_merge( $defaults, $current_manifest );

		// Enforce canonical keys.
		foreach ( self::IMMUTABLE_MANIFEST_KEYS as $key ) {
			if ( array_key_exists( $key, $defaults ) ) {
				$manifest[ $key ] = $defaults[ $key ];
			}
		}

		return $manifest;
	}

	/**
	 * Build default WordPress app.json manifest data
	 * 
	 * @param \SmartLicenseServer\HostedApps\AbstractHostedApp $app The application instance.
	 * @param array $metadata The app metadata.
	 */
	protected function default_manifest( AbstractHostedApp $app, array $metadata ) {
		$min_ver     = $metadata['requires_at_least'] ?? '';
		$max_ver     = $metadata['tested_up_to'] ?? '';
		$type        = strtolower( $app->get_type() );
		$name_key    = sprintf( '%s_name', $type );
		$version_key = ( $app instanceof Plugin ) ? 'stable_tag' : 'version';
		return [
			'name'       => $metadata[$name_key] ?? $app->get_name(),
			'slug'       => $app->get_slug(),
			'version'    => $metadata[$version_key] ?? $app->get_meta( 'version' ),
			'type'       => \sprintf( 'wordpress-%s', $type ),
			'platforms'  => ['WordPress'],
			'tech_stack' => ['PHP', 'JavaScript'],
			'tested'     => $max_ver,
			'requires'   => [
				'Wordpress' => \sprintf( '%s +', $min_ver ),
				'PHP'       => \sprintf( ' %s +', $metadata['requires_php'] ?? '7.4' ),
			],
			
		];
	}

}