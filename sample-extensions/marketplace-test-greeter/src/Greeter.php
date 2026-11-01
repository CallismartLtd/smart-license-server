<?php

declare( strict_types = 1 );

namespace Callismart\MarketplaceTestGreeter;

/** Text shared with extensions that depend on the sample greeter. */
final class Greeter {

	public static function greeting() : string {
		return 'Hello from the Marketplace Test Greeter extension.';
	}
}
