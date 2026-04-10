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
     * Email editor asset group name
     */
    const GROUP_EMAIL_EDITOR    = 'email_editor';

    /**
     * Client dashboard asset group name
     */
    const GROUP_CLIENT_DASHBOARD    = 'client_dashboard';

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
     * Get a single CSS asset definition.
     *
     * @param string $handle
     * @return array<string, mixed>|null
     */
    public static function getCSS( string $handle ) : ?array {
        $all = self::allCSS();
        return $all[ $handle ] ?? null;
    }

    /**
     * Get a single JS asset definition.
     *
     * @param string $handle
     * @return array<string, mixed>|null
     */
    public static function getJS( string $handle ) : ?array {
        $all = self::allJS();
        return $all[ $handle ] ?? null;
    }

    /**
     * Get only the CSS asset URL.
     *
     * @param string $handle
     * @return string|null
     */
    public static function getCSSUrl( string $handle ) : ?string {
        $asset = self::getCSS( $handle );
        return $asset['src'] ?? null;
    }

    /**
     * Get only the JS asset URL.
     *
     * @param string $handle
     * @return string|null
     */
    public static function getJSUrl( string $handle ) : ?string {
        $asset = self::getJS( $handle );
        return $asset['src'] ?? null;
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

    /**
     * Print a single CSS <link> tag.
     *
     * @param string $handle
     * @param bool   $echo
     * @return string|null
     */
    public static function print_style( string $handle, bool $echo = true ) : ?string {
        $src = self::getCSSUrl( $handle );

        if ( empty( $src ) ) {
            return null;
        }

        $html = sprintf(
            '<link rel="stylesheet" id="%s-css" href="%s" />',
            esc_attr( $handle ),
            esc_url( $src )
        );

        if ( $echo ) {
            echo $html . "\n"; // phpcs:ignore
            return null;
        }

        return $html;
    }

    /**
     * Print a single JS <script> tag.
     *
     * @param string $handle
     * @param bool   $echo
     * @return string|null
     */
    public static function print_script( string $handle, bool $echo = true ) : ?string {
        $src = self::getJSUrl( $handle );

        if ( empty( $src ) ) {
            return null;
        }

        $html = sprintf(
            '<script id="%s-js" src="%s"></script>',
            esc_attr( $handle ),
            esc_url( $src )
        );

        if ( $echo ) {
            echo $html . "\n"; // phpcs:ignore
            return null;
        }

        return $html;
    }

    /**
     * Print multiple CSS assets with dependency resolution.
     *
     * @param string[] $handles
     */
    public static function print_styles( string ...$handles ) : void {

        $handles = self::resolve_css( $handles );

        foreach ( $handles as $handle ) {
            self::print_style( $handle );
        }
    }

    /**
     * Print multiple JS assets with dependency resolution.
     *
     * @param string[] $handles
     */
    public static function print_scripts( string ...$handles ) : void {

        $handles = self::resolve_js( $handles );

        foreach ( $handles as $handle ) {
            self::print_script( $handle );
        }
    }

    /**
     * Print asset group (styles + scripts).
     *
     * Expected format:
     * [
     *   'styles' => [ ['handle' => '', 'src' => ''], ... ],
     *   'scripts' => [ ['handle' => '', 'src' => ''], ... ]
     * ]
     *
     * @param array<string, array<int, array<string, string>>> $assets
     */
    public static function print_assets( array $assets ) : void {

        if ( ! empty( $assets['styles'] ) ) {
            $handles = array_column( $assets['styles'], 'handle' );
            self::print_styles( ...$handles );
        }

        if ( ! empty( $assets['scripts'] ) ) {
            $handles = array_column( $assets['scripts'], 'handle' );
            self::print_scripts( ...$handles );
        }
    }

    /**
     * Resolve CSS dependencies.
     *
     * @param array<int, string> $handles
     * @return array<int, string>
     */
    public static function resolve_css( array $handles ) : array {
        return self::resolve_dependencies( $handles, 'css' );
    }

    /**
     * Resolve JS dependencies.
     *
     * @param array<int, string> $handles
     * @return array<int, string>
     */
    public static function resolve_js( array $handles ) : array {
        return self::resolve_dependencies( $handles, 'js' );
    }

    /**
     * Resolve dependencies for given handles.
     *
     * @param array<int, string> $handles
     * @param string             $type    css|js
     * @return array<int, string>
     */
    private static function resolve_dependencies( array $handles, string $type ) : array {

        $all = 'css' === $type ? self::allCSS() : self::allJS();

        $resolved = [];
        $seen     = [];
        $stack    = [];

        $resolve = function ( string $handle ) use ( &$resolve, &$resolved, &$seen, &$stack, $all ) {

            if ( isset( $resolved[ $handle ] ) ) {
                return;
            }

            if ( isset( $stack[ $handle ] ) ) {
                // Circular dependency detected.
                return;
            }

            $stack[ $handle ] = true;

            $asset = $all[ $handle ] ?? null;

            if ( $asset && ! empty( $asset['deps'] ) && is_array( $asset['deps'] ) ) {
                foreach ( $asset['deps'] as $dep ) {
                    $resolve( $dep );
                }
            }

            $resolved[ $handle ] = $handle;
            unset( $stack[ $handle ] );
        };

        foreach ( $handles as $handle ) {
            if ( ! isset( $seen[ $handle ] ) ) {
                $resolve( $handle );
                $seen[ $handle ] = true;
            }
        }

        return array_values( $resolved );
    }

    /*
    |--------------------
    | ASSETS GROUPING
    |--------------------
    */

    /**
     * Asset groups registry.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public static function groups() : array {
        return [

            static::GROUP_CLIENT_DASHBOARD => [
                'styles' => [
                    'smliser-tabler-icons',
                    'smliser-styles',
                    'smliser-form-styles',
                    'smliser-modal',
                    'smliser-client-dashboard',
                    'smliser-datetime-picker',
                    'select2',
                ],
                'scripts' => [
                    'smliser-jquery',
                    'select2',
                    'smliser-datetime-picker',
                    'smliser-script',
                    'smliser-modal',
                    'smliser-client-dashboard',
                ],
            ],

            static::GROUP_EMAIL_EDITOR => [
                'styles' => [
                    'smliser-tabler-icons',
                    'smliser-styles',
                    'smliser-form-styles',
                    'smliser-modal',
                    'smliser-datetime-picker',
                    'smliser-email-editor',
                ],
                'scripts' => [
                    'smliser-jquery',
                    'select2',
                    'smliser-datetime-picker',
                    'smliser-script',
                    'smliser-modal',
                    'smliser-email-editor',
                ],
            ],

        ];
    }

    /**
     * Get asset group with resolved dependencies.
     *
     * @param string $group
     * @return array<string, array<int, string>>
     */
    public static function get_group( string $group ) : array {

        $groups = self::groups();

        if ( ! isset( $groups[ $group ] ) ) {
            return [
                'styles'  => [],
                'scripts' => [],
            ];
        }

        $assets = $groups[ $group ];

        return [
            'styles'  => self::resolve_css( $assets['styles'] ?? [] ),
            'scripts' => self::resolve_js( $assets['scripts'] ?? [] ),
        ];
    }

    /**
     * Print asset group.
     *
     * @param string $group
     */
    public static function print_group( string $group ) : void {

        $assets = self::get_group( $group );

        if ( ! empty( $assets['styles'] ) ) {
            self::print_styles( ...$assets['styles'] );
        }

        if ( ! empty( $assets['scripts'] ) ) {
            self::print_scripts( ...$assets['scripts'] );
        }
    }
}