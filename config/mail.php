<?php
/**
 * Outgoing email settings (used for password-reset emails).
 *
 * driver:
 *   'log'  - Does NOT send anything. The email (including the reset link)
 *            is written to the PHP error log. Use this on a local machine
 *            where mail() isn't set up, such as a default XAMPP install.
 *   'mail' - Sends with PHP's built-in mail(). Use this in production,
 *            on a server that has a working mail transport (sendmail,
 *            postfix, or your host's mail service).
 *
 * base_url:
 *   The public URL of this app, with no trailing slash. Reset links in
 *   emails are built from this value. It is deliberately NOT taken from
 *   the request's Host header, because that can be forged to poison links.
 *
 * from_email:
 *   For deliverability, use an address on your own domain.
 */
return [
    'driver'     => 'log',
    'base_url'   => 'http://localhost/task-manager',
    'from_email' => 'no-reply@example.com',
    'from_name'  => 'TaskFlow',
];
