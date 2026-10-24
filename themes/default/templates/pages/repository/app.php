<?php
/**
 * One app.
 *
 * What only the app's type has (e.g. a plugin's requirements) comes from the
 * optional partial 'repository.details-<type>', so a new app type renders
 * here as it is, and gets its own details by adding that partial.
 *
 * @author Callistus Nwachukwu
 * @var list<array{slug: string, label: string, plural: string, url: string, count: int}> $types
 * @var array{slug: string, label: string, plural: string, url: string, count: int} $type
 * @var array{type: string, slug: string, name: string, url: string, icon: string, summary: string, version: string, author: string, updated: DateTimeImmutable} $card
 * @var SmartLicenseServer\HostedApps\AbstractHostedApp $app
 * @var array<string, string> $sections Heading => readme HTML.
 * @var list<array{src: string, caption: string}> $screenshots
 * @var array{url: string, monetized: bool} $download
 * @var list<array{id: int, name: string, term_label: string, max_sites: int, features: list<string>, price: string, checkout_url: string}> $pricing
 *      Active pricing tiers; empty when the app is not for sale.
 * @var array{homepage: string, support: string, author: string} $links
 * @var list<string> $tags
 * @var array{license: string, license_uri: string} $license
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$details_partial = 'repository.details-' . $type['slug'];
?>
<nav class="crumbs" aria-label="Breadcrumb">
	<a href="<?php echo escUrl( $this->urls()->repository_url()->url() ); ?>">Repository</a>
	<span aria-hidden="true">/</span>
	<a href="<?php echo escUrl( $type['url'] ); ?>"><?php echo escHtml( $type['plural'] ); ?></a>
</nav>

<header class="app-hero">
	<img class="app-icon app-icon-large" src="<?php echo escUrl( $card['icon'] ); ?>" alt="" width="96" height="96">

	<div class="app-hero-copy">
		<h1><?php echo escHtml( $card['name'] ); ?></h1>

		<?php if ( '' !== $card['author'] ) : ?>
			<p class="app-author">
				By
				<?php if ( '' !== $links['author'] ) : ?>
					<a href="<?php echo escUrl( $links['author'] ); ?>" rel="nofollow noopener"><?php echo escHtml( $card['author'] ); ?></a>
				<?php else : ?>
					<?php echo escHtml( $card['author'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $card['summary'] ) : ?>
			<p class="app-summary"><?php echo escHtml( $card['summary'] ); ?></p>
		<?php endif; ?>

		<div class="actions">
			<?php if ( $download['monetized'] ) : ?>
				<span class="badge">Requires a license</span>
				<?php if ( [] !== $pricing ) : ?>
					<a class="btn btn-accent" href="#pricing">See pricing</a>
				<?php endif; ?>
			<?php else : ?>
				<a class="btn btn-accent" href="<?php echo escUrl( $download['url'] ); ?>"><?php echo escHtml( '' !== $card['version'] ? sprintf( 'Download %s', $card['version'] ) : 'Download' ); ?></a>
			<?php endif; ?>

			<?php if ( '' !== $links['homepage'] && $links['homepage'] !== $card['url'] ) : ?>
				<a class="btn" href="<?php echo escUrl( $links['homepage'] ); ?>" rel="noopener">Homepage</a>
			<?php endif; ?>

			<?php if ( '' !== $links['support'] ) : ?>
				<a class="btn" href="<?php echo escUrl( $links['support'] ); ?>" rel="noopener">Support</a>
			<?php endif; ?>
		</div>
	</div>
</header>

<div class="app-layout">
	<div class="app-main">
		<?php if ( [] !== $pricing ) : ?>
			<?php $this->partial( 'repository.pricing', [ 'tiers' => $pricing ] ); ?>
		<?php endif; ?>

		<?php if ( [] !== $screenshots ) : ?>
			<section class="app-section">
				<h2>Screenshots</h2>
				<ul class="shots">
					<?php foreach ( $screenshots as $shot ) : ?>
						<li>
							<a href="<?php echo escUrl( $shot['src'] ); ?>"><img src="<?php echo escUrl( $shot['src'] ); ?>" alt="<?php echo escAttr( $shot['caption'] ); ?>" loading="lazy"></a>
							<?php if ( '' !== $shot['caption'] ) : ?>
								<span><?php echo escHtml( $shot['caption'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php foreach ( $sections as $heading => $html ) : ?>
			<section class="app-section">
				<h2><?php echo escHtml( $heading ); ?></h2>
				<div class="prose"><?php echo sanitize_html( $html ); // phpcs:ignore -- sanitized readme HTML. ?></div>
			</section>
		<?php endforeach; ?>

		<?php if ( [] === $sections && [] === $screenshots ) : ?>
			<p class="muted">This app has no description yet.</p>
		<?php endif; ?>
	</div>

	<aside class="app-aside">
		<dl class="facts">
			<div><dt>Type</dt><dd><a href="<?php echo escUrl( $type['url'] ); ?>"><?php echo escHtml( $type['label'] ); ?></a></dd></div>

			<?php if ( '' !== $card['version'] ) : ?>
				<div><dt>Version</dt><dd><code><?php echo escHtml( $card['version'] ); ?></code></dd></div>
			<?php endif; ?>

			<div><dt>Updated</dt><dd><time datetime="<?php echo escAttr( $card['updated']->format( DATE_ATOM ) ); ?>"><?php echo escHtml( $card['updated']->format( 'M j, Y' ) ); ?></time></dd></div>

			<?php if ( $this->has_partial( $details_partial ) ) : ?>
				<?php $this->partial( $details_partial, [ 'app' => $app ] ); ?>
			<?php endif; ?>

			<?php if ( '' !== $license['license'] ) : ?>
				<div>
					<dt>License</dt>
					<dd>
						<?php if ( '' !== $license['license_uri'] ) : ?>
							<a href="<?php echo escUrl( $license['license_uri'] ); ?>" rel="noopener"><?php echo escHtml( $license['license'] ); ?></a>
						<?php else : ?>
							<?php echo escHtml( $license['license'] ); ?>
						<?php endif; ?>
					</dd>
				</div>
			<?php endif; ?>
		</dl>

		<?php if ( [] !== $tags ) : ?>
			<ul class="tags" aria-label="Tags">
				<?php foreach ( $tags as $tag ) : ?>
					<li><?php echo escHtml( $tag ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</aside>
</div>