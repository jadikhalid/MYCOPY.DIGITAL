<?php
/**
 * MYCOPY.DIGITAL — base SQLite (file d'attente)
 */

declare(strict_types=1);

require_once __DIR__ . '/mail/SmtpMailer.php';

const DB_PATH = __DIR__ . '/data/mycopy.sqlite';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS waitlist (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            created_at TEXT NOT NULL,
            notified INTEGER NOT NULL DEFAULT 0
        )'
    );

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_waitlist_email ON waitlist (email)'
    );

    return $pdo;
}

function generate_ticket(int $id): string
{
    return sprintf('MC-%05d', $id);
}

/**
 * @return array{
 *   ok: bool,
 *   toast: array{type: string, title: string, message: string},
 *   keep_form?: bool
 * }
 */
function reserve_place(string $name, string $email): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    $email = strtolower(trim($email));

    if ($name === '' || mb_strlen($name) < 2) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Inscription impossible',
                'message' => 'Indiquez votre nom complet.',
            ],
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Inscription impossible',
                'message' => 'Adresse e-mail invalide.',
            ],
        ];
    }

    if (mb_strlen($name) > 120 || mb_strlen($email) > 180) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Inscription impossible',
                'message' => 'Données trop longues.',
            ],
        ];
    }

    $pdo = db();

    $existing = $pdo->prepare('SELECT ticket, name FROM waitlist WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $email]);
    $row = $existing->fetch();

    if ($row) {
        $sent = send_ticket_email($email, (string) $row['name'], (string) $row['ticket']);

        if ($sent) {
            $pdo->prepare('UPDATE waitlist SET notified = 1 WHERE email = :email')
                ->execute(['email' => $email]);

            return [
                'ok' => true,
                'toast' => [
                    'type' => 'success',
                    'title' => 'Déjà inscrit',
                    'message' => 'Votre ticket a été renvoyé par e-mail.',
                ],
            ];
        }

        return [
            'ok' => false,
            'toast' => [
                'type' => 'error',
                'title' => 'Envoi échoué',
                'message' => 'Compte déjà inscrit, mais l’e-mail n’a pas pu être envoyé. Réessayez plus tard.',
            ],
        ];
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO waitlist (ticket, name, email, phone, created_at, notified)
             VALUES (:ticket, :name, :email, NULL, :created_at, 0)'
        );

        $stmt->execute([
            'ticket' => 'PENDING',
            'name' => $name,
            'email' => $email,
            'created_at' => gmdate('c'),
        ]);

        $id = (int) $pdo->lastInsertId();
        $ticket = generate_ticket($id);

        $upd = $pdo->prepare('UPDATE waitlist SET ticket = :ticket WHERE id = :id');
        $upd->execute(['ticket' => $ticket, 'id' => $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Inscription échouée',
                'message' => 'Impossible d’enregistrer votre place. Réessayez.',
            ],
        ];
    }

    $sent = send_ticket_email($email, $name, $ticket);

    $pdo->prepare('UPDATE waitlist SET notified = :n WHERE ticket = :ticket')
        ->execute(['n' => $sent ? 1 : 0, 'ticket' => $ticket]);

    if ($sent) {
        return [
            'ok' => true,
            'toast' => [
                'type' => 'success',
                'title' => 'Inscription confirmée',
                'message' => 'Votre ticket a été envoyé par e-mail. Vérifiez votre boîte de réception.',
            ],
        ];
    }

    return [
        'ok' => false,
        'toast' => [
            'type' => 'error',
            'title' => 'Envoi échoué',
            'message' => 'Inscription enregistrée, mais le ticket n’a pas pu être envoyé. Contactez le support ou réessayez.',
        ],
    ];
}

function send_ticket_email(string $email, string $name, string $ticket): bool
{
    $subject = 'MYCOPY — votre ticket file d’attente';
    $body = "Bonjour {$name},\n\n"
        . "Votre place dans la file d’attente MYCOPY est réservée.\n\n"
        . "Numéro de ticket : {$ticket}\n\n"
        . "Conservez ce numéro : il vous permettra d’entrer dans le processus d’entraînement.\n"
        . "Ne partagez ce ticket avec personne.\n\n"
        . "— MYCOPY.DIGITAL\n";

    $mailer = smtp_mailer();
    return $mailer->send($email, $name, $subject, $body);
}
