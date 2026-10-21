<?php
/**
 * A WordPress plugin's own facts, as rows of the app page's facts list.
 *
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\HostedApps\WPPlugin $app
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$facts = [
	'Requires WordPress' => $app->get_requires_at_least(),
	'Tested up to'       => $app->get_tested_up_to(),
	'Requires PHP'       => $app->get_required_php(),
];
?>
<?php foreach ( array_filter( $facts, fn ( $value ) : bool => '' !== (string) $value ) as $label => $value ) : ?>
	<div><dt><?php echo escHtml( $label ); ?></dt><dd><code><?php echo escHtml( (string) $value ); ?></code></dd></div>
<?php endforeach; ?>