<?php
/**
 * General asset manager for CSS/JS.
 * Handles grouped asset retrieval for WP or standalone pages.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

final class AssetsManager {

    /**
     * Get script suffix depending on debug.
     */
    public static function script_suffix() : string {
        return defined( 'SCRIPT_DEBUG' ) && \SCRIPT_DEBUG ? '' : '.min';
    }

    /**
     * Get all CSS assets.
     * 
     * @return array<string, array<string, mixed>>
     */
    public static function allCSS() : array {
        return CSS::all( self::script_suffix() );
    }

    /**
     * Get all JS assets.
     * 
     * @return array<string, array<string, mixed>>
     */
    public static function allJS() : array {
        return JS::all( self::script_suffix() );
    }

    /**
     * Return the asset definitions required by the standalone email editor page.
     *
     * Scripts are ordered so that dependencies come before dependants:
     *   jquery → smliser-script → smliser-modal → smliser-email-editor
     *
     * @return array<string, array<int, array<string, string>>>
     */
    public static function get_email_editor_assets() : array {
        $all_css = self::allCSS();
        $all_js  = self::allJS();

        $styles = [
            'smliser-tabler-icons',
            'smliser-styles',
            'smliser-form-styles',
            'smliser-modal',
            'smliser-datetime-picker',
            'smliser-email-editor',
        ];

        $scripts = [
            'smliser-jquery',
            'select2',
            'smliser-datetime-picker',
            'smliser-script',
            'smliser-modal',
            'smliser-email-editor',
        ];

        return [
            'styles'  => array_map(
                fn( $handle ) => [
                    'handle' => $handle,
                    'src'    => $all_css[ $handle ]['src'],
                ],
                $styles
            ),
            'scripts' => array_map(
                fn( $handle ) => [
                    'handle' => $handle,
                    'src'    => $all_js[ $handle ]['src'],
                ],
                $scripts
            ),
        ];
    }

}