<?php
/**
 * The client dashboard frame: menus around the page, built from the registry.
 *
 * @author Callistus Nwachukwu
 * @var string $content The page's HTML.
 * @var array{
 *     menu: list<array{key: string, title: string, icon: string, url: string, current: bool,
 *         submenu: list<array{slug: string, title: string, url: string, current: bool}>}>,
 *     top_menu: list<array{key: string, title: string, icon: string,
 *         icons: list<array{class: string, attributes: array<string, string>}>,
 *         type: string, href: string, attributes: array<string, string>}>,
 *     current: array{menu: string, submenu: ?string},
 *     context: array{action: string, current: string,
 *         options: list<array{value: string, label: string, role: string, type: string, current: bool}>},
 *     account: array{name: string, email: string, verified: bool, resend_url: string}
 * } $dashboard See ClientDashboard\Page\Dispatcher.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<div class="dash">
	<aside class="dash-side">
		<a class="site-brand" href="<?php echo escUrl( $this->url( '/' )->url() ); ?>"><?php echo escHtml( SMLISER_APP_NAME ); ?></a>

		<nav class="dash-menu" aria-label="Dashboard">
			<ul>
				<?php foreach ( $dashboard['menu'] as $item ) : ?>
					<li>
						<a href="<?php echo escUrl( $item['url'] ); ?>"<?php echo $item['current'] && null === $dashboard['current']['submenu'] ? ' aria-current="page"' : ''; ?>>
							<?php if ( '' !== $item['icon'] ) : ?>
								<i class="<?php echo escAttr( $item['icon'] ); ?>" aria-hidden="true"></i>
							<?php endif; ?>
							<span><?php echo escHtml( $item['title'] ); ?></span>
						</a>

						<?php if ( [] !== $item['submenu'] && $item['current'] ) : ?>
							<ul class="dash-submenu">
								<?php foreach ( $item['submenu'] as $sub ) : ?>
									<li>
										<a href="<?php echo escUrl( $sub['url'] ); ?>"<?php echo $sub['current'] ? ' aria-current="page"' : ''; ?>><?php echo escHtml( $sub['title'] ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</aside>

	<div class="dash-main">
		<header class="dash-top">
			<h1 class="dash-title"><?php echo escHtml( $view->get_title() ); ?></h1>

			<?php if ( count( $dashboard['context']['options'] ) > 1 ) : ?>
				<form class="dash-context" method="post" action="<?php echo escUrl( $dashboard['context']['action'] ); ?>" data-smliser-form data-auto-submit>
					<label class="dash-context-field">
						<span class="dash-context-label">Acting as</span>
						<select name="context">
							<?php foreach ( $dashboard['context']['options'] as $option ) : ?>
								<option value="<?php echo escAttr( $option['value'] ); ?>"<?php echo $option['current'] ? ' selected' : ''; ?>>
									<?php echo escHtml( sprintf( '%s — %s', $option['label'], $option['role'] ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<div class="notice" data-smliser-form-message hidden></div>
				</form>
			<?php endif; ?>

			<nav class="dash-top-menu" aria-label="Account">
				<?php foreach ( $dashboard['top_menu'] as $item ) : ?>
					<?php $this->partial( 'dashboard.top-menu-item', [ 'item' => $item ] ); ?>
				<?php endforeach; ?>
			</nav>
		</header>

		<?php if ( ! $dashboard['account']['verified'] ) : ?>
			<div class="notice dash-notice" role="status">
				<p>
					<?php echo escHtml( sprintf( 'Verify your email address, %s. Licenses bought with it are added to your account once it is verified.', $dashboard['account']['email'] ) ); ?>
				</p>
				<form method="post" action="<?php echo escUrl( $dashboard['account']['resend_url'] ); ?>" data-smliser-form>
					<div class="notice" data-smliser-form-message hidden></div>
					<button type="submit" class="btn btn-small">Send me a new link</button>
				</form>
			</div>
		<?php endif; ?>

		<div class="dash-content">
			<?php echo $content; // phpcs:ignore -- rendered page. ?>
		</div>
	</div>
</div>