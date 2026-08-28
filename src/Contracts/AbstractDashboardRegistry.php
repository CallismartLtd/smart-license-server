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
     *     handler: AdminPageInterface,
     *     icon: string,
     *     visibility: bool|callable():bool
     * }>
     */
    protected array $menu = [];

    /**
     * Submenu registry (ordered per parent key).
     * 
     * @var array<string, array<int, array{
     *     title: string,
     *     slug: string,
     *     callback: callable,
     *     visibility: bool|callable():bool
     * }>>
     */
    protected array $submenu = [];

    /**
	 * Top menu registry (ordered).
	 *
	 * @var array<string, array{
	 *     title: string,
	 *     icon: string,
	 *     type: 'button'|'link',
	 *     href: string,
	 *     callback: ?callable,
	 *     attributes: array<string, string>,
	 *     visibility: bool|callable():bool
	 * }>
	 */
	protected array $top_menu = [];

    /**
	 * Allowed top menu item types.
	 *
	 * @var string[]
	 */
	protected const TOP_MENU_TYPES = [ 'button', 'link' ];

	/**
	 * Attribute names reserved for structured top menu fields — cannot be
	 * set through the free-form `attributes` array.
	 *
	 * @var string[]
	 */
	protected const TOP_MENU_RESERVED_ATTRIBUTES = [ 'href', 'type' ];

    /**
     * Boot flag.
     *
     * @var bool
     */
    protected bool $booted = false;

    /*
    |---------------------
    | MAIN MENU APIs
    |---------------------
    */

    /**
     * Register a single menu item.
     *
     * @param string $key Unique menu identifier.
     * @param array{
     *     title: string,
     *     slug: string,
     *     handler: AdminPageInterface,
     *     visibility: bool|callable():bool,
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

        if ( ! isset( $data['visibility'] ) || ( ! is_bool( $data['visibility'] ) && ! is_callable( $data['visibility'] ) ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Menu %s visibility must be a boolean or callable type.', $key )
            );
        }

        $this->assert_menu_handler_implements_admin_page( $data['handler'], $key );

        if ( ! is_callable( $data['handler']->index_page_handler() ) ) {
            throw new EnvironmentBootstrapException(
                'submenu_error',
                sprintf( 'Menu "%s" callback must be callable.', $key )
            );
        }

        $this->assert_request_is_first_arg( $data['handler']->index_page_handler(), 'menu', $data['slug'] );

        $menu = [
            'title'         => (string) $data['title'],
            'slug'          => trim( (string) $data['slug'], '/' ),
            'handler'       => $data['handler'],
            'icon'          => isset( $data['icon'] ) ? (string) $data['icon'] : '',
            'visibility'    => $data['visibility']
        ];

        return $this->insert_menu_item( $key, $menu, $position );
    }

    /**
     * Get all menu items.
     *
     * @return array<string,
     *      array{title: string,
     *      slug: string,
     *      handler: class-string<AdminPageInterface>,
     *      icon: string,
     *      visibility: bool|callable():bool
     * }>
     */
    public function all() : array {
        $this->boot();
        return $this->menu;
    }

    /**
     * Get a single menu item.
     *
     * @param string $key
     * @return array{
     *      title: string,
     *      slug: string,
     *      handler: AdminPageInterface,
     *      icon: string,
     *      visibility: bool|callable():bool
     * }|null
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

        return $handler->index_page_handler();
    }

    /**
     * Remove a menu item.
     *
     * @param string $key
     * @param bool $remove_submenu
     * @return bool
     */
    public function remove( string $key, bool $remove_submenu = true ) : bool {
        $this->boot();
        $key = $this->canonical_key( $key );

        if ( ! $this->has( $key ) ) {
            return false;
        }

        unset( $this->menu[ $key ] );
        
        if ( $remove_submenu ) {
            unset( $this->submenu[ $key ] );
        }

        return true;
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
     * @param AdminPageInterface $handler
     * @return static
     */
    public function set_handler( string $key, AdminPageInterface $handler ) : static {
        $key = $this->canonical_key( $key );
        $this->assert_menu_exists( $key );

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
    |---------------------
    | MAIN MENU ORDERING
    |---------------------
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

    /*
    |------------------
    | SUBMENU APIs
    |------------------
    */

    /**
     * Add submenu.
     * 
     * A parent menu must exist before add a submenu.
     * 
     * @param string $parent_key
     * @param array{
     *     title: string,
     *     slug: string,
     *     callback: callable,
     *     visibility: bool|callable():bool
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

        if ( ! isset( $data['visibility'] ) || ( ! is_bool( $data['visibility'] ) && ! is_callable( $data['visibility'] ) ) ) {
            throw new EnvironmentBootstrapException(
                'menu_error',
                sprintf( 'Submenu %s visibility must be a boolean or callable type.', "{$parent_key} -> {$data['slug']}" )
            );
        }

        $this->assert_request_is_first_arg( $data['callback'], 'submenu', "{$parent_key} -> {$data['slug']}" );

        $submenu = [
            'title'         => (string) $data['title'],
            'slug'          => trim( (string) $data['slug'], '/' ),
            'callback'      => $data['callback'],
            'visibility'    => $data['visibility']
        ];

        return $this->insert_submenu( $parent_key, $submenu, $position );
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

    /**
     * Get all submenus.
     * 
     * @return array<string,array<int, array{
     *      title: string,
     *      slug: string,
     *      callback: callable,
     *      visibility: bool|callable():bool
     * }>>
     */
    public function get_submenus() : array {
        $this->boot();
        return $this->submenu;
    }

    /**
     * Get the submenu items associated with a given menu key.
     *
     * @param string $parent_key
     * @return array<int, array{
     *      title: string,
     *      slug: string,
     *      callback: callable,
     *      visibility: bool|callable():bool
     * }>|null
     */
    public function get_submenu( string $parent_key ) : ?array {
        $this->boot();
        $key = $this->canonical_key( $parent_key );
        return $this->submenu[$key] ?? null;
    }

    /**
     * Get a single submenu item under a parent menu by its slug.
     *
     * @param string $parent_key
     * @param string $slug
     * @return array{
     *      title: string,
     *      slug: string,
     *      callback: callable,
     *      visibility: bool|callable():bool
     * }|null
     */
    public function get_submenu_by_slug( string $parent_key, string $slug ) : ?array {
        $this->boot();

        $parent_key = $this->canonical_key( $parent_key );
        $slug       = trim( $slug, '/' );

        if ( empty( $this->submenu[ $parent_key ] ) ) {
            return null;
        }

        foreach ( $this->submenu[ $parent_key ] as $submenu ) {
            if ( $submenu['slug'] === $slug ) {
                return $submenu;
            }
        }

        return null;
    }

    /**
     * Get the callback for a submenu item.
     *
     * @param string $parent_key
     * @param string $slug
     * @return callable|null
     */
    public function get_submenu_callback( string $parent_key, string $slug ) : ?callable {
        return $this->get_submenu_by_slug( $parent_key, $slug )['callback'] ?? null;
    }

    /**
     * Check if a menu has at least one submenu.
     * 
     * @param string $parent_key
     * @return bool
     */
    public function has_submenu( string $parent_key ) : bool {
        $this->boot();

        $parent_key    = $this->canonical_key( $parent_key );

        return array_key_exists( $parent_key, $this->submenu );
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
    |-----------------
    | TOP MENU APIs
    |-----------------
    */

	/**
	 * Add a top menu item (button, link, or icon-only item).
	 *
	 * @param string $key Unique item identifier.
	 * @param array{
	 *     title?: string,
	 *     icon?: string,
     *     icons?: array<int, array{ class: string, attributes?: array<string,string> }>,
	 *     type?: 'button'|'link',
	 *     href?: string,
	 *     callback?: callable,
	 *     attributes?: array<string, string>,
	 *     visibility: bool|callable():bool
	 * } $data
	 * @param int|null $position Optional zero-based index to insert into.
	 *
	 * @return static
	 * @throws EnvironmentBootstrapException
	 */
	public function add_top_menu( string $key, array $data, ?int $position = null ) : static {
		$this->boot();
		$key = $this->canonical_key( $key );

		if ( '' === $key ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				'Top menu key cannot be empty.'
			);
		}

		if ( isset( $this->top_menu[ $key ] ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" already exists.', $key )
			);
		}

        if ( empty( $data['title'] ) && empty( $data['icon'] ) && empty( $data['icons'] ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" must have a title, an icon, or icons.', $key )
			);
		}

		if ( ! isset( $data['visibility'] ) || ( ! is_bool( $data['visibility'] ) && ! is_callable( $data['visibility'] ) ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" visibility must be a boolean or callable type.', $key )
			);
		}

		$type = $data['type'] ?? 'button';

		if ( ! in_array( $type, self::TOP_MENU_TYPES, true ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" type must be one of: %s.', $key, implode( ', ', self::TOP_MENU_TYPES ) )
			);
		}

		if ( 'link' === $type && empty( $data['href'] ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" is of type "link" and requires an "href".', $key )
			);
		}

		if ( isset( $data['callback'] ) ) {
			if ( ! is_callable( $data['callback'] ) ) {
				throw new EnvironmentBootstrapException(
					'topmenu_error',
					sprintf( 'Top menu "%s" callback must be callable.', $key )
				);
			}

			$this->assert_request_is_first_arg( $data['callback'], 'topmenu', $key );
		}

        $item = [
			'title'         => isset( $data['title'] ) ? (string) $data['title'] : '',
			'icon'          => isset( $data['icon'] ) ? (string) $data['icon'] : '',
			'icons'         => $this->normalize_top_menu_icons( $data['icons'] ?? [], $key ),
			'type'          => $type,
			'href'          => isset( $data['href'] ) ? (string) $data['href'] : '',
			'callback'      => $data['callback'] ?? null,
			'attributes'    => $this->normalize_top_menu_attributes( $data['attributes'] ?? [], $key ),
			'visibility'    => $data['visibility'],
		];

		return $this->insert_top_menu( $key, $item, $position );
	}

	/**
	 * Get all top menu items.
	 *
	 * @return array<string, array>
	 */
	public function get_top_menus() : array {
		$this->boot();
		return $this->top_menu;
	}

	/**
	 * Get a single top menu item.
	 *
	 * @param string $key
	 * @return array|null
	 */
	public function get_top_menu( string $key ) : ?array {
		$this->boot();
		return $this->top_menu[ $this->canonical_key( $key ) ] ?? null;
	}

	/**
	 * Get the callback for a top menu item, if any.
	 *
	 * @param string $key
	 * @return callable|null
	 */
	public function get_top_menu_callback( string $key ) : ?callable {
		$this->boot();
		return $this->top_menu[ $this->canonical_key( $key ) ]['callback'] ?? null;
	}

	/**
	 * Check if a top menu item exists.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function has_top_menu( string $key ) : bool {
		$this->boot();
		return isset( $this->top_menu[ $this->canonical_key( $key ) ] );
	}

	/**
	 * Remove a top menu item.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function remove_top_menu( string $key ) : bool {
		$this->boot();
		$key = $this->canonical_key( $key );

		if ( ! $this->has_top_menu( $key ) ) {
			return false;
		}

		unset( $this->top_menu[ $key ] );
		return true;
	}

	public function set_top_menu_title( string $key, string $title ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		if ( '' === trim( $title ) && '' === $this->top_menu[ $key ]['icon'] ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" must have a title, an icon, or both.', $key )
			);
		}

		$this->top_menu[ $key ]['title'] = $title;

		return $this;
	}

	public function set_top_menu_icon( string $key, string $icon ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		if ( '' === trim( $icon ) && '' === $this->top_menu[ $key ]['title'] ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" must have a title, an icon, or both.', $key )
			);
		}

		$this->top_menu[ $key ]['icon'] = $icon;

		return $this;
	}

	public function set_top_menu_icons( string $key, array $icons ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		$normalized = $this->normalize_top_menu_icons( $icons, $key );

		if ( empty( $normalized ) && '' === $this->top_menu[ $key ]['title'] && '' === $this->top_menu[ $key ]['icon'] ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" must have a title, an icon, or icons.', $key )
			);
		}

		$this->top_menu[ $key ]['icons'] = $normalized;

		return $this;
	}
    
	/**
	 * @param string      $key
	 * @param string      $type
	 * @param string|null $href Required when $type is "link" and no href is set yet.
	 */
	public function set_top_menu_type( string $key, string $type, ?string $href = null ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		if ( ! in_array( $type, self::TOP_MENU_TYPES, true ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" type must be one of: %s.', $key, implode( ', ', self::TOP_MENU_TYPES ) )
			);
		}

		if ( null !== $href ) {
			$this->top_menu[ $key ]['href'] = $href;
		}

		if ( 'link' === $type && empty( $this->top_menu[ $key ]['href'] ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" is being set to type "link" and requires an "href".', $key )
			);
		}

		$this->top_menu[ $key ]['type'] = $type;

		return $this;
	}

	public function set_top_menu_href( string $key, string $href ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		$this->top_menu[ $key ]['href'] = $href;

		return $this;
	}

	public function set_top_menu_callback( string $key, callable $callback ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		$this->assert_request_is_first_arg( $callback, 'topmenu', $key );
		$this->top_menu[ $key ]['callback'] = $callback;

		return $this;
	}

	public function set_top_menu_visibility( string $key, bool|callable $visibility ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		$this->top_menu[ $key ]['visibility'] = $visibility;

		return $this;
	}

	public function set_top_menu_attributes( string $key, array $attributes ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		$this->top_menu[ $key ]['attributes'] = $this->normalize_top_menu_attributes( $attributes, $key );

		return $this;
	}

	public function add_top_menu_attributes( string $key, array $attributes ) : static {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		$this->top_menu[ $key ]['attributes'] = array_merge(
			$this->top_menu[ $key ]['attributes'],
			$this->normalize_top_menu_attributes( $attributes, $key )
		);

		return $this;
	}

	public function set_top_menu_attribute( string $key, string $name, string $value ) : static {
		return $this->add_top_menu_attributes( $key, [ $name => $value ] );
	}

	public function remove_top_menu_attribute( string $key, string $name ) : bool {
		$key = $this->canonical_key( $key );
		$this->assert_top_menu_exists( $key );

		if ( ! array_key_exists( $name, $this->top_menu[ $key ]['attributes'] ) ) {
			return false;
		}

		unset( $this->top_menu[ $key ]['attributes'][ $name ] );
		return true;
	}

	protected function get_top_menu_index( string $key ) : int {
		$this->boot();
		$index = array_search( $key, array_keys( $this->top_menu ), true );

		if ( false === $index ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" does not exist.', $key )
			);
		}

		return (int) $index;
	}

	protected function move_top_menu_to_index( string $key, int $new_index ) : static {
		$index = array_search( $key, array_keys( $this->top_menu ), true );

		if ( false === $index ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" does not exist.', $key )
			);
		}

		$item = $this->top_menu[ $key ];
		unset( $this->top_menu[ $key ] );

		$new_index      = max( 0, min( $new_index, count( $this->top_menu ) ) );
		$before         = array_slice( $this->top_menu, 0, $new_index, true );
		$after          = array_slice( $this->top_menu, $new_index, null, true );
		$this->top_menu = $before + [ $key => $item ] + $after;

		return $this;
	}

	public function move_top_menu_up( string $key ) : static {
		$key   = $this->canonical_key( $key );
		$index = $this->get_top_menu_index( $key );
		if ( $index === 0 ) return $this;
		$this->move_top_menu_to_index( $key, $index - 1 );

		return $this;
	}

	public function move_top_menu_down( string $key ) : static {
		$key   = $this->canonical_key( $key );
		$index = $this->get_top_menu_index( $key );
		if ( $index === count( $this->top_menu ) - 1 ) return $this;

		$this->move_top_menu_to_index( $key, $index + 1 );

		return $this;
	}

	public function move_top_menu_to( string $key, int $position ) : static {
		$this->move_top_menu_to_index( $this->canonical_key( $key ), $position );

		return $this;
	}

	public function move_top_menu_after( string $key, string $target ) : static {
		$key = $this->canonical_key( $key );
		$this->move_top_menu_to_index( $key, $this->get_top_menu_index( $this->canonical_key( $target ) ) + 1 );

		return $this;
	}

	public function move_top_menu_before( string $key, string $target ) : static {
		$key = $this->canonical_key( $key );
		$this->move_top_menu_to_index( $key, $this->get_top_menu_index( $this->canonical_key( $target ) ) );

		return $this;
	}

	public function move_top_menu_to_top( string $key ) : static {
		$this->move_top_menu_to_index( $this->canonical_key( $key ), 0 );

		return $this;
	}

	public function move_top_menu_to_bottom( string $key ) : static {
		$key = $this->canonical_key( $key );
		$this->move_top_menu_to_index( $key, count( $this->top_menu ) );

		return $this;
	}
    
    /**
	 * Insert a top menu item into the registry at a given zero-based position.
	 *
	 * @param string   $key
	 * @param array    $item
	 * @param int|null $position Zero-based index.
	 * @return static
	 */
	protected function insert_top_menu( string $key, array $item, ?int $position ) : static {
		if ( null === $position || $position >= count( $this->top_menu ) ) {
			$this->top_menu[ $key ] = $item;
			return $this;
		}

		$position       = (int) max( 0, $position );
		$before         = array_slice( $this->top_menu, 0, $position, true );
		$after          = array_slice( $this->top_menu, $position, null, true );
		$this->top_menu = $before + [ $key => $item ] + $after;

		return $this;
	}

    /*
    |--------------------
    | ABSTRACT METHODS
    |--------------------
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

    /*
    |---------------
    | ASSERTIONS
    |---------------
    */

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
     * @param object $handler
     * @param string $key
     * @throws EnvironmentBootstrapException
     */
    protected function assert_menu_handler_implements_admin_page( object $handler, string $key ) : void {
        if ( is_subclass_of( $handler, AdminPageInterface::class ) ) {
            return;
        }

        throw new EnvironmentBootstrapException(
            'menu_error',
            sprintf( 'Menu "%s" handler must implement %s.', $key, AdminPageInterface::class )
        );
    }

	/**
	 * Ensure a top menu item exists before mutation.
	 *
	 * @param string $key
	 * @throws EnvironmentBootstrapException
	 */
	protected function assert_top_menu_exists( string $key ) : void {
		$this->boot();

		if ( ! isset( $this->top_menu[ $key ] ) ) {
			throw new EnvironmentBootstrapException(
				'topmenu_error',
				sprintf( 'Top menu "%s" does not exist.', $key )
			);
		}
	}

	/**
	 * Validate and normalize a raw top menu attributes array.
	 *
	 * @param array  $attributes
	 * @param string $key
	 * @return array<string, string>
	 * @throws EnvironmentBootstrapException
	 */
	protected function normalize_top_menu_attributes( array $attributes, string $key ) : array {
		$normalized = [];

		foreach ( $attributes as $name => $value ) {
			if ( ! is_string( $name ) || '' === $name ) {
				throw new EnvironmentBootstrapException(
					'topmenu_error',
					sprintf( 'Top menu "%s" has an invalid attribute name.', $key )
				);
			}

			if ( in_array( strtolower( $name ), self::TOP_MENU_RESERVED_ATTRIBUTES, true ) ) {
				throw new EnvironmentBootstrapException(
					'topmenu_error',
					sprintf( 'Top menu "%s" cannot set reserved attribute "%s" via attributes — use the dedicated field instead.', $key, $name )
				);
			}

			if ( ! is_scalar( $value ) ) {
				throw new EnvironmentBootstrapException(
					'topmenu_error',
					sprintf( 'Top menu "%s" attribute "%s" must be a scalar value.', $key, $name )
				);
			}

			$normalized[ $name ] = (string) $value;
		}

		return $normalized;
	}

	/**
	 * Validate and normalize a raw icons array.
	 *
	 * @param array  $icons
	 * @param string $key
	 * @return array<int, array{ class: string, attributes: array<string, string> }>
	 * @throws EnvironmentBootstrapException
	 */
	protected function normalize_top_menu_icons( array $icons, string $key ) : array {
		$normalized = [];

		foreach ( $icons as $icon ) {
			if ( empty( $icon['class'] ) ) {
				throw new EnvironmentBootstrapException(
					'topmenu_error',
					sprintf( 'Top menu "%s" has an icon missing a "class".', $key )
				);
			}

			$normalized[] = [
				'class'      => (string) $icon['class'],
				'attributes' => $this->normalize_top_menu_attributes( $icon['attributes'] ?? [], $key ),
			];
		}

		return $normalized;
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