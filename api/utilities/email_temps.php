<?php
// ========================================
// EMAIL TEMPLATES - Maveren Capital (Optimized and Consistent)
// ========================================
/**
 * Returns all email templates used in the system.
 * Each template includes 'subject' and 'html' keys.
 *
 * Usage (in email.php):
 * $templates = getEmailTemplates();
 * $templates['deposit_initiated']['html'];
 */
function getEmailTemplates() {
    $year = date('Y');
    // Absolute URL, because mail clients have no page to resolve a relative
    // path against. The masthead band is brand ink, so this is the ORANGE mark;
    // it is a 72px PNG (2x the 36px render) rather than the 2000px master, so
    // the mail stays small enough that Gmail does not clip it.
    $logoUrl = 'https://maverencapital.com/assets/images/logo/mvc-mark-email.png';
    $appName = 'Maveren Capital';
    $supportEmail = 'support@maverencapital.com'; // Define support email for easy updates
    $websiteUrl = 'https://maverencapital.com/'; // Define main website URL
    $adminUrl = 'https://maverencapital.com/admin'; // Define Admin Login URL

    // MVC email palette. Deliberately light-mode only: email clients have no
    // reliable prefers-color-scheme support, and the dark UI palette does not
    // survive Outlook's renderer. Brand orange carries the identity instead.
    $colors = [
        'primary'           => '#B04102',   // Brand orange, darkened for AA on white
        'primary_light'     => '#FFFFFF',   // Email outer body - white
        'surface'           => '#FFFFFF',   // Email card surface - white
        'background'        => '#FAF6F4',   // Warm neutral for data blocks
        'text'              => '#16232A',   // Body text - brand ink
        'muted'             => '#526670',   // Muted text
        // Opaque hex, NOT rgba(). Outlook desktop renders through Word, which
        // does not support rgba() and drops the whole declaration - so every
        // hairline in the email (card outline, data blocks, footer divider)
        // silently vanished there. #E8E4E2 is the flattened equivalent of
        // rgba(22,35,42,0.10) over the white card, so it looks identical in
        // clients that DO support alpha.
        'border'            => '#E8E4E2',   // Hairline border
        'success'           => '#15803D',   // Success green
        'danger'            => '#B91C1C',   // True red, reserved for security alerts
        'warning_bg'        => '#FEF3C7',   // Warning block background (warm light)
        'warning_border'    => '#F59E0B',   // Warning block border (amber)
        'highlight_text'    => '#16232A',   // Contrast text color
        'header_bg'         => '#16232A',   // Masthead - brand ink
        'accent'            => '#FF5B04',   // True brand orange for fills/rules
    ];

    // The font stack, defined once.
    //
    // READ THIS BEFORE EDITING ANY style ATTRIBUTE BELOW. In a PHP DOUBLE-quoted
    // string, \' is not an escape sequence - PHP has no such escape, so the
    // backslash AND the quote both survive into the output verbatim. Three
    // attributes here were written as
    //     style='... font-family: \'Segoe UI\', Tahoma ...'
    // which reached the client as a single-quoted attribute containing a raw
    // single quote. The parser closes the attribute at that quote, so email
    // sanitizers threw the whole style declaration away - and with it the body
    // cell's 32px 28px padding (content hugged the card border) and the CTA
    // link's padding/colour/text-decoration (the button arrived as underlined
    // text inside a bare orange box).
    //
    // The fix, used everywhere the stack appears: a DOUBLE-quoted HTML attribute
    // (escaped \" for PHP) wrapping SINGLE-quoted CSS strings. No quote
    // character ever collides with its own delimiter. Same pattern the <body>
    // tag and the masthead already use.
    $font = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

    // --- Reusable HTML Blocks ---

    // 1. Consistent Data Block for Amounts, References, etc.
    $dataBlockStyle = "background-color: {$colors['background']}; padding: 16px; margin: 18px 0; border-radius: 6px; border: 1px solid {$colors['border']};";

    // 2. Consistent Alert Block for Security/Cancellation/Warning
    $alertBlockStyle = "background-color: {$colors['background']}; border-left: 4px solid {{color}}; padding: 14px 18px; margin: 20px 0; border-radius: 0 6px 6px 0;";

    // Masthead - brand ink band carrying the lockup: leaf mark left, company
    // name right.
    //
    // The name is live text, not part of the image, for two reasons. Outlook
    // and Gmail block remote images by default on first view, so a single
    // wordmark image left the masthead as an empty box for most first-time
    // recipients - now the brand still reads. And Word ignores CSS sizing on
    // images, so the mark carries explicit width/height ATTRIBUTES as well.
    //
    // Two <td>s in a nested table rather than an inline-block: Word does not
    // support inline-block, so the name would have wrapped under the mark.
    // alt='' on the mark keeps the blocked-image placeholder from repeating the
    // company name that already sits next to it.
    //
    // A <tr>, NOT a standalone <table>. $header and $footer are spliced straight
    // into the outer .mvc-card <table>, and a <table> is not a permitted child
    // of <table> - the HTML "in table" insertion mode treats a nested <table>
    // start tag as an implied </table>. So the card closed at the masthead and
    // every row after it, including the body cell that carries the 32px 28px
    // padding, was foster-parented out of the table with its <tr>/<td> tags
    // DISCARDED. There was no cell left to pad, which is why the body copy sat
    // flush against the card border. The lockup table below is fine - it is
    // inside a <td>, which is a legal place for a table.
    $header = "
            <tr>
                <td style='background:{$colors['header_bg']}; padding: 18px 28px; border-bottom: 1px solid {$colors['primary']};'>
                    <a href='{$websiteUrl}' target='_blank' style='text-decoration:none;'>
                        <table role='presentation' cellspacing='0' cellpadding='0' border='0'>
                            <tr>
                                <td valign='middle' style='padding-right:10px; line-height:0;'>
                                    <img src='{$logoUrl}' alt='' width='36' height='36' style='display:block; width:36px; height:36px; border:0; outline:none; text-decoration:none;'>
                                </td>
                                <td valign='middle' style=\"font-family:{$font}; font-size:18px; line-height:22px; mso-line-height-rule: exactly; font-weight:600; letter-spacing:-0.2px; color:{$colors['primary_light']}; white-space:nowrap;\">{$appName}</td>
                            </tr>
                        </table>
                    </a>
                </td>
            </tr>";

    // Footer structure. A <tr>, not a <table> - same reason as $header above.
    $footer = "
            <tr>
                <td style=\"background:{$colors['background']}; padding: 20px 28px; text-align: center; font-family: {$font}; font-size: 12px; line-height: 18px; mso-line-height-rule: exactly; color:{$colors['muted']}; border-top: 1px solid {$colors['border']};\">
                    <p style='margin: 8px 0;'>&copy; {$year} {$appName}. All rights reserved.</p>
                    <p style='margin: 8px 0; font-size: 11px;'>
                        If you have any questions, feel free to contact us at 
                        <a href='mailto:{$supportEmail}' style='color:{$colors['primary']}; text-decoration: none;'>{$supportEmail}</a>.
                    </p>
                    <p style='margin: 8px 0; font-size: 11px;'>
                        <a href='{$websiteUrl}' style='color:{$colors['primary']}; text-decoration: none;'>Visit our Website</a> |
                        <a href='{$websiteUrl}pages/public/privacy.php' style='color:{$colors['primary']}; text-decoration: none;'>Privacy Policy</a>
                    </p>
                </td>
            </tr>";

    // --- Shared body pieces -------------------------------------------
    // Every template used to hand-roll its own heading colour (text / danger /
    // success / primary, chosen arbitrarily - a routine signup confirmation
    // rendered in alarm red) and its own CTA colour (three different ones for
    // the same visual role). These three helpers are the single source now.

    // Section heading. One colour: brand ink. Severity is carried by the alert
    // block and the copy, not by shouting the headline in red.
    $h2 = fn($text) => "<h2 style=\"margin: 0 0 16px 0; font-family: {$font}; font-size: 22px; line-height: 1.3; mso-line-height-rule: exactly; font-weight: 600; color: {$colors['text']};\">{$text}</h2>";

    // Call to action. Brand orange with ink text - 5.16:1, where white on the
    // same orange is 3.11:1. Table-wrapped so Outlook's Word renderer keeps the
    // padding, which a bare <a> loses.
    // The <!--[if mso]> block is a VML roundrect that ONLY Outlook desktop
    // parses; every other client ignores it as a comment and renders the
    // table. Without it Word drops the border-radius AND collapses the <a>'s
    // padding, so the button arrived as bare underlined text.
    $cta = fn($label, $href) => "
        <div style='text-align:center; margin: 26px 0;'>
        <!--[if mso]>
        <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' xmlns:w='urn:schemas-microsoft-com:office:word'
                     href='{$href}' style='height:44px;v-text-anchor:middle;width:220px;' arcsize='14%'
                     stroke='f' fillcolor='{$colors['accent']}'>
          <w:anchorlock/>
          <center style=\"color:{$colors['highlight_text']};font-family:'Segoe UI',Tahoma,sans-serif;font-size:15px;font-weight:600;\">{$label}</center>
        </v:roundrect>
        <![endif]-->
        <!--[if !mso]><!-- -->
        <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' style='margin: 0 auto;'>
            <tr><td align='center' style='background-color: {$colors['accent']}; border-radius: 6px;'>
                <a href='{$href}' target='_blank' style=\"display: inline-block; padding: 13px 30px; font-family: {$font}; font-size: 15px; font-weight: 600; line-height: 18px; mso-line-height-rule: exactly; color: {$colors['highlight_text']}; text-decoration: none;\">{$label}</a>
            </td></tr>
        </table>
        <!--<![endif]-->
        </div>";

    // Sign-off. Was a mix of "Best regards" / "Kind regards" / "Warmly" /
    // "Warm regards" chosen at random per template.
    $signoff = "<p style='margin: 24px 0 0 0;'>Kind regards,<br><strong>The {$appName} Team</strong></p>";

    // Base HTML wrapper.
    //
    // Two bugs lived here. The <body> style attribute was single-quoted and
    // contained 'Segoe UI', so it terminated at that inner quote and the whole
    // declaration was silently dropped. And the CSS sat in <head>, which Gmail
    // strips in several contexts - the .button class defined there was used by
    // no template at all, since every one repeated the button inline.
    //
    // Everything that matters is inline now. The remaining <head> block is
    // progressive enhancement only: a mobile padding rule, which cannot be
    // expressed inline.
    $wrap = fn($content) => "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta name='x-apple-disable-message-reformatting'>
            <title>" . htmlspecialchars($appName) . " Notification</title>
            <style type='text/css'>
                @media only screen and (max-width: 600px) {
                    .mvc-pad { padding: 24px 18px !important; }
                    .mvc-card { border-radius: 0 !important; margin: 0 !important; }
                }
            </style>
        </head>
        <body style=\"margin:0; padding:0; width:100%; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color:{$colors['primary_light']};\">
            <!-- Preheader: the preview line inboxes show beside the subject.
                 Zero-height so it never renders in the opened mail. -->
            <div style='display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:{$colors['primary_light']};'>{{preheader}}</div>
            <center style='width: 100%; background-color: {$colors['primary_light']};'>
            <!--[if mso | IE]>
            <table role='presentation' width='600' cellspacing='0' cellpadding='0' border='0' align='center'><tr><td>
            <![endif]-->
            <!-- Word ignores max-width entirely, so without the 600px table
                 above the email stretched to the full Outlook window width. -->
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' align='center' class='mvc-card' style='width: 100%; max-width: 600px; background-color: {$colors['surface']}; border: 1px solid {$colors['border']}; border-radius: 8px; overflow: hidden; margin: 20px auto;'>
                {$header}
                <tr>
                    <td class='mvc-pad' style=\"padding: 32px 28px; font-family: {$font}; font-size: 15px; line-height: 24px; mso-line-height-rule: exactly; color: {$colors['text']};\">
                        {$content}
                    </td>
                </tr>
                {$footer}
            </table>
            <!--[if mso | IE]>
            </td></tr></table>
            <![endif]-->
            </center>
        </body>
        </html>";

    // ------------------------------
    // Template definitions
    // ------------------------------
    return [
        'login_alert' => [
            'subject' => '[Security Alert] New Login Detected on Your ' . $appName . ' Account',
            'preheader' => 'A new sign-in to your account. If this was you, nothing to do.',
            'html' => $wrap("
                " . $h2("New Login Detected on Your Account") . "
                <p>Dear {{user_name}},</p>
                <p>We noticed a recent login to your <strong>{$appName}</strong> account. Please review the details below immediately.</p>

                " . str_replace(['{{color}}', '{{content}}'], [$colors['danger'], "
                    <p style='margin: 6px 0;'><strong>Date & Time:</strong> {{login_time}}</p>
                    <p style='margin: 6px 0;'><strong>IP Address:</strong> {{ip}}</p>
                    <p style='margin: 6px 0;'><strong>Browser:</strong> {{browser}}</p>
                    <p style='margin: 6px 0;'><strong>Location:</strong> {{location}}</p>
                "], "<div style='{$alertBlockStyle}'>{{content}}</div>") . "

                <p>If this was you, no further action is needed.</p>

                <p>If you <strong>did not authorize</strong> this login, please take the following immediate action:</p>
                <ul style='padding-left: 20px;'>
                    <li><strong>Immediately reset your password</strong> using the secure link below.</li>
                    <li>Review your dashboard activity for unauthorized transactions.</li>
                    <li>Contact our support team at <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</li>
                </ul>

                " . $cta("Reset Password", "{$websiteUrl}forgotpassword") . "

                " . $signoff . "
            "),
        ],
        'admin_login_alert' => [
            'subject' => '[Admin Security Alert] New Login to Your ' . $appName . ' Admin Account',
            'preheader' => 'An administrator signed in to the panel.',
            'html' => $wrap("
                " . $h2("Admin Login Detected") . "
                <p>Dear {{admin_name}},</p>
                <p>A new login to your <strong>{$appName}</strong> <em>admin account</em> was detected.</p>

                " . str_replace(['{{color}}', '{{content}}'], [$colors['danger'], "
                    <p style='margin:0; color: {$colors['text']}; line-height:1.6;'>
                        <strong>Login Time:</strong> {{login_time}}<br>
                        <strong>IP Address:</strong> {{ip}}<br>
                        <strong>Browser:</strong> {{browser}}<br>
                        <strong>Location:</strong> {{location}}
                    </p>
                "], "<div style='{$alertBlockStyle}'>{{content}}</div>") . "

                <p>If this was you, no further action is needed. Due to the sensitivity of this account, please:</p>
                <ul style='padding-left:20px;'>
                    <li>Change your password immediately via the Admin Dashboard.</li>
                    <li>Review recent activity for unauthorized changes.</li>
                    <li>Contact our Security Team if the login is unfamiliar.</li>
                </ul>

                " . $cta("Go to Admin Dashboard", "{$adminUrl}") . "

                " . $signoff . "
            "),
        ],
        'admin_user_login_notification' => [
            'subject' => '[User Login Alert] A User Has Just Logged In',
            'preheader' => 'A member signed in to their account.',
            'html' => $wrap("
                " . $h2("User Login Detected") . "
                <p>Hello Admin,</p>
                <p>A user just logged into their {$appName} account. This notification is for your records.</p>

                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>User Name:</strong> {{user_name}}</p>
                    <p style='margin: 6px 0;'><strong>Email:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>Login Time:</strong> {{login_time}}</p>
                    <p style='margin: 6px 0;'><strong>IP:</strong> {{ip}}</p>
                    <p style='margin: 6px 0;'><strong>Browser:</strong> {{browser}}</p>
                    <p style='margin: 6px 0;'><strong>Location:</strong> {{location}}</p>
                </div>

                " . $signoff . "
            "),
        ],
        'welcome_user' => [
            'subject' => 'Welcome to ' . $appName . '!',
            'preheader' => 'Your account is verified and ready to fund.',
            'html' => $wrap("
                " . $h2("Welcome Aboard!") . "
                <p>Dear {{user_name}},</p>
                <p>Congratulations on joining the <strong>{$appName}</strong> community! We are thrilled to have you as a member.</p>
                <p>Your account has been successfully created, and your personalized digital wallet is now active and ready for you to explore.</p>
                
                <h3 style='font-size: 18px; color: {$colors['primary']}; margin-top: 30px; margin-bottom: 12px;'>What You Can Do Next:</h3>
                <ul style='padding-left: 20px;'>
                    <li><strong>Deposit Funds:</strong> Easily add money to your wallet using various secure methods.</li>
                    <li><strong>Track everything:</strong> Follow each payout as it lands and support those in need.</li>
                    <li><strong>Explore Investments:</strong> Discover opportunities to grow your funds while contributing positively.</li>
                </ul>
                <p>We believe that together, we can create a healthier and more caring world. Your journey with us starts now!</p>
                
                <p style='margin-top: 24px;'>If you have any questions or need assistance, our friendly support team is always here for you at <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</p>
                
                " . $signoff . "
            "),
        ],
        'welcome_admin' => [
            'subject' => 'Your ' . $appName . ' admin account is ready',
            'preheader' => 'Your administrator account is live.',
            'html' => $wrap("
                " . $h2("Welcome to the Admin Team") . "
                <p>Hi {{admin_name}},</p>
                <p>Your administrator account for <strong>{$appName}</strong> has been created successfully.</p>

                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Account:</strong> {{admin_email}}</p>
                    <p style='margin: 6px 0;'><strong>Role:</strong> {{admin_role}}</p>
                </div>

                <p>Next steps:</p>
                <ul style='padding-left:20px;'>
                    <li>Sign in securely here: <a href='{$adminUrl}' style='color:{$colors['primary']};'>Admin Dashboard</a></li>
                    <li>Update your profile and set a strong, unique password.</li>
                </ul>

                <p>If you did not request this account, please contact <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a> immediately.</p>

                " . $signoff . "
            ")
        ],
        'deposit_initiated' => [
            'subject' => 'Deposit Request Received!',
            'preheader' => 'We have your deposit request and are waiting on the transfer.',
            'html' => $wrap("
                " . $h2("Deposit Request Confirmed") . "
                <p>Hi {{user_name}},</p>
                <p>Thank you for choosing {$appName} to add funds to your wallet. We have successfully received your deposit request.</p>
                
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Deposit Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Payment Method:</strong> {{method}}</p>
                    <p style='margin: 6px 0;'><strong>Transaction Reference:</strong> {{reference}}</p>
                </div>
                
                <p>Our team is currently reviewing your request. You will receive an email shortly with specific instructions and payment details (e.g., bank details or wallet address) based on your chosen method.</p>
                <p>Please follow the instructions carefully to complete your deposit. The funds will be added to your wallet once payment is confirmed.</p>
                
                " . $signoff . "
            "),
        ],
        'admin_deposit_notification' => [
            'subject' => 'New Deposit Request!',
            'preheader' => 'A deposit is waiting for review.',
            'html' => $wrap("
                " . $h2("New Deposit Request Awaiting Action") . "
                <p>Hello Admin,</p>
                <p>A new deposit request has been submitted by a user and requires your attention.</p>
                
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>User Name:</strong> {{user_name}}</p>
                    <p style='margin: 6px 0;'><strong>User Email:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>Deposit Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Payment Method:</strong> {{method}}</p>
                    <p style='margin: 6px 0;'><strong>Transaction Reference:</strong> {{reference}}</p>
                </div>
                
                <p>Please log in to the admin dashboard to review this request and provide the necessary payment details to the user.</p>
                
                " . $signoff . "
            "),
        ],
        'deposit_details_provided' => [
            'subject' => 'Deposit Instructions Ready!',
            'preheader' => 'Your transfer details are ready - here is where to send it.',
            'html' => $wrap("
                " . $h2("Deposit Instructions Ready") . "
                <p>Hi {{user_name}},</p>
                <p>Great news! The payment details for your deposit request (Reference: <strong>{{reference}}</strong>) are now ready. Please proceed with your deposit of <strong>\${{amount}}</strong> using the information below.</p>
                
                " . str_replace(['{{color}}', '{{content}}'], [$colors['primary'], "
                    <p style='margin: 0; color: {$colors['text']};'><strong>Deposit Address/Details:</strong><br>{{deposit_address}}</p>
                "], "<div style='{$alertBlockStyle}'>{{content}}</div>") . "
                
                <p><strong>Important:</strong> Please ensure the amount sent matches exactly \${{amount}} and use the provided details precisely. Any mismatch may cause delays.</p>
                <p>After sending the payment, please return to your wallet dashboard and click the 'I Have Paid' button for this transaction to notify us so we can quickly confirm your deposit.</p>
                
                " . $signoff . "
            "),
        ],
        'deposit_confirmed' => [
            'subject' => 'Success! Your Deposit Has Been Approved and Credited!',
            'preheader' => 'Your deposit has cleared and your wallet is credited.',
            'html' => $wrap("
                " . $h2("Deposit Approved & Credited") . "
                <p>Hi {{user_name}},</p>
                <p>Great news! Your deposit request with the reference <strong>{{reference}}</strong> has been <strong>approved</strong> and the funds have now been safely added to your {$appName} wallet.</p>

                <div style='{$dataBlockStyle}; text-align: center; border-left: 4px solid {$colors['success']};'>
                    <p style='margin: 0; font-size: 16px; color: {$colors['text']};'>
                        <strong>Amount Credited:</strong> \${{amount}}
                    </p>
                </div>

                <p>You can now use your wallet balance to make donations, explore investment plans, or participate in other rewarding programs within {$appName}.</p>
                
                " . $signoff . "
            "),
        ],

        'deposit_cancelled' => [
            'subject' => 'Your Deposit Request Has Been Cancelled',
            'preheader' => 'We could not complete your deposit request.',
            'html' => $wrap("
                " . $h2("Deposit Request Cancelled") . "
                <p>Hi {{user_name}},</p>
                <p>We regret to inform you that your pending deposit request (Reference: <strong>{{reference}}</strong>) for <strong>\${{amount}}</strong> has been <strong>cancelled</strong> by our team.</p>

                " . str_replace(['{{color}}', '{{content}}'], [$colors['danger'], "
                    <p style='margin: 0; color: {$colors['text']};'><strong>Reason for Cancellation:</strong> {{reason}}</p>
                "], "<div style='{$alertBlockStyle}'>{{content}}</div>") . "
                
                <p>This may occur when payment cannot be verified or requested details are incomplete. If you believe this decision was made in error or need clarification, please contact our support team at <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</p>
                
                " . $signoff . "
            "),
        ],
        'admin_payment_confirmed' => [
            'subject' => 'User Confirmed Payment for Deposit!',
            'preheader' => 'A member marked a deposit as paid.',
            'html' => $wrap("
                " . $h2("User Payment Confirmed") . "
                <p>Hello Admin,</p>
                <p>A user has marked a pending deposit as paid in their dashboard. <strong>Manual verification is required.</strong></p>
                
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>User Name:</strong> {{user_name}}</p>
                    <p style='margin: 6px 0;'><strong>User Email:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>Deposit Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Payment Method:</strong> {{method}}</p>
                    <p style='margin: 6px 0;'><strong>Transaction Reference:</strong> {{reference}}</p>
                    <p style='margin: 6px 0;'><strong>Confirmation Details:</strong> {{details}}</p>
                </div>
                
                <p>Please log in to the admin panel to verify the payment manually and finalize the deposit process.</p>
                
                " . $signoff . "
            "),
        ],
        'withdrawal_initiated' => [
            'subject' => 'Withdrawal Request Submitted!',
            'preheader' => 'Your withdrawal request is in the queue.',
            'html' => $wrap("
                " . $h2("Withdrawal Request Received") . "
                <p>Hi {{user_name}},</p>
                <p>Your request to withdraw funds from your {$appName} wallet has been successfully submitted. The details are below:</p>
                
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Withdrawal Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Withdrawal Method:</strong> {{method}}</p>
                    <p style='margin: 6px 0;'><strong>Transaction Reference:</strong> {{reference}}</p>
                </div>
                
                <p>Your wallet balance has been temporarily adjusted to reflect this pending request. You will receive an email notification as soon as the status of your withdrawal changes (approved or declined).</p>
                
                " . $signoff . "
            "),
        ],
        'admin_withdrawal_notification' => [
            'subject' => 'New Withdrawal Request!',
            'preheader' => 'A withdrawal is waiting for review.',
            'html' => $wrap("
                " . $h2("New Withdrawal Request Awaiting Review") . "
                <p>Hello Admin,</p>
                <p>A user has submitted a new withdrawal request that requires your review and action. The details are below:</p>
                
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>User Name:</strong> {{user_name}}</p>
                    <p style='margin: 6px 0;'><strong>User Email:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>Withdrawal Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Withdrawal Method:</strong> {{method}}</p>
                    <p style='margin: 6px 0;'><strong>Transaction Reference:</strong> {{reference}}</p>
                    {{details_html}} 
                </div>
                
                <p>Please log in to the admin panel to review the details and either approve or decline this request according to our policies.</p>
                
                " . $signoff . "
            "),
        ],
        'withdrawal_approved' => [
            'subject' => 'Great News! Your Withdrawal Has Been Approved!',
            'preheader' => 'Your withdrawal is approved and on its way.',
            'html' => $wrap("
                " . $h2("Withdrawal Approved!") . "
                <p>Hi {{user_name}},</p>
                <p>We are pleased to inform you that your withdrawal request (Reference: <strong>{{reference}}</strong>) has been successfully reviewed and <strong>approved</strong> by our team.</p>
                
                <div style='{$dataBlockStyle}; text-align: center; border-left: 4px solid {$colors['success']};'>
                    <p style='margin: 0; font-size: 16px; color: {$colors['text']};'>
                        <strong>Approved Amount:</strong> \${{amount}}
                    </p>
                </div>
                
                <p>The funds of \${{amount}} will be transferred to your designated account (via <strong>{{method}}</strong>) within the next 1-3 business days. Please allow for standard processing time.</p>
                
                " . $signoff . "
            "),
        ],
        'withdrawal_declined' => [
            'subject' => 'Withdrawal Request Declined',
            'preheader' => 'We could not complete your withdrawal request.',
            'html' => $wrap("
                " . $h2("Withdrawal Request Status Update") . "
                <p>Hi {{user_name}},</p>
                <p>We regret to inform you that your withdrawal request for \${{amount}} (Reference: <strong>{{reference}}</strong>) has been <strong>declined</strong>.</p>
                <p>The funds associated with this request have been returned to your {$appName} wallet balance. You can access them immediately.</p>
                
                " . str_replace(['{{color}}', '{{content}}'], [$colors['danger'], "
                    <p style='margin: 0; color: {$colors['text']};'><strong>Reason:</strong> {{reason}}</p>
                "], "<div style='{$alertBlockStyle}'>{{content}}</div>") . "
                
                <p>If you need further clarification, please contact our support team at <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</p>
                
                " . $signoff . "
            "),
        ],
        // ========================== INVESTMENT EMAILS ==========================
        'investment_confirmed' => [
            'subject' => 'Your Investment Has Been Started Successfully',
            'preheader' => 'Your position is open and the payout schedule has started.',
            'html' => $wrap("
                " . $h2("Investment Confirmed") . "
                <p>Hi {{user_name}},</p>
                <p>Your investment in <strong>{{plan_name}}</strong> has been successfully initiated.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Amount invested:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Rate:</strong> {{roi_percent}}% per {{cadence}} period</p>
                    <p style='margin: 6px 0;'><strong>You receive:</strong> \${{per_payout}} every {{cadence}} period</p>
                    <p style='margin: 6px 0;'><strong>Number of payouts:</strong> {{payouts_total}}</p>
                    <p style='margin: 6px 0;'><strong>First payout:</strong> {{first_payout}}</p>
                    <p style='margin: 6px 0;'><strong>Maturity date:</strong> {{maturity_date}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                </div>
                <p>Each payout is credited straight to your wallet and is available to
                withdraw immediately. Your original \${{amount}} is returned in full on
                the maturity date, on top of every payout you have already received.</p>
                <p>Thank you for trusting <strong>{$appName}</strong> with your investment. You can monitor its progress anytime in your dashboard.</p>
                " . $signoff . "
            "),
        ],

        'investment_payout' => [
            'subject' => 'Your scheduled payout has been credited',
            'preheader' => 'A scheduled payout just landed in your wallet.',
            'html' => $wrap("
                " . $h2("Payout Credited") . "
                <p>Hi {{user_name}},</p>
                <p>A scheduled payout from <strong>{{plan_name}}</strong> has just landed in your wallet.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Amount credited:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Schedule:</strong> {{cadence}}</p>
                    <p style='margin: 6px 0;'><strong>Payouts so far:</strong> {{payouts_made}} of {{payouts_total}}</p>
                    <p style='margin: 6px 0;'><strong>Next payout:</strong> {{next_payout}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                </div>
                <p>The funds are available to withdraw now, or you can leave them in your
                wallet to put towards another plan.</p>
                " . $cta("View your positions", "{$websiteUrl}dashboard.invest") . "
                " . $signoff . "
            "),
        ],

        'admin_investment_notification' => [
            'subject' => 'New Investment Started on ' . $appName,
            'preheader' => 'A member opened a new position.',
            'html' => $wrap("
                " . $h2("New Investment Alert") . "
                <p>Hello Admin,</p>
                <p>A new investment has been started by <strong>{{user_name}}</strong> ({{user_email}}).</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Plan:</strong> {{plan_name}}</p>
                    <p style='margin: 6px 0;'><strong>Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                </div>
                <p>Please log in to the admin dashboard to review this investment.</p>
                <p style='margin-top:24px;'><strong>{$appName} System</strong></p>
            "),
        ],

        // ========================== INVESTMENT EMAILS ==========================
        // 11 templates were removed here: trustfund_*, maintenance_* and
        // donation_* were written for a previous product (the maintenance ones
        // still described the inherited product's own equipment and service
        // windows), and weekly_investment_update was never sent by anything.
        // None had a call site anywhere in the repo. ~470 dead lines.
        // ========================== KYC EMAILS ==========================
        'kyc_submitted' => [
            'subject' => 'We received your verification documents',
            'preheader' => 'Your documents are in the review queue.',
            'html' => $wrap("
                " . $h2("Documents Received") . "
                <p>Hi {{user_name}},</p>
                <p>Your identity documents have been received and are now in the review queue.
                You do not need to do anything else.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Document type:</strong> {{id_type}}</p>
                    <p style='margin: 6px 0;'><strong>Submitted:</strong> {{submitted_at}}</p>
                </div>
                <p>We will email you as soon as a decision is made. Withdrawals stay closed
                until then; deposits and investments are unaffected and remain open.</p>
                <p>Your documents are visible only to our compliance team and are never shown
                on your profile. We will never email you to ask for them again.</p>
                " . $signoff . "
            "),
        ],

        'admin_kyc_notification' => [
            'subject' => 'New identity verification awaiting review',
            'preheader' => 'A member has submitted KYC documents.',
            'html' => $wrap("
                " . $h2("KYC Submission Awaiting Review") . "
                <p>Hello Admin,</p>
                <p>A member has submitted identity documents and is waiting on a decision.
                Verification gates withdrawals, so this member cannot withdraw until it is
                reviewed.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Member:</strong> {{user_name}}</p>
                    <p style='margin: 6px 0;'><strong>Email:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>Document type:</strong> {{id_type}}</p>
                    <p style='margin: 6px 0;'><strong>Country of issue:</strong> {{country}}</p>
                    <p style='margin: 6px 0;'><strong>Submitted:</strong> {{submitted_at}}</p>
                </div>
                " . $cta("Review this submission", "{$websiteUrl}admin.kyc") . "
                " . $signoff . "
            "),
        ],

        'kyc_approved' => [
            'subject' => 'Your identity has been verified',
            'preheader' => 'Verification complete - withdrawals are now open on your account.',
            'html' => $wrap("
                " . $h2("Identity Verified") . "
                <p>Hi {{user_name}},</p>
                <p>Your documents have been reviewed and your identity is verified. Withdrawals
                are now open on your account, and nothing further is needed from you.</p>
                <p>Your documents are stored securely and are visible only to our compliance
                team. We will never email you to ask for them again.</p>
                " . $cta("Go to your wallet", "{$websiteUrl}dashboard.wallet") . "
                " . $signoff . "
            "),
        ],

        'kyc_rejected' => [
            'subject' => 'We could not verify your identity documents',
            'preheader' => 'Your submission needs a correction before we can approve it.',
            'html' => $wrap("
                " . $h2("Verification Needs Attention") . "
                <p>Hi {{user_name}},</p>
                <p>We were not able to verify your account from the documents you sent. This is
                usually something small - a blurred edge, a cropped corner, or a document that
                has expired.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>What needs fixing:</strong> {{reason}}</p>
                </div>
                <p>You can submit again straight away. Photograph the document flat, in good
                light, with all four corners visible and nothing covering the text.</p>
                " . $cta("Submit again", "{$websiteUrl}dashboard.kyc") . "
                " . $signoff . "
            "),
        ],

        'investment_matured' => [
            'subject' => 'Your investment has matured and your principal is released',
            'preheader' => 'Your position reached maturity and the principal is back.',
            'html' => $wrap("
                " . $h2("Investment Matured") . "
                <p>Hi {{user_name}},</p>
                <p>Your <strong>{{plan_name}}</strong> position has reached its maturity date and
                your original capital has been released back to your wallet.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Principal returned:</strong> \${{principal}}</p>
                    <p style='margin: 6px 0;'><strong>Total payouts received:</strong> \${{roi_earned}}</p>
                    <p style='margin: 6px 0;'><strong>Total value of this position:</strong> \${{payout}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                </div>
                <p>Your payouts were credited period by period throughout the term, so this
                final transfer is the principal only.</p>
                " . $cta("Start another plan", "{$websiteUrl}dashboard.invest") . "
                " . $signoff . "
            "),
        ],
        // ===============================
        // 📧 LOCKED-PLAN EMAIL TEMPLATES
        // ===============================

        'admin_broadcast' => [
            // Subject will be dynamically set by admin input
            'preheader' => 'A message from the Maveren Capital team.',
            'subject' => '{{subject_line}}', 
            'html' => $wrap("
                " . $h2("{{subject_line}}") . "
                <p>Dear {{user_name}},</p>
                <div style='background-color: {$colors['background']}; padding: 18px; margin: 20px 0; border-radius: 8px; border: 1px solid {$colors['border']};'>
                    {{message_body}}
                </div>
                <p>This is a direct message from the Maveren Capital Administration team.</p>
                <p>If you have questions, reply to this email or contact <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</p>
                " . $signoff . "
            "),
        ],
        'password_reset' => [
            'subject' => 'Password Reset Request - Your OTP Code for ' . $appName,
            'preheader' => 'Your password reset code, valid for 10 minutes.',
            'html' => $wrap("
                " . $h2("Password Reset Requested") . "
                <p>Hi {{user_name}},</p>
                <p>We received a request to reset the password for your <strong>{$appName}</strong> account associated with this email address.</p>
                <p>To proceed with resetting your password, please use the following One-Time Password (OTP) code:</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <span style='display: inline-block; padding: 15px 30px; font-size: 32px; font-weight: bold; letter-spacing: 5px; background-color: {$colors['primary_light']}; color: {$colors['primary']}; border-radius: 8px; border: 2px dashed {$colors['primary']};'>
                        {{otp}}
                    </span>
                </div>
                <p style='text-align: center;'><strong>This code is valid for 10 minutes.</strong></p>
                <p>If you did not request this password reset, you can safely ignore this email. Do not share this code with anyone.</p>
                " . $signoff . "
            "),
        ],
        'email_verification' => [
            'subject' => 'Verify your email with your ' . $appName . ' code',
            'preheader' => 'Your verification code, valid for 10 minutes.',
            'html' => $wrap("
                " . $h2("Confirm Your Email Address") . "
                <p>Hi {{user_name}},</p>
                <p>Thanks for signing up with <strong>{$appName}</strong>. To activate your account, please enter the verification code below:</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <span style='display: inline-block; padding: 15px 30px; font-size: 32px; font-weight: bold; letter-spacing: 5px; background-color: {$colors['primary_light']}; color: {$colors['primary']}; border-radius: 8px; border: 2px dashed {$colors['primary']};'>
                        {{otp}}
                    </span>
                </div>
                <p style='text-align: center;'><strong>This code is valid for 10 minutes.</strong></p>
                <p>If you did not create a {$appName} account, you can safely ignore this email. Do not share this code with anyone.</p>
                " . $signoff . "
            "),
        ],
        'password_reset_success' => [
            'subject' => 'Success! Your ' . $appName . ' Password Has Been Changed!',
            'preheader' => 'Your password was changed. If this was not you, act now.',
            'html' => $wrap("
                " . $h2("Password Successfully Reset") . "
                <p>Hi {{user_name}},</p>
                <p>This is a confirmation that the password for your <strong>{$appName}</strong> account has been successfully changed.</p>
                <div style='{$dataBlockStyle}; text-align: center; border-left: 4px solid {$colors['success']};'>
                    <p style='margin: 6px 0;'><strong>Account:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>Status:</strong> Password Updated Successfully</p>
                </div>
                <p>If you performed this action, no further steps are needed. Your account is secure.</p>
                
                " . str_replace(['{{color}}', '{{content}}'], [$colors['danger'], "
                    <p style='margin: 0; color: {$colors['text']};'><strong>Security Alert:</strong> If you did not request this password change, your account may have been compromised. Please contact our support team immediately at <a href='mailto:{$supportEmail}' style='color:{$colors['danger']};'>{$supportEmail}</a> to secure your account.</p>
                "], "<div style='{$alertBlockStyle}'>{{content}}</div>") . "

                <p>We recommend using a strong, unique password.</p>
                " . $signoff . "
            "),
        ],
        'logout_notification' => [
            'subject' => 'You Have Successfully Logged Out of Your ' . $appName . ' Account',
            'preheader' => 'You signed out of your account.',
            'html' => $wrap("
                " . $h2("Logout Confirmation") . "
                <p>Hi {{user_name}},</p>
                <p>This email confirms that you have successfully logged out of your <strong>{$appName}</strong> account.</p>
                
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Logged Out At:</strong> {{logout_time}}</p>
                    <p style='margin: 6px 0;'><strong>Session Status:</strong> Ended Securely</p>
                </div>
                
                <p>For your security, we recommend that you always log out when using shared or public devices. Please ensure that you are accessing {$appName} through our official website: <a href='{$websiteUrl}' style='color:{$colors['primary']};'>{$websiteUrl}</a>.</p>
                
                " . $signoff . "
            "),
        ],
        'admin_logout_notification' => [
            'subject' => 'Admin Session Ended - ' . $appName . ' Console',
            'preheader' => 'You signed out of the admin panel.',
            'html' => $wrap("
                " . $h2("Administrator Sign-Out") . "
                <p>Hi {{admin_name}},</p>
                <p>Your administrator session on the <strong>{$appName}</strong> console has ended.</p>

                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Signed Out At:</strong> {{logout_time}}</p>
                    <p style='margin: 6px 0;'><strong>IP Address:</strong> {{ip_address}}</p>
                    <p style='margin: 6px 0;'><strong>Session Status:</strong> Ended Securely</p>
                </div>

                " . str_replace(['{{color}}', '{{content}}'], [$colors['danger'],
                    "If you did not sign out at this time, secure your account immediately and contact the platform owner. Administrator access controls member funds."],
                    "<div style='{$alertBlockStyle}'>{{content}}</div>") . "

                <p>Console: <a href='{$adminUrl}' style='color:{$colors['primary']};'>{$adminUrl}</a></p>

                " . $signoff . "
            "),
        ],
        // --- Account security -----------------------------------------
        // These two fired for nobody before. A session hijacker could change
        // the account email and password with the real owner never being told.
        'account_email_changed' => [
            'subject' => 'Your ' . $appName . ' email address was changed',
            'preheader' => 'The email address on your account was changed. If this was not you, act now.',
            'html' => $wrap("
                " . $h2("Your email address was changed") . "
                <p>Hi {{user_name}},</p>
                <p>The email address on your <strong>{$appName}</strong> account was just changed.
                This message has been sent to both the old and the new address.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Previous address:</strong> {{old_email}}</p>
                    <p style='margin: 6px 0;'><strong>New address:</strong> {{new_email}}</p>
                    <p style='margin: 6px 0;'><strong>When:</strong> {{change_time}}</p>
                </div>
                <div style='" . str_replace('{{color}}', $colors['danger'], $alertBlockStyle) . "'>
                    <p style='margin: 0;'><strong>If you did not make this change</strong>, contact us at
                    <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>
                    immediately and reset your password.</p>
                </div>
                " . $cta("Reset your password", "{$websiteUrl}forgotpassword") . "
                " . $signoff . "
            "),
        ],

        'account_password_changed' => [
            'subject' => 'Your ' . $appName . ' password was changed',
            'preheader' => 'Your password was changed. If this was not you, act now.',
            'html' => $wrap("
                " . $h2("Your password was changed") . "
                <p>Hi {{user_name}},</p>
                <p>The password on your <strong>{$appName}</strong> account was just changed
                from the profile page.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Account:</strong> {{user_email}}</p>
                    <p style='margin: 6px 0;'><strong>When:</strong> {{change_time}}</p>
                    <p style='margin: 6px 0;'><strong>IP address:</strong> {{ip}}</p>
                </div>
                <div style='" . str_replace('{{color}}', $colors['danger'], $alertBlockStyle) . "'>
                    <p style='margin: 0;'><strong>If you did not make this change</strong>, reset your
                    password now and contact
                    <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</p>
                </div>
                " . $cta("Reset your password", "{$websiteUrl}forgotpassword") . "
                " . $signoff . "
            "),
        ],

        // A crypto deposit that underpays, fails or expires used to update the
        // transaction row and tell nobody at all.
        'deposit_failed' => [
            'subject' => 'Your deposit could not be completed - ' . $appName,
            'preheader' => 'Your crypto deposit did not complete. Nothing has been charged.',
            'html' => $wrap("
                " . $h2("Your deposit could not be completed") . "
                <p>Hi {{user_name}},</p>
                <p>The crypto payment for the deposit below did not complete, so your wallet
                has not been credited.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                    <p style='margin: 6px 0;'><strong>Reason:</strong> {{failure_reason}}</p>
                </div>
                <p>If funds did leave your wallet, reply to this email with the transaction
                hash and we will trace it. Otherwise you can simply start a new deposit.</p>
                " . $cta("Start a new deposit", "{$websiteUrl}dashboard.wallet") . "
                " . $signoff . "
            "),
        ],

        // Member receipt for the "I Have Paid" button. Until now only the
        // admin was notified, so the member pressed a button, got a toast, and
        // received nothing they could point at later.
        'deposit_marked_paid' => [
            'subject' => 'We are checking your transfer - ' . $appName,
            'preheader' => 'Thanks for confirming. We are verifying your transfer now.',
            'html' => $wrap("
                " . $h2("We are checking your transfer") . "
                <p>Hi {{user_name}},</p>
                <p>Thanks for letting us know you have sent the transfer. Our team is
                verifying it now, and your wallet will be credited as soon as it clears.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Amount:</strong> \${{amount}}</p>
                    <p style='margin: 6px 0;'><strong>Method:</strong> {{method}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                    <p style='margin: 6px 0;'><strong>Transaction hash:</strong> {{tx_hash}}</p>
                </div>
                <p>Most transfers are confirmed within a few hours. You will get another
                email the moment the funds land. Nothing further is needed from you.</p>
                " . $cta("View your wallet", "{$websiteUrl}dashboard.wallet") . "
                " . $signoff . "
            "),
        ],

        // --- Contact form (public marketing site) ---------------------
        'contact_received' => [
            'subject' => 'We received your message - ' . $appName,
            'preheader' => 'Thanks for getting in touch. We usually reply within two working hours.',
            'html' => $wrap("
                " . $h2("Thanks for getting in touch") . "
                <p>Hi {{user_name}},</p>
                <p>We have your message and someone from the team will read it shortly.
                Our typical response time is under two working hours on weekdays.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                    <p style='margin: 6px 0;'><strong>Subject:</strong> {{subject}}</p>
                    <p style='margin: 6px 0;'><strong>Type:</strong> {{message_type}}</p>
                </div>
                <p>There is nothing else you need to do - we will reply to this address.
                If your message was urgent, you can also reach us at
                <a href='mailto:{$supportEmail}' style='color:{$colors['primary']};'>{$supportEmail}</a>.</p>
                " . $signoff . "
            "),
        ],

        'admin_contact_notification' => [
            'subject' => 'New contact message: {{subject}}',
            'preheader' => 'A new message arrived through the website contact form.',
            'html' => $wrap("
                " . $h2("New contact message") . "
                <p>A message came in through the website contact form. Reply directly to
                this email to answer the sender.</p>
                <div style='{$dataBlockStyle}'>
                    <p style='margin: 6px 0;'><strong>From:</strong> {{sender_name}} &lt;{{sender_email}}&gt;</p>
                    <p style='margin: 6px 0;'><strong>Type:</strong> {{message_type}}</p>
                    <p style='margin: 6px 0;'><strong>Subject:</strong> {{subject}}</p>
                    <p style='margin: 6px 0;'><strong>Reference:</strong> {{reference}}</p>
                    <p style='margin: 6px 0;'><strong>Attachment:</strong> {{attachment}}</p>
                </div>
                <p style='margin: 18px 0 6px 0;'><strong>Message</strong></p>
                <div style='{$dataBlockStyle}'>{{message_body}}</div>
                " . $signoff . "
            "),
        ],

        'generic' => [
            'subject' => $appName . " Notification",
            'preheader' => 'You have a new notification from Maveren Capital.',
            'html' => $wrap("
                " . $h2("Notification") . "
                <p>Dear {{user_name}},</p>
                <p>You have a new notification from <strong>{$appName}</strong>.</p>
                <p>Please log in to your account to view the full details.</p>
                <p>If you have any concerns, feel free to contact our support team.</p>
                " . $signoff . "
            "),
        ],
    ];
}
?>
