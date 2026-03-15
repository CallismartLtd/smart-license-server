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
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use function defined;
defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The REST API bulk messages class.
 */
class BulkMessages {
    use SanitizeAwareTrait;
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
        $app_slugs  = static::sanitize_deep( $app_slugs );
        $app_types  = static::sanitize_deep( $app_types );

        if ( empty( $app_types ) && empty( $app_slugs ) ) {
            $results = MessageModel::get_all( compact( 'page', 'limit' ) );
        } else {
            $results = MessageModel::get_for_slugs( compact( 'app_slugs', 'app_types', 'page', 'limit' ) );
        }

        if ( empty( $results['items'] ) ) {
            return new RequestException( 'no_bulk_messages', 'No bulk messages found.', [ 'status' => 404 ] );
        }

        $request->set( 'smliser_resource', $results );

        return true;
    }

    /**
     * Dispatch bulk messages response.
     * 
     * @param Request $request The REST API request object.
     * @return Response The response object.
     */
    public static function dispatch_response( Request $request ) {
        /** @var array{items: \SmartLicenseServer\Messaging\BulkMessages[], pagination: array{page: mixed, limit: mixed, total: int, total_pages: int}} $result*/
        $results = $request->get( 'smliser_resource' );
        
        foreach ( $results['items'] as &$message ) {
            $message = $message->to_array();
        }

        $response = new Response( 200, array(), $results );

        return $response;
    }

    /**
     * Dispatch mock messages
     *
     * @param Request $request The REST API request object.
     * @return Response The response object
     */
    public static function mock_dispatch( Request $request ) : Response {
        $page  = (int) $request->get( 'page', 1 );
        $limit = (int) $request->get( 'limit', 10 );
        $page  = max( 1, $page );
        $limit = max( 1, $limit );

        $subjects   = array(
            'Welcome to Callismart Tech Solutions!',
            'Introducing Smart License Server',
            'Boost Your Business with Smart Woo Pro 🚀',
            'Custom Software Development Services',
            'Developer Tools Built for Professionals 🧠',
            'Why Choose Callismart Tech?',
            'Product Ecosystem Overview',
            'Let Us Power Your Next Project 💬',
            'Smart Woo: Automate Your Service Invoicing Today',
            'License Management Has Never Been This Easy',
            'Enterprise-Grade Plugins for WooCommerce Stores',
            'Callismart Tech: Trusted by Developers Worldwide 🌍',
            'Seamless Software Updates with Smart License Server',
            'Recurring Billing Made Simple with Smart Woo',
            'Scalable WordPress Solutions for Growing Businesses',
            'API-First Design for Modern Developers',
            'Your Software. Our Infrastructure. Zero Hassle. ✅',
            'Smart Woo Service Invoicing – Now Even Better',
            'Protect Your Software with License Validation APIs',
            'We Build. You Scale. Callismart Tech. 💡',
            'New Feature Alert: Enhanced Dashboard for Smart Woo',
            'Automate License Delivery with Smart License Server',
            'WooCommerce Automation Starts Here',
            'From Idea to Launch – Callismart Has You Covered',
            'Discover the Power of Smart License Server 🔑',
            'Reduce Churn with Automated Service Reminders',
            'Smart Woo Pro: Premium Features for Power Users',
            'Integrate License Validation in Minutes',
            'Built on WordPress Standards You Can Trust',
            'Meet the Team Behind Your Favourite Plugins 👋',
            'Smart Woo Now Supports Multiple Service Types',
            'Callismart Tech Product Roadmap – What\'s Coming Next',
            'Effortless Invoice Generation with Smart Woo',
            'Keeping Your Software Secure, One License at a Time 🔒',
            'Developer Documentation Just Got an Upgrade',
            'Real-Time License Status – Powered by Callismart',
            'Smart Woo: Designed for Agencies and Freelancers',
            'World-Class Support from the Callismart Team 🤝',
            'Save Hours Every Week with Smart Woo Automation',
            'Smart License Server: Multi-Product License Management',
            'Why Hundreds of Developers Trust Callismart Tech',
            'New Integrations Available for Smart Woo Pro 🔗',
            'Your Clients Deserve Professional Invoicing – Use Smart Woo',
            'Callismart Tech: Powering Digital Products Since Day One',
            'Unlimited License Seats – Available on Smart License Server',
            'Automate Renewals and Never Miss a Payment Again',
            'Get Started with Smart Woo in Under 10 Minutes ⚡',
            'Custom Plugin Development – Built Around Your Needs',
            'Callismart Tech: Where Innovation Meets Reliability',
            'Thank You for Being Part of the Callismart Community 🎉',
        );

        $bodies = array(
            '<p><strong>Demo Message:</strong> Welcome to Callismart Tech – creators of powerful automation tools for WordPress and WooCommerce. We are glad to have you on board!</p>',
            '<p><strong>Demo Message:</strong> Smart License Server helps developers manage software licenses, control activations, and deliver updates seamlessly to end users.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo Service Invoicing automates recurring service billing so you can focus on delivering value, not chasing payments.</p>',
            '<p><strong>Demo Message:</strong> Our team builds scalable WordPress plugins and enterprise-grade web applications tailored to your business requirements.</p>',
            '<p><strong>Demo Message:</strong> Callismart Tech provides secure, API-first solutions for license validation and software update delivery – built for modern developers.</p>',
            '<p><strong>Demo Message:</strong> Every product we ship follows WordPress coding standards and WooCommerce best practices, ensuring reliability and long-term maintainability.</p>',
            '<p><strong>Demo Message:</strong> Discover our growing ecosystem of developer tools designed to automate, protect, and scale your digital products with ease.</p>',
            '<p><strong>Demo Message:</strong> Let our experienced engineers help you design and build your next digital product – from architecture to launch and beyond.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo Service Invoicing removes the complexity of managing recurring services. Set it up once and let the automation handle the rest.</p>',
            '<p><strong>Demo Message:</strong> With Smart License Server, you can assign, revoke, and monitor software licenses in real time from a single, intuitive dashboard.</p>',
            '<p><strong>Demo Message:</strong> Running a WooCommerce store? Our enterprise-grade plugins are built to handle high volume with zero compromise on performance.</p>',
            '<p><strong>Demo Message:</strong> Thousands of developers worldwide rely on Callismart Tech products to power their businesses. Join our growing community today.</p>',
            '<p><strong>Demo Message:</strong> Smart License Server automates software update delivery directly to your users – keeping everyone on the latest, most secure version.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo makes recurring billing effortless. Define your service cycle, set pricing, and let automation take care of the invoicing.</p>',
            '<p><strong>Demo Message:</strong> Whether you are a startup or an established agency, Callismart Tech plugins are built to scale alongside your business growth.</p>',
            '<p><strong>Demo Message:</strong> Our API-first approach means you can integrate Smart License Server and Smart Woo into any workflow or platform with minimal effort.</p>',
            '<p><strong>Demo Message:</strong> Focus on building great software. Callismart Tech handles the infrastructure for licensing, billing, and updates so you do not have to.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo Service Invoicing has been updated with new features, including improved service renewal flows and enhanced reporting capabilities.</p>',
            '<p><strong>Demo Message:</strong> Protect your software investment with Smart License Server\'s robust license validation APIs – stop unauthorised usage before it starts.</p>',
            '<p><strong>Demo Message:</strong> Callismart Tech builds the tools, and you drive the growth. Partner with us and let your business reach its full potential.</p>',
            '<p><strong>Demo Message:</strong> The Smart Woo dashboard has received a major upgrade. Enjoy a cleaner interface, faster load times, and deeper insights into your services.</p>',
            '<p><strong>Demo Message:</strong> Automate license key delivery to customers upon purchase using Smart License Server – fully integrated with WooCommerce out of the box.</p>',
            '<p><strong>Demo Message:</strong> WooCommerce automation starts with the right plugins. Callismart Tech products are engineered to reduce manual work and increase efficiency.</p>',
            '<p><strong>Demo Message:</strong> From initial concept to final deployment, Callismart Tech is your end-to-end development partner for WordPress and WooCommerce solutions.</p>',
            '<p><strong>Demo Message:</strong> Smart License Server gives you full control over how your software is distributed, activated, and updated across all your customers.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo automatically sends service renewal reminders to your clients, helping you reduce churn and maintain steady recurring revenue.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo Pro unlocks premium features including advanced analytics, priority support, and extended integration options for power users.</p>',
            '<p><strong>Demo Message:</strong> Integrating license validation into your application takes just minutes with Smart License Server\'s well-documented REST API and SDKs.</p>',
            '<p><strong>Demo Message:</strong> All Callismart Tech products are built on solid WordPress foundations – ensuring compatibility, security, and a familiar developer experience.</p>',
            '<p><strong>Demo Message:</strong> Get to know the passionate team behind Callismart Tech. We are developers building tools we wish had existed when we started out.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo now supports multiple service types, giving you the flexibility to invoice for subscriptions, retainers, one-time projects, and more.</p>',
            '<p><strong>Demo Message:</strong> Check out the Callismart Tech product roadmap to see what exciting features and integrations are coming to Smart Woo and Smart License Server.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo generates professional invoices automatically, complete with your branding, service details, and payment instructions for your clients.</p>',
            '<p><strong>Demo Message:</strong> Smart License Server ensures every copy of your software is legitimate and authorised, giving you and your customers peace of mind.</p>',
            '<p><strong>Demo Message:</strong> Our developer documentation has been revamped with new guides, code examples, and tutorials to help you get up and running faster.</p>',
            '<p><strong>Demo Message:</strong> Monitor the real-time status of every software license you issue – active, expired, or revoked – all from the Smart License Server dashboard.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo is the invoicing solution of choice for WordPress agencies and freelancers who need reliable, automated billing for client services.</p>',
            '<p><strong>Demo Message:</strong> The Callismart Tech support team is here to help. Reach out via our helpdesk and get expert assistance from the people who built your tools.</p>',
            '<p><strong>Demo Message:</strong> Automating service invoicing with Smart Woo saves businesses hours every week – time better spent on growth, clients, and new opportunities.</p>',
            '<p><strong>Demo Message:</strong> Smart License Server supports multi-product license management, allowing you to manage all your software titles from one centralised platform.</p>',
            '<p><strong>Demo Message:</strong> Our products are trusted by hundreds of developers and businesses. Here is what makes Callismart Tech the go-to choice for WordPress professionals.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo Pro now offers new third-party integrations, making it even easier to connect your invoicing workflow with the tools you already use.</p>',
            '<p><strong>Demo Message:</strong> Your clients expect professionalism. Smart Woo delivers polished, branded service invoices that reflect the quality of your business.</p>',
            '<p><strong>Demo Message:</strong> Since day one, Callismart Tech has been dedicated to building software that empowers developers and businesses to do more with less effort.</p>',
            '<p><strong>Demo Message:</strong> Smart License Server supports unlimited license seats on eligible plans – ideal for growing software products with large and expanding user bases.</p>',
            '<p><strong>Demo Message:</strong> Smart Woo handles service renewals and payment reminders automatically, so you never miss a billing cycle or lose track of a client contract.</p>',
            '<p><strong>Demo Message:</strong> Getting started with Smart Woo takes less than ten minutes. Install, configure your services, and start generating invoices right away.</p>',
            '<p><strong>Demo Message:</strong> Need a custom WordPress plugin? Callismart Tech offers bespoke plugin development services designed and built entirely around your unique requirements.</p>',
            '<p><strong>Demo Message:</strong> At Callismart Tech, innovation and reliability are not a trade-off – they are the foundation of every product we design, build, and ship.</p>',
            '<p><strong>Demo Message:</strong> Thank you for being a valued member of the Callismart Tech community. We are committed to building tools that grow alongside your business.</p>',
        );

        $total_messages = 50;
        $total_pages    = (int) ceil( $total_messages / $limit );
        $offset = ( $page - 1 ) * $limit;
        $start  = $offset + 1;
        $end    = min( $offset + $limit, $total_messages );

        $items      = array();
        $base_time  = time();

        for ( $i = $start; $i <= $end; $i++ ) {
            $timestamp = $base_time - ( $i * HOUR_IN_SECONDS );
            $date      = gmdate( 'Y-m-d H:i:s', $timestamp );

            $items[] = array(
                'id'         => 'msg_' . gmdate( 'Ymd', $timestamp ) . '_' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ),
                'subject'    => $subjects[ $i - 1 ],
                'body'       => $bodies[ $i - 1 ],
                'created_at' => $date,
                'updated_at' => $date,
                'read'       => (bool) random_int( 0, 1 ),
            );
        }

        $result = array(
            'items'      => $items,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total_messages,
                'total_pages' => $total_pages,
            ),
        );

        return new Response( 200, array(), $result );
    }
}