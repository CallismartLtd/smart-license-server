<?php
/**
 * Other admin forms and actions class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Contracts\AdminRequests\BulkActionHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Messaging\MessageController;
use SmartLicenseServer\Utils\HtmlSanitizer;

/**
 * Handles other admin forms and actions.
 */
class OtherFormsActions implements BulkActionHandlerInterface {

    public function __construct(
        protected MessageController $message_controller
    ) {}
    
    public function handle_bulk_message_publish_request( Request $request ) : Response {
        $raw_message    = $request->post( 'message_body', '' );

        $message_body   = ( new HtmlSanitizer() )->sanitize_editor( $raw_message );

        $request->set( 'message_body', $message_body );

        $apps = [];
        foreach ( (array) $request->get( 'associated_apps', [] ) as $app_data ) {
            try {
                [ $type, $slug ] = explode( ':', $app_data );
                if ( ! empty( $type ) && ! empty( $slug ) ) {
                    $apps[ $type ][] = $slug;
                }
            } catch ( \Throwable $th ) {}
        }

        $request->set( 'associated_apps', $apps );

        return $this->message_controller->save_bulk_message( $request );
    }

    public function handle_bulk_action_request(Request $request): Response {
        throw new \Exception('Not implemented');
    }
}