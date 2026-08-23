# MYCOPY.DIGITAL

PHP landing page: **train an AI to be you**, with paid waitlist reservation ($49), protocol PDF by email, and an admin panel to grant studio access.

## Run locally

```bash
php -S localhost:8080 -t .
```

## SMTP

1. Copy `config.smtp.example.php` → `config.smtp.php`
2. Fill in host, port, credentials, and sender

## Stripe ($49 waitlist + PDF)

1. Copy `config.stripe.example.php` → `config.stripe.php`
2. Fill in:
   - `secret_key` (test or live)
   - `webhook_secret`
   - `site_url` (e.g. `https://mycopy.digital` or `http://localhost:8001`)
3. In Stripe Dashboard → Developers → Webhooks:
   - Endpoint: `https://your-domain/payments/webhook.php`
   - Event: `checkout.session.completed`
4. Replace `assets/docs/mycopy-protocol.pdf` with your final PDF when ready

Flow:

1. User submits name + email on `/attente.php`
2. Redirect to Stripe Checkout ($49 USD)
3. Webhook marks payment paid
4. Email sends waitlist ticket + protocol PDF (attachment)
5. Admin later grants studio code (`STU-…`)

The waitlist ticket is **never** shown on screen: email only.

## Admin panel

1. Copy `config.admin.example.php` → `config.admin.php`
2. Generate a password hash:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
```

3. Paste the hash into `config.admin.php`
4. Open `/admin/login.php`

## Pages

| File | Role |
|------|------|
| `index.php` | Studio access (code) |
| `attente.php` | Paid waitlist reservation |
| `reserve.php` | Starts Stripe Checkout |
| `payments/` | Checkout success/cancel + webhook |
| `admin/` | Waitlist admin panel |
| `mail/SmtpMailer.php` | SMTP client (attachments) |
| `vault.php` | Private studio |
| `data/mycopy.sqlite` | Database (auto-created) |
| `assets/docs/mycopy-protocol.pdf` | Fixed protocol PDF |

## Waitlist schema

| Column | Purpose |
|--------|---------|
| `ticket` | Waitlist ticket (`MC-00001`) |
| `payment_status` | `unpaid` / `paid` |
| `status` | `pending_payment` / `waiting` / `approved` |
| `studio_code` | Issued studio access code |
| `pdf_sent` | Protocol PDF emailed (0/1) |
| `paid_at` | Payment timestamp |
