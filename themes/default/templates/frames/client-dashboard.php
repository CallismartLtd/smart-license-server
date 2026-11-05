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
 *         options: list<array{value: string, label: string, role: string, type: string, avatar: string, current: bool}>},
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

			<div class="dash-top-actions">
				<nav class="dash-top-menu" aria-label="Account">
					<?php if ( count( $dashboard['context']['options'] ) > 1 ) : ?>
						<?php
						$current_context = $dashboard['context']['options'][0];
						foreach ( $dashboard['context']['options'] as $option ) {
							if ( $option['current'] ) {
								$current_context = $option;
								break;
							}
						}
						?>
						<div class="dash-context">
							<details class="dash-context-dropdown">
								<summary class="dash-context-trigger" aria-label="Switch account">
									<img class="dash-context-avatar" src="<?php echo escUrl( $current_context['avatar'] ); ?>" alt="" width="38" height="38">
									<span class="dash-context-current">
										<span class="dash-context-label">Acting as</span>
										<strong><?php echo escHtml( $current_context['label'] ); ?></strong>
									</span>
									<svg class="dash-context-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
										<path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
								</summary>

								<div class="dash-context-panel">
									<p class="dash-context-heading">Acting as</p>
									<ul>
										<?php foreach ( $dashboard['context']['options'] as $option ) : ?>
											<li>
												<form class="dash-context-option" method="post" action="<?php echo escUrl( $dashboard['context']['action'] ); ?>" data-smliser-form>
													<input type="hidden" name="context" value="<?php echo escAttr( $option['value'] ); ?>">
													<button type="submit"<?php echo $option['current'] ? ' aria-current="true" disabled' : ''; ?>>
														<img class="dash-context-option-avatar" src="<?php echo escUrl( $option['avatar'] ); ?>" alt="" width="36" height="36">
														<span class="dash-context-option-details">
															<strong><?php echo escHtml( $option['label'] ); ?></strong>
															<span><?php echo escHtml( $option['role'] ); ?></span>
														</span>
														<?php if ( $option['current'] ) : ?>
															<svg class="dash-context-check" viewBox="0 0 20 20" fill="none" aria-hidden="true">
																<path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
															</svg>
														<?php endif; ?>
													</button>
													<div class="notice" data-smliser-form-message hidden></div>
												</form>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							</details>
						</div>
					<?php endif; ?>

					<div class="dash-top-menu-links">
						<?php foreach ( $dashboard['top_menu'] as $item ) : ?>
							<?php $this->partial( 'dashboard.top-menu-item', [ 'item' => $item ] ); ?>
						<?php endforeach; ?>
					</div>
				</nav>
			</div>
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