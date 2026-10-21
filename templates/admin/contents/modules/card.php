<?php
/**
 * A module card: one installed or published module (a theme; extensions to come).
 *
 * The same markup for every kind of module and every Modules page, so they
 * look and behave alike. Included by the Modules templates for each card.
 * modules.js posts an action button to admin/json/<kind>s/<action> with the
 * card's slug.
 *
 * @author Callistus Nwachukwu
 * @var array{
 *     slug: string, name: string, kind: string, version: string, description: string, image: string,
 *     badges: list<array{0: string, 1: string}>, meta: list<array{0: string, 1: string}>,
 *     actions: list<array{action: string, label: string, icon: string, confirm: string, danger: bool}>,
 *     links: list<array{0: string, 1: string}>, highlight: bool
 * } $card See ModulesPage::card().
 */

?>
<article class="smliser-module-card<?php echo $card['highlight'] ? ' is-highlighted' : ''; ?>" data-module-card data-module-kind="<?php echo escAttr( $card['kind'] ); ?>" data-slug="<?php echo escAttr( $card['slug'] ); ?>">
	<div class="smliser-module-card-media">
		<?php if ( '' !== $card['image'] ) : ?>
			<img src="<?php echo escUrl( $card['image'] ); ?>" alt="" loading="lazy">
		<?php else : ?>
			<span class="smliser-module-card-initial" aria-hidden="true"><?php echo escHtml( strtoupper( mb_substr( $card['name'], 0, 1 ) ) ); ?></span>
		<?php endif; ?>
	</div>

	<div class="smliser-module-card-body">
		<header class="smliser-module-card-header">
			<h3 class="smliser-module-card-title"><?php echo escHtml( $card['name'] ); ?></h3>
			<?php if ( '' !== $card['version'] ) : ?>
				<code class="smliser-module-card-version"><?php echo escHtml( $card['version'] ); ?></code>
			<?php endif; ?>
		</header>

		<code class="smliser-module-card-slug"><?php echo escHtml( $card['slug'] ); ?></code>

		<?php if ( [] !== $card['badges'] ) : ?>
			<ul class="smliser-module-card-badges">
				<?php foreach ( $card['badges'] as [ $label, $tone ] ) : ?>
					<li class="smliser-module-badge smliser-module-badge--<?php echo escAttr( $tone ); ?>"><?php echo escHtml( $label ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( '' !== $card['description'] ) : ?>
			<p class="smliser-module-card-description"><?php echo escHtml( $card['description'] ); ?></p>
		<?php endif; ?>

		<?php if ( [] !== $card['meta'] ) : ?>
			<ul class="smliser-module-card-meta">
				<?php foreach ( $card['meta'] as [ $icon, $text ] ) : ?>
					<li><i class="<?php echo escAttr( $icon ); ?>"></i> <?php echo escHtml( $text ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p class="smliser-module-card-status" data-module-status hidden></p>
	</div>

	<?php if ( [] !== $card['actions'] || [] !== $card['links'] ) : ?>
		<footer class="smliser-module-card-actions">
			<?php foreach ( $card['actions'] as $action ) : ?>
				<button
					type="button"
					class="smliser-btn smliser-btn-glass<?php echo $action['danger'] ? ' smliser-module-action--danger' : ''; ?>"
					data-module-action="<?php echo escAttr( $action['action'] ); ?>"
					<?php if ( '' !== $action['confirm'] ) : ?>data-confirm="<?php echo escAttr( $action['confirm'] ); ?>"<?php endif; ?>
					<?php if ( $action['danger'] ) : ?>data-danger="1"<?php endif; ?>
				><i class="<?php echo escAttr( $action['icon'] ); ?>"></i> <?php echo escHtml( $action['label'] ); ?></button>
			<?php endforeach; ?>

			<?php foreach ( $card['links'] as [ $label, $url ] ) : ?>
				<a class="smliser-module-card-link" href="<?php echo escUrl( $url ); ?>" target="_blank" rel="noopener"><?php echo escHtml( $label ); ?> <i class="ti ti-external-link"></i></a>
			<?php endforeach; ?>
		</footer>
	<?php endif; ?>
</article>