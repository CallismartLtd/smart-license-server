<?php
/**
 * An app's pricing: its active tiers, each with a way to buy it.
 *
 * The Buy button opens the tier's checkout URL, whichever provider sells
 * it: the purchase page for manual payment, the store for a remote store.
 * A tier without a checkout URL is shown without one.
 *
 * @author Callistus Nwachukwu
 * @var list<array{id: int, name: string, term_label: string, max_sites: int, features: list<string>, price: string, checkout_url: string}> $tiers
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<section class="app-section" id="pricing">
	<h2>Pricing</h2>
	<ul class="pricing">
		<?php foreach ( $tiers as $tier ) : ?>
			<li class="pricing-tier">
				<h3 class="pricing-name"><?php echo escHtml( $tier['name'] ); ?></h3>
				<?php if ( '' !== $tier['price'] ) : ?>
					<p class="pricing-price"><?php echo escHtml( $tier['price'] ); ?></p>
				<?php endif; ?>
				<ul class="pricing-terms">
					<li><?php echo escHtml( sprintf( 'License term: %s', $tier['term_label'] ) ); ?></li>
					<li><?php echo escHtml( -1 === $tier['max_sites'] ? 'Unlimited sites' : sprintf( '%d %s', $tier['max_sites'], 1 === $tier['max_sites'] ? 'site' : 'sites' ) ); ?></li>
					<?php foreach ( $tier['features'] as $feature ) : ?>
						<li><?php echo escHtml( $feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( '' !== $tier['checkout_url'] ) : ?>
					<a class="btn btn-accent btn-block" href="<?php echo escUrl( $tier['checkout_url'] ); ?>" rel="noopener">Buy</a>
				<?php else : ?>
					<p class="muted pricing-note">Contact the seller to buy this option.</p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>