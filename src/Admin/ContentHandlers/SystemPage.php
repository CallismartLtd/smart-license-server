<?php
/**
 * The admin system page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Background\Schedule\Scheduler;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The admin system operations page handler.
 */
class SystemPage implements AdminPageInterface {

    public function __construct(
        protected Scheduler $scheduler,
        protected TemplateLocator $locator,
        protected URLManager $urlmanager
    ) {}

    public function schedules_page( Request $request ) : void {
        $tasks          = $this->scheduler->get_tasks_with_state();
        $page_handler   = $this;

        $vars   = \compact( 'tasks', 'page_handler', 'request' );
        $this->locator->render( 'admin.contents.system.index', $vars );        
    }

    public function queues_page( Request $request ) {
        $jobs           = [];
        $page_handler   = $this;
        $vars   = \compact( 'jobs', 'page_handler', 'request' );
        $this->locator->render( 'admin.contents.system.index', $vars ); 
    }

    public function get_menu_key() : string {
        return 'system';
    }

    public function get_menu_data(): array {
        return [
            'title'         => 'System',
            'icon'          => 'ti ti-tool',
            'handler'       => $this,
            'slug'          => 'system',
            'visibility'    => true
        ];
    }

    public function get_submenu(): array {
        return [
            [
                'title'         => 'Schedules',
                'slug'          => 'schedules',
                'callback'      => [$this, 'schedules_page'],
                'visibility'    => true
            ],
            [
                'title'         => 'Queue Monitor',
                'slug'          => 'queues',
                'callback'      => [$this, 'queues_page'],
                'visibility'    => true
            ],
            [
                'title'         => 'System Diagnostics',
                'slug'          => 'diagnostics',
                'callback'      => [$this, 'schedules_page'],
                'visibility'    => true
            ],
            [
                'title'         => 'Site Health',
                'slug'          => 'health',
                'callback'      => [$this, 'schedules_page'],
                'visibility'    => true
            ],
        ];
    }

    public function index_page_handler(): callable {
        return [$this, 'schedules_page'];
    }

    /**
     * Get menu args.
     *
     * @return array<string, mixed>
     */
    public function get_top_menu_args( Request $request ): array {
        $tab    = $request->get( 'tab' ) ?? $request->route_param( 'tab' );
        $title  = 'Schedules';

        foreach ( $this->get_submenu() as $sub ) {
            if ( $tab === $sub['slug'] ) {
                $title  = $sub['title'];
            }
        }

        return [
            'breadcrumbs' => [
                // [
                //     'label' => 'General Settings',
                //     'url'   => $this->urlmanager->admin_options_url(),
                //     'icon'  => 'ti ti-home',
                // ],
                [
                    'label' => $title,
                ],
            ],
            'actions' => [],
        ];
    }
}