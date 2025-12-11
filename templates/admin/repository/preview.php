<?php
/**
 * Hosted Application preview file.
 * 
 * @author Callistus Nwachukwu
 * 
 * @var array $template_header Array containing: icon, name, badges, short_description, buttons
 * @var array $template_sidebar Array containing: Author, Performance Metrics, App Info, Installation, Changelog
 * @var array $template_content Array containing: Icons, Banners, Screenshots
 */

defined( 'SMLISER_ABSPATH' ) || exit;
?>

<div class="smliser-admin-repository-template">
    <!-- Top Navigation Breadcrumb -->
    <nav class="smliser-top-nav">
        <div class="smliser-breadcrumb">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=repository' ) ); ?>">
                <i class="dashicons dashicons-admin-home"></i> Repository
            </a>
            <span>/</span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=repository&type=' . $app->get_type() ) ); ?>">
                <i class="dashicons dashicons-open-folder"></i> <?php echo esc_html( rtrim( ucfirst( $app->get_type() ), 's' ) . 's' ); ?>
            </a>
            <span>/</span>
            <span><?php echo esc_html( $template_header['name'] ); ?></span>
        </div>
        <div class="smliser-quick-actions">
            <a class="smliser-icon-btn" href="<?php echo esc_url( smliser_admin_repo_tab( 'edit', array( 'item_id' => $app->get_id(), 'type' => $app->get_type() ) ) ); ?>" title="<?php esc_attr_e( 'Edit', 'smliser' ); ?>">
                <i class="dashicons dashicons-edit"></i>
            </a>
            <button class="smliser-icon-btn" title="<?php esc_attr_e( 'Settings', 'smliser' ); ?>">
                <i class="dashicons dashicons-admin-generic"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="smliser-hero-section">
        <div class="smliser-hero-content">
            <div class="smliser-app-header-row">
                <div class="smliser-app-icon-large">
                    <?php if ( ! empty( $template_header['icon'] ) ) : ?>
                        <img src="<?php echo esc_url( $template_header['icon'] ); ?>" alt="<?php echo esc_attr( $template_header['name'] ); ?>">
                    <?php else : ?>
                        <i class="dashicons dashicons-admin-plugins"></i>
                    <?php endif; ?>
                </div>
                
                <div class="smliser-app-title-section">
                    <h1 class="smliser-app-title"><?php echo esc_html( $template_header['name'] ); ?></h1>
                    
                    <div class="smliser-badge-row">
                        <?php foreach ( $template_header['badges'] as $badge ) : ?>
                            <span class="smliser-badge smliser-badge-<?php echo esc_attr( strtolower( str_replace( ' ', '-', $badge ) ) ); ?>">
                                <?php echo esc_html( $badge ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if ( array_key_exists( 'short_description', $template_header ) ) : ?>
                <p class="smliser-app-description">
                    <?php echo wp_kses_post( $template_header['short_description'] ); ?>
                </p>
            <?php endif; ?>
            
            <div class="smliser-action-bar">
                <?php foreach ( $template_header['buttons'] as $button ) : ?>
                    <a href="<?php echo esc_url( $button['url'] ); ?>" 
                       class="smliser-btn <?php echo strpos( strtolower( $button['text'] ), 'delete' ) !== false ? 'smliser-btn-danger smliser-app-delete-button' : 'smliser-btn-glass'; ?>"
                       <?php 
                       if ( ! empty( $button['attr'] ) ) {
                           foreach ( $button['attr'] as $attr_key => $attr_value ) {
                               echo sprintf( '%s="%s" ', esc_attr( $attr_key ), esc_attr( $attr_value ) );
                           }
                       }
                       ?>>
                        <?php if ( ! empty( $button['icon'] ) ) : ?>
                            <i class="<?php echo esc_attr( $button['icon'] ); ?>"></i>
                        <?php endif; ?>
                        <?php echo esc_html( $button['text'] ); ?>
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
                            <i class="dashicons dashicons-format-image"></i>
                        </div>
                        <h2 class="smliser-card-title"><?php esc_html_e( 'Visual Preview', 'smliser' ); ?></h2>
                    </div>
                    
                    <div class="smliser-screenshot-gallery">
                        <div class="smliser-gallery-preview">
                            <?php 
                                $first_title = array_key_first( $images );
                                $first_image = current( $images );                            
                            ?>
                            <h3 class="smliser-gallery-preview_title"><?php echo esc_html( $first_title ); ?></h3>
                            <div class="smliser-gallery-preview_image">
                                <img class="smliser-request-fullscreen" src="<?php echo esc_url( current( $first_image )?: SMLISER_URL . 'assets/images/no-image.svg' ); ?>" alt="image" title="Double click for fullscreen">
                            </div>
                        </div>
                        
                        <div class="smliser-gallery-list-container">
                            <?php foreach ( $images as $title => $data ) : ?>
                                <h3><?php echo esc_html( $title ); ?></h3>
                                <?php foreach( $data as $image_url ) : ?>
                                    <ul class="smliser-gallery-list-container_ul">
                                        <li><img class="repo-image-preview" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" data-repo-image-title="<?php echo esc_html( $title ); ?>" title="Preview"></li>
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
                            <i class="dashicons dashicons-download"></i>
                        </div>
                        <h2 class="smliser-card-title"><?php esc_html_e( 'Installation', 'smliser' ); ?></h2>
                    </div>
                    <div class="smliser-card-content">
                        <?php echo wp_kses_post( $template_content['Installation'] ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Changelog -->
            <?php if ( array_key_exists( 'Changelog', $template_content ) ) : ?>
                <div class="smliser-card">
                    <div class="smliser-card-header">
                        <div class="smliser-card-icon">
                            <i class="dashicons dashicons-list-view"></i>
                        </div>
                        <h2 class="smliser-card-title"><?php esc_html_e( 'Changelog', 'smliser' ); ?></h2>
                    </div>
                    <div class="smliser-card-content smliser-changelog">
                        <?php echo wp_kses_post( $template_content['Changelog'] ); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="smliser-sidebar">
            
            <?php foreach( $template_sidebar as $heading => $data ) : ?>
                <div class="smliser-sidebar-card">
                    <h3>
                        <i class="<?php echo esc_attr( $data['icon'] ?? 'dashicons dashicons-chart-bar' ) ?>"></i>
                        <?php echo esc_html( $heading ); ?>
                        <!-- <?php esc_html_e( 'Analytics (30 Days)', 'smliser' ); ?> -->
                    </h3>
                    <div class="smliser-sidebar-content">
                        <?php echo wp_kses_post( $data['content'] ?? '' ); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </aside>
    </div>
    <div style="padding: 10px;">
        <h2>REST API Documentation</h2>
        <div class="smliser-admin-api-description-section">
            <div class="smliser-api-base-url">
                <strong>Base URL:</strong>
                <code><?php echo esc_url( rest_url() ); ?></code>
            </div>
            
            <?php foreach ( $route_descriptions as $path => $html ) : 
                echo $html; // Already safely escaped in the V1 class
            endforeach; ?>
        </div>
    </div>
</div>