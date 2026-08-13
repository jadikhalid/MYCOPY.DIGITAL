<?php
/**
 * Minimal SMTP client (AUTH LOGIN, STARTTLS / SSL).
 */

declare(strict_types=1);

final class SmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private int $timeout;

    /** @var resource|null */
    private $socket = null;

    private string $lastError = '';

    public function __construct(array $config)
    {
        $this->host = (string) ($config['host'] ?? '');
        $this->port = (int) ($config['port'] ?? 587);
        $this->encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->fromEmail = (string) ($config['from_email'] ?? '');
        $this->fromName = (string) ($config['from_name'] ?? 'MYCOPY');
        $this->timeout = (int) ($config['timeout'] ?? 20);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function send(string $toEmail, string $toName, string $subject, string $bodyText): bool
    {
        $this->lastError = '';

        if ($this->host === '' || $this->fromEmail === '') {
            $this->lastError = 'Incomplete SMTP configuration.';
            return false;
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Invalid recipient.';
            return false;
        }

        try {
            $this->connect();
            $this->expect([220]);

            $this->command('EHLO mycopy.digital');
            $this->expect([250]);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS');
                $this->expect([220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('TLS handshake failed.');
                }
                $this->command('EHLO mycopy.digital');
                $this->expect([250]);
            }

            if ($this->username !== '') {
                $this->command('AUTH LOGIN');
                $this->expect([334]);
                $this->command(base64_encode($this->username));
                $this->expect([334]);
                $this->command(base64_encode($this->password));
                $this->expect([235]);
            }

            $this->command('MAIL FROM:<' . $this->fromEmail . '>');
            $this->expect([250]);

            $this->command('RCPT TO:<' . $toEmail . '>');
            $this->expect([250, 251]);

            $this->command('DATA');
            $this->expect([354]);

            $payload = $this->buildMessage($toEmail, $toName, $subject, $bodyText);
            fwrite($this->socket, $payload . "\r\n.\r\n");
            $this->expect([250]);

            $this->command('QUIT');
            $this->close();

            return true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            $this->close();
            return false;
        }
    }

    private function connect(): void
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ])
        );

        if ($socket === false) {
            throw new RuntimeException("SMTP connection failed ({$errno}): {$errstr}");
        }

        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
    }

    private function command(string $line): void
    {
        if ($this->socket === null) {
            throw new RuntimeException('SMTP socket closed.');
        }
        fwrite($this->socket, $line . "\r\n");
    }

    /** @param list<int> $codes */
    private function expect(array $codes): string
    {
        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
        }

        return $response;
    }

    private function readResponse(): string
    {
        if ($this->socket === null) {
            throw new RuntimeException('SMTP socket closed.');
        }

        $data = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($data === '') {
            throw new RuntimeException('No response from SMTP server.');
        }

        return $data;
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $bodyText): string
    {
        $fromName = $this->encodeHeader($this->fromName);
        $toNameEnc = $this->encodeHeader($toName);
        $subjectEnc = $this->encodeHeader($subject);
        $date = date('r');
        $messageId = sprintf('<%s@mycopy.digital>', bin2hex(random_bytes(12)));

        $headers = [
            "Date: {$date}",
            "From: {$fromName} <{$this->fromEmail}>",
            "To: {$toNameEnc} <{$toEmail}>",
            "Subject: {$subjectEnc}",
            "Message-ID: {$messageId}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: MYCOPY-SMTP',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $bodyText);
        $body = str_replace("\n", "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}

function smtp_config(): array
{
    $path = __DIR__ . '/../config.smtp.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/../config.smtp.example.php';
    }

    /** @var array $config */
    $config = require $path;
    return $config;
}

function smtp_mailer(): SmtpMailer
{
    return new SmtpMailer(smtp_config());
}
