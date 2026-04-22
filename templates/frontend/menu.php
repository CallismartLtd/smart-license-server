<?php
/**
 * Client Dashboard Menu Partial
 *
 * Renders the left sidebar navigation for the client dashboard.
 * Included by the shell template — not intended to be rendered standalone.
 *
 * Expected variables (extracted by TemplateLocator):
 *
 * @var array<string, array{title: string, slug: string, handler: callable, icon: string}> $menu
 *     Ordered menu items from ClientDashboardRegistry.
 *
 * @var string $active_slug
 *     The slug of the currently active menu section.
 *
 * @var \SmartLicenseServer\Security\Context\Principal $principal
 *     The authenticated principal for this request.
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$menu        = $menu        ?? [];
$active_slug = $active_slug ?? '';
?>
<aside class="smlcd-sidebar" id="smlcd-sidebar" role="navigation" aria-label="Dashboard navigation">

    <div class="smlcd-sidebar-header">
        <span class="smlcd-sidebar-brand" title="<?php echo esc_attr( $repo_name ); ?>"><?php echo escHtml( $repo_name ); ?></span>
    </div>

    <?php if ( ! empty( $menu ) ) : ?>
        <nav class="smlcd-nav" role="list">
            <?php foreach ( $menu as $key => $item ) :
                $slug      = $item['slug'] ?? $key;
                $title     = $item['title'] ?? $key;
                $icon      = $item['icon'] ?? '';
                $is_active = ( $slug === $active_slug ) || ( '' === $slug && '' === $active_slug );
            ?>
            <button
                type="button"
                class="smlcd-nav-item<?php echo $is_active ? ' smlcd-nav-item--active' : ''; ?>"
                data-slug="<?php echo esc_attr( $slug ); ?>"
                aria-current="<?php echo $is_active ? 'page' : 'false'; ?>"
                role="listitem"
            >
                <?php if ( $icon ) : ?>
                    <span class="smlcd-nav-icon <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="smlcd-nav-label"><?php echo escHtml( $title ); ?></span>
            </button>
            <?php endforeach; ?>
        </nav>
    <?php else : ?>
        <p class="smlcd-nav-empty">No sections available.</p>
    <?php endif; ?>

    <div class="smlcd-sidebar-footer">
        <button class="smlcd-sidebar-role smlcd-btn" id="smlcd-logout"> 
            <i class="ti ti-logout"></i>
            Logout
        </button>
    </div>

</aside>