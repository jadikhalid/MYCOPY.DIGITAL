<?php
/**
 * MYCOPY.DIGITAL — SQLite waitlist + studio access + paid reservation
 */

declare(strict_types=1);

require_once __DIR__ . '/mail/SmtpMailer.php';
require_once __DIR__ . '/payments/StripeClient.php';

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
            status TEXT NOT NULL DEFAULT \'pending_payment\',
            studio_code TEXT UNIQUE,
            access_sent_at TEXT,
            payment_status TEXT NOT NULL DEFAULT \'unpaid\',
            stripe_session_id TEXT,
            amount_cents INTEGER,
            currency TEXT,
            paid_at TEXT,
            pdf_sent INTEGER NOT NULL DEFAULT 0
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

    $additions = [
        'status' => "ALTER TABLE waitlist ADD COLUMN status TEXT NOT NULL DEFAULT 'pending_payment'",
        'studio_code' => 'ALTER TABLE waitlist ADD COLUMN studio_code TEXT',
        'access_sent_at' => 'ALTER TABLE waitlist ADD COLUMN access_sent_at TEXT',
        'payment_status' => "ALTER TABLE waitlist ADD COLUMN payment_status TEXT NOT NULL DEFAULT 'unpaid'",
        'stripe_session_id' => 'ALTER TABLE waitlist ADD COLUMN stripe_session_id TEXT',
        'amount_cents' => 'ALTER TABLE waitlist ADD COLUMN amount_cents INTEGER',
        'currency' => 'ALTER TABLE waitlist ADD COLUMN currency TEXT',
        'paid_at' => 'ALTER TABLE waitlist ADD COLUMN paid_at TEXT',
        'pdf_sent' => 'ALTER TABLE waitlist ADD COLUMN pdf_sent INTEGER NOT NULL DEFAULT 0',
    ];

    foreach ($additions as $col => $sql) {
        if (!in_array($col, $names, true)) {
            $pdo->exec($sql);
        }
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
        'SELECT id, ticket, name, email, created_at, notified, status, studio_code,
                access_sent_at, payment_status, amount_cents, currency, paid_at, pdf_sent
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
        'SELECT id, name, email, ticket, status, studio_code, payment_status
         FROM waitlist WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $waitlistId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'message' => 'Waitlist entry not found.'];
    }

    if (($row['payment_status'] ?? '') !== 'paid') {
        return ['ok' => false, 'message' => 'Cannot grant studio access before payment.'];
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
 * Start paid reservation: create/reuse unpaid row, open Stripe Checkout.
 *
 * @return array{
 *   ok: bool,
 *   client_secret?: string,
 *   session_id?: string,
 *   toast?: array{type: string, title: string, message: string},
 *   keep_form?: bool
 * }
 */
function start_checkout_reservation(string $name, string $email): array
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    $email = strtolower(trim($email));

    if ($name === '' || mb_strlen($name) < 2) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Reservation failed',
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
                'title' => 'Reservation failed',
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
                'title' => 'Reservation failed',
                'message' => 'Input is too long.',
            ],
        ];
    }

    if (!stripe_is_configured()) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Payments unavailable',
                'message' => 'Stripe is not configured yet. Please try again later.',
            ],
        ];
    }

    $pdo = db();
    $config = stripe_config();
    $amount = (int) ($config['amount_cents'] ?? 4900);
    $currency = strtolower((string) ($config['currency'] ?? 'usd'));

    $existing = $pdo->prepare(
        'SELECT id, ticket, name, email, payment_status, status
         FROM waitlist WHERE email = :email LIMIT 1'
    );
    $existing->execute(['email' => $email]);
    $row = $existing->fetch();

    if ($row && ($row['payment_status'] ?? '') === 'paid') {
        return [
            'ok' => false,
            'toast' => [
                'type' => 'success',
                'title' => 'Already reserved',
                'message' => 'This email already has a paid waitlist place. Check your inbox for the ticket and PDF.',
            ],
        ];
    }

    if ($row) {
        $waitlistId = (int) $row['id'];
        $ticket = (string) $row['ticket'];
        $pdo->prepare(
            "UPDATE waitlist
             SET name = :name, status = 'pending_payment', payment_status = 'unpaid'
             WHERE id = :id"
        )->execute(['name' => $name, 'id' => $waitlistId]);
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO waitlist (
                    ticket, name, email, phone, created_at, notified, status,
                    payment_status, amount_cents, currency, pdf_sent
                 ) VALUES (
                    :ticket, :name, :email, NULL, :created_at, 0, 'pending_payment',
                    'unpaid', :amount_cents, :currency, 0
                 )"
            );

            $stmt->execute([
                'ticket' => 'PENDING',
                'name' => $name,
                'email' => $email,
                'created_at' => gmdate('c'),
                'amount_cents' => $amount,
                'currency' => $currency,
            ]);

            $waitlistId = (int) $pdo->lastInsertId();
            $ticket = generate_ticket($waitlistId);

            $pdo->prepare('UPDATE waitlist SET ticket = :ticket WHERE id = :id')
                ->execute(['ticket' => $ticket, 'id' => $waitlistId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'ok' => false,
                'keep_form' => true,
                'toast' => [
                    'type' => 'error',
                    'title' => 'Reservation failed',
                    'message' => 'Could not start checkout. Please try again.',
                ],
            ];
        }
    }

    $checkout = stripe_create_checkout_session([
        'name' => $name,
        'email' => $email,
        'waitlist_id' => $waitlistId,
        'ticket' => $ticket,
    ]);

    if (!$checkout['ok']) {
        return [
            'ok' => false,
            'keep_form' => true,
            'toast' => [
                'type' => 'error',
                'title' => 'Checkout failed',
                'message' => $checkout['error'] ?? 'Could not open Stripe Checkout.',
            ],
        ];
    }

    $pdo->prepare(
        'UPDATE waitlist
         SET stripe_session_id = :session_id, amount_cents = :amount, currency = :currency
         WHERE id = :id'
    )->execute([
        'session_id' => $checkout['session_id'],
        'amount' => $amount,
        'currency' => $currency,
        'id' => $waitlistId,
    ]);

    return [
        'ok' => true,
        'client_secret' => (string) ($checkout['client_secret'] ?? ''),
        'session_id' => (string) ($checkout['session_id'] ?? ''),
    ];
}

/**
 * Mark reservation as paid after Stripe webhook, then email ticket + PDF.
 *
 * @return array{ok: bool, message: string}
 */
function fulfill_paid_reservation(int $waitlistId, string $sessionId): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, name, email, ticket, payment_status, pdf_sent
         FROM waitlist WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $waitlistId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'message' => 'Waitlist entry not found.'];
    }

    if (($row['payment_status'] ?? '') === 'paid' && (int) ($row['pdf_sent'] ?? 0) === 1) {
        return ['ok' => true, 'message' => 'Already fulfilled.'];
    }

    $config = stripe_config();
    $amount = (int) ($config['amount_cents'] ?? 4900);
    $currency = strtolower((string) ($config['currency'] ?? 'usd'));

    $pdo->prepare(
        "UPDATE waitlist SET
            payment_status = 'paid',
            status = 'waiting',
            stripe_session_id = :session_id,
            amount_cents = :amount,
            currency = :currency,
            paid_at = :paid_at
         WHERE id = :id"
    )->execute([
        'session_id' => $sessionId,
        'amount' => $amount,
        'currency' => $currency,
        'paid_at' => gmdate('c'),
        'id' => $waitlistId,
    ]);

    $sent = send_paid_ticket_email(
        (string) $row['email'],
        (string) $row['name'],
        (string) $row['ticket']
    );

    $pdo->prepare('UPDATE waitlist SET notified = :n, pdf_sent = :pdf WHERE id = :id')
        ->execute([
            'n' => $sent ? 1 : 0,
            'pdf' => $sent ? 1 : 0,
            'id' => $waitlistId,
        ]);

    if (!$sent) {
        return [
            'ok' => false,
            'message' => 'Payment recorded, but ticket/PDF email failed.',
        ];
    }

    return ['ok' => true, 'message' => 'Payment fulfilled and email sent.'];
}

function send_paid_ticket_email(string $email, string $name, string $ticket): bool
{
    $pdfPath = protocol_pdf_path();
    $attachments = [];

    if (is_file($pdfPath)) {
        $attachments[] = [
            'path' => $pdfPath,
            'filename' => 'MYCOPY-Protocol.pdf',
            'mime' => 'application/pdf',
        ];
    }

    $subject = 'MYCOPY — your waitlist ticket + protocol PDF';
    $body = "Hello {$name},\n\n"
        . "Payment confirmed. Your place on the MYCOPY waitlist is reserved.\n\n"
        . "Ticket number: {$ticket}\n"
        . "Amount: \$49 USD\n\n"
        . "Your protocol PDF is attached to this email.\n"
        . "Keep your ticket for your records.\n"
        . "When studio access is approved, you will receive a separate access code.\n\n"
        . "Do not share your ticket with anyone.\n\n"
        . "— MYCOPY.DIGITAL\n";

    $mailer = smtp_mailer();
    return $mailer->send($email, $name, $subject, $body, $attachments);
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
