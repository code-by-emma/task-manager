<?php
/**
 * Minimal outgoing-mail helper. Settings live in config/mail.php.
 */

/**
 * Public base URL of the app (no trailing slash), from config/mail.php.
 */
function appBaseUrl(): string
{
    $config = require __DIR__ . '/../config/mail.php';
    return rtrim((string) ($config['base_url'] ?? ''), '/');
}

/**
 * Sends a plain-text email. Returns true on success.
 * With the 'log' driver nothing is sent; the message is written to the
 * PHP error log instead.
 */
function sendMail(string $to, string $subject, string $body): bool
{
    $config    = require __DIR__ . '/../config/mail.php';
    $driver    = $config['driver'] ?? 'log';
    $fromEmail = (string) ($config['from_email'] ?? '');
    $fromName  = (string) ($config['from_name'] ?? '');

    // Refuse anything that could inject extra mail headers.
    foreach ([$to, $subject, $fromEmail, $fromName] as $value) {
        if (preg_match('/[\r\n]/', $value)) {
            error_log('sendMail: refused value containing a line break.');
            return false;
        }
    }

    if ($driver === 'log') {
        error_log("[mail:log] To: {$to} | Subject: {$subject}\n{$body}");
        return true;
    }

    $headers = [
        'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . '<' . $fromEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    $sent = @mail(
        $to,
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        $body,
        implode("\r\n", $headers)
    );

    if (!$sent) {
        error_log('sendMail: mail() returned false for ' . $to);
    }
    return $sent;
}
