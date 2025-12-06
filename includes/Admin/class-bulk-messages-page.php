<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\admin;

use \SmartLicenseServer\BulkMessages;
use SmartLicenseServer\RESTAPI\Versions\V1;

defined( 'ABSPATH' ) || exit;

/**
 * The admin bulk message page class
 */
class Bulk_Message_Page {
    /**
     * Page router
     */
    public static function router() {
        $tab = smliser_get_query_param( 'tab' );
        switch ( $tab ) {
            case 'compose-new':
                self::compose_message_page();
                break;
            case 'edit':
                self::edit_message_page();
                break;
            case 'delete':
                self::delete_page();
                break;
            default:
            self::dashboard();
        }
    
    }

    /**
     * Bulk messages page dashbard.
     */
    private static function dashboard() {
        $messages   = BulkMessages::get_all();
        $route_descriptions = V1::describe_routes('bulk-messages');   
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/dashboard.php';
    
    }

    /**
     * Compose message page.
     */
    private static function compose_message_page() {
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/compose.php';
    }

    /**
     * Edit message page.
     */
    private static function edit_message_page() {
        $message_id = smliser_get_query_param( 'msg_id' );
        
        $message    = BulkMessages::get_message( $message_id );
   
        include_once SMLISER_PATH . 'templates/admin/bulk-messages/edit.php';
    }

    /**
     * Delete a given message.
     */
    private static function delete_page() {
        $message_id = smliser_get_query_param( 'msg_id' );
        
        $message    = BulkMessages::get_message( $message_id );

        include_once SMLISER_PATH . 'templates/admin/bulk-messages/delete.php';
       
    }

    /**
     * WP Editor Options
     *
     * @return array
     */
    private static function editor_options() {
        return array(
            'textarea_name' => 'message_body',
            'textarea_rows' => 15,
            'teeny'         => false, // must be false for full HTML control
            'media_buttons' => true,
            'quicktags'     => true,
            'tinymce'       => array(
                'wp_autoresize_on'   => true,
                'browser_spellcheck' => true,
                'resize'             => true,
                'plugins'            => 'lists,link,paste,code,wordpress',
                'toolbar1'           => 'formatselect,bold,italic,underline,link,bullist,numlist,blockquote,code,undo,redo',
                'forced_root_block'  => 'p',
                'force_p_newlines'   => true,
            ),
        );
    }

    /**
     * Run action hooks
     */
    public static function listen() {
        add_action( 'wp_ajax_smliser_publish_bulk_message', [__CLASS__, 'publish_bulk_message'] );
        add_action( 'admin_post_smliser_bulk_message_bulk_action', [__CLASS__, 'bulk_action'] );
    }

    /**
     * Handle ajax bulk message publish
     */
    public static function publish_bulk_message() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        $subject    = smliser_get_post_param( 'subject', null ) ?? smliser_send_json_error( [ 'message' => __( 'Message subject cannot be empty', 'smliser' ) ] );
        $body       = isset( $_POST['message_body'] ) ? wp_kses_post( unslash( $_POST['message_body'] ) ) : smliser_send_json_error( [ 'message' => __( 'Message body cannot be empty', 'smliser' ) ] );
        $message_id = smliser_get_post_param( 'message_id' );
        $assocs_apps    = smliser_get_post_param( 'associated_apps' );

        $apps       = [];
        foreach( $assocs_apps as $app_data ) {

            try {
                list( $type, $slug ) = explode( ':', $app_data );

                if ( ! empty( $type ) && ! empty( $slug ) ) {
                    $apps[$type][]  = $slug;
                }
            } catch (\Throwable $th) {}

        }

        if ( $message_id ) {
            $bulk_msg = BulkMessages::get_message( $message_id );

            if ( ! $bulk_msg ) {
                smliser_send_json_error( ['message' => __( 'Invalid or deleted message', 'smliser' )] );
            }
        } else {
            $bulk_msg   = new BulkMessages();
        }
        

        $bulk_msg->set_subject( $subject );
        $bulk_msg->set_body( $body );
        $bulk_msg->set_associated_apps( $apps, true );

        if ( $bulk_msg->save() ) {
            smliser_send_json_success( ['message' => __( 'Message has been published.', 'smliser' ), 'redirect_url' => admin_url( 'admin.php?page=smliser-bulk-message&tab=edit&msg_id=' . $bulk_msg->get_message_id() )], 200 );
        }
        
        smliser_send_json_error( ['message' => __( 'Unable to publish message.', 'smliser' )], 503 );

    }

    /**
     * Perform bulk action on bulk message IDs
     */
    public static function bulk_action() {
        if ( ! wp_verify_nonce( smliser_get_post_param( 'smliser_table_nonce' ), 'smliser_table_nonce' ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=smliser-bulk-message' ) );
            exit;
        }

        $message_ids    = smliser_get_post_param( 'message_ids', [] );
        $action         = smliser_get_post_param( 'bulk_action' );

        $allowed_actions = [ 'delete'];

        if ( ! in_array( $action, $allowed_actions, true ) ) {
            smliser_send_json_error( array( 'message' => __( 'Action is not allowed', 'smliser' ) ), 400 );
        }

        switch( $action ) {

            case 'delete': 
                foreach( (array) $message_ids as $id ) {
                    $message = BulkMessages::get_message( $id );

                    if ( $message ) {
                        $message->delete();
                    }

                }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=smliser-bulk-message&success=1' ) );
        exit;
    }
}

Bulk_Message_Page::listen();