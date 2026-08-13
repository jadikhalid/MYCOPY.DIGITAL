# MYCOPY.DIGITAL

Page vitrine PHP : **entraînez une IA à être vous**, avec file d’attente par ticket (e-mail SMTP).

## Lancer en local

```bash
php -S localhost:8080 -t .
```

## SMTP

1. Copiez `config.smtp.example.php` → `config.smtp.php`
2. Renseignez host, port, identifiants, expéditeur

Le ticket n’est **jamais** affiché à l’écran : il part uniquement par e-mail.

## Pages

| Fichier | Rôle |
|---------|------|
| `index.php` | Accès studio (code) |
| `attente.php` | Inscription file d’attente + toasts |
| `reserve.php` | Traitement inscription |
| `mail/SmtpMailer.php` | Client SMTP |
| `vault.php` | Studio privé |
| `data/mycopy.sqlite` | Base (auto) |
