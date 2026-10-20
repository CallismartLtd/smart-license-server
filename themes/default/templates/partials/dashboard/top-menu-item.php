<?php
/**
 * One top menu item of a dashboard: a link or a button.
 *
 * @author Callistus Nwachukwu
 * @var array{key: string, title: string, icon: string,
 *     icons: list<array{class: string, attributes: array<string, string>}>,
 *     type: string, href: string, attributes: array<string, string>} $item
 */

$attributes = $item['attributes'];
$classes    = trim( 'btn btn-small ' . ( $attributes['class'] ?? '' ) );

unset( $attributes['class'] );

$attribute_html = '';

foreach ( $attributes as $name => $value ) {
	$attribute_html .= sprintf( ' %s="%s"', escAttr( $name ), escAttr( $value ) );
}

$tag   = 'link' === $item['type'] ? 'a' : 'button';
$extra = 'a' === $tag ? sprintf( ' href="%s"', escUrl( $item['href'] ) ) : ' type="button"';
?>
<<?php echo $tag; // phpcs:ignore -- "a" or "button". ?> class="<?php echo escAttr( $classes ); ?>"<?php echo $extra . $attribute_html; // phpcs:ignore -- escaped above. ?>>
	<?php if ( '' !== $item['icon'] ) : ?>
		<i class="<?php echo escAttr( $item['icon'] ); ?>" aria-hidden="true"></i>
	<?php endif; ?>
	<?php foreach ( $item['icons'] as $icon ) : ?>
		<i class="<?php echo escAttr( $icon['class'] ); ?>"<?php foreach ( $icon['attributes'] ?? [] as $name => $value ) { printf( ' %s="%s"', escAttr( $name ), escAttr( $value ) ); } ?>></i>
	<?php endforeach; ?>
	<?php if ( '' !== $item['title'] ) : ?>
		<span><?php echo escHtml( $item['title'] ); ?></span>
	<?php endif; ?>
</<?php echo $tag; // phpcs:ignore -- "a" or "button". ?>>