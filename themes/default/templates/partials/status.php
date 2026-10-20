<?php
/**
 * A state, told by a line diagram next to its message.
 *
 * The layout of the built-in error document, for themed pages.
 *
 * @author Callistus Nwachukwu
 * @var string $diagram  Diagram partial name (partials/diagrams/<name>.php).
 * @var string $heading  The heading.
 * @var string $message  The message.
 * @var string $tone     Optional: '', 'success', 'failure' or 'neutral'.
 * @var array<int, array{label: string, url?: string, action?: string, accent?: bool}> $actions
 *                       Buttons: a link (url) or a behaviour (action, e.g. "back").
 * @var string $detail   Optional monospace line under the actions.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$tone    ??= '';
$actions ??= [];
$detail  ??= '';
?>
<section class="status<?php echo '' !== $tone ? ' tone-' . escAttr( $tone ) : ''; ?>">
	<div class="status-visual">
		<?php $this->partial( 'diagrams.' . $diagram ); ?>
	</div>

	<div class="status-copy">
		<h1 class="status-heading"><?php echo escHtml( $heading ); ?></h1>
		<p class="status-message"><?php echo escHtml( $message ); ?></p>

		<?php if ( [] !== $actions ) : ?>
			<div class="actions">
				<?php foreach ( $actions as $action ) : ?>
					<?php $class = 'btn' . ( ! empty( $action['accent'] ) ? ' btn-accent' : '' ); ?>
					<?php if ( isset( $action['url'] ) ) : ?>
						<a class="<?php echo escAttr( $class ); ?>" href="<?php echo escUrl( $action['url'] ); ?>"><?php echo escHtml( $action['label'] ); ?></a>
					<?php else : ?>
						<button type="button" class="<?php echo escAttr( $class ); ?>" data-action="<?php echo escAttr( $action['action'] ?? '' ); ?>"><?php echo escHtml( $action['label'] ); ?></button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $detail ) : ?>
			<p class="status-detail"><?php echo escHtml( $detail ); ?></p>
		<?php endif; ?>
	</div>
</section>