<?php
/**
 * Signup email job class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Jobs\Accounts
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Jobs\Accounts;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Email\Templates\Accounts\AdminNewUserNotificationEmail;
use SmartLicenseServer\Email\Templates\Accounts\WelcomeEmail;
use SmartLicenseServer\Security\Actors\User;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Asynchronously sends welcome emails to new users.
 */
class SignupEmailJob implements JobHandlerInterface {
    /*
    |----------------------
    | JobHandlerInterface
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * @param array{
     *      user_id: int,
     *      recipient: string,
     *      for_admin: bool,
     *      ip_address: string,
     *      account_type: string,
     *      signup_time: string,
     * } $payload
     */
    public function handle( array $payload = [] ) : mixed {
        $user_id    = $payload['user_id'] ?? 0;
        $recipient  = (string) $payload['recipient'] ?? '';
        $for_admin  = (bool) ( $payload['for_admin'] ?? false );

        $user       = User::get_by_id( $user_id );

        if ( ! $user ) {
            return [
                'success'   => false,
                'message'   => 'User does not exist.'
            ];
        }

        if ( $for_admin ) {
            $ip_address     = $payload['ip_address'] ?? 'Unknown';
            $account_type   = $payload['account_type'] ?? 'Unknown';
            $signup_time    = $payload['signup_time'] ?? date( 'Y-m-d H:i:s' );

            $welcome_email  = new AdminNewUserNotificationEmail(
                user: $user,
                to: $recipient,
                ip_address: $ip_address,
                account_type: $account_type,
                signup_time: $signup_time
            );
        } else {
            $welcome_email = new WelcomeEmail(
                user: $user,
                to: $recipient
            );
        }
        
        $response       = \smliser_mailer()->send( $welcome_email->to_message() );

        return $response->to_array();
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'Send Signup Email';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Asyncronously send welcome email to newly registered users.';
    }
}