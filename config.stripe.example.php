<?php
/**
 * Stripe config — copy to config.stripe.php and fill in your keys.
 * Do not commit real secrets.
 *
 * Dashboard: https://dashboard.stripe.com
 * Webhook endpoint: https://your-domain/payments/webhook.php
 * Event: checkout.session.completed
 */

declare(strict_types=1);

return [
    'secret_key'      => 'sk_test_...',
    'webhook_secret'  => 'whsec_...',
    'currency'        => 'usd',
    'amount_cents'    => 4900,
    'product_name'    => 'MYCOPY Waitlist Reservation',
    'product_description' => 'Waitlist place + protocol PDF',
    // Public site URL used for success/cancel redirects (no trailing slash)
    'site_url'        => 'https://mycopy.digital',
];
