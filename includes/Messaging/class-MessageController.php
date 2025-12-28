<?php
/**
 * Bulk messaging controller class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Messaging
 * @since 0.2.0
 */

namespace SmartLicenseServer\Messaging;

use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\RequestException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The bulk messaging controller class
 */
class MessageController {
    /**
     * Savw a bulk message.
     * 
     * @param Request $request The request object.
     * @return Response The response object.
     */
    public static function save_bulk_message( Request $request ) : Response {
        try {
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'permission_denied' );
            }

            $subject    = $request->get( 'subject' );

            if ( empty( $subject ) ) {
                throw new RequestException( 'required_param', 'The message subject is required.', ['status' => 400] );
            }
            $body       = $request->get( 'message_body' );
            if ( empty( $body ) ) {
                throw new RequestException( 'required_param', 'The message body is required.', ['status' => 400] );
            }

            $message_id         = $request->get( 'message_id', 0 );
            $associated_apps    = $request->get( 'associated_apps', [] );
            $is_new_message     = true;

            if ( $message_id ) {
                $message    = BulkMessages::get_message( $message_id );

                if ( ! $message ) {
                    throw new RequestException( 'not_found', 'The specified message was not found.', ['status' => 404] );
                }

                $is_new_message = false;
                
            } else {
                $message    = new BulkMessages();
            }

            $message->set_subject( $subject );
            $message->set_body( $body );
            $message->set_associated_apps( $associated_apps, true );

            if ( ! $message->save() ) {
                throw new RequestException( 'save_failed', 'Failed to save the bulk message.', ['status' => 500] );
            }

            $response_data  = [
                'success'   => true,
                'data'      => [
                    'message'       => $is_new_message ? 'Message has been published.' : 'Message has been updated.',
                    'message_id'    => $message->get_message_id(),
                ],
            ];

            return ( new Response( 200, [], \smliser_safe_json_encode( $response_data ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }

    }

    /**
     * Handle bulk action on bulk messages
     * 
     * @param Request $request The request object.
     * @return Response The response object.
     */
    public static function bulk_message_action( Request $request ) : Response {
        try {
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'permission_denied', 'Sorry, you do not have the required permission to perform this action.' );
            }

            $message_ids    = $request->get( 'ids', [] );
            $action         = $request->get( 'bulk_action', '' );

            $allowed_actions = ['delete'];

            if ( ! in_array( $action, $allowed_actions, true ) ) {
                throw new RequestException( 'invalid_action', 'The specified action is not allowed.', ['status' => 400] );
            }
            $affected   = 0;

            switch( $action ) {

                case 'delete': 
                    foreach( (array) $message_ids as $id ) {
                        $message = BulkMessages::get_message( $id );

                        if ( $message && $message->delete() ) {
                            $affected++;
                        }

                    }
            }

            $request->set( 'message', \sprintf( '%s affected!', $affected ) );
            $response = ( new Response( 200, [], '' ) )
                ->set_response_data( $request );

            \smliser_cache()->clear();
            return $response;

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }
}