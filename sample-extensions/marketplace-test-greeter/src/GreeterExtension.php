<?php

declare( strict_types = 1 );

namespace Callismart\MarketplaceTestGreeter;

use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionInterface;
use SmartLicenseServer\Routing\Router;

/** Adds a small endpoint for testing install and enable behavior. */
final class GreeterExtension implements ExtensionInterface {

	public function register( Container $container ) : void {}

	public function boot( Container $container ) : void {
		$container->get( Router::class )->get(
			'/sample-extensions/hello',
			static fn( Request $request ) : Response => new Response(
				200,
				[ 'Content-Type' => 'text/plain; charset=utf-8' ],
				Greeter::greeting()
			)
		);
	}
}
