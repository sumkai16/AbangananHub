<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body
    style="margin: 0; padding: 0; background-color: #F7FCFC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F7FCFC; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0"
                    style="background-color: #FFFFFF; border-radius: 12px; padding: 48px 40px; max-width: 560px;">
                    <tr>
                        <td style="text-align: center; padding-bottom: 32px;">
                            <span style="font-size: 20px; font-weight: 700; color: #156F8C;">AbangananHub</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h1
                                style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #1F2937; text-align: center;">
                                Reset your password
                            </h1>
                            <p
                                style="margin: 0 0 8px; font-size: 15px; color: #64748B; line-height: 1.6; text-align: center;">
                                We received a request to reset the password for your <strong
                                    style="color: #1F2937;">AbangananHub</strong> account. Click the button below to
                                choose a new one.
                            </p>
                            <p style="margin: 0 0 32px; font-size: 14px; color: #64748B; text-align: center;">
                                This link will expire in 60 minutes. If you didn't request a password reset, you can
                                safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-bottom: 32px;">
                            <a href="{{ $resetUrl }}"
                                style="display: inline-block; background-color: #2AA7A1; color: #FFFFFF; font-size: 15px; font-weight: 600; text-decoration: none; padding: 14px 40px; border-radius: 8px;">
                                Reset password
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 32px;">
                            <p style="margin: 0; font-size: 12px; color: #94A3B8; line-height: 1.6; text-align: center; word-break: break-all;">
                                Or copy and paste this link into your browser:<br>
                                <a href="{{ $resetUrl }}" style="color: #2AA7A1;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #E2E8F0; padding-top: 24px;">
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
