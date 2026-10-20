<?php
/**
 * A grid of app cards.
 *
 * @author Callistus Nwachukwu
 * @var list<array{type: string, slug: string, name: string, url: string, icon: string, summary: string, version: string, author: string, updated: DateTimeImmutable}> $apps
 * @var string $empty What to say when there are no apps; empty to say nothing.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<?php if ( [] === $apps ) : ?>
	<?php if ( '' !== $empty ) : ?>
		<p class="muted"><?php echo escHtml( $empty ); ?></p>
	<?php endif; ?>
<?php else : ?>
	<ul class="app-grid">
		<?php foreach ( $apps as $card ) : ?>
			<li class="app-card">
				<img class="app-icon" src="<?php echo escUrl( $card['icon'] ); ?>" alt="" width="56" height="56" loading="lazy">

				<div class="app-card-copy">
					<h3><a href="<?php echo escUrl( $card['url'] ); ?>"><?php echo escHtml( $card['name'] ); ?></a></h3>

					<?php if ( '' !== $card['summary'] ) : ?>
						<p><?php echo escHtml( $card['summary'] ); ?></p>
					<?php endif; ?>

					<p class="app-meta">
						<?php if ( '' !== $card['version'] ) : ?>
							<code><?php echo escHtml( $card['version'] ); ?></code>
						<?php endif; ?>
						<?php if ( '' !== $card['author'] ) : ?>
							<span><?php echo escHtml( $card['author'] ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>