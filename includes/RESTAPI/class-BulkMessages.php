<?php
/**
 * The REST API bulk messages class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\classes
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Messaging\BulkMessages as MessageModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The REST API bulk messages class.
 */
class BulkMessages {
    /**
     * Bulk messages permission callback.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return bool|WP_Error True if permitted, WP_Error otherwise.
     */
    public static function permission_callback( WP_REST_Request $request ) {
        $page       = max( 1, (int) $request->get_param( 'page' ) );
        $limit      = max( 50, (int) $request->get_param( 'limit' ) );

        // Normalize array inputs.
        $app_slugs  = (array) $request->get_param( 'slug' );
        $app_types  = (array) $request->get_param( 'type' );

        // Remove empty strings or invalid entries.
        $app_slugs  = array_filter( $app_slugs, 'sanitize_text_field' );
        $app_types  = array_filter( $app_types, 'sanitize_text_field' );

        if ( empty( $app_types ) && empty( $app_slugs ) ) {
            $bulk_messages = MessageModel::get_all( compact( 'page', 'limit' ) );
        } else {
            $bulk_messages = MessageModel::get_for_slugs( compact( 'app_slugs', 'app_types', 'page', 'limit' ) );
        }

        if ( empty( $bulk_messages ) ) {
            return new WP_Error( 'no_bulk_messages', 'No bulk messages found.', [ 'status' => 404 ] );
        }

        $request->set_param( 'smliser_resource', $bulk_messages );

        return true;
    }

    /**
     * Dispatch bulk messages response.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response object.
     */
    public static function dispatch_response( WP_REST_Request $request ) {
        $data = array();

        /** @var MessageModel[] $bulk_messages */
        $bulk_messages = $request->get_param( 'smliser_resource' );

        foreach ( $bulk_messages as $message ) {
            $data[] = $message->to_array();
        }

        $response = new WP_REST_Response( $data );
        $response->set_status( 200 );

        return $response;
    }

    /**
     * Dispatch mock messages
     * 
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function mock_dispatch( WP_REST_Request $request ) {
        $messages = array(
            array(
                'id'         => 'msg_20251013_001',
                'subject'    => 'Welcome to Smart Woo!',
                'body'       => '<p>We’re thrilled to have you onboard 🎉. Smart Woo simplifies your invoicing, payments, and client management. </p>
                                <p>Start by exploring our <a href="https://smartwoo.com/docs" target="_blank">documentation</a> and check your <strong>Service Dashboard</strong> for quick actions.</p>
                                <p>Need help? Contact <a href="mailto:support@smartwoo.com">support@smartwoo.com</a>.</p>',
                'created_at' => '2025-10-13 09:15:00',
                'updated_at' => '2025-10-13 09:15:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_002',
                'subject'    => 'New Feature: Automated Invoicing',
                'body'       => '<p>Introducing <strong>Smart Auto Billing</strong> — automatically generate and send invoices for recurring services.</p>
                                <p>You can configure this under <em>Smart Woo → Settings → Automation</em>. The feature supports both manual and scheduled triggers.</p>
                                <p><a href="https://smartwoo.com/blog/auto-billing" target="_blank">Learn more →</a></p>',
                'created_at' => '2025-10-13 09:45:00',
                'updated_at' => '2025-10-13 09:45:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_003',
                'subject'    => 'Smart Woo Pro 1.5 Released 🚀',
                'body'       => '<p>We’re excited to announce <strong>Smart Woo Pro v1.5</strong> with new features like:</p>
                                <ul>
                                    <li>Improved checkout analytics dashboard</li>
                                    <li>Custom hooks for subscription renewals</li>
                                    <li>Performance enhancements</li>
                                </ul>
                                <p>Update your plugin from the dashboard or visit <a href="https://smartwoo.com/changelog">our changelog</a> for details.</p>',
                'created_at' => '2025-10-12 14:30:00',
                'updated_at' => '2025-10-12 14:30:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_004',
                'subject'    => 'Tips: Speed Up Your Billing Workflow',
                'body'       => '<p>Did you know you can create <strong>service templates</strong> to reuse invoice structures? This saves time and reduces errors.</p>
                                <p>Navigate to <em>Smart Woo → Templates</em> and create your first one.</p>',
                'created_at' => '2025-10-11 08:00:00',
                'updated_at' => '2025-10-11 08:00:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_005',
                'subject'    => 'Long Text',
                'body'       => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur eu mauris nec velit fermentum facilisis et eget mauris. Quisque eu nulla nec tortor vehicula finibus. Fusce quis laoreet lacus. Vivamus sagittis nisi vel velit tincidunt, nec tempus tortor hendrerit. Quisque varius pretium orci, in dignissim velit iaculis quis. Vestibulum vitae nisl quis turpis ultricies sagittis pellentesque a neque. Etiam tempus tellus vel velit varius porttitor. Donec id eros aliquet, malesuada justo nec, luctus ex. Pellentesque consequat nisl at mi suscipit feugiat.

                    Nulla eu fringilla lacus, eu porta lacus. Donec interdum ultricies metus id fringilla. Nam volutpat faucibus odio a blandit. Ut pretium, erat vitae porttitor dictum, purus odio scelerisque lacus, in venenatis felis eros nec nunc. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur facilisis dignissim ligula eget auctor. Phasellus volutpat maximus nisi sed fringilla. Integer ut posuere orci. Mauris ex metus, dignissim sit amet risus non, gravida suscipit felis. Donec dapibus varius bibendum. Cras a augue dapibus, ornare felis ac, interdum velit. Ut vitae tortor tempus, venenatis mauris quis, efficitur dui. Pellentesque in posuere arcu. Duis ut pulvinar erat.

                    Morbi et porta diam, at semper nisl. Nulla dapibus viverra pellentesque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Nullam eros nisi, euismod vitae lectus porta, dignissim rutrum lorem. Nulla massa libero, egestas ut dapibus posuere, blandit in libero. Curabitur euismod quam sit amet rutrum dapibus. Sed at eros diam. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Fusce tempus arcu vel dolor pellentesque, eget bibendum lorem tristique.

                    Proin a purus leo. Curabitur dictum faucibus purus, in elementum eros pellentesque vel. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Mauris in justo enim. Pellentesque sed turpis euismod, rhoncus augue pharetra, bibendum elit. Ut lectus dolor, porttitor nec elit euismod, dignissim ultrices ante. Donec fringilla lacinia mauris vitae hendrerit. Integer in ligula eget erat pretium hendrerit vel id dolor. In lobortis purus mauris, sit amet bibendum ex rhoncus a. Curabitur porttitor vulputate lectus in scelerisque. Nullam dolor turpis, venenatis sed sem at, tempor dignissim felis. Donec dictum et diam eu imperdiet. Duis efficitur, orci ut fringilla tincidunt, ligula ex sagittis nibh, at aliquam ante ante a ligula. Phasellus mollis augue quis pellentesque maximus.

                    Nullam diam diam, lobortis ut tincidunt id, consequat quis eros. Suspendisse tincidunt vestibulum magna non lacinia. Curabitur semper enim sem, condimentum tempor orci placerat at. Proin faucibus sapien eu lorem sollicitudin, et ornare tellus cursus. Phasellus eleifend tortor non mi condimentum posuere. Proin pellentesque placerat nulla ut blandit. Morbi vitae ligula ultrices, posuere leo vel, malesuada lectus. Nulla ornare ipsum ac molestie pretium. Duis nec libero ut felis sagittis dignissim. Aliquam erat volutpat. Ut sit amet bibendum sapien. Aliquam erat volutpat. Mauris enim sem, ultricies nec ex nec, volutpat molestie orci. Sed posuere ac lorem in dictum. Donec ornare nisi nulla, eu posuere enim placerat et. Curabitur rutrum hendrerit lobortis.

                    Sed luctus fringilla dui quis euismod. Sed nec nisl vitae neque porttitor eleifend. Vestibulum tempor ligula nisl, eget suscipit nunc elementum a. Integer euismod congue metus et placerat. Etiam eleifend interdum leo quis interdum. Nam iaculis, turpis vel rhoncus consectetur, tortor mi viverra nisl, quis dictum lacus sapien sit amet quam. Sed sit amet ipsum vulputate, ultricies nunc in, mattis quam.

                    Nullam ligula orci, malesuada et fermentum imperdiet, laoreet nec dolor. Donec ornare gravida sodales. Etiam convallis molestie nulla in posuere. Aliquam vitae scelerisque augue. Donec facilisis mauris laoreet varius pulvinar. Mauris pretium orci vel enim finibus auctor. Cras efficitur non enim quis dignissim. Donec finibus tortor id est finibus fermentum. Pellentesque vulputate vestibulum lacus id elementum. Nulla ullamcorper odio at augue finibus pellentesque. Etiam a interdum turpis, venenatis volutpat sapien. Pellentesque magna eros, convallis at ex ac, blandit semper odio. Cras eu mauris eu est aliquet rutrum. Donec malesuada non enim at vehicula.</p>
                                <p>Please ensure your plugin is updated to <strong>v1.5.1</strong>.</p>',
                'created_at' => '2025-10-10 13:00:00',
                'updated_at' => '2025-10-10 13:00:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_006',
                'subject'    => 'Upcoming Webinar: Advanced Smart Woo Techniques 🎥',
                'body'       => '<p>Join our next live session where we’ll cover:</p>
                                <ul>
                                    <li>Creating automated renewals</li>
                                    <li>Integrating external APIs</li>
                                    <li>Optimizing performance for large sites</li>
                                </ul>
                                <p>Register now on <a href="https://smartwoo.com/webinars">our webinar page</a>.</p>',
                'created_at' => '2025-10-09 15:45:00',
                'updated_at' => '2025-10-09 15:45:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_007',
                'subject'    => 'Smart Woo API Enhancements for Developers 🧠',
                'body'       => '<p>Developers can now access extended API endpoints for license verification and customer lookup.</p>
                                <p>Check the <a href="https://smartwoo.com/docs/api" target="_blank">API Reference</a> for the full list of methods.</p>',
                'created_at' => '2025-10-08 10:25:00',
                'updated_at' => '2025-10-08 10:25:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_008',
                'subject'    => 'We’d Love Your Feedback 💬',
                'body'       => '<p>Your feedback helps us improve Smart Woo. Please take a moment to complete our quick survey.</p>
                                <p><a href="https://smartwoo.com/feedback" target="_blank">Take the survey →</a></p>',
                'created_at' => '2025-10-07 17:20:00',
                'updated_at' => '2025-10-07 17:20:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_009',
                'subject'    => 'Maintenance Scheduled 🕐',
                'body'       => '<p>We’ll be performing scheduled maintenance on <strong>October 20, 2025</strong>.</p>
                                <p>Service interruptions may occur between 1:00 AM and 2:00 AM UTC.</p>',
                'created_at' => '2025-10-07 08:10:00',
                'updated_at' => '2025-10-07 08:10:00',
                'read'       => false,
            ),
            array(
                'id'         => 'msg_20251013_010',
                'subject'    => 'Thank You for Being Part of Smart Woo 💙',
                'body'       => '<p>We’ve grown thanks to your support and feedback. Stay tuned for exciting updates and community features!</p>
                                <p>Visit our <a href="https://community.smartwoo.com" target="_blank">Community Portal</a> to connect with other users.</p>',
                'created_at' => '2025-10-06 11:55:00',
                'updated_at' => '2025-10-06 11:55:00',
                'read'       => false,
            ),
        );

        return new WP_REST_Response( $messages, 200 );
    }
}