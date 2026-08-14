<?php
/**
 * Dashboard Content.
 *
 * Renders the left menu, top menu, and opens the main content area.
 * Include this after header.php.
 *
 * @package Dashboard
 */

/**
 * Convert a string into a URL/ID-safe slug.
 *
 * Used to derive a menu item's id from its text when no id is supplied.
 *
 * @param string $string String to slugify.
 * @return string
 */
function dashboard_slugify( $string ) {
	$slug = strtolower( trim( (string) $string ) );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
	return trim( $slug, '-' );
}

/**
 * Render the left (side) menu from an array of menu item definitions.
 *
 * Each top-level item accepts:
 *   - icon    string  Optional. CSS class name for the icon element.
 *   - text    string  Optional. Visible label.
 *   - link    string  Optional. href. Defaults to '#'.
 *   - id      string  Optional. Unique id. Derived from text (slugified)
 *                      when omitted, falling back to the item's index.
 *   - submenu array   Optional. List of child items, each accepting
 *                      text, link, and id (same derivation rules).
 *
 * @param array $menu_items List of menu item definitions.
 */
function dashboard_render_left_menu( array $menu_items ) {
	?>
	<nav class="dashboard-left-menu" id="dashboard-left-menu" aria-label="Primary">
		<ul class="dashboard-menu-list">
			<?php foreach ( $menu_items as $index => $menu_item ) : ?>
				<?php
				$text        = isset( $menu_item['text'] ) ? $menu_item['text'] : '';
				$icon        = isset( $menu_item['icon'] ) ? $menu_item['icon'] : '';
				$link        = isset( $menu_item['link'] ) ? $menu_item['link'] : '#';
				$id          = isset( $menu_item['id'] ) ? $menu_item['id'] : dashboard_slugify( $text !== '' ? $text : 'menu-item-' . $index );
				$submenu     = isset( $menu_item['submenu'] ) && is_array( $menu_item['submenu'] ) ? $menu_item['submenu'] : array();
				$has_submenu = ! empty( $submenu );
				?>
				<li class="dashboard-menu-item<?php echo $has_submenu ? ' has-submenu' : ''; ?>" id="menu-<?php echo htmlspecialchars( $id, ENT_QUOTES, 'UTF-8' ); ?>">
					<a
						href="<?php echo htmlspecialchars( $link, ENT_QUOTES, 'UTF-8' ); ?>"
						class="dashboard-menu-link"
						<?php echo $has_submenu ? ' data-toggle="submenu"' : ''; ?>
					>
						<?php if ( $icon ) : ?>
							<span class="dashboard-menu-icon <?php echo htmlspecialchars( $icon, ENT_QUOTES, 'UTF-8' ); ?>" aria-hidden="true"></span>
						<?php endif; ?>

						<?php if ( $text ) : ?>
							<span class="dashboard-menu-text"><?php echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); ?></span>
						<?php endif; ?>

						<?php if ( $has_submenu ) : ?>
							<span class="dashboard-menu-arrow" aria-hidden="true"></span>
						<?php endif; ?>
					</a>

					<?php if ( $has_submenu ) : ?>
						<ul class="dashboard-submenu">
							<?php foreach ( $submenu as $sub_index => $sub_item ) : ?>
								<?php
								$sub_text = isset( $sub_item['text'] ) ? $sub_item['text'] : '';
								$sub_link = isset( $sub_item['link'] ) ? $sub_item['link'] : '#';
								$sub_id   = isset( $sub_item['id'] ) ? $sub_item['id'] : dashboard_slugify( $sub_text !== '' ? $sub_text : $id . '-sub-' . $sub_index );
								?>
								<li class="dashboard-submenu-item" id="submenu-<?php echo htmlspecialchars( $sub_id, ENT_QUOTES, 'UTF-8' ); ?>">
									<a href="<?php echo htmlspecialchars( $sub_link, ENT_QUOTES, 'UTF-8' ); ?>" class="dashboard-submenu-link">
										<?php echo htmlspecialchars( $sub_text, ENT_QUOTES, 'UTF-8' ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}

/**
 * Sample top-level menu array — one item with one submenu, as a shape
 * reference. Build the real menu (and wire it into your extension system)
 * from this array shape.
 */
$dashboard_menu_items = array(
	array(
		'icon' => 'icon-dashboard',
		'text' => 'Dashboard',
		'link' => '#',
		'id'   => 'dashboard',
		'submenu' => array(
			array(
				'text' => 'Overview',
				'link' => '#',
				'id'   => 'dashboard-overview',
			),
		),
	),
	// Add more top-level items here, or build $dashboard_menu_items
	// dynamically from your app's registered extensions.
);
?>

<div class="dashboard-wrapper" id="dashboard-wrapper">

	<?php dashboard_render_left_menu( $dashboard_menu_items ); ?>

	<div class="dashboard-overlay" id="dashboard-overlay"></div>

	<div class="dashboard-main">

		<header class="dashboard-top-menu">
			<div class="dashboard-top-menu-left">
				<button type="button" class="dashboard-menu-toggle" id="dashboard-menu-toggle" aria-label="Collapse menu" aria-controls="dashboard-wrapper">
					<span></span><span></span><span></span>
				</button>
				<button type="button" class="dashboard-mobile-menu-toggle" id="dashboard-mobile-menu-toggle" aria-label="Open menu" aria-controls="dashboard-wrapper">
					<span></span><span></span><span></span>
				</button>
			</div>

			<div class="dashboard-top-menu-right">
				<button type="button" class="dashboard-theme-toggle" id="dashboard-theme-toggle" aria-label="Toggle theme">
					<span class="dashboard-theme-icon dashboard-theme-icon-light" aria-hidden="true">&#9728;&#65039;</span>
					<span class="dashboard-theme-icon dashboard-theme-icon-dark" aria-hidden="true">&#127769;</span>
				</button>

				<!-- Add notifications, user menu, or other app-specific top menu items here. -->
			</div>
		</header>

		<main class="dashboard-content" id="dashboard-content">
			<!-- Main content: generated by app APIs. -->
		</main>

	</div>

</div>