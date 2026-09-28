<?php
/**
 * The admin system diagnostics page template.
 *
 * @author Callistus Nwachukwu
 * @var array<string, array<string, string>> $diagnostics
 * @var SmartLicenseServer\Admin\ContentHandlers\ToolsPage $page_handler
 * @var SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\Utils\Format;

$menu_args = $page_handler->get_top_menu_args( $request );

$section_icons = [
    'Server'             => 'ti ti-server-2',
    'Database'                => 'ti ti-database',
    'Directories'             => 'ti ti-folder',
    'Background Processing'   => 'ti ti-list-check',
];
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>

    <?php if ( empty( $diagnostics ) ) : ?>
        <div class="smliser-not-found-container">
            <p>No diagnostic data available.</p>
        </div>
    <?php else : ?>
        <div class="smliser-diagnostics-grid" id="smliser-diagnostics-grid">
            <?php foreach ( $diagnostics as $section_title => $rows ) : ?>
                <details class="smliser-diagnostics-panel" id="<?php echo escAttr( Format::slugify( $section_title ) ); ?>">
                    <summary class="smliser-diagnostics-panel-summary">
                        <span class="smliser-diagnostics-panel-summary-left">
                            <span class="smliser-diagnostics-panel-icon">
                                <i class="<?php echo escAttr( $section_icons[ $section_title ] ?? 'ti ti-info-circle' ); ?>"></i>
                            </span>
                            <span class="smliser-diagnostics-panel-title"><?php echo escHtml( $section_title ); ?></span>
                        </span>
                        <span class="smliser-diagnostics-panel-summary-right">
                            <span class="smliser-diagnostics-panel-count"><?php echo escHtml( (string) \count( $rows ) ); ?></span>
                            <svg class="smliser-diagnostics-panel-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </span>
                    </summary>
                    <div class="smliser-diagnostics-panel-body">
                        <ul class="smliser-app-meta">
                            <?php foreach ( $rows as $label => $value ) : ?>
                                <li>
                                    <span><?php echo escHtml( $label ); ?></span>
                                    <span><?php echo escHtml( $value ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div