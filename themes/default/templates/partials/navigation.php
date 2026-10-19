<?php
/**
 * The site navigation.
 *
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$principal = $this->principal();
$links     = [
	[ $this->url( '/' ), 'Home' ],
];

if ( null !== $principal ) {
	$links[] = [ $this->urls()->client_dashboard_url(), 'Client Area' ];

	if ( $principal->is( 'system_admin' ) ) {
		$links[] = [ $this->urls()->admin_url(), 'Admin Area' ];
	}

	$links[] = [ $this->urls()->logout_url(), 'Logout' ];
} else {
	$links[] = [ $this->urls()->login_url(), 'Login' ];
}

$links[] = [ $this->url( 'documentation' ), 'Documentation' ];
?>
<nav class="site-nav">
	<a class="brand" href="<?php echo escUrl( $this->url( '/' )->url() ); ?>"><?php echo escHtml( SMLISER_APP_NAME ); ?></a>

	<div class="nav-links">
		<?php foreach ( $links as [ $url, $label ] ) : ?>
			<a href="<?php echo escUrl( $url->url() ); ?>"><?php echo escHtml( $label ); ?></a>
		<?php endforeach; ?>
	</div>
</nav>