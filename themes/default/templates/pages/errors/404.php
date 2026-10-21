<?php
/**
 * The 404 page: no route matches the path.
 *
 * @author Callistus Nwachukwu
 * @var string $path The requested path.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'status',
	[
		'diagram' => 'missing',
		'heading' => 'Not Found',
		'message' => 'The requested resource could not be found.',
		'actions' => [
			[ 'label' => 'Go back', 'action' => 'back' ],
			[ 'label' => 'Home', 'url' => $this->url( '/' )->url() ],
		],
		'detail'  => sprintf( 'HTTP 404 · %s', $path ),
	]
);
