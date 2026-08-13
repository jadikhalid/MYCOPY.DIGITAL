<?php
/**
 * SMTP configuration — copy to config.smtp.php and fill in your credentials.
 * Do not commit real passwords.
 */

declare(strict_types=1);

return [
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'encryption' => 'ssl', // 465 = ssl | otherwise 587 + tls
    'username'   => 'you@email.com',
    'password'   => 'your-password',
    'from_email' => 'you@email.com',
    'from_name'  => 'MYCOPY',
    'timeout'    => 30,
];
