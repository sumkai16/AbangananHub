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
                            <h1 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #060D26; text-align: center;">
                                Your GCash payout details changed
                            </h1>
                            <p style="margin: 0 0 16px; font-size: 15px; color: #5B6A8E; line-height: 1.6; text-align: center;">
                                Hi {{ $user->first_name }}, the GCash number or account name that AbangananHub pays your rent to was just changed.
                            </p>
                            <p style="margin: 0 0 16px; font-size: 15px; color: #5B6A8E; line-height: 1.6; text-align: center;">
                                As a safety check, no payouts will be sent to you until
                                <strong style="color: #060D26;">{{ $holdUntil->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A') }}</strong>.
                                Money you are owed stays safe and goes out after that.
                            </p>
                            <p style="margin: 0 0 8px; font-size: 14px; color: #5B6A8E; line-height: 1.6; text-align: center;">
                                If this was you, there is nothing to do. If it was not you, change your password now and message us.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
