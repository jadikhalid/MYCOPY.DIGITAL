<?php
/**
 * Configuration SMTP — copiez vers config.smtp.php et renseignez vos identifiants.
 * Ne commitez pas de mots de passe réels.
 */

declare(strict_types=1);

return [
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'encryption' => 'ssl', // 465 = ssl | sinon 587 + tls
    'username'   => 'votre@email.com',
    'password'   => 'votre-mot-de-passe',
    'from_email' => 'votre@email.com',
    'from_name'  => 'MYCOPY',
    'timeout'    => 30,
];
