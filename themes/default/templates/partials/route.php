<?php
/**
 * One REST route on the documentation page.
 *
 * @author Callistus Nwachukwu
 * @var array{humanized_route: string, methods: array<string, array{name: string, args: array<int, array{name: string, type: string, required: bool, description: string, default: mixed}>}>} $route
 */

?>
<article class="route">
	<div class="route-path">
		<code><?php echo escHtml( $route['humanized_route'] ); ?></code>
		<button type="button" class="btn btn-small" data-copy="<?php echo escAttr( $route['humanized_route'] ); ?>">Copy</button>
	</div>

	<?php foreach ( $route['methods'] as $method => $details ) : ?>
		<div class="route-method">
			<h4>
				<span class="method method-<?php echo escAttr( strtolower( $method ) ); ?>"><?php echo escHtml( $method ); ?></span>
				<?php echo escHtml( $details['name'] ); ?>
			</h4>

			<?php if ( [] === $details['args'] ) : ?>
				<p class="muted">No parameters.</p>
			<?php else : ?>
				<ul class="args">
					<?php foreach ( $details['args'] as $arg ) : ?>
						<li>
							<code><?php echo escHtml( $arg['name'] ); ?></code>
							<span class="arg-meta"><?php echo escHtml( $arg['type'] ); ?></span>
							<?php if ( $arg['required'] ) : ?>
								<span class="arg-meta arg-required">required</span>
							<?php elseif ( is_scalar( $arg['default'] ) ) : ?>
								<span class="arg-meta">default: <?php echo escHtml( is_bool( $arg['default'] ) ? ( $arg['default'] ? 'true' : 'false' ) : (string) $arg['default'] ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $arg['description'] ) : ?>
								<p><?php echo escHtml( $arg['description'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</article>