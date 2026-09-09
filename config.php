<?php

$envPath = __DIR__ . '/.env';
$env = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $env[$key] = $value;
    }
}

return array_merge(
    [
        'DB_HOST' => '127.0.0.1',
        'DB_NAME' => 'sentinel_ai',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'AI_PROVIDER' => 'openrouter',
        'OPENROUTER_API_KEY' => '',
        'OPENROUTER_MODEL' => 'openai/gpt-4o-mini',
        'AI_TIMEOUT' => '45',
        'NVIDIA_API_KEY' => '',
        'MAIL_FROM' => '',
        'SMTP_HOST' => '',
        'SMTP_PORT' => '587',
        'SMTP_USER' => '',
        'SMTP_PASS' => '',
        'SMTP_SECURE' => 'tls',
        'INBOUND_WEBHOOK_TOKEN' => '',
    ],
    $env
);
