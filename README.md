# MYCOPY.DIGITAL

PHP landing page: **train an AI to be you**, with a waitlist ticket (SMTP email) and an admin panel to grant studio access.

## Run locally

```bash
php -S localhost:8080 -t .
```

## SMTP

1. Copy `config.smtp.example.php` → `config.smtp.php`
2. Fill in host, port, credentials, and sender

The waitlist ticket is **never** shown on screen: it is sent by email only.

## Admin panel (waitlist → studio codes)

1. Copy `config.admin.example.php` → `config.admin.php`
2. Generate a password hash:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
```

3. Paste the hash into `config.admin.php`
4. Open `/admin/login.php`

From the admin panel you can:

- Review waitlist signups
- **Send code** — generates a unique studio code (`STU-XXXXXX`), marks the entry as approved, emails the code
- **Resend** — resends the existing studio code by email

Studio login on the homepage accepts:

- Codes issued from the waitlist (`STU-…`, stored in SQLite)
- Optional bootstrap codes in `config.php` (`ACCESS_CODES`)

## Pages

| File | Role |
|------|------|
| `index.php` | Studio access (code) |
| `attente.php` | Waitlist signup + toasts |
| `reserve.php` | Signup handler |
| `admin/` | Waitlist admin panel |
| `mail/SmtpMailer.php` | SMTP client |
| `vault.php` | Private studio |
| `data/mycopy.sqlite` | Database (auto-created) |

## Waitlist schema

Table `waitlist`:

| Column | Purpose |
|--------|---------|
| `ticket` | Waitlist ticket (`MC-00001`) |
| `status` | `waiting` or `approved` |
| `studio_code` | Issued studio access code |
| `access_sent_at` | When the studio code was last sent |
