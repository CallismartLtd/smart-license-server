<?php
/**
 * Admin Dashboard Template
 */


?>
<div class="smliser-admin-dashboard-template ">
    <nav class="smliser-top-nav">
        <div class="smliser-breadcrumb">
            <h1>Smart License Server Dashboard</h1>
        </div>
        <div class="smliser-quick-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=smliser-options')) ?>" class="smliser-icon-btn" title="<?php esc_attr_e( 'Settings', 'smliser' ); ?>">
                <i class="dashicons dashicons-admin-generic"></i>
            </a>
        </div>
    </nav>

   <div class="smliser-admin-body">
        <div class="smliser-dashboard-hero">
            <div class="smliser-dashboard-hero_up">
                <h2>Overview</h2>
            </div>
            <div class="smliser-dashboard-hero_down">
                <?php foreach( $totals as $app_type => $value ) : ?>
                    <div class="smliser-dashboard-hero_down-item">
                        <div class="smliser-dashboard-hero_down-item-icon">
                            <img src="<?php echo esc_url( smliser_get_placeholder_icon( $app_type ) ); ?>" alt="">
                        </div>
                        <div class="smliser-dashboard-hero_down-item-content">
                            <span><?php echo esc_html( $value ); ?></span>
                            <span><?php echo esc_html( sprintf( 'Total %s', ucfirst( $app_type ) ) ); ?></span>
                        </div>

                    </div>

                <?php endforeach; ?>
            </div>
        </div>
        <div class="smliser-dashboard-content">
            <div class="smliser-dashboard-content_item"></div>
            
        </div>
   </div>

</div>
