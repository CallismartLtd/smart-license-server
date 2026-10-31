<?php
/**
 * Extension management action handler class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Admin\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Extensions\Extension;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionException;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionInstaller;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Admin actions on extensions (admin/json/extensions/*), from the Modules >
 * Extensions and Modules > Marketplace pages (ModulesPage, modules.js).
 *
 * System administrators only: an enabled extension runs PHP on every
 * request. See ExtensionInstaller for how packages are verified and
 * installed, and ExtensionManager for what enabling means.
 *
 * Installing never enables an extension unless the request asks for it, and
 * an extension enabled or disabled here loads or stops loading from the next
 * request on.
 *
 * Response: { success, data: { message, warnings?: string[], updates?: array } }
 */
class ExtensionManagement {

	/**
	 * Constructor.
	 *
	 * @param ExtensionManager   $extensions The installed extensions and the enabled ones.
	 * @param ExtensionInstaller $installer  Installs, updates and removes extensions.
	 * @param Guard              $guard      The signed-in principal.
	 */
	public function __construct(
		protected ExtensionManager $extensions,
		protected ExtensionInstaller $installer,
		protected Guard $guard
	) {}

	/**
	 * POST extensions/enable
	 *
	 * Fields: slug.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function enable( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$extension = $this->extensions->enable( $this->slug( $request ) );

			return $this->ok( \sprintf( '%s is enabled and loads from the next request on.', $extension->name ) );
		} );
	}

	/**
	 * POST extensions/disable
	 *
	 * Fields: slug.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function disable( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$slug = $this->slug( $request );

			$this->extensions->disable( $slug );

			return $this->ok( \sprintf( 'The extension "%s" is disabled and stops loading from the next request on.', $slug ) );
		} );
	}

	/**
	 * POST extensions/install
	 *
	 * Install an extension from the marketplace, or update it when it is installed.
	 *
	 * Fields: slug, enable ("1" enables it once installed).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function install( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$slug   = $this->slug( $request );
			$result = $this->installer->install_from_marketplace( $slug );

			if ( null === $result ) {
				return $this->ok( \sprintf( 'The extension "%s" is already the latest version.', $slug ) );
			}

			return $this->installed( $result, '1' === (string) $request->get( 'enable', '' ) );
		} );
	}

	/**
	 * POST extensions/upload (multipart)
	 *
	 * Files: package (.zip), checksums (.sha256, optional), signature
	 * (.sha256.sig, optional). Fields: trust_unsigned ("1" accepts a package
	 * without a valid signature), replace ("1" replaces an installed extension
	 * of the same slug), enable ("1").
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function upload( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$package = $request->get_file( 'package' );

			if ( null === $package ) {
				return $this->fail( 'Choose the extension package (.zip) to upload.', 400 );
			}

			$result = $this->installer->install_upload(
				$package,
				$request->get_file( 'checksums' ),
				$request->get_file( 'signature' ),
				'1' === (string) $request->get( 'trust_unsigned', '' ),
				'1' === (string) $request->get( 'replace', '' )
			);

			return $this->installed( $result, '1' === (string) $request->get( 'enable', '' ) );
		} );
	}

	/**
	 * POST extensions/update
	 *
	 * Fields: slug.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function update( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$slug   = $this->slug( $request );
			$result = $this->installer->update( $slug );

			return null === $result
				? $this->ok( \sprintf( 'The extension "%s" is up to date.', $slug ) )
				: $this->installed( $result, false );
		} );
	}

	/**
	 * POST extensions/check
	 *
	 * Ask the marketplace for the latest version of every extension installed from it.
	 *
	 * @param Request $request
	 * @return Response data.updates: { slug: { installed, latest, update, error } }
	 */
	public function check( Request $request ) : Response {
		return $this->run( function () : Response {
			$updates   = $this->installer->check_updates();
			$available = \count( \array_filter( $updates, static fn ( array $u ) : bool => $u['update'] ) );
			$failed    = \count( \array_filter( $updates, static fn ( array $u ) : bool => null !== $u['error'] ) );

			$message = match ( true ) {
				[] === $updates => 'No installed extension comes from the marketplace.',
				$failed > 0     => \sprintf( 'The marketplace could not be asked about %d extension(s); see the status of each card.', $failed ),
				$available > 0  => \sprintf( '%d extension update(s) available.', $available ),
				default         => 'Every marketplace extension is up to date.',
			};

			return $this->ok( $message, [ 'updates' => $updates ] );
		} );
	}

	/**
	 * POST extensions/marketplace
	 *
	 * The extensions the marketplace publishes, with what is installed here. Read
	 * by modules.js to draw the Modules > Marketplace page.
	 *
	 * Fields: search (matched against the extension's slug, name and author; optional), paged (page number, from 1).
	 *
	 * @param Request $request
	 * @return Response data.modules: list of {slug, name, version, summary, author, icon, homepage, installed, update};
	 *                  data.pagination: {total, page, limit, total_pages}
	 */
	public function marketplace( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$listing = $this->installer->marketplace(
				\trim( (string) $request->get( 'search', '' ) ),
				\max( 1, (int) $request->get( 'paged', 1 ) )
			);

			return $this->ok(
				'The marketplace was read.',
				[
					'modules'    => $listing[ ExtensionInstaller::LIST_KEY ],
					'pagination' => $listing['pagination'],
				]
			);
		} );
	}

	/**
	 * POST extensions/remove
	 *
	 * Fields: slug.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function remove( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$slug     = $this->slug( $request );
			$warnings = $this->installer->remove_extension( $slug );

			return $this->ok( \sprintf( 'The extension "%s" was removed.', $slug ), [ 'warnings' => $warnings ] );
		} );
	}

	/**
	 * POST extensions/publish
	 *
	 * Publish every installed extension's assets in public/ again.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function publish( Request $request ) : Response {
		return $this->run( function () : Response {
			$results = $this->installer->publish_all( true );
			$lines   = [];

			foreach ( $results as $slug => $result ) {
				$lines[] = \sprintf( '%s: %s', $slug, $result );
			}

			return $this->ok( [] === $lines ? 'No extension is installed.' : 'Extension assets published: ' . \implode( '; ', $lines ) . '.' );
		} );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Run an action for a system administrator, answering failures as JSON.
	 *
	 * @param callable(): Response $action
	 * @return Response
	 */
	private function run( callable $action ) : Response {
		$principal = $this->guard->get_principal();

		if ( null === $principal || ! $principal->is( 'system_admin' ) ) {
			return $this->fail( 'Only system administrators can manage extensions.', 403 );
		}

		try {
			return $action();
		} catch ( ExtensionException $e ) {
			return $this->fail( $e->getMessage(), 400 );
		}
	}

	/**
	 * The answer to an install or update, enabling the extension when asked.
	 *
	 * An extension that cannot be enabled (a dependency is not enabled yet)
	 * stays installed; the answer says why it is not enabled.
	 *
	 * @param array{extension: Extension, previous: ?string, record: array<string, mixed>, warnings: string[]} $result
	 * @param bool $enable
	 * @return Response
	 */
	private function installed( array $result, bool $enable ) : Response {
		$extension = $result['extension'];
		$message   = null === $result['previous']
			? \sprintf( 'Installed %s %s.', $extension->name, $extension->version )
			: \sprintf( 'Updated %s from %s to %s.', $extension->name, $result['previous'], $extension->version );

		if ( $enable ) {
			try {
				$this->extensions->enable( $extension->slug );

				$message .= ' It is enabled and loads from the next request on.';
			} catch ( ExtensionException $e ) {
				$message .= ' It was not enabled: ' . $e->getMessage();
			}
		}

		return $this->ok( $message, [ 'warnings' => $result['warnings'] ] );
	}

	/**
	 * The slug field.
	 *
	 * @param Request $request
	 * @return string
	 * @throws ExtensionException When it is missing.
	 */
	private function slug( Request $request ) : string {
		$slug = \trim( (string) $request->get( 'slug', '' ) );

		if ( '' === $slug ) {
			throw new ExtensionException( 'Name the extension (its slug).' );
		}

		return $slug;
	}

	/**
	 * A success response.
	 *
	 * @param string               $message
	 * @param array<string, mixed> $data
	 * @return Response
	 */
	private function ok( string $message, array $data = [] ) : Response {
		return Response::json( data: [ 'success' => true, 'data' => [ 'message' => $message ] + $data ], status_code: 200 );
	}

	/**
	 * A failure response.
	 *
	 * @param string $message
	 * @param int    $status
	 * @return Response
	 */
	private function fail( string $message, int $status ) : Response {
		return Response::json( data: [ 'success' => false, 'data' => [ 'message' => $message ] ], status_code: $status );
	}
}