<?php

require_once __DIR__ . '/db.php';

function send_app_mail(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
{
    $cfg = app_config()['mail'] ?? [];
    $fromEmail = trim((string) ($cfg['from_email'] ?? 'no-reply@localhost'));
    $fromName = trim((string) ($cfg['from_name'] ?? 'PatriotContracts'));
    $replyTo = trim((string) ($cfg['reply_to'] ?? $fromEmail));

    $headers = [];
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $replyTo;
    $headers[] = 'MIME-Version: 1.0';

    if ($htmlBody !== null && $htmlBody !== '') {
        $boundary = 'pc_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $textBody . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n"
            . "--{$boundary}--";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $message = $textBody;
    }

    return @mail($to, $subject, $message, implode("\r\n", $headers));
}

function send_verification_email(string $email, string $verifyUrl): bool
{
    $subject = 'Verify your PatriotContracts email';
    $text = "Verify your email to complete account activation:\n\n{$verifyUrl}\n\nThis link expires in 24 hours.";
    $html = '<p>Verify your email to complete account activation:</p><p><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '">Verify Email</a></p><p>This link expires in 24 hours.</p>';
    return send_app_mail($email, $subject, $text, $html);
}

function send_password_reset_email(string $email, string $resetUrl): bool
{
    $subject = 'Reset your PatriotContracts password';
    $text = "Use this link to reset your password:\n\n{$resetUrl}\n\nThis link expires in 1 hour.";
    $html = '<p>Use this link to reset your password:</p><p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Reset Password</a></p><p>This link expires in 1 hour.</p>';
    return send_app_mail($email, $subject, $text, $html);
}
