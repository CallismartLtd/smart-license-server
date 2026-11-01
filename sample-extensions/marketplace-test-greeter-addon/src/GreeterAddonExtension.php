<?php

declare( strict_types = 1 );

namespace Callismart\MarketplaceTestGreeterAddon;

use Callismart\MarketplaceTestGreeter\Greeter;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionInterface;
use SmartLicenseServer\Routing\Router;

/** Adds a dependent route that calls the greeter extension. */
final class GreeterAddonExtension implements ExtensionInterface {

	public function register( Container $container ) : void {}

	public function boot( Container $container ) : void {
		$container->get( Router::class )->get(
			'/sample-extensions/companion',
			static fn( Request $request ) : Response => new Response(
				200,
				[ 'Content-Type' => 'text/plain; charset=utf-8' ],
				Greeter::greeting() . ' This route comes from the dependent add-on.'
			)
		);
	}
}
