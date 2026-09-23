<?php
// ========================================
// EMAIL HANDLER - Maveren Capital (Finalized v2)
// ========================================

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../utilities/email_temps.php';
require_once __DIR__ . '/../utilities/helpers.php'; // ✅ helpers now available (getUserIP, getUserBrowser, etc.)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Append a line to logs/email.log, falling back to error_log() if that file is
 * not writable.
 *
 * logs/ is owned by the deploying user, not by the web server, so every mail
 * sent from an HTTP request wrote nothing at all - and because failures are
 * logged the same way, a bounced deposit confirmation left no trace anywhere.
 * The fallback keeps the record even when the directory is wrong.
 */
function mvcMailLog(string $line): void
{
    $path = __DIR__ . '/../../logs/email.log';
    $ok = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    if ($ok === false) {
        error_log('email.log not writable, entry: ' . rtrim($line));
    }
}

/**
 * Did sendEmail() actually hand the message to the SMTP server?
 *
 * sendEmail() returns false for a rejected address and an array carrying a
 * 'success' flag in every other case, so a caller that wanted the answer had
 * to know both shapes - and none of them asked. Failures were logged and
 * discarded while the endpoint replied "we sent you a code", which is the
 * worst possible outcome for a one-time code: the member sits waiting on mail
 * that does not exist and has no reason to retry.
 */
function mvcEmailSent($result): bool
{
    return is_array($result) && !empty($result['success']);
}

/**
 * sendEmail()
 * Sends an HTML email using PHPMailer and a chosen template.
 *
 * @param array $params [
 * 'to' => recipient email,
 * 'template' => key from getEmailTemplates(),
 * 'variables' => array('placeholder' => 'value'),
 * 'subject' => optional override,
 * 'body' => optional raw HTML override,
 * 'debug' => optional true to preview in browser,
 * 'cc_admin' => optional true to auto-send to admin,
 * 'admin_template' => optional template key for admin notification,
 * 'reply_to' => optional address to reply to instead of support,
 * 'preheader' => optional inbox preview line
 * ]
 * @return bool|array
 */
function sendEmail($params)
{
    if (empty($params['to']) || !filter_var($params['to'], FILTER_VALIDATE_EMAIL)) {
        error_log('sendEmail: Invalid recipient email');
        return false;
    }

    $templates = getEmailTemplates();
    $templateKey = $params['template'] ?? 'generic';
    $variables = $params['variables'] ?? [];

    // Template fallback
    $template = $templates[$templateKey] ?? $templates['generic'];
    $subject = $params['subject'] ?? $template['subject'] ?? (APP_NAME . ' Notification');
    $bodyHtml = $params['body'] ?? $template['html'];

    // Replace template variables safely
    foreach ($variables as $key => $value) {
        // 🚨 CRITICAL FIX: Do not escape pre-formatted HTML variables.
        if ($key === 'details_html' || $key === 'message_body') {
            $safeValue = (string)$value;
        } else {
            // Escape all other dynamic variables
            $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
        $bodyHtml = str_replace('{{' . $key . '}}', $safeValue, $bodyHtml);
        $subject = str_replace('{{' . $key . '}}', $safeValue, $subject);
    }

    // Replace global placeholders if any are left
    $bodyHtml = str_replace(
        ['{{year}}', '{{app_name}}', '{{support_email}}', '{{website_url}}'],
        [date('Y'), APP_NAME, ADMIN_CONTACT_EMAIL, APP_URL],
        $bodyHtml
    );

    // Preheader: the grey line an inbox shows next to the subject. Hidden in
    // the rendered mail by the zero-height span in the wrapper. Without it,
    // clients preview whatever text comes first - which used to be the logo
    // alt text.
    $preheader = $params['preheader'] ?? ($template['preheader'] ?? '');
    $bodyHtml = str_replace(
        '{{preheader}}',
        htmlspecialchars((string) $preheader, ENT_QUOTES, 'UTF-8'),
        $bodyHtml
    );

    // Any placeholder the caller forgot used to be DELIVERED VERBATIM - there
    // was no fallback pass, so members received literal "{{user_email}}" in
    // password-reset mail and "{{plan_name}}" in maturity mail. Blank the
    // leftovers and log them so the gap surfaces in development instead of in
    // someone's inbox.
    if (preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $bodyHtml, $leftovers)) {
        error_log(sprintf(
            'sendEmail: unresolved placeholders in template "%s": %s',
            $templateKey,
            implode(', ', array_unique($leftovers[1]))
        ));
        $bodyHtml = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $bodyHtml);
    }
    $subject = preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $subject);

    // Debug mode (template preview)
    if (!empty($params['debug'])) {
        header('Content-Type: text/html; charset=UTF-8');
        echo $bodyHtml;
        exit;
    }

    // --- Initialize PHPMailer ---
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth  = true;
        $mail->Username  = SMTP_USER;
        $mail->Password  = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port      = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->isHTML(true);

        // Sender / Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        // A contact-form copy should reply to the person who wrote in, not to
        // our own support address.
        if (!empty($params['reply_to']) && filter_var($params['reply_to'], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($params['reply_to']);
        } else {
            $mail->addReplyTo(ADMIN_CONTACT_EMAIL, APP_NAME . ' Support');
        }
        $mail->addAddress($params['to']);

        // Message
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        // strip_tags() alone leaves the CONTENTS of <style> and <title>, so the
        // plain-text part of every message used to open with raw CSS rules -
        // a real spam-score liability. Drop those elements wholesale first,
        // then collapse the whitespace the table layout leaves behind.
        $textSource = preg_replace('#<(style|script|head|title)\b[^>]*>.*?</\1>#is', ' ', $bodyHtml);
        $textSource = preg_replace('#<(br|/p|/div|/tr|/h[1-6])\s*/?>#i', "\n", $textSource);
        $altBody = html_entity_decode(strip_tags($textSource), ENT_QUOTES, 'UTF-8');
        $altBody = preg_replace("/[ \t]+/", ' ', $altBody);
        $altBody = preg_replace("/\n{3,}/", "\n\n", $altBody);
        $mail->AltBody = trim($altBody);

        // Send
        $mail->send();

        // Optional: Send admin copy
        if (!empty($params['cc_admin'])) {
            $adminTemplate = $params['admin_template'] ?? 'admin_user_login_notification';
            $adminData = [
                'to' => ADMIN_CONTACT_EMAIL,
                'template' => $adminTemplate,
                'variables' => array_merge($variables, [
                    'admin_name' => 'Admin',
                    'admin_email' => ADMIN_CONTACT_EMAIL,
                ])
            ];
            sendEmail($adminData);
        }

        // Log success
        $log = sprintf("[%s] SENT → %s | Template: %s | Subject: %s\n",
            date('Y-m-d H:i:s'), $params['to'], $templateKey, $subject
        );
        mvcMailLog($log);

        return [
            'success' => true,
            'recipient' => $params['to'],
            'subject' => $subject,
            'template' => $templateKey
        ];

    } catch (Exception $e) {
        $errorLog = sprintf("[%s] ERROR → %s | %s | Mailer: %s\n",
            date('Y-m-d H:i:s'),
            $params['to'],
            $e->getMessage(),
            $mail->ErrorInfo
        );
        mvcMailLog($errorLog);
        error_log($errorLog);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>