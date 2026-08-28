<?php
/**
 * Admin credentials — copy to config.admin.php and set a password hash.
 * Generate hash: php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
 * Do not commit config.admin.php or real passwords.
 */

declare(strict_types=1);

return [
    'password_hash' => '',
    // Personal studio code (founder / cobaye). Enter on /home like any access code.
    'founder_studio_code' => 'STU-KJ9A4F',
];
