<?php
/**
 * One REST route on the documentation page.
 *
 * @author Callistus Nwachukwu
 * @var array{humanized_route: string, methods: array<string, array{name: string, args: array<int, array{name: string, type: string, required: bool, description: string, default: mixed}>}>} $route
 */

?>
<div class="card api-route-card">
	<div class="api-route-path-container">
		<div class="api-route-path"><?php echo escHtml( $route['humanized_route'] ); ?></div>
		<button type="button" class="api-copy-btn" data-copy="<?php echo escAttr( $route['humanized_route'] ); ?>">Copy</button>
	</div>

	<?php foreach ( $route['methods'] as $method => $details ) : ?>
		<div class="api-method-block">
			<h4>
				<span class="api-method-badge api-method-<?php echo escAttr( strtolower( $method ) ); ?>"><?php echo escHtml( $method ); ?></span>
				<?php echo escHtml( $details['name'] ); ?>
			</h4>

			<?php if ( [] === $details['args'] ) : ?>
				<p class="api-no-args">No parameters required.</p>
			<?php endif; ?>

			<?php foreach ( $details['args'] as $arg ) : ?>
				<div class="api-argument">
					<div class="api-argument-header">
						<span class="api-argument-name"><?php echo escHtml( $arg['name'] ); ?></span>
						<span class="api-argument-type"><?php echo escHtml( $arg['type'] ); ?></span>
						<?php if ( $arg['required'] ) : ?>
							<span class="api-argument-required">Required</span>
						<?php else : ?>
							<span class="api-argument-optional">Optional</span>
						<?php endif; ?>
						<?php if ( null !== $arg['default'] && is_scalar( $arg['default'] ) ) : ?>
							<span class="api-argument-default">Default: <?php echo escHtml( is_bool( $arg['default'] ) ? ( $arg['default'] ? 'true' : 'false' ) : (string) $arg['default'] ); ?></span>
						<?php endif; ?>
					</div>
					<p class="api-argument-description"><?php echo escHtml( $arg['description'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>