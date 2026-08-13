<?php
/**
 * MYCOPY.DIGITAL — SQLite waitlist
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
                'title' => 'Signup failed',
                'message' => 'Please enter your full name.',
            ],
        ];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Signup failed',
                'message' => 'Invalid email address.',
            ],
        ];
    }

    if (mb_strlen($name) > 120 || mb_strlen($email) > 180) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Signup failed',
                'message' => 'Input is too long.',
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
                    'title' => 'Already registered',
                    'message' => 'Your ticket was resent by email.',
                ],
            ];
        }

        return [
            'ok' => false,
            'toast' => [
                'type' => 'error',
                'title' => 'Send failed',
                'message' => 'You are already on the list, but the email could not be sent. Try again later.',
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
                'title' => 'Signup failed',
                'message' => 'Could not save your place. Please try again.',
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
                'title' => 'Signup confirmed',
                'message' => 'Your ticket was sent by email. Check your inbox.',
            ],
        ];
    }

    return [
        'ok' => false,
        'toast' => [
            'type' => 'error',
            'title' => 'Send failed',
            'message' => 'Signup saved, but the ticket email could not be sent. Contact support or try again.',
        ],
    ];
}

function send_ticket_email(string $email, string $name, string $ticket): bool
{
    $subject = 'MYCOPY — your waitlist ticket';
    $body = "Hello {$name},\n\n"
        . "Your place on the MYCOPY waitlist is reserved.\n\n"
        . "Ticket number: {$ticket}\n\n"
        . "Keep this number — it will let you enter the training process.\n"
        . "Do not share this ticket with anyone.\n\n"
        . "— MYCOPY.DIGITAL\n";

    $mailer = smtp_mailer();
    return $mailer->send($email, $name, $subject, $body);
}
