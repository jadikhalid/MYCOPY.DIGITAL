# MYCOPY.DIGITAL

PHP landing page: **train an AI to be you**, with a waitlist ticket (SMTP email).

## Run locally

```bash
php -S localhost:8080 -t .
```

## SMTP

1. Copy `config.smtp.example.php` → `config.smtp.php`
2. Fill in host, port, credentials, and sender

The ticket is **never** shown on screen: it is sent by email only.

## Pages

| File | Role |
|------|------|
| `index.php` | Studio access (code) |
| `attente.php` | Waitlist signup + toasts |
| `reserve.php` | Signup handler |
| `mail/SmtpMailer.php` | SMTP client |
| `vault.php` | Private studio |
| `data/mycopy.sqlite` | Database (auto-created) |
