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

use SmartLicenseServer\Admin\OptionsPage;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Email\Templates\EmailTemplateRegistry;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Settings controller class handles request and response for settings API.
 */
class SettingsController {
    use SanitizeAwareTrait, SecurityAwareTrait;

    /**
     * Save system settings
     * 
     * @param Request $request The object.
     * @return Response
     */
    public static function save_system_settings( Request $request ) : Response {
        try {
            static::is_system_admin();
            $settings   = \smliser_settings_adapter();
            $collection = Collection::make( OptionsPage::system_settings_fields() );
            $fields     = $collection->map(
                fn( $v ) => $v['input']['name'] ?? ''
            );

            $affected   = 0;
            foreach ( $fields as $key ) {
                if ( empty( $key ) ) {
                    continue;
                }

                if ( $request->has( $key ) ) {
                    $value  = static::sanitize_auto( $request->get( $key ) );
                    if ( $settings->set( $key, $value, true ) ) {
                        $affected++;
                    }
                }
            }

            $response_data  = array(
                'success'   => true,
                'data'  => array(
                    'message' => $affected > 0
                        ? sprintf( '%d option(s) saved successfully.', $affected )
                        : 'No changes were made.',
                )
            );

            return ( new Response( 200, [], $response_data ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }

    }

    /**
     * Save routing settings.
     *
     * @param  Request $request
     * @return Response
     */
    public static function save_routing_settings( Request $request ): Response {
        try {
            static::is_system_admin();

            $collection = Collection::make( OptionsPage::get_routing_fields() );
            $fields     = $collection->map(
                fn( $v ) => $v['input']['name'] ?? ''
            );

            $settings = smliser_settings_adapter();

            foreach ( $fields as $key ) {
                if ( empty( $key ) ) {
                    continue;
                }

                $default    = match( $key ) {
                    'repository_url_prefix' => $settings->get( $key, 'repository', true ),
                    'download_url_prefix'   => $settings->get( $key, 'downloads', true ),
                    default                 => ''
                };

                $value  = static::sanitize_slug( $request->get( $key, $default ) ) ?: $default;
                $settings->set( $key, $value, true );
        
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
    public static function toggle_email_template( Request $request ): Response {
        try {
            static::is_system_admin();

            $template_key = $request->get( 'template_key' );
            $new_state    = $request->get( 'new_state' );

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

            if ( ! in_array( $new_state, [ '0', '1' ], true ) ) {
                throw new RequestException(
                    'invalid_param',
                    'Invalid state value provided.'
                );
            }

            $preview = EmailTemplateRegistry::preview( $template_key );

            $success = $preview->is_enabled() ? $preview->disable() : $preview->enable();


            if ( ! $success ) {
                throw new RequestException(
                    'server_error',
                    'Failed to update email template state. Please try again.'
                );
            }

            $label   = EmailTemplateRegistry::entry( $template_key )['label'];
            $message = $new_state === '1'
                ? sprintf( '%s email has been enabled.', $label )
                : sprintf( '%s email has been disabled.', $label );

            $response_data  = [
                'success' => true,
                'data'    => [
                    'message'     => $message,
                    'template_key' => $template_key,
                    'is_enabled'  => $new_state === '1',
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
    
}