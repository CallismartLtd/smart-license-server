<?php
/**
 * Test email template file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

defined( 'SMLISER_ABSPATH' ) || exit;

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