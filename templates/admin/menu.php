<?php
/**
 * Admin dashboard menu template
 * 
 * @author Callistus Nwachukwu
 * @var \SmartLicenseServer\Admin\AdminDashboardRegistry $registry
 * @var array{
 *      title: string,
 *      slug: string,
 *      handler: class-string<\SmartLicenseServer\Admin\Contracts\AdminPageInterface>,
 *      icon: string, visibility: bool|(callable(): bool)
 * } $current_menu
 * @var array{
 *      title: string,
 *      slug: string,
 *      callback: callable,
 *      visibility: bool|(callable(): bool)
 *  }|null $current_submenu
 */
defined( 'SMLISER_ROOT' ) || exit; ?>

<nav class="dashboard-left-menu" id="dashboard-left-menu" aria-label="Primary">
    <ul class="dashboard-menu-list">
        <?php foreach ( $registry->all() as $key => $menu ) : ?>
            <?php if ( ( is_callable( $menu['visibility'] ) && ! $menu['visibility']() ) || ! $menu['visibility'] ) : continue; ?>
            <?php endif; ?>

            <?php
            $url           = adminUrl( $menu['slug'] );
            $has_submenu   = $registry->has_submenu( $key );
            $is_current_section = $menu['slug'] === $current_menu['slug'];
            $is_current_page    = $is_current_section;

            $extra_li_class  = $is_current_section ? ' is-open' : '';
            $extra_li_class .= $is_current_page ? ' is-current' : '';
            $extra_li_class .= $has_submenu ? ' has-submenu' : '';
            ?>
            <li class="dashboard-menu-item<?php echo escAttr( $extra_li_class ); ?>" id="menu-<?php echo escAttr( $key ); ?>">
                <a
                    href="<?php echo escUrl( $url->url() ); ?>"
                    class="dashboard-menu-link"
                    <?php echo $is_current_page ? ' aria-current="page"' : ''; ?>
                >
                    <span class="dashboard-menu-icon <?php echo escAttr( $menu['icon'] ); ?>" aria-hidden="true"></span>

                    <span class="dashboard-menu-text"><?php echo escHtml( $menu['title'] ); ?></span>

                    <?php if ( $has_submenu ) : ?>
                        <span class="dashboard-menu-arrow" data-toggle="submenu" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>

                <?php if ( $has_submenu ) : ?>
                    <ul class="dashboard-submenu">
                        <?php foreach ( $registry->get_submenu( $key ) as $index => $submenu ) : ?>
                        <?php if ( ( is_callable( $submenu['visibility'] ) && ! $submenu['visibility']() ) || ! $submenu['visibility'] ) : continue; ?>
                        <?php endif; ?>
                            <?php
                            $sub_url        = adminUrl( "{$menu['slug']}/{$submenu['slug']}" );
                            $is_current_sub = null !== $current_submenu && $submenu['slug'] === $current_submenu['slug'];
                            ?>
                            <li class="dashboard-submenu-item<?php echo $is_current_sub ? ' is-current' : ''; ?>" id="submenu-<?php echo escAttr( $submenu['slug'] ); ?>">
                                <a
                                    href="<?php echo escUrl( $sub_url->url() ); ?>"
                                    class="dashboard-submenu-link"
                                    <?php echo $is_current_sub ? ' aria-current="page"' : ''; ?>
                                >
                                    <?php echo escHtml( $submenu['title'] ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>