<?php
/**
 * The site header: brand and navigation.
 *
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$principal = $this->principal();
$current   = rtrim( (string) $this->request()->path(), '/' );
$links     = [
	[ $this->urls()->repository_url(), 'Repository' ],
	[ $this->url( 'documentation' ), 'Documentation' ],
];

if ( null === $principal ) {
	$links[] = [ $this->urls()->login_url(), 'Sign in' ];
} else {
	$links[] = $principal->is( 'system_admin' )
		? [ $this->urls()->admin_url(), 'Admin' ]
		: [ $this->urls()->client_dashboard_url(), 'Dashboard' ];
	$links[] = [ $this->urls()->logout_url(), 'Sign out' ];
}
?>
<header class="site-header">
	<a class="site-brand" href="<?php echo escUrl( $this->url( '/' )->url() ); ?>"><?php echo escHtml( SMLISER_APP_NAME ); ?></a>

	<nav class="site-nav" aria-label="Site">
		<?php foreach ( $links as [ $url, $label ] ) : ?>
			<?php
			$path       = rtrim( (string) $url->get_path(), '/' );
			$is_current = $path === $current || ( '' !== $path && str_starts_with( $current, $path . '/' ) );
			?>
			<a href="<?php echo escUrl( $url->url() ); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>><?php echo escHtml( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
</header>