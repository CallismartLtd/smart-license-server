<?php
/**
 * Theme management action handler class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Admin\ActionHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Theming\ThemeException;
use SmartLicenseServer\Environments\Application\Theming\ThemeInstaller;
use SmartLicenseServer\Environments\Application\Theming\ThemeManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Admin actions on site themes (admin/json/themes/*), from the Modules >
 * Themes and Modules > Marketplace pages (ModulesPage, modules.js).
 *
 * System administrators only: a theme's templates run PHP on every public
 * page. See ThemeInstaller for how packages are verified and installed.
 *
 * Response: { success, data: { message, warnings?: string[], updates?: array } }
 */
class ThemeManagement {

	/**
	 * Constructor.
	 *
	 * @param ThemeManager   $themes    The installed themes and the active one.
	 * @param ThemeInstaller $installer Installs, updates and removes themes.
	 * @param Guard          $guard     The signed-in principal.
	 */
	public function __construct(
		protected ThemeManager $themes,
		protected ThemeInstaller $installer,
		protected Guard $guard
	) {}

	/**
	 * POST themes/activate
	 *
	 * Fields: slug ("default" for the built-in theme).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function activate( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$theme = $this->themes->activate( $this->slug( $request ) );

			return $this->ok( \sprintf( '%s now renders the public pages.', $theme->name ) );
		} );
	}

	/**
	 * POST themes/install
	 *
	 * Install a theme from the marketplace, or update it when it is installed.
	 *
	 * Fields: slug, activate ("1" activates it once installed).
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function install( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$slug   = $this->slug( $request );
			$result = $this->installer->install_from_marketplace( $slug );

			if ( null === $result ) {
				return $this->ok( \sprintf( 'The theme "%s" is already the latest version.', $slug ) );
			}

			return $this->installed( $result, '1' === (string) $request->get( 'activate', '' ) );
		} );
	}

	/**
	 * POST themes/upload (multipart)
	 *
	 * Files: package (.zip), checksums (.sha256, optional), signature
	 * (.sha256.sig, optional). Fields: trust_unsigned ("1" accepts a package
	 * without a valid signature), replace ("1" replaces an installed theme
	 * of the same slug), activate ("1").
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function upload( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$package = $request->get_file( 'package' );

			if ( null === $package ) {
				return $this->fail( 'Choose the theme package (.zip) to upload.', 400 );
			}

			$result = $this->installer->install_upload(
				$package,
				$request->get_file( 'checksums' ),
				$request->get_file( 'signature' ),
				'1' === (string) $request->get( 'trust_unsigned', '' ),
				'1' === (string) $request->get( 'replace', '' )
			);

			return $this->installed( $result, '1' === (string) $request->get( 'activate', '' ) );
		} );
	}

	/**
	 * POST themes/update
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
				? $this->ok( \sprintf( 'The theme "%s" is up to date.', $slug ) )
				: $this->installed( $result, false );
		} );
	}

	/**
	 * POST themes/check
	 *
	 * Ask the marketplace for the latest version of every theme installed from it.
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
				[] === $updates => 'No installed theme comes from the marketplace.',
				$failed > 0     => \sprintf( 'The marketplace could not be asked about %d theme(s); see the Version column.', $failed ),
				$available > 0  => \sprintf( '%d theme update(s) available.', $available ),
				default         => 'Every marketplace theme is up to date.',
			};

			return $this->ok( $message, [ 'updates' => $updates ] );
		} );
	}

	/**
	 * POST themes/marketplace
	 *
	 * The themes the marketplace publishes, with what is installed here. Read
	 * by modules.js to draw the Modules > Marketplace page.
	 *
	 * Fields: search (matched against the theme's slug, name and author; optional), paged (page number, from 1).
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
					'modules'    => $listing[ ThemeInstaller::LIST_KEY ],
					'pagination' => $listing['pagination'],
				]
			);
		} );
	}

	/**
	 * POST themes/remove
	 *
	 * Fields: slug.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function remove( Request $request ) : Response {
		return $this->run( function () use ( $request ) : Response {
			$slug     = $this->slug( $request );
			$warnings = $this->installer->remove_theme( $slug );

			return $this->ok( \sprintf( 'The theme "%s" was removed.', $slug ), [ 'warnings' => $warnings ] );
		} );
	}

	/**
	 * POST themes/publish
	 *
	 * Publish every installed theme's assets in public/ again.
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

			return $this->ok( [] === $lines ? 'No theme is installed.' : 'Theme assets published: ' . \implode( '; ', $lines ) . '.' );
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
			return $this->fail( 'Only system administrators can manage themes.', 403 );
		}

		try {
			return $action();
		} catch ( ThemeException $e ) {
			return $this->fail( $e->getMessage(), 400 );
		}
	}

	/**
	 * The answer to an install or update, activating the theme when asked.
	 *
	 * @param array{theme: \SmartLicenseServer\Environments\Application\Theming\Theme, previous: ?string, record: array<string, mixed>, warnings: string[]} $result
	 * @param bool $activate
	 * @return Response
	 * @throws ThemeException When activating fails.
	 */
	private function installed( array $result, bool $activate ) : Response {
		$theme   = $result['theme'];
		$message = null === $result['previous']
			? \sprintf( 'Installed %s %s.', $theme->name, $theme->version )
			: \sprintf( 'Updated %s from %s to %s.', $theme->name, $result['previous'], $theme->version );

		if ( $activate ) {
			$this->themes->activate( $theme->slug );
			$message .= ' It now renders the public pages.';
		}

		return $this->ok( $message, [ 'warnings' => $result['warnings'] ] );
	}

	/**
	 * The slug field.
	 *
	 * @param Request $request
	 * @return string
	 * @throws ThemeException When it is missing.
	 */
	private function slug( Request $request ) : string {
		$slug = \trim( (string) $request->get( 'slug', '' ) );

		if ( '' === $slug ) {
			throw new ThemeException( 'Name the theme (its slug).' );
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