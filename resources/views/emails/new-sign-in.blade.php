<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body
    style="margin: 0; padding: 0; background-color: #F7F8FC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F7F8FC; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0"
                    style="background-color: #FFFFFF; border-radius: 12px; padding: 48px 40px; max-width: 560px;">
                    <tr>
                        <td style="text-align: center; padding-bottom: 32px;">
                            <span style="font-size: 20px; font-weight: 700; color: #060D26;">AbangananHub</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h1
                                style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #060D26; text-align: center;">
                                New sign-in to your account
                            </h1>
                            <p
                                style="margin: 0 0 24px; font-size: 15px; color: #5B6A8E; line-height: 1.6; text-align: center;">
                                Someone signed in to your <strong style="color: #060D26;">AbangananHub</strong> account
                                from a device we have not seen lately.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0"
                                style="background-color: #F7F8FC; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #5B6A8E;">When</td>
                                    <td style="padding: 12px 16px; font-size: 14px; color: #060D26; text-align: right;">{{ $when }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #5B6A8E; border-top: 1px solid #E2E4EC;">Device</td>
                                    <td style="padding: 12px 16px; font-size: 14px; color: #060D26; text-align: right; border-top: 1px solid #E2E4EC;">{{ $device }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; font-size: 13px; color: #5B6A8E; border-top: 1px solid #E2E4EC;">IP address</td>
                                    <td style="padding: 12px 16px; font-size: 14px; color: #060D26; text-align: right; border-top: 1px solid #E2E4EC;">{{ $ip ?: 'Unknown' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p style="margin: 0 0 8px; font-size: 14px; color: #5B6A8E; line-height: 1.6; text-align: center;">
                                If this was you, you can ignore this email.
                            </p>
                            <p style="margin: 0 0 32px; font-size: 14px; color: #5B6A8E; line-height: 1.6; text-align: center;">
                                If it was not, reset your password now.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-bottom: 32px;">
                            <a href="{{ $resetUrl }}"
                                style="display: inline-block; background-color: #FF8A66; color: #060D26; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 40px; border-radius: 8px;">
                                Reset my password
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #E2E4EC; padding-top: 24px;">
                            <p style="margin: 0; font-size: 12px; color: #94A3B8; text-align: center;">
                                AbangananHub — Secure Rental Accommodation Platform
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
