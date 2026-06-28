<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Minimal SMTP client (AUTH LOGIN + optional STARTTLS).
 */
final class SmtpClient
{
    private mixed $socket = null;

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $username,
        private string $password
    ) {
    }

    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $body
    ): void {
        $this->connect();
        $this->expect(220);

        $this->command('EHLO ' . $this->host);
        $this->expect(250);

        if ($this->encryption === 'tls' && $this->port !== 465) {
            $this->command('STARTTLS');
            $this->expect(220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS failed.');
            }
            $this->command('EHLO ' . $this->host);
            $this->expect(250);
        }

        if ($this->username !== '') {
            $this->command('AUTH LOGIN');
            $this->expect(334);
            $this->command(base64_encode($this->username));
            $this->expect(334);
            $this->command(base64_encode($this->password));
            $this->expect(235);
        }

        $this->command('MAIL FROM:<' . $this->sanitizeAddress($from) . '>');
        $this->expect(250);
        $this->command('RCPT TO:<' . $this->sanitizeAddress($to) . '>');
        $this->expect(250);
        $this->command('DATA');
        $this->expect(354);

        $encodedSubject = $this->encodeHeader($subject);
        $fromHeader = $this->formatFrom($from, $fromName);
        $message = "From: {$fromHeader}\r\n";
        $message .= "To: <{$this->sanitizeAddress($to)}>\r\n";
        $message .= "Subject: {$encodedSubject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n";
        $message .= "\r\n";
        $message .= str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body);
        $message .= "\r\n.\r\n";

        fwrite($this->socket, $message);
        $this->expect(250);

        $this->command('QUIT');
        $this->close();
    }

    private function connect(): void
    {
        $target = $this->encryption === 'ssl' ? "ssl://{$this->host}:{$this->port}" : "{$this->host}:{$this->port}";
        $socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
        );

        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, 15);
        $this->socket = $socket;
    }

    private function command(string $line): void
    {
        fwrite($this->socket, $line . "\r\n");
    }

    private function expect(int $code): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if (!str_starts_with($response, (string) $code)) {
            throw new \RuntimeException("SMTP error, expected {$code}, got: " . trim($response));
        }
    }

    private function sanitizeAddress(string $email): string
    {
        return trim($email, " \t\n\r\0\x0B<>");
    }

    private function formatFrom(string $from, string $fromName): string
    {
        $from = $this->sanitizeAddress($from);
        if ($fromName === '') {
            return "<{$from}>";
        }

        return $this->encodeHeader($fromName) . " <{$from}>";
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
