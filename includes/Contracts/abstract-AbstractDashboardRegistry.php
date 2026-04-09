<?php
/**
 * Abstract Dashboard Registry
 *
 * Framework-agnostic ordered menu registry for PHP web applications.
 * Extend this class to provide application-specific default menu items.
 *
 * @package SmartLicenseServer\Contracts
 */

namespace SmartLicenseServer\Contracts;

use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

abstract class AbstractDashboardRegistry {

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
        $key = $this->canonical_key( $key );

        if ( '' === $key ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                'Menu key cannot be empty.'
            );
        }

        if ( isset( $this->menu[ $key ] ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" already exists.', $key )
            );
        }

        foreach ( [ 'title', 'slug', 'handler' ] as $field ) {
            if ( empty( $data[ $field ] ) ) {
                throw new EnvironmentBootstrapException(
                    'menu_error',
                    sprintf( 'Menu "%s" missing required field "%s".', $key, $field )
                );
            }
        }

        if ( ! is_callable( $data['handler'] ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" handler must be callable.', $key )
            );
        }

        $menu = [
            'title'   => (string) $data['title'],
            'slug'    => trim( (string) $data['slug'], '/' ),
            'handler' => $data['handler'],
            'icon'    => isset( $data['icon'] ) ? (string) $data['icon'] : '',
        ];

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
     * @param string   $key
     * @param array    $menu
     * @param int|null $position
     */
    protected function insert_menu_item( string $key, array $menu, ?int $position ) : void {
        if ( $position !== null ) {
            $position = max( 0, $position - 1 );
        }

        if ( null === $position || $position >= count( $this->menu ) ) {
            $this->menu[ $key ] = $menu;
            return;
        }

        $before     = array_slice( $this->menu, 0, $position, true );
        $after      = array_slice( $this->menu, $position, null, true );
        $this->menu = $before + [ $key => $menu ] + $after;
    }

    /*
    |-----------
    | RETRIEVAL
    |-----------
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
        return $this->menu[ $this->canonical_key( $key ) ] ?? null;
    }

    /**
     * Check if a menu exists.
     *
     * @param string $key
     * @return bool
     */
    public function has( string $key ) : bool {
        $this->boot();
        return isset( $this->menu[ $this->canonical_key( $key ) ] );
    }

    /**
     * Remove a menu item.
     *
     * @param string $key
     * @return bool
     */
    public function remove( string $key ) : bool {
        $this->boot();
        $key = $this->canonical_key( $key );

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
     * Initialize default menu items.
     *
     * Implement this in your concrete subclass to register application-specific
     * default menu items. Called once on first access.
     *
     * @return void
     */
    abstract protected function boot() : void;

    /**
     * Ensure a menu exists before mutation.
     *
     * @param string $key
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

    /*
    |---------
    | SETTERS
    |---------
    */

    /**
     * Update menu title.
     *
     * @param string $key
     * @param string $title
     */
    public function set_title( string $key, string $title ) : void {
        $key = $this->canonical_key( $key );
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
     */
    public function set_slug( string $key, string $slug ) : void {
        $key  = $this->canonical_key( $key );
        $slug = trim( $slug, '/' );
        $this->assert_menu_exists( $key );

        if ( '' === $slug && ! $this->is_root_menu( $key ) ) {
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
     */
    public function set_handler( string $key, callable $handler ) : void {
        $key = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        $this->menu[ $key ]['handler'] = $handler;
    }

    /**
     * Update menu icon.
     *
     * @param string $key
     * @param string $icon
     */
    public function set_icon( string $key, string $icon ) : void {
        $key = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        $this->menu[ $key ]['icon'] = $icon;
    }

    /*
    |----------
    | ORDERING
    |----------
    */

    /**
     * Get the numeric index of a menu item.
     *
     * @param string $key
     * @return int
     */
    protected function get_index( string $key ) : int {
        $this->boot();
        $index = array_search( $key, array_keys( $this->menu ), true );

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
     */
    protected function move_to_index( string $key, int $new_index ) : void {
        $index = array_search( $key, array_keys( $this->menu ), true );

        if ( false === $index ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" does not exist.', $key )
            );
        }

        $item = $this->menu[ $key ];
        unset( $this->menu[ $key ] );

        $new_index  = max( 0, min( $new_index, count( $this->menu ) ) );
        $before     = array_slice( $this->menu, 0, $new_index, true );
        $after      = array_slice( $this->menu, $new_index, null, true );
        $this->menu = $before + [ $key => $item ] + $after;
    }

    public function move_up( string $key ) : void {
        $key   = $this->canonical_key( $key );
        $index = $this->get_index( $key );
        if ( $index === 0 ) return;
        $this->move_to_index( $key, $index - 1 );
    }

    public function move_down( string $key ) : void {
        $key   = $this->canonical_key( $key );
        $index = $this->get_index( $key );
        if ( $index === count( $this->menu ) - 1 ) return;
        $this->move_to_index( $key, $index + 1 );
    }

    public function move_to( string $key, int $position ) : void {
        $this->move_to_index( $this->canonical_key( $key ), $position );
    }

    public function move_after( string $key, string $target ) : void {
        $key = $this->canonical_key( $key );
        $this->move_to_index( $key, $this->get_index( $this->canonical_key( $target ) ) + 1 );
    }

    public function move_before( string $key, string $target ) : void {
        $key = $this->canonical_key( $key );
        $this->move_to_index( $key, $this->get_index( $this->canonical_key( $target ) ) );
    }

    public function move_to_top( string $key ) : void {
        $this->move_to_index( $this->canonical_key( $key ), 0 );
    }

    public function move_to_bottom( string $key ) : void {
        $key = $this->canonical_key( $key );
        $this->move_to_index( $key, count( $this->menu ) );
    }

    /*
    |----------------
    | PRIVATE HELPERS
    |----------------
    */

    /**
     * Normalize a menu key — hyphens to underscores, lowercase.
     *
     * @param string $key
     * @return string
     */
    private function canonical_key( string $key ) : string {
        return str_replace( '-', '_', $key );
    }

    /**
     * Determine whether a key represents the root/overview menu item.
     *
     * Override in subclasses if your root menu key differs from 'overview'.
     *
     * @param string $key Already canonicalized.
     * @return bool
     */
    protected function is_root_menu( string $key ) : bool {
        return 'overview' === $key;
    }
}