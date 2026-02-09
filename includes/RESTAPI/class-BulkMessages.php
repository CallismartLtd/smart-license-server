<?php
/**
 * The REST API bulk messages class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\classes
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Messaging\BulkMessages as MessageModel;

use function defined;
defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The REST API bulk messages class.
 */
class BulkMessages {
    /**
     * Bulk messages permission callback.
     * 
     * @param Request $request The request object.
     * @return bool|RequestException True if permitted, error otherwise.
     */
    public static function permission_callback( Request $request ) : bool|RequestException {
        
        $page       = max( 1, (int) $request->get( 'page' ) );
        $limit      = max( 50, (int) $request->get( 'limit' ) );

        // Normalize array inputs.
        $app_slugs  = (array) $request->get( 'slug' );
        $app_types  = (array) $request->get( 'type' );

        // Remove empty strings or invalid entries.
        $app_slugs  = array_filter( $app_slugs, 'sanitize_text_field' );
        $app_types  = array_filter( $app_types, 'sanitize_text_field' );

        if ( empty( $app_types ) && empty( $app_slugs ) ) {
            $bulk_messages = MessageModel::get_all( compact( 'page', 'limit' ) );
        } else {
            $bulk_messages = MessageModel::get_for_slugs( compact( 'app_slugs', 'app_types', 'page', 'limit' ) );
        }

        if ( empty( $bulk_messages ) ) {
            return new RequestException( 'no_bulk_messages', 'No bulk messages found.', [ 'status' => 404 ] );
        }

        $request->set( 'smliser_resource', $bulk_messages );

        return true;
    }

    /**
     * Dispatch bulk messages response.
     * 
     * @param Request $request The REST API request object.
     * @return Response The response object.
     */
    public static function dispatch_response( Request $request ) {
        $data = array();

        /** @var MessageModel[] $bulk_messages */
        $bulk_messages = $request->get( 'smliser_resource' );

        foreach ( $bulk_messages as $message ) {
            $data[$message->get_message_id()] = $message->to_array();
        }

        $response = new Response( 200, array(), $data );

        return $response;
    }

    /**
     * Dispatch mock messages
     * 
     * @return Response The response object
     */
    public static function mock_dispatch() : Response {

        $messages = array(

            'msg_20251013_001' => array(
                'id'         => 'msg_20251013_001',
                'subject'    => 'Welcome to Callismart Tech Solutions!',
                'body'       => '<p><strong>Demo Message:</strong> This is a sample notification generated for preview purposes.</p>
                                <p>Welcome to <strong>Callismart Tech</strong> – creators of powerful business automation tools for WordPress and WooCommerce.</p>
                                <p>Our flagship product, <strong>Smart Woo Service Invoicing</strong>, helps businesses manage recurring services, invoices, and client billing with ease.</p>
                                <p>Explore our solutions and documentation at <a href="https://callismart.com" target="_blank">callismart.com</a>.</p>
                                <p>For assistance, reach us at <a href="mailto:hello@callismart.com">hello@callismart.com</a>.</p>',
                'created_at' => '2025-10-13 09:15:00',
                'updated_at' => '2025-10-13 09:15:00',
                'read'       => false,
            ),

            'msg_20251013_002' => array(
                'id'         => 'msg_20251013_002',
                'subject'    => 'Introducing Smart License Server',
                'body'       => '<p><strong>Demo Message:</strong> Example of a promotional update.</p>
                                <p>Meet <strong>Smart License Server</strong> – a robust licensing and update management platform for WordPress plugins, themes, and digital products.</p>
                                <p>With Smart License Server you can:</p>
                                <ul>
                                    <li>Generate and manage product licenses</li>
                                    <li>Deliver automatic updates</li>
                                    <li>Secure your software from unauthorized usage</li>
                                </ul>
                                <p>Built for developers and SaaS businesses by <strong>Callismart Tech</strong>.</p>
                                <p><a href="https://callismart.com/smart-license-server" target="_blank">Learn more →</a></p>',
                'created_at' => '2025-10-13 09:45:00',
                'updated_at' => '2025-10-13 09:45:00',
                'read'       => false,
            ),

            'msg_20251013_003' => array(
                'id'         => 'msg_20251013_003',
                'subject'    => 'Boost Your Business with Smart Woo Pro 🚀',
                'body'       => '<p><strong>Demo Message:</strong> Sample product announcement.</p>
                                <p>Upgrade your WooCommerce service business with <strong>Smart Woo Service Invoicing Pro</strong>.</p>
                                <p>Powerful features include:</p>
                                <ul>
                                    <li>Automated recurring billing</li>
                                    <li>Client service portals</li>
                                    <li>Advanced reporting and analytics</li>
                                    <li>Professional invoicing workflows</li>
                                </ul>
                                <p>Developed and maintained by <strong>Callismart Tech – Your Trusted Software Partner.</strong></p>
                                <p><a href="https://callismart.com/products/smart-woo" target="_blank">View product details →</a></p>',
                'created_at' => '2025-10-12 14:30:00',
                'updated_at' => '2025-10-12 14:30:00',
                'read'       => false,
            ),

            'msg_20251013_004' => array(
                'id'         => 'msg_20251013_004',
                'subject'    => 'Custom Software Development Services',
                'body'       => '<p><strong>Demo Message:</strong> Promotional content preview.</p>
                                <p>Beyond plugins, <strong>Callismart Tech</strong> is a full-service software development company.</p>
                                <p>We specialize in:</p>
                                <ul>
                                    <li>Custom WordPress and WooCommerce solutions</li>
                                    <li>Plugin and API development</li>
                                    <li>Enterprise web applications</li>
                                    <li>System integrations and automation</li>
                                </ul>
                                <p>Need a custom solution for your business? Let’s build it together.</p>
                                <p><a href="https://callismart.com/services" target="_blank">Contact our team →</a></p>',
                'created_at' => '2025-10-11 08:00:00',
                'updated_at' => '2025-10-11 08:00:00',
                'read'       => false,
            ),

            'msg_20251013_005' => array(
                'id'         => 'msg_20251013_005',
                'subject'    => 'Mock System Notification Example',
                'body'       => '<p><strong>Demo Message Notice:</strong></p>
                                <p>This long message block is a placeholder to demonstrate how extended content will appear inside the Smart Woo messaging interface.</p>
                                <p>All messages shown here are simulated examples generated by the <strong>Smart Woo Service Invoicing Plugin</strong>.</p>
                                <p>At <strong>Callismart Tech</strong>, we build reliable business tools that help companies automate workflows, manage clients, and grow revenue.</p>
                                <p>Discover our growing ecosystem of products including:</p>
                                <ul>
                                    <li>Smart Woo Service Invoicing</li>
                                    <li>Smart License Server</li>
                                    <li>Custom enterprise integrations</li>
                                </ul>
                                <p>Visit <a href="https://callismart.com" target="_blank">callismart.com</a> to learn more.</p>',
                'created_at' => '2025-10-10 13:00:00',
                'updated_at' => '2025-10-10 13:00:00',
                'read'       => false,
            ),

            'msg_20251013_006' => array(
                'id'         => 'msg_20251013_006',
                'subject'    => 'Developer Tools Built for Professionals 🧠',
                'body'       => '<p><strong>Demo Message:</strong></p>
                                <p>The <strong>Smart License Server</strong> API empowers developers with:</p>
                                <ul>
                                    <li>Secure license verification endpoints</li>
                                    <li>Automated update delivery</li>
                                    <li>Client and product management APIs</li>
                                </ul>
                                <p>Everything you need to distribute and protect your digital products – built by developers, for developers.</p>
                                <p><a href="https://callismart.com/docs" target="_blank">View API documentation →</a></p>',
                'created_at' => '2025-10-09 15:45:00',
                'updated_at' => '2025-10-09 15:45:00',
                'read'       => false,
            ),

            'msg_20251013_007' => array(
                'id'         => 'msg_20251013_007',
                'subject'    => 'Why Choose Callismart Tech?',
                'body'       => '<p><strong>Demo Message:</strong></p>
                                <p>We combine technical expertise with real-world business experience to deliver solutions that work.</p>
                                <p>Our mission is simple:</p>
                                <ul>
                                    <li>Build reliable software</li>
                                    <li>Simplify business processes</li>
                                    <li>Help companies scale with technology</li>
                                </ul>
                                <p>Whether you need plugins, integrations, or full applications – we’ve got you covered.</p>
                                <p><a href="https://callismart.com/about" target="_blank">About Callismart Tech →</a></p>',
                'created_at' => '2025-10-08 10:25:00',
                'updated_at' => '2025-10-08 10:25:00',
                'read'       => false,
            ),

            'msg_20251013_008' => array(
                'id'         => 'msg_20251013_008',
                'subject'    => 'Let Us Power Your Next Project 💬',
                'body'       => '<p><strong>Demo Message:</strong></p>
                                <p>Do you have an idea that needs expert implementation?</p>
                                <p><strong>Callismart Tech</strong> partners with businesses to design and develop custom digital solutions tailored to real needs.</p>
                                <p>Tell us what you’re building and we’ll help bring it to life.</p>
                                <p><a href="https://callismart.com/contact" target="_blank">Start a conversation →</a></p>',
                'created_at' => '2025-10-07 17:20:00',
                'updated_at' => '2025-10-07 17:20:00',
                'read'       => false,
            ),

            'msg_20251013_009' => array(
                'id'         => 'msg_20251013_009',
                'subject'    => 'Product Ecosystem Overview',
                'body'       => '<p><strong>Demo Message:</strong></p>
                                <p>Our product ecosystem is designed to work together:</p>
                                <ul>
                                    <li><strong>Smart Woo Service Invoicing</strong> – manage services and recurring billing</li>
                                    <li><strong>Smart License Server</strong> – protect and distribute your digital products</li>
                                    <li>Custom plugins and integrations for unique business needs</li>
                                </ul>
                                <p>All built with WordPress and WooCommerce best practices.</p>
                                <p><a href="https://callismart.com/products" target="_blank">Explore our products →</a></p>',
                'created_at' => '2025-10-07 08:10:00',
                'updated_at' => '2025-10-07 08:10:00',
                'read'       => false,
            ),

            'msg_20251013_010' => array(
                'id'         => 'msg_20251013_010',
                'subject'    => 'Thank You from Callismart Tech 💙',
                'body'       => '<p><strong>Demo Message:</strong></p>
                                <p>Thank you for exploring our demo messaging system.</p>
                                <p>Every tool we build at <strong>Callismart Tech</strong> is focused on helping businesses operate smarter and grow faster.</p>
                                <p>We look forward to supporting your journey with reliable software solutions.</p>
                                <p><a href="https://callismart.com" target="_blank">Visit our website →</a></p>',
                'created_at' => '2025-10-06 11:55:00',
                'updated_at' => '2025-10-06 11:55:00',
                'read'       => false,
            ),
        );

        $response = new Response( 200, array(), $messages );

        return $response;
    }

}