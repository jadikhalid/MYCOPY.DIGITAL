<?php
/**
 * MYCOPY.DIGITAL — SQLite waitlist + studio access bridge
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
            notified INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT \'waiting\',
            studio_code TEXT UNIQUE,
            access_sent_at TEXT
        )'
    );

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_waitlist_email ON waitlist (email)'
    );

    migrate_waitlist_schema($pdo);

    return $pdo;
}

function migrate_waitlist_schema(PDO $pdo): void
{
    $cols = $pdo->query('PRAGMA table_info(waitlist)')->fetchAll();
    $names = array_column($cols, 'name');

    if (!in_array('status', $names, true)) {
        $pdo->exec("ALTER TABLE waitlist ADD COLUMN status TEXT NOT NULL DEFAULT 'waiting'");
    }

    if (!in_array('studio_code', $names, true)) {
        $pdo->exec('ALTER TABLE waitlist ADD COLUMN studio_code TEXT');
    }

    if (!in_array('access_sent_at', $names, true)) {
        $pdo->exec('ALTER TABLE waitlist ADD COLUMN access_sent_at TEXT');
    }

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_waitlist_studio_code ON waitlist (studio_code)
         WHERE studio_code IS NOT NULL'
    );
}

function generate_ticket(int $id): string
{
    return sprintf('MC-%05d', $id);
}

function generate_studio_code(PDO $pdo): string
{
    do {
        $code = 'STU-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $check = $pdo->prepare('SELECT 1 FROM waitlist WHERE studio_code = :code LIMIT 1');
        $check->execute(['code' => $code]);
    } while ($check->fetch());

    return $code;
}

/**
 * @return array<string, mixed>|null
 */
function find_studio_access(string $code): ?array
{
    $normalized = strtoupper(trim($code));
    if ($normalized === '') {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT id, name, email, ticket, studio_code, status
         FROM waitlist
         WHERE studio_code = :code AND status = 'approved'
         LIMIT 1"
    );
    $stmt->execute(['code' => $normalized]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function fetch_waitlist_entries(): array
{
    $stmt = db()->query(
        'SELECT id, ticket, name, email, created_at, notified, status, studio_code, access_sent_at
         FROM waitlist
         ORDER BY created_at DESC'
    );

    return $stmt->fetchAll();
}

/**
 * @return array{ok: bool, message: string, code?: string}
 */
function grant_studio_access(int $waitlistId, bool $resend = false): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, name, email, ticket, status, studio_code FROM waitlist WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $waitlistId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'message' => 'Waitlist entry not found.'];
    }

    $code = (string) ($row['studio_code'] ?? '');

    if ($code === '') {
        $code = generate_studio_code($pdo);
        $upd = $pdo->prepare(
            "UPDATE waitlist
             SET status = 'approved', studio_code = :code, access_sent_at = :sent_at
             WHERE id = :id"
        );
        $upd->execute([
            'code' => $code,
            'sent_at' => gmdate('c'),
            'id' => $waitlistId,
        ]);
    } elseif ($resend) {
        $pdo->prepare('UPDATE waitlist SET access_sent_at = :sent_at WHERE id = :id')
            ->execute(['sent_at' => gmdate('c'), 'id' => $waitlistId]);
    } else {
        return ['ok' => false, 'message' => 'Studio access already granted. Use resend instead.'];
    }

    $sent = send_studio_access_email(
        (string) $row['email'],
        (string) $row['name'],
        (string) $row['ticket'],
        $code
    );

    if (!$sent) {
        return [
            'ok' => false,
            'message' => 'Code saved but email could not be sent. Check SMTP settings.',
            'code' => $code,
        ];
    }

    $action = $resend && !empty($row['studio_code']) ? 'resent' : 'sent';

    return [
        'ok' => true,
        'message' => $action === 'resent'
            ? "Studio code {$code} resent to {$row['email']}."
            : "Studio access granted. Code {$code} sent to {$row['email']}.",
        'code' => $code,
    ];
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
            'INSERT INTO waitlist (ticket, name, email, phone, created_at, notified, status)
             VALUES (:ticket, :name, :email, NULL, :created_at, 0, \'waiting\')'
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
        . "Keep this number for your records.\n"
        . "When your studio access is approved, you will receive a separate access code by email.\n"
        . "Do not share your ticket with anyone.\n\n"
        . "— MYCOPY.DIGITAL\n";

    $mailer = smtp_mailer();
    return $mailer->send($email, $name, $subject, $body);
}

function send_studio_access_email(string $email, string $name, string $ticket, string $code): bool
{
    $subject = 'MYCOPY — your studio access code';
    $body = "Hello {$name},\n\n"
        . "Your MYCOPY studio access has been approved.\n\n"
        . "Waitlist ticket: {$ticket}\n"
        . "Studio access code: {$code}\n\n"
        . "Go to https://mycopy.digital and enter this code to open your private studio.\n"
        . "Do not share this code with anyone.\n\n"
        . "— MYCOPY.DIGITAL\n";

    $mailer = smtp_mailer();
    return $mailer->send($email, $name, $subject, $body);
}
