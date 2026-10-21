<?php
/**
 * The 405 page: the path exists, but not for this method.
 *
 * @author Callistus Nwachukwu
 * @var string   $method  The request method.
 * @var string[] $allowed The methods the path accepts.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$this->partial(
	'status',
	[
		'diagram' => 'missing',
		'heading' => 'Method Not Allowed',
		'message' => sprintf( '%s is not supported here. This address accepts %s.', $method, implode( ', ', $allowed ) ),
		'actions' => [
			[ 'label' => 'Go back', 'action' => 'back' ],
			[ 'label' => 'Home', 'url' => $this->url( '/' )->url() ],
		],
		'detail'  => 'HTTP 405',
	]
);
