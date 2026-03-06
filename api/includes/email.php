<?php
declare(strict_types=1);
/**
 * WellCore Fitness — Email sender via Mailjet API v3.1
 * Uses cURL to send transactional emails through Mailjet.
 */

require_once __DIR__ . '/../config/email.php';

/**
 * Send an email via Mailjet API v3.1.
 *
 * @param string $to      Recipient email
 * @param string $subject Email subject
 * @param string $html    HTML body
 * @param string $text    Plain text fallback (optional, auto-generated from HTML if empty)
 * @return array ['ok' => bool, 'error' => string|null]
 */
function sendEmail(string $to, string $subject, string $html, string $text = '', array $attachments = []): array {
    if (!$text) {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html));
    }

    $payload = [
        'Messages' => [
            [
                'From' => [
                    'Email' => MAIL_FROM_EMAIL,
                    'Name'  => MAIL_FROM_NAME,
                ],
                'To' => [
                    [
                        'Email' => $to,
                    ],
                ],
                'Subject'  => $subject,
                'TextPart' => $text,
                'HTMLPart' => $html,
            ],
        ],
    ];

    // Add attachments if provided
    if (!empty($attachments)) {
        $mjAttachments = [];
        foreach ($attachments as $att) {
            $mjAttachments[] = [
                'ContentType'   => $att['mime'] ?? 'application/octet-stream',
                'Filename'      => $att['filename'] ?? 'attachment',
                'Base64Content' => base64_encode($att['content'] ?? ''),
            ];
        }
        $payload['Messages'][0]['Attachments'] = $mjAttachments;
    }

    $ch = curl_init(MAILJET_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => MAILJET_API_KEY . ':' . MAILJET_SECRET_KEY,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[WellCore][Mailjet] cURL error: {$curlErr}");
        return ['ok' => false, 'error' => "cURL error: {$curlErr}"];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['ok' => true, 'error' => null];
    }

    error_log("[WellCore][Mailjet] HTTP {$httpCode}: {$response}");
    return ['ok' => false, 'error' => "Mailjet HTTP {$httpCode}"];
}
