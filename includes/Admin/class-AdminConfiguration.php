<?php
/**
 * Admin Menu Configuration
 *
 * Provides a simple, ordered menu registry with support for:
 * - Default menu bootstrapping
 * - Registering single menu items
 * - Optional positional insertion (array index)
 *
 * This class maintains menu order strictly by array position.
 *
 * @package SmartLicenseServer\Admin
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

defined( 'SMLISER_ABSPATH' ) || exit;

final class AdminConfiguration {

    /**
     * Menu registry (ordered).
     *
     * @var array<string, array{
     *     title: string,
     *     slug: string,
     *     handler: callable,
     *     icon: string
     * }>
     */
    protected array $menu = [];

    /**
     * Boot flag.
     *
     * @var bool
     */
    protected bool $booted = false;

    /*
    |-----------
    | REGISTER
    |-----------
    */

    /**
     * Register a single menu item.
     *
     * @param string $key Unique menu identifier.
     * @param array{
     *     title: string,
     *     slug: string,
     *     handler: callable,
     *     icon?: string
     * } $data
     * @param int|null $position Optional zero-based index to insert into.
     *
     * @throws EnvironmentBootstrapException
     */
    public function register( string $key, array $data, ?int $position = null ) : void {
        $this->boot();
        $key    = $this->canonical_key( $key );

        // Validate key.
        if ( '' === $key ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                'Menu key cannot be empty.'
            );
        }

        // Prevent duplicate keys.
        if ( isset( $this->menu[ $key ] ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" already exists.', $key )
            );
        }

        // Validate required fields.
        foreach ( [ 'title', 'slug', 'handler' ] as $field ) {
            if ( empty( $data[ $field ] ) ) {
                throw new EnvironmentBootstrapException(
                    'menu_error',
                    sprintf( 'Menu "%s" missing required field "%s".', $key, $field )
                );
            }
        }

        // Validate handler.
        if ( ! is_callable( $data['handler'] ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" handler must be callable.', $key )
            );
        }

        // Normalize.
        $menu = [
            'title'   => (string) $data['title'],
            'slug'    => trim( (string) $data['slug'], '/' ),
            'handler' => $data['handler'],
            'icon'    => isset( $data['icon'] ) ? (string) $data['icon'] : '',
        ];

        // Insert into menu
        $this->insert_menu_item( $key, $menu, $position );
    }

    /*
    |------------
    | INSERTION
    |------------
    */

    /**
     * Insert a menu item into the registry at a given position.
     *
     * If position is null or out of bounds, the item is appended.
     *
     * @param string   $key
     * @param array    $menu
     * @param int|null $position
     *
     * @return void
     */
    protected function insert_menu_item( string $key, array $menu, ?int $position ) : void {

        if ( $position !== null ) {
            $position = max( 0, $position - 1 );
        }

        // Append if no valid position
        if ( null === $position || $position >= count( $this->menu ) ) {
            $this->menu[ $key ] = $menu;
            return;
        }

        // Split array and insert
        $before = array_slice( $this->menu, 0, $position, true );
        $after  = array_slice( $this->menu, $position, null, true );

        $this->menu = $before + [ $key => $menu ] + $after;
    }

    /*
    |--------------------------------------------------------------------------
    | RETRIEVAL
    |--------------------------------------------------------------------------
    */

    /**
     * Get all menu items.
     *
     * @return array<string, array{title: string, slug: string, handler: callable, icon: string}>
     */
    public function all() : array {

        $this->boot();

        return $this->menu;
    }

    /**
     * Get a single menu item.
     *
     * @param string $key
     * @return array{title: string, slug: string, handler: callable, icon: string}|null
     */
    public function get( string $key ) : ?array {
        $this->boot();
        $key    = $this->canonical_key( $key );

        return $this->menu[ $key ] ?? null;
    }

    /**
     * Check if a menu exists.
     *
     * @param string $key
     * @return bool
     */
    public function has( string $key ) : bool {
        $this->boot();
        $key    = $this->canonical_key( $key );

        return isset( $this->menu[ $key ] );
    }

    /**
     * Remove a menu item
     * 
     * @param string $key
     * @return bool
     */
    public function remove( string $key ) : bool {
        $this->boot();
        $key    = $this->canonical_key( $key );

        if ( ! $this->has( $key ) ) {
            return false;
        }

        unset( $this->menu[ $key ] );

        return true;
    }

    /*
    |-------------
    | BOOTSTRAP
    |-------------
    */

    /**
     * Initialize default menu.
     *
     * Runs once.
     *
     * @return void
     */
    protected function boot() : void {

        if ( $this->booted ) {
            return;
        }

        $this->booted = true;

        $this->menu = [
            'overview' => [
                'title'   => 'Overview',
                'slug'    => '',
                'handler' => [ DashboardPage::class, 'router' ],
                'icon'    => 'ti ti-home',
            ],
            'repository' => [
                'title'   => 'Repository',
                'slug'    => 'repository',
                'handler' => [ RepositoryPage::class, 'router' ],
                'icon'    => 'ti ti-folder',
            ],
            'licenses' => [
                'title'   => 'Licenses',
                'slug'    => 'licenses',
                'handler' => [ LicensePage::class, 'router' ],
                'icon'    => 'ti ti-license',
            ],
            'bulk_messages' => [
                'title'   => 'Bulk Messages',
                'slug'    => 'bulk-messages',
                'handler' => [ BulkMessagePage::class, 'router' ],
                'icon'    => 'ti ti-envelop',
            ],
            'accounts' => [
                'title'   => 'Accounts',
                'slug'    => 'accounts',
                'handler' => [ AccessControlPage::class, 'router' ],
                'icon'    => 'ti ti-users-group',
            ],
            'settings' => [
                'title'   => 'Settings',
                'slug'    => 'settings',
                'handler' => [ OptionsPage::class, 'router' ],
                'icon'    => 'ti ti-generic',
            ],
        ];
    }

    /**
     * Ensure a menu exists before mutation.
     *
     * @param string $key
     * @return void
     * @throws EnvironmentBootstrapException
     */
    protected function assert_menu_exists( string $key ) : void {

        $this->boot();

        if ( ! isset( $this->menu[ $key ] ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" does not exist.', $key )
            );
        }
    }

    /**
     * Normalizes menu key.
     * 
     * 
     * @param string $key
     * @return string
     */
    private function canonical_key( string $key ) : string {
        return \str_replace( '-', '_', $key );
    }

    /**
     * Update menu title.
     *
     * @param string $key
     * @param string $title
     * @return void
     */
    public function set_title( string $key, string $title ) : void {
        $key    = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        if ( '' === trim( $title ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" title cannot be empty.', $key )
            );
        }

        $this->menu[ $key ]['title'] = $title;
    }

    /**
     * Update menu slug.
     *
     * @param string $key
     * @param string $slug
     * @return void
     */
    public function set_slug( string $key, string $slug ) : void {
        $key    = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        $slug = trim( $slug, '/' );

        if ( '' === $slug && $key !== 'overview' ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" slug cannot be empty.', $key )
            );
        }

        $this->menu[ $key ]['slug'] = $slug;
    }

    /**
     * Update menu handler.
     *
     * @param string   $key
     * @param callable $handler
     * @return void
     */
    public function set_handler( string $key, callable $handler ) : void {
        $key    = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        if ( ! is_callable( $handler ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" handler must be callable.', $key )
            );
        }

        $this->menu[ $key ]['handler'] = $handler;
    }

    /**
     * Update menu icon.
     *
     * @param string $key
     * @param string $icon
     * @return void
     */
    public function set_icon( string $key, string $icon ) : void {
        $key    = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        $this->menu[ $key ]['icon'] = $icon;
    }

    /**
     * Get the numeric index of a menu item.
     *
     * @param string $key
     * @return int
     */
    protected function get_index( string $key ) : int {
        $this->boot();

        $keys   = array_keys( $this->menu );
        $index  = array_search( $key, $keys, true );

        if ( false === $index ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" does not exist.', $key )
            );
        }

        return $index;
    }

    /**
     * Reinsert a menu item at a given index.
     *
     * @param string $key
     * @param int    $new_index
     * @return void
     */
    protected function move_to_index( string $key, int $new_index ) : void {

        $menu = $this->menu;

        $keys  = array_keys( $menu );
        $index = array_search( $key, $keys, true );

        if ( false === $index ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" does not exist.', $key )
            );
        }

        $item = $menu[ $key ];

        // Remove item
        unset( $menu[ $key ] );

        // Clamp index
        $new_index = max( 0, min( $new_index, count( $menu ) ) );

        // Rebuild
        $before = array_slice( $menu, 0, $new_index, true );
        $after  = array_slice( $menu, $new_index, null, true );

        $this->menu = $before + [ $key => $item ] + $after;
    }

    /**
     * Move menu item one position up.
     *
     * @param string $key
     * @return void
     */
    public function move_up( string $key ) : void {
        $key    = $this->canonical_key( $key );
        $index  = $this->get_index( $key );

        if ( $index === 0 ) {
            return; // already at top
        }

        $this->move_to_index( $key, $index - 1 );
    }

    /**
     * Move menu item one position down.
     *
     * @param string $key
     * @return void
     */
    public function move_down( string $key ) : void {
        $key    = $this->canonical_key( $key );
        $index  = $this->get_index( $key );

        if ( $index === count( $this->menu ) - 1 ) {
            return; // already at bottom
        }

        $this->move_to_index( $key, $index + 1 );
    }

    /**
     * Move menu item to a specific position.
     *
     * @param string $key
     * @param int    $position Zero-based index.
     * @return void
     */
    public function move_to( string $key, int $position ) : void {
        $key    = $this->canonical_key( $key );
        $this->move_to_index( $key, $position );
    }

    /**
     * Move $key after $targetKey.
     *
     * @param string $key
     * @param string $targetKey
     * @return void
     */
    public function move_after( string $key, string $targetKey ) : void {
        $key            = $this->canonical_key( $key );
        $targetIndex    = $this->get_index( $targetKey );

        // New index is right after target
        $this->move_to_index( $key, $targetIndex + 1 );
    }

    /**
     * Move $key before $targetKey.
     *
     * @param string $key
     * @param string $targetKey
     * @return void
     */
    public function move_before( string $key, string $targetKey ) : void {
        $key            = $this->canonical_key( $key );
        $targetKey      = $this->canonical_key( $targetKey );
        $targetIndex    = $this->get_index( $targetKey );

        $this->move_to_index( $key, $targetIndex );
    }

    /**
     * Move menu item to the top (first position).
     *
     * @param string $key
     * @return void
     */
    public function move_to_top( string $key ) : void {
        $key    = $this->canonical_key( $key );
        $this->move_to_index( $key, 0 );
    }

    /**
     * Move menu item to the bottom (last position).
     *
     * @param string $key
     * @return void
     */
    public function move_to_bottom( string $key ) : void {
        $key    = $this->canonical_key( $key );
        $this->move_to_index( $key, count( $this->menu ) );
    }
}