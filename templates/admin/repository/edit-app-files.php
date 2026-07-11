<?php
/**
 * Application zip files variation template.
 * 
 * @author Callistus Nwachukwu
 * 
 * @see \SmartLicenseServer\Admin\RepositoryPage::upload_page()
 * @see \SmartLicenseServer\Admin\RepositoryPage::edit_page()
 * @var \SmartLicenseServer\Core\Request $request
 * @var array $essential_fields
 * @var string $type
 * @var string $type_title
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp $app
 * @var array{slug: string, path: string, size: int, mtime: int, mime_type: string|null, filename: string}[] $app_files
 */

use SmartLicenseServer\Admin\RepositoryPage;
use SmartLicenseServer\Utils\Format;

$args   = RepositoryPage::get_menu_args( $request, isset( $app ) ? $app : null );
$max_upload_size_bytes  = wp_max_upload_size();
$max_upload_size_mb     = $max_upload_size_bytes / 1024 / 1024;
?>

<div class="application-uploader-page artifact-editor">
    <?php smliser_print_admin_content_header( $args ); ?>
 

    <div class="smliser-app-files-list">
        <div class="notice notice-info">
            <h2>
                <?php printf( 'Manage all distributable artifacts for <a href="%s">%s</a>', 
                smliser_admin_repo_tab( 'view', ['app_id' => $app->get_id(), 'type' => $app->get_type()] ), $app->get_name() ); ?>
            </h2>
            <em>
                A distributable artifact is packaged version of your <strong><?php echo escHtml( $type ); ?></strong> that is ready for
                deployment or sharing. 
            </em>
        </div>

        <ul class="smliser-app-artifacts">
            <?php foreach( $app_files as $file_data ) : 
                $download_url   = 'main' === $file_data['slug'] ? $app->get_download_url() : $app->get_artifact_url( $file_data['filename'] );
            ?>
                <li class="smliser-app-artifacts_item">
                    <div class="smliser-app-artifacts_item-heading">
                        <span>
                            <strong>Name:</strong> <?php echo escHtml( $file_data['filename'] ); ?>
                        </span>
                         
                        <?php if ( 'main' === $file_data['slug'] ) : ?>
                            <span class="smliser-provider-card__badge">
                                Main artifact
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="smliser-app-artifacts_item-info">
                        <table>
                            <tbody>
                                <tr>
                                    <th>Slug:</th>
                                    <td><?php echo escHtml( $file_data['slug'] ) ?></td>
                                </tr>
                                <tr>
                                    <th>Size:</th>
                                    <td><?php echo escHtml( Format::bytes( $file_data['size'] ) ) ?></td>
                                </tr>
                                <tr>
                                    <th>Mime Type:</th>
                                    <td><?php echo escHtml( $file_data['mime_type'] ) ?></td>
                                </tr>
                                <tr>
                                    <th>Download URL:</th>
                                    <td>
                                        <span title="<?php echo esc_attr( $download_url ) ?>">
                                            <?php echo escHtml( Format::truncate( $download_url->url(), 40 ) ) ?>    
                                        </span>
                                        <i class="ti ti-copy smliser-click-to-copy" title="Click to copy" data-copy-value="<?php echo esc_url( $download_url ) ?>"></i>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Last Updated:</th>
                                    <td><?php echo escHtml( Format::time_ago( $file_data['mtime'] ) ) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="smliser-app-artifacts_item-buttons">
                        <button title="Edit artifact" class="smliser-edit-artifact"> <i class="ti ti-edit"></i></button>
                        <button title="Delete artifact" class="smliser-delete-artifact"> <i class="ti ti-trash"></i></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <button class="smliser-nav-btn button smliser-add-new-artifact" title="Add new artifact">
            <i class="ti ti-add"></i> Add Artifact
        </button>
    </div>
</div>