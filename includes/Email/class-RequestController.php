<?php
/**
 * Email Request Controller class file.
 * 
 * @author Callistus Nwachukwu
 * @since 0.2.0
 */
declare( strict_types = 1 );
namespace SmartLicenseServer\Email;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Email request constroller class handles all HTTP request for emails in the admin UI
 */
class RequestController {
    use SanitizeAwareTrait, SecurityAwareTrait;

    /**
     * Handles request to save default email options
     * @param Request The request object.
     * @return Response
     */
    public static function save_default_email_options( Request $request ) : Response {
        try {
            static::is_system_admin();

            $collection   = EmailProviderCollection::instance();

            $default_mailer_key = EmailProviderCollection::DEFAULT_PROVIDER_KEY;

            $provider_id     = static::sanitize_text( $request->get( $default_mailer_key ) );

            if ( ! $provider_id ) {
                throw new RequestException(
                    'required_param',
                    'The default mailer is required.'
                );
            }

            if ( ! $collection->has_provider( $provider_id) ) {
                throw new RequestException(
                    'validation_failed',
                    sprintf( 'Invalid email provider.' )
                );
            }

            $sender_name    = static::sanitize_text( $request->get( EmailProviderCollection::DEFAULT_SENDER_NAME_KEY, '' ) );
            $sender_email   = static::sanitize_email( $request->get( EmailProviderCollection::DEFAULT_SENDER_EMAIL_KEY, '' ) );

            if ( empty( $sender_name ) ) {
                throw new RequestException(
                    'required_param',
                    'The default sender name is required'
                );
            }

            if ( empty( $sender_email ) ) {
                throw new RequestException(
                    'required_param',
                    'The default sender email is required'
                );
            }

            EmailProviderCollection::set_default_provider( $provider_id );
            $collection->set_default_sender_name( $sender_name );
            $collection->set_default_sender_email( $sender_email );

            $response_data  = array(
                'success'   => true,
                'data'      => array(
                    'message'   => 'Saved successfully.'
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
     * Handle a request to save settings for a specific email provider.
     *
     * Reads all fields defined in the provider's settings schema from the
     * request, validates required fields, skips masked password placeholders
     * so saved credentials are never overwritten with '********', then
     * persists the full settings batch via update_provider_settings().
     *
     * Optionally sets the provider as the system default if the
     * 'set_as_default' flag is present in the request.
     *
     * @param Request $request
     * @return Response
     */
    public static function save_provider_settings( Request $request ): Response {
        try {
            static::is_system_admin();

            $collection  = EmailProviderCollection::instance();
            $provider_id = static::sanitize_text( $request->get( 'provider_id' ) );

            if ( ! $provider_id ) {
                throw new RequestException(
                    'required_param',
                    'Provider ID is required.'
                );
            }

            $provider = $collection->get_provider( $provider_id );

            if ( $provider === null ) {
                throw new RequestException(
                    'validation_failed',
                    'Invalid email provider.'
                );
            }

            $schema          = $provider->get_settings_schema();
            $saved_settings  = [];
            $missing_fields  = [];

            foreach ( $schema as $key => $field ) {
                $raw_value = $request->get( $key, null );

                // Field was not submitted at all.
                if ( $raw_value === null ) {
                    if ( ! empty( $field['required'] ) ) {
                        $missing_fields[] = $field['label'] ?? $key;
                    }
                    continue;
                }

                // Password field submitted with the masked placeholder —
                // preserve the previously saved value rather than overwriting.
                if ( $field['type'] === 'password' && $raw_value === '********' ) {
                    $saved_settings[ $key ] = EmailProviderCollection::get_option( $provider_id, $key );
                    continue;
                }

                // Sanitize by field type.
                $saved_settings[ $key ] = match ( $field['type'] ) {
                    'password' => static::sanitize_text( $raw_value ),
                    'number'   => (int) $raw_value,
                    'select'   => static::sanitize_text( $raw_value ),
                    default    => static::sanitize_text( $raw_value ),
                };

                // Validate required fields are non-empty after sanitization.
                if ( ! empty( $field['required'] ) && $saved_settings[ $key ] === '' ) {
                    $missing_fields[] = $field['label'] ?? $key;
                }
            }

            if ( ! empty( $missing_fields ) ) {
                throw new RequestException(
                    'required_param',
                    sprintf(
                        'The following fields are required: %s.',
                        implode( ', ', $missing_fields )
                    )
                );
            }

            // Validate the full settings batch against the provider before persisting.
            // This catches provider-specific rules (e.g. invalid API key format,
            // unsupported region) before anything is written to storage.
            try {
                $cloned = clone $provider;
                $cloned->set_settings( $saved_settings );
            } catch ( \InvalidArgumentException $e ) {
                throw new RequestException(
                    'validation_failed',
                    $e->getMessage()
                );
            }

            EmailProviderCollection::update_provider_settings( $provider_id, $saved_settings );

            // Optionally promote this provider to the system default.
            if ( (bool) $request->get( 'set_as_default', false ) ) {
                EmailProviderCollection::set_default_provider( $provider_id );
            }

            return ( new Response( 200, [], [
                'success' => true,
                'data'    => [
                    'message'    => sprintf( '%s settings saved successfully.', $provider->get_name() ),
                    'is_default' => EmailProviderCollection::get_default_provider_id() === $provider_id,
                ],
            ] ) )->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

/**
 * Handle a request to send a test email using a specific provider.
 *
 * Loads the provider with its persisted settings, sends a test email
 * to the address supplied in the request, and returns a response
 * indicating whether the send succeeded or failed.
 *
 * @param Request $request
 * @return Response
 */
public static function send_test_email( Request $request ): Response {
    try {
        static::is_system_admin();

        $collection  = EmailProviderCollection::instance();
        $provider_id = static::sanitize_text( $request->get( 'provider_id' ) );
        $recipient   = static::sanitize_email( $request->get( 'test_email' ) );

        if ( ! $provider_id ) {
            throw new RequestException(
                'required_param',
                'Provider ID is required.'
            );
        }

        if ( ! $recipient ) {
            throw new RequestException(
                'required_param',
                'A recipient email address is required to send a test email.'
            );
        }

        if ( ! filter_var( $recipient, FILTER_VALIDATE_EMAIL ) ) {
            throw new RequestException(
                'validation_failed',
                sprintf( '"%s" is not a valid email address.', $recipient )
            );
        }

        $provider = $collection->get_provider_with_settings( $provider_id );

        if ( $provider === null ) {
            throw new RequestException(
                'validation_failed',
                'Invalid email provider.'
            );
        }

        $site_name = smliser_settings_adapter()->get( 'repository_name', SMLISER_APP_NAME, true );

        $message = new EmailMessage( [
            'to'      => $recipient,
            'subject' => sprintf( '[%s] Test Email', $site_name ),
            'body'    => static::build_test_email_body( $site_name, $provider->get_name(), $recipient ),
        ] );

        $email_response = $provider->send( $message );

        $response_data = [
            'success' => true,
            'data'    => [
                'message'    => sprintf(
                    'Test email sent successfully to %s via %s.',
                    $recipient,
                    $provider->get_name()
                ),
                'message_id' => $email_response->get( 'message_id' ) ?? '',
            ],
        ];

        return ( new Response( 200, [], $response_data ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

    } catch ( RequestException $e ) {
        return ( new Response() )
            ->set_exception( $e )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

    } catch ( \InvalidArgumentException $e ) {
        // Provider settings are incomplete or invalid.
        return ( new Response() )
            ->set_exception( new RequestException( 'validation_failed', $e->getMessage() ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

    } catch ( \SmartLicenseServer\Exceptions\EmailTransportException $e ) {
        // Provider connected but the send was rejected.
        return ( new Response() )
            ->set_exception( new RequestException( 'send_failed', $e->getMessage() ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

    } catch ( \RuntimeException $e ) {
        // Connection-level failure — wrong credentials, network issue, etc.
        return ( new Response() )
            ->set_exception( new RequestException( 'send_failed', $e->getMessage() ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
    }
}

    /**
     * Build the HTML body for the test email.
     *
     * @param string $site_name     Repository name from system settings.
     * @param string $provider_name Display name of the provider being tested.
     * @param string $recipient     The address the test is being sent to.
     * @return string
     */
    protected static function build_test_email_body(
        string $site_name,
        string $provider_name,
        string $recipient
    ): string {
        $sent_at = ( new \DateTimeImmutable( 'now' ) )->format( 'Y-m-d H:i:s T' );

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0"
                            style="background-color:#ffffff;border-radius:6px;
                                    box-shadow:0 1px 4px rgba(0,0,0,0.08);overflow:hidden;">

                            <!-- Header -->
                            <tr>
                                <td style="background-color:#1a1a2e;padding:28px 40px;">
                                    <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">
                                        {$site_name}
                                    </p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style="padding:36px 40px;">
                                    <h1 style="margin:0 0 16px;font-size:22px;color:#1a1a2e;">
                                        Test Email
                                    </h1>
                                    <p style="margin:0 0 16px;font-size:15px;color:#444444;line-height:1.6;">
                                        This is a test email confirming that your
                                        <strong>{$provider_name}</strong> email provider
                                        is correctly configured and sending successfully.
                                    </p>
                                    <p style="margin:0 0 24px;font-size:15px;color:#444444;line-height:1.6;">
                                        If you received this message, everything is working as expected.
                                    </p>

                                    <!-- Details block -->
                                    <table width="100%" cellpadding="0" cellspacing="0"
                                        style="background-color:#f8f9fa;border-radius:4px;
                                                border:1px solid #e9ecef;">
                                        <tr>
                                            <td style="padding:20px 24px;">
                                                <p style="margin:0 0 8px;font-size:13px;
                                                        color:#6c757d;text-transform:uppercase;
                                                        letter-spacing:0.5px;">
                                                    Send Details
                                                </p>
                                                <table cellpadding="0" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td style="font-size:13px;color:#444;
                                                                padding:4px 0;width:120px;">
                                                            Provider
                                                        </td>
                                                        <td style="font-size:13px;color:#1a1a2e;
                                                                font-weight:bold;padding:4px 0;">
                                                            {$provider_name}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size:13px;color:#444;padding:4px 0;">
                                                            Sent to
                                                        </td>
                                                        <td style="font-size:13px;color:#1a1a2e;
                                                                font-weight:bold;padding:4px 0;">
                                                            {$recipient}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size:13px;color:#444;padding:4px 0;">
                                                            Sent at
                                                        </td>
                                                        <td style="font-size:13px;color:#1a1a2e;
                                                                font-weight:bold;padding:4px 0;">
                                                            {$sent_at}
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="padding:20px 40px;border-top:1px solid #e9ecef;">
                                    <p style="margin:0;font-size:12px;color:#aaaaaa;line-height:1.5;">
                                        This email was generated automatically by {$site_name}.
                                        No action is required.
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}