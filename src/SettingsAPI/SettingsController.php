<?php
/**
 * The settings controller class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since 0.2.0
 */

declare( strict_types=1 );
namespace SmartLicenseServer\SettingsAPI;

use SmartLicenseServer\Admin\ContentHandlers\OptionsPage;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Email\Templates\EmailTemplateRegistry;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Settings controller class handles request and response for settings API.
 */
class SettingsController {
    use SanitizeAwareTrait, SecurityAwareTrait;

    public function __construct(
        protected Settings $settings,
        protected OptionsPage $options_page,
        Guard $guard

    ) {
        $this->guard    = $guard;
    }

    /**
     * Save general settings
     * 
     * @param Request $request The object.
     * @return Response
     */
    public function save_general_settings( Request $request ) : Response {
        try {
            $this->is_system_admin();
            $collection = Collection::make( $this->options_page->general_settings_fields() );
            $fields     = $collection->map(
                fn( $v ) => $v['input']['name'] ?? ''
            );

            foreach ( $fields as $key ) {
                if ( empty( $key ) ) {
                    continue;
                }

                if ( $request->has( $key ) ) {
                    $value  = $this->sanitize_auto( $request->get( $key ) );
                    if ( $this->settings->set( $key, $value ) ) {
                    }
                }
            }

            return Response::json([
                'success'   => true,
                'data'      => [
                    'message'   => 'Settings updated successfully.'
                ]
            ]);
        } catch ( RequestException $e ) {
            return Response::error( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }

    }

    /**
     * Save routing settings.
     *
     * @param  Request $request
     * @return Response
     */
    public function save_routing_settings( Request $request ): Response {
        try {
            $this->is_system_admin();

            $collection = Collection::make( $this->options_page->get_routing_fields() );
            $fields     = $collection->map(
                fn( $v ) => $v['input']['name'] ?? ''
            );

            foreach ( $fields as $key ) {
                if ( empty( $key ) ) {
                    continue;
                }

                $default    = match( $key ) {
                    'repository_url_prefix'         => $this->settings->get( $key, 'repository' ),
                    'download_url_prefix'           => $this->settings->get( $key, 'downloads' ),
                    'client_dashboard_url_prefix'   => $this->settings->get( $key, 'client-dashboard' ),
                    default                         => ''
                };

                $value  = $this->sanitize_slug( $request->get( $key, $default ) ) ?: $default;
                $this->settings->set( $key, $value );
        
            }

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message' => 'Routes has been updated.',
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Handle email template toggling.
     *
     * @param Request $request
     * @return Response
     */
    public function toggle_email_template( Request $request ): Response {
        try {
            $this->is_system_admin();

            $template_key = $request->get( 'template_key' );

            if ( ! $template_key ) {
                throw new RequestException(
                    'required_param',
                    'Email template key is required.'
                );
            }

            if ( ! EmailTemplateRegistry::has( $template_key ) ) {
                throw new RequestException(
                    'invalid_param',
                    'Email template key is invalid.'
                );
            }

            $preview = EmailTemplateRegistry::preview( $template_key );

            $new_state  = $preview->is_enabled() ? false : true;
            $success    = $preview->is_enabled() ? $preview->disable() : $preview->enable();


            if ( ! $success ) {
                throw new RequestException(
                    'server_error',
                    'Failed to update email template state. Please try again.'
                );
            }

            $label   = EmailTemplateRegistry::entry( $template_key )['label'];
            $message = $new_state
                ? sprintf( '%s email has been enabled.', $label )
                : sprintf( '%s email has been disabled.', $label );

            $response_data  = [
                'success' => true,
                'data'    => [
                    'message'     => $message,
                    'template_key' => $template_key,
                    'is_enabled'  => $new_state,
                ],
            ];

            return ( new Response( 200, [], $response_data ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }
    
    /**
     * Render a live preview from the editor's current block and style state.
     *
     * Called on every debounced change in the editor. Renders the full email
     * HTML via render_from_blocks() without persisting anything — the result
     * is written directly into the editor's preview iframe.
     *
     * Expected POST params:
     *   template_key (string) — registered template key
     *   blocks       (string) — JSON-encoded block array from editor state
     *   styles       (string) — JSON-encoded style token map from editor state
     *
     * @param  Request  $request
     * @return Response JSON — { success: true, data: { html: string } }
     */
    public function preview_email_template( Request $request ): Response {
        try {
            $this->is_system_admin();

            $template_key = $request->get( 'template_key' );
            $blocks_raw   = $request->get_file( 'blocks' )?->get_contents() ?? '';
            $styles_raw   = $request->get_file( 'styles' )?->get_contents() ?? '';

            if ( ! $template_key ) {
                throw new RequestException( 'required_param', 'Template key is required.' );
            }

            if ( ! EmailTemplateRegistry::has( $template_key ) ) {
                throw new RequestException( 'invalid_param', 'Invalid template key.' );
            }

            $blocks = json_decode( $blocks_raw, true );
            $styles = json_decode( $styles_raw, true );

            if ( ! is_array( $blocks ) || ! is_array( $styles ) ) {
                throw new RequestException( 'invalid_param', 'Invalid blocks or styles data.' );
            }

            $html = EmailTemplateRegistry::preview( $template_key )
                ->render_from_blocks( $blocks, $styles );

            return ( new Response( 200 ) )
                ->set_body( [
                    'success' => true,
                    'data'    => [ 'html' => $html ],
                ] )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
                ->set_exception( $e );
        }
    }

    /**
     * Save the editor's current block and style state as a custom template.
     *
     * Renders the full HTML from the submitted block and style data via
     * render_from_blocks(), then persists it via save_custom_template() so
     * subsequent render() calls return the custom version without needing
     * block data at send time.
     *
     * Expected POST params:
     *   template_key (string) — registered template key
     *   blocks       (string) — JSON-encoded block array from editor state
     *   styles       (string) — JSON-encoded style token map from editor state
     *
     * @param  Request  $request
     * @return Response JSON — { success: true, data: { message: string, template_key: string } }
     */
    public function save_email_template( Request $request ): Response {
        try {
            $this->is_system_admin();

            $template_key = $request->get( 'template_key' );
            $blocks_raw   = $request->get_file( 'blocks' )?->get_contents() ?? '';
            $styles_raw   = $request->get_file( 'styles' )?->get_contents() ?? '';

            if ( ! $template_key ) {
                throw new RequestException( 'required_param', 'Template key is required.' );
            }

            if ( ! EmailTemplateRegistry::has( $template_key ) ) {
                throw new RequestException( 'invalid_param', 'Invalid template key.' );
            }

            $blocks = json_decode( $blocks_raw, true );
            $styles = json_decode( $styles_raw, true );

            if ( ! is_array( $blocks ) || ! is_array( $styles ) ) {
                throw new RequestException( 'invalid_param', 'Invalid blocks or styles data.' );
            }

            $preview = EmailTemplateRegistry::preview( $template_key );
            $html    = $preview->render_from_blocks( $blocks, $styles );

            if ( ! $preview->save_custom_template( $html ) ) {
                throw new RequestException(
                    'server_error',
                    'Failed to save template. Please try again.'
                );
            }

            $label = EmailTemplateRegistry::entry( $template_key )['label'];

            return ( new Response( 200 ) )
                ->set_body( [
                    'success' => true,
                    'data'    => [
                        'message'      => "{$label} template saved successfully.",
                        'template_key' => $template_key,
                    ],
                ] )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
                ->set_exception( $e );
        }
    }

    /**
     * Reset a template type to its system default.
     *
     * Deletes the stored custom template HTML so render() falls back to
     * the system default skeleton on the next send. The editor reloads
     * the page after a successful reset to reflect the fresh default state.
     *
     * Expected POST params:
     *   template_key (string) — registered template key
     *
     * @param  Request  $request
     * @return Response JSON — { success: true, data: { message: string, template_key: string } }
     */
    public function reset_email_template( Request $request ): Response {
        try {
            $this->is_system_admin();

            $template_key = $request->get( 'template_key' );

            if ( ! $template_key ) {
                throw new RequestException( 'required_param', 'Template key is required.' );
            }

            if ( ! EmailTemplateRegistry::has( $template_key ) ) {
                throw new RequestException( 'invalid_param', 'Invalid template key.' );
            }

            $preview = EmailTemplateRegistry::preview( $template_key );

            if ( ! $preview->reset_to_default() ) {
                throw new RequestException(
                    'server_error',
                    'Failed to reset template. Please try again.'
                );
            }

            $label = EmailTemplateRegistry::entry( $template_key )['label'];

            return ( new Response() )
                ->set_body( [
                    'success' => true,
                    'data'    => [
                        'message'      => "{$label} template reset to default.",
                        'template_key' => $template_key,
                    ],
                ] )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
                ->set_exception( $e );
        }
    }
}