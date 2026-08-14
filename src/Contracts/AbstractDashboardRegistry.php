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

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

abstract class AbstractDashboardRegistry {

    /**
     * Menu registry (ordered).
     *
     * @var array<string, array{
     *     title: string,
     *     slug: string,
     *     handler: class-string<AdminPageInterface>,
     *     icon: string
     * }>
     */
    protected array $menu = [];

    /**
     * Submenu registry (ordered per parent key).
     * 
     * @var array<string, array<int, array{
     *     title: string,
     *     slug: string,
     *     callback: callable
     * }>>
     */
    protected array $submenu = [];

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
     *     handler: class-string<AdminPageInterface>,
     *     icon?: string
     * } $data
     * @param int|null $position Optional zero-based index to insert into.
     *
     * @return static
     * @throws EnvironmentBootstrapException
     */
    public function register( string $key, array $data, ?int $position = null ) : static {
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

        $this->assert_menu_handler_implements_admin_page( $data['handler'], $key );

        if ( ! is_callable( $data['handler']::index_page_handler() ) ) {
            throw new EnvironmentBootstrapException(
                'submenu_error',
                sprintf( 'Menu "%s" callback must be callable.', $key )
            );
        }

        $this->assert_request_is_first_arg( $data['handler']::index_page_handler(), 'menu', $data['slug'] );

        $menu = [
            'title'   => (string) $data['title'],
            'slug'    => trim( (string) $data['slug'], '/' ),
            'handler' => $data['handler'],
            'icon'    => isset( $data['icon'] ) ? (string) $data['icon'] : '',
        ];

        return $this->insert_menu_item( $key, $menu, $position );
    }

    /**
     * Add submenu.
     * 
     * @param string $parent_key
     * @param array{
     *     title: string,
     *     slug: string,
     *     callback: callable
     * } $data
     * 
     * @param int|null $position Optional zero-based index to insert into.
     * @return static
     */
    public function add_submenu( string $parent_key, array $data, ?int $position = null ) : static {
        $parent_key = $this->canonical_key( $parent_key );

        $this->assert_menu_exists( $parent_key );

        foreach ( [ 'title', 'slug', 'callback' ] as $field ) {
            if ( empty( $data[ $field ] ) ) {
                throw new EnvironmentBootstrapException(
                    'submenu_error',
                    sprintf( 'A submenu for "%s" is missing a required field "%s".', $parent_key, $field )
                );
            }
        }

        if ( ! is_callable( $data['callback'] ) ) {
            throw new EnvironmentBootstrapException(
                'submenu_error',
                sprintf( 'Submenu "%s" callback must be callable.', $data['slug'] )
            );
        }

        $this->assert_request_is_first_arg( $data['callback'], 'submenu', "{$parent_key} -> {$data['slug']}" );

        $submenu = [
            'title'    => (string) $data['title'],
            'slug'     => trim( (string) $data['slug'], '/' ),
            'callback' => $data['callback'],
        ];

        return $this->insert_submenu( $parent_key, $submenu, $position );
    }

    /*
    |------------
    | INSERTION
    |------------
    */

    /**
     * Insert a menu item into the registry at a given zero-based position.
     *
     * @param string   $key
     * @param array    $menu
     * @param int|null $position Zero-based index.
     * @return static
     */
    protected function insert_menu_item( string $key, array $menu, ?int $position ) : static {
        if ( null === $position || $position >= count( $this->menu ) ) {
            $this->menu[ $key ] = $menu;
            return $this;
        }

        $position   = (int) max( 0, $position );
        $before     = array_slice( $this->menu, 0, $position, true );
        $after      = array_slice( $this->menu, $position, null, true );
        $this->menu = $before + [ $key => $menu ] + $after;

        return $this;
    }

    /**
     * Insert a submenu at a given zero-based position.
     * 
     * @param string $parent_key
     * @param array{title: string, slug: string, callback: callable} $submenu
     * @param int|null $position Zero-based index.
     * @return static
     */
    protected function insert_submenu( string $parent_key, array $submenu, ?int $position ) : static {
        $all_subm = $this->submenu[$parent_key] ?? [];

        if ( null === $position || empty( $all_subm ) || $position >= count( $all_subm ) ) {
            $this->submenu[$parent_key][] = $submenu;
            return $this;
        }

        $position   = (int) max( 0, $position );
        $before     = array_slice( $all_subm, 0, $position );
        $after      = array_slice( $all_subm, $position );

        $this->submenu[$parent_key] = array_merge( $before, [$submenu], $after );
        
        return $this;
    }

    /*
    |-----------
    | RETRIEVAL
    |-----------
    */

    /**
     * Get all menu items.
     *
     * @return array<string, array{title: string, slug: string, handler: class-string<AdminPageInterface>, icon: string}>
     */
    public function all() : array {
        $this->boot();
        return $this->menu;
    }

    /**
     * Get a single menu item.
     *
     * @param string $key
     * @return array{title: string, slug: string, handler: class-string<AdminPageInterface>, icon: string}|null
     */
    public function get( string $key ) : ?array {
        $this->boot();
        return $this->menu[ $this->canonical_key( $key ) ] ?? null;
    }

    /**
     * Get all submenus.
     * 
     * @return array<string, array<int, array{title: string, slug: string, callback: callable}>>
     */
    public function get_submenus() : array {
        $this->boot();
        return $this->submenu;
    }

    /**
     * Get the submenu items associated with a given menu key.
     *
     * @param string $parent_key
     * @return array<int, array{title: string, slug: string, callback: callable}>|null
     */
    public function get_submenu( string $parent_key ) : ?array {
        $this->boot();
        $key = $this->canonical_key( $parent_key );

        return $this->submenu[$key] ?? null;
    }

    /**
     * Get the callback for a menu item.
     *
     * @param string $key
     * @return callable|null
     */
    public function get_menu_callback( string $key ) : ?callable {
        $this->boot();

        $key = $this->canonical_key( $key );

        if ( ! isset( $this->menu[ $key ] ) ) {
            return null;
        }

        $handler = $this->menu[ $key ]['handler'];

        return $handler::index_page_handler();
    }


    /**
     * Get the callback for a submenu item.
     *
     * @param string $parent_key
     * @param string $slug
     * @return callable|null
     */
    public function get_submenu_callback( string $parent_key, string $slug ) : ?callable {
        $this->boot();

        $parent_key = $this->canonical_key( $parent_key );
        $slug       = trim( $slug, '/' );

        if ( empty( $this->submenu[ $parent_key ] ) ) {
            return null;
        }

        foreach ( $this->submenu[ $parent_key ] as $submenu ) {
            if ( $submenu['slug'] === $slug ) {
                return $submenu['callback'];
            }
        }

        return null;
    }

    /**
     * Get all registered menu slugs (ordered).
     *
     * @return string[]
     */
    public function slugs() : array {
        $this->boot();

        return array_values(
            array_map(
                static fn( $item ) : string => $item['slug'],
                $this->menu
            )
        );
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
        unset( $this->submenu[ $key ] );
        return true;
    }

    /**
     * Clears the submenu of a menu.
     *
     * @param string $parent_key
     * @return bool
     */
    public function clear_submenu( string $parent_key ) : bool {
        $this->boot();
        $key = $this->canonical_key( $parent_key );

        if ( ! $this->has( $key ) ) {
            return false;
        }

        unset( $this->submenu[$key] );
        return true;
    }

    /**
     * Remove a submenu item using its slug.
     * 
     * @param string $parent_key
     * @param string $slug
     * @return bool
     */
    public function remove_submenu( string $parent_key, string $slug ) : bool {
        $this->boot();
        $key = $this->canonical_key( $parent_key );

        if ( ! $this->has( $key ) || empty( $this->submenu[$key] ) ) {
            return false;
        }

        foreach ( $this->submenu[$key] as $index => $data ) {
            if ( $slug === $data['slug'] ) {
                unset( $this->submenu[$key][$index] );
                $this->submenu[$key] = array_values( $this->submenu[$key] );
                return true;
            }
        }

        return false;
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
     * Determine whether a key represents the root menu item.
     *
     * @param string $key.
     * @return bool
     */
    abstract public function is_root_menu( string $key ) : bool;

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

    /**
     * Ensure a callback accepts the request object as first argument.
     * 
     * @param callable $callback
     * @param string   $type
     * @param string   $desc
     * @throws EnvironmentBootstrapException
     */
    protected function assert_request_is_first_arg( callable $callback, string $type = 'menu', string $desc = '' ) : void {
        if ( is_array( $callback ) ) {
            $reflection = new \ReflectionMethod( $callback[0], $callback[1] );
        } elseif ( is_object( $callback ) && ! $callback instanceof \Closure ) {
            $reflection = new \ReflectionMethod( $callback, '__invoke' );
        } else {
            // Handles Closures, function name strings, and "Class::method" strings
            $reflection = new \ReflectionFunction( $callback );
        }

        $params = $reflection->getParameters();

        if ( empty( $params ) ) {
            return;
        }

        $first_param_type = $params[0]->getType();

        // Handle Named Types (single types)
        if ( $first_param_type instanceof \ReflectionNamedType ) {
            if ( $first_param_type->getName() === Request::class ) {
                return;
            }
        }

        // Handle Union Types (PHP 8.0+) or Intersection Types (PHP 8.1+)
        if ( $first_param_type instanceof \ReflectionUnionType || $first_param_type instanceof \ReflectionIntersectionType ) {
            foreach ( $first_param_type->getTypes() as $named_type ) {
                if ( $named_type->__toString() === Request::class ) {
                    return;
                }
            }
        }

        throw new EnvironmentBootstrapException(
            'invalid_menu_callback',
            sprintf( '%s callback for "%s" must accept %s as first argument.', ucfirst( $type ), $desc, Request::class )
        );
    }

    /**
     * Ensure a handler class implements AdminPageInterface.
     * 
     * @param string $handler
     * @param string $key
     * @throws EnvironmentBootstrapException
     */
    protected function assert_menu_handler_implements_admin_page( string $handler, string $key ) : void {
        if ( is_subclass_of( $handler, AdminPageInterface::class ) ) {
            return;
        }

        throw new EnvironmentBootstrapException(
            'menu_error',
            sprintf( 'Menu "%s" handler must implement %s.', $key, AdminPageInterface::class )
        );
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
     * @return static
     */
    public function set_title( string $key, string $title ) : static {
        $key = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        if ( '' === trim( $title ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu "%s" title cannot be empty.', $key )
            );
        }

        $this->menu[ $key ]['title'] = $title;

        return $this;
    }

    /**
     * Update menu slug.
     *
     * @param string $key
     * @param string $slug
     * @return static
     */
    public function set_slug( string $key, string $slug ) : static {
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

        return $this;
    }

    /**
     * Update menu handler.
     *
     * @param string $key
     * @param class-string<AdminPageInterface> $handler
     * @return static
     */
    public function set_handler( string $key, string $handler ) : static {
        $key = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        $this->assert_menu_handler_implements_admin_page( $handler, $key );
        $this->menu[ $key ]['handler'] = $handler;

        return $this;
    }

    /**
     * Update menu icon.
     *
     * @param string $key
     * @param string $icon
     * @return static
     */
    public function set_icon( string $key, string $icon ) : static {
        $key = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

        $this->menu[ $key ]['icon'] = $icon;

        return $this;
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

        return (int) $index;
    }

    /**
     * Reinsert a menu item at a given index.
     *
     * @param string $key
     * @param int    $new_index
     * @return static
     */
    protected function move_to_index( string $key, int $new_index ) : static {
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

        return $this;
    }

    public function move_up( string $key ) : static {
        $key   = $this->canonical_key( $key );
        $index = $this->get_index( $key );
        if ( $index === 0 ) return $this;
        $this->move_to_index( $key, $index - 1 );

        return $this;
    }

    public function move_down( string $key ) : static {
        $key   = $this->canonical_key( $key );
        $index = $this->get_index( $key );
        if ( $index === count( $this->menu ) - 1 ) return $this;

        $this->move_to_index( $key, $index + 1 );

        return $this;
    }

    public function move_to( string $key, int $position ) : static {
        $this->move_to_index( $this->canonical_key( $key ), $position );

        return $this;
    }

    public function move_after( string $key, string $target ) : static {
        $key = $this->canonical_key( $key );
        $this->move_to_index( $key, $this->get_index( $this->canonical_key( $target ) ) + 1 );

        return $this;
    }

    public function move_before( string $key, string $target ) : static {
        $key = $this->canonical_key( $key );
        $this->move_to_index( $key, $this->get_index( $this->canonical_key( $target ) ) );

        return $this;
    }

    public function move_to_top( string $key ) : static {
        $this->move_to_index( $this->canonical_key( $key ), 0 );

        return $this;
    }

    public function move_to_bottom( string $key ) : static {
        $key = $this->canonical_key( $key );
        $this->move_to_index( $key, count( $this->menu ) );

        return $this;
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
    protected function canonical_key( string $key ) : string {
        return str_replace( '-', '_', strtolower( $key ) );
    }
}