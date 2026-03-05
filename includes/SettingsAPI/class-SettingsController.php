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
    
}