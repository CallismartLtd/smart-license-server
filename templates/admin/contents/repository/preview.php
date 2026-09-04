<?php
/**
 * Hosted Application preview file.
 * 
 * @author Callistus Nwachukwu
 * 
 * @var array $template_header Array containing: icon, name, badges, short_description, buttons
 * @var array $template_sidebar Array containing: Author, Performance Metrics, App Info, Installation, Changelog
 * @var array $template_content Array containing: Icons, Banners, Screenshots
 * @var \SmartlicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Admin\ContentHandlers\RepositoryPage $repo_page
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

defined( 'SMLISER_ROOT' ) || exit;
?>

<div class="smliser-admin-repository-template repo-page">
    <?php smliser_print_admin_content_header( $repo_page->get_menu_args( $request, isset( $app ) ? $app : null ) ); ?>

    <!-- Hero Section -->
    <section class="smliser-hero-section">
        <div class="smliser-hero-content">
            <div class="smliser-app-header-row">
                <div class="smliser-app-icon-large">
                    <?php if ( ! empty( $template_header['icon'] ) ) : ?>
                        <img src="<?php echo escUrl( $template_header['icon'] ); ?>" alt="<?php echo escAttr( $template_header['name'] ); ?>">
                    <?php else : ?>
                        <i class="ti ti-app-window"></i>
                    <?php endif; ?>
                </div>
                
                <div class="smliser-app-title-section">
                    <h1 class="smliser-app-title"><?php echo escHtml( $template_header['name'] ); ?></h1>
                    
                    <div class="smliser-badge-row">
                        <?php foreach ( $template_header['badges'] as $badge ) : ?>
                            <span class="smliser-badge smliser-badge-<?php echo escAttr( strtolower( str_replace( ' ', '-', $badge ) ) ); ?>">
                                <?php echo escHtml( $badge ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if ( array_key_exists( 'short_description', $template_header ) ) : ?>
                <p class="smliser-app-description">
                    <?php echo sanitize_html( $template_header['short_description'] ); ?>
                </p>
            <?php endif; ?>
            
            <div class="smliser-action-bar">
                <?php foreach ( $template_header['buttons'] as $button ) : ?>
                    <a href="<?php echo escUrl( $button['url'] ); ?>" 
                       class="<?php echo implode( ' ', $button['class'] ); ?>"
                       <?php 
                       if ( ! empty( $button['attr'] ) ) {
                           foreach ( $button['attr'] as $attr_key => $attr_value ) {
                               echo sprintf( '%s="%s" ', escAttr( $attr_key ), escAttr( $attr_value ) );
                           }
                       }
                       ?>>
                        <?php if ( ! empty( $button['icon'] ) ) : ?>
                            <i class="<?php echo escAttr( $button['icon'] ); ?>"></i>
                        <?php endif; ?>
                        <?php echo escHtml( $button['text'] ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Main Content Grid -->
    <div class="smliser-content-grid">
        <!-- Main Content Area -->
        <div class="smliser-main-content">
            
            <!-- Visual Preview Card -->
            <?php if ( ! empty( $images ) ) : ?>
                <div class="smliser-card">
                    <div class="smliser-card-header">
                        <div class="smliser-card-icon">
                            <i class="ti ti-photo"></i>
                        </div>
                        <h2 class="smliser-card-title"><?php echo escHtml( 'Visual Preview' ); ?></h2>
                    </div>
                    
                    <div class="smliser-screenshot-gallery">
                        <div tabindex="0" class="smliser-gallery-preview">
                            <?php 
                                $first_title = array_key_first( $images );
                                $first_image = current( $images );                            
                            ?>
                            <h3 class="smliser-gallery-preview_title"><?php echo escHtml( $first_title ); ?></h3>
                            <div class="smliser-gallery-preview_image">
                                <img class="smliser-request-fullscreen" src="<?php echo escUrl( current( $first_image )?: $urlmanager->assets_url( 'images/no-image.svg' ) ); ?>" alt="image" title="Double click for fullscreen">
                            </div>
                        </div>
                        
                        <div class="smliser-gallery-list-container">
                            <?php foreach ( $images as $title => $data ) : ?>
                                <h3><?php echo escHtml( $title ); ?></h3>
                                <?php foreach( $data as $image_url ) : ?>
                                    <ul class="smliser-gallery-list-container_ul">
                                        <li><img class="repo-image-preview" src="<?php echo escUrl( $image_url ); ?>" alt="<?php echo escAttr( $title ); ?>" data-repo-image-title="<?php echo escHtml( $title ); ?>" title="Preview"></li>
                                    </ul>
                                <?php endforeach; ?>                            
                            <?php endforeach; ?>                            
                        </div>

                    </div>
                </div>
            <?php endif; ?>

            <!-- Installation Instructions -->
            <?php if ( array_key_exists( 'Installation', $template_content ) ) : ?>
                <div class="smliser-card">
                    <div class="smliser-card-header">
                        <div class="smliser-card-icon">
                            <i class="ti ti-download"></i>
                        </div>
                        <h2 class="smliser-card-title"><?php echo escHtml( 'Installation' ); ?></h2>
                    </div>
                    <div class="smliser-card-content">
                        <?php echo sanitize_html( $template_content['Installation'] ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Changelog -->
            <?php if ( array_key_exists( 'Changelog', $template_content ) ) : ?>
                <div class="smliser-card">
                    <div class="smliser-card-header">
                        <div class="smliser-card-icon">
                            <i class="ti ti-clock-edit"></i>
                        </div>
                        <h2 class="smliser-card-title"><?php echo escHtml( 'Changelog' ); ?></h2>
                    </div>
                    <div class="smliser-card-content smliser-changelog">
                        <?php echo sanitize_html( $template_content['Changelog'] ); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="smliser-sidebar">
            
            <?php foreach( $template_sidebar as $heading => $data ) : ?>
                <div class="smliser-sidebar-card">
                    <h3>
                        <i class="<?php echo escAttr( $data['icon'] ?? 'ti ti-chart-bar' ) ?>"></i>
                        <?php echo escHtml( $heading ); ?>
                    </h3>
                    <div class="smliser-sidebar-content">
                        <?php echo ( $data['content'] ?? '' ); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </aside>
    </div>
</div>