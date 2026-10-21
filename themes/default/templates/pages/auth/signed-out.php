<?php
/**
 * The signed-out page.
 *
 * @author Callistus Nwachukwu
 * @var bool   $ended      Whether a session was ended by this request.
 * @var string $name       Who signed out; empty when $ended is false.
 * @var bool   $everywhere Arrived from "sign out everywhere".
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

[ $tone, $diagram, $heading, $message ] = match ( true ) {
	$ended      => [ 'success', 'session-closed', sprintf( 'Good bye, %s', $name ), 'Your session has been closed. You will need to sign in again to continue.' ],
	$everywhere => [ 'success', 'session-closed', 'Signed out everywhere', 'Every session of your account has been closed, on every device.' ],
	default     => [ 'neutral', 'no-session', 'Already signed out', 'There was no active session on this device.' ],
};

$this->partial(
	'status',
	[
		'tone'    => $tone,
		'diagram' => $diagram,
		'heading' => $heading,
		'message' => $message,
		'actions' => [
			[ 'label' => 'Sign in', 'url' => $this->urls()->login_url()->url(), 'accent' => true ],
			[ 'label' => 'Return to home', 'url' => $this->url( '/' )->url() ],
		],
	]
);
