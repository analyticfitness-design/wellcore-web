<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Minimal SMTP email sender
 * Uses native PHP sockets with STARTTLS (no PHPMailer needed).
 */

require_once __DIR__ . '/../config/email.php';

/**
 * Send an email via SMTP with STARTTLS authentication.
 *
 * @param string $to          Recipient email
 * @param string $subject     Email subject
 * @param string $html        HTML body
 * @param string $text        Plain text fallback (optional)
 * @param array  $attachments Optional attachments [['filename'=>'...','content'=>'...','mime'=>'...']]
 * @return array ['ok' => bool, 'error' => string|null]
 */
function sendEmail(string $to, string $subject, string $html, string $text = '', array $attachments = []): array {
    $host    = SMTP_HOST;
    $port    = SMTP_PORT;
    $user    = SMTP_USER;
    $pass    = SMTP_PASS;
    $from    = SMTP_FROM;
    $name    = SMTP_FROM_NAME;
    $timeout = SMTP_TIMEOUT;

    if (!$text) {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));
    }

    $messageId = '<' . bin2hex(random_bytes(12)) . '@wellcorefitness.com>';

    if (empty($attachments)) {
        // Simple multipart/alternative (text + html)
        $boundary = '----=_WellCore_' . bin2hex(random_bytes(8));

        $headers  = "From: {$name} <{$from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Message-ID: {$messageId}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($text) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($html) . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";
    } else {
        // multipart/mixed → multipart/alternative (body) + attachments
        $mixedBoundary = '----=_WC_Mixed_' . bin2hex(random_bytes(8));
        $altBoundary   = '----=_WC_Alt_'   . bin2hex(random_bytes(8));

        $headers  = "From: {$name} <{$from}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Message-ID: {$messageId}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"\r\n";
        $headers .= "\r\n";

        // Body part (alternative: text + html)
        $body  = "--{$mixedBoundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($text) . "\r\n\r\n";
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($html) . "\r\n\r\n";
        $body .= "--{$altBoundary}--\r\n\r\n";

        // Attachment parts
        foreach ($attachments as $att) {
            $fname = $att['filename'] ?? 'attachment';
            $mime  = $att['mime'] ?? 'application/octet-stream';
            $raw   = $att['content'] ?? '';
            $body .= "--{$mixedBoundary}\r\n";
            $body .= "Content-Type: {$mime}; name=\"{$fname}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$fname}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($raw), 76, "\r\n");
            $body .= "\r\n";
        }
        $body .= "--{$mixedBoundary}--\r\n";
    }

    $data = $headers . $body;

    // Connect
    $errno  = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        $timeout
    );

    if (!$socket) {
        return ['ok' => false, 'error' => "Connection failed: {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, $timeout);

    try {
        $greeting = smtpRead($socket);
        if (!str_starts_with($greeting, '220')) {
            throw new RuntimeException("Bad greeting: {$greeting}");
        }

        // EHLO
        smtpCmd($socket, "EHLO wellcorefitness.com", '250');

        // STARTTLS
        smtpCmd($socket, "STARTTLS", '220');
        $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        if (!$crypto) {
            throw new RuntimeException("STARTTLS handshake failed");
        }

        // EHLO again after TLS
        smtpCmd($socket, "EHLO wellcorefitness.com", '250');

        // AUTH LOGIN
        smtpCmd($socket, "AUTH LOGIN", '334');
        smtpCmd($socket, base64_encode($user), '334');
        smtpCmd($socket, base64_encode($pass), '235');

        // MAIL FROM
        smtpCmd($socket, "MAIL FROM:<{$from}>", '250');

        // RCPT TO
        smtpCmd($socket, "RCPT TO:<{$to}>", '250');

        // DATA
        smtpCmd($socket, "DATA", '354');

        // Send message body (dot-stuffing)
        $lines = explode("\r\n", $data);
        foreach ($lines as $line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
            fwrite($socket, $line . "\r\n");
        }
        smtpCmd($socket, ".", '250');

        // QUIT
        fwrite($socket, "QUIT\r\n");

        return ['ok' => true, 'error' => null];

    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } finally {
        @fclose($socket);
    }
}

function smtpRead($socket): string {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        // Multi-line responses have a dash after the code, last line has space
        if (isset($line[3]) && $line[3] === ' ') break;
        if (strlen($line) < 4) break;
    }
    return trim($response);
}

function smtpCmd($socket, string $cmd, string $expectedCode): string {
    fwrite($socket, $cmd . "\r\n");
    $response = smtpRead($socket);
    if (!str_starts_with($response, $expectedCode)) {
        throw new RuntimeException("SMTP error on '{$cmd}': {$response}");
    }
    return $response;
}
